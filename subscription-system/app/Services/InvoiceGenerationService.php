<?php
/**
 * Invoice Generation Service
 * Handles creating invoices and allocating cameras
 */

namespace App\Services;

use App\Database;
use DateTime;
use Exception;

class InvoiceGenerationService {
    private $db;
    private $pricingService;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->pricingService = new PricingService();
    }
    
    /**
     * Create a new invoice for a legal entity
     * 
     * @param int $legalEntityId
     * @param string $invoiceDate (Y-m-d format)
     * @param array $options Optional parameters
     * @return int Invoice ID
     */
    public function createInvoice($legalEntityId, $invoiceDate, $options = []) {
        $this->db->beginTransaction();
        
        try {
            // Get legal entity details
            $entity = $this->db->fetchOne(
                "SELECT * FROM legal_entities WHERE id = :id",
                ['id' => $legalEntityId]
            );
            
            if (!$entity) {
                throw new Exception("Legal entity not found");
            }
            
            // Generate invoice number
            $invoiceNumber = $this->generateInvoiceNumber($invoiceDate);
            
            // Calculate next generation date based on payment frequency
            $nextGenDate = $this->calculateNextGenerationDate(
                $invoiceDate, 
                $entity['payment_frequency']
            );
            
            // Create invoice record
            $invoiceId = $this->db->insert('invoices', [
                'legal_entity_id' => $legalEntityId,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $invoiceDate,
                'invoice_amount' => 0, // Will be calculated after camera allocation
                'payment_frequency' => $entity['payment_frequency'],
                'invoice_status' => $options['status'] ?? 'draft',
                'is_auto_generated' => ($options['is_auto_generated'] ?? false) ? 1 : 0,
                'parent_invoice_id' => $options['parent_invoice_id'] ?? null,
                'generation_date' => date('Y-m-d H:i:s'),
                'next_generation_date' => $nextGenDate,
                'created_by' => $options['created_by'] ?? 'system',
                'notes' => $options['notes'] ?? null
            ]);
            
            // Log the generation
            $this->logGeneration($invoiceId, [
                'generation_type' => ($options['is_auto_generated'] ?? false) ? 'auto_repeat' : 'manual',
                'triggered_by' => $options['created_by'] ?? 'system',
                'trigger_reason' => $options['trigger_reason'] ?? 'Manual invoice creation'
            ]);
            
            $this->db->commit();
            return $invoiceId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Allocate cameras to an invoice
     * 
     * @param int $invoiceId
     * @param array $cameraIds Array of camera_installation IDs
     * @param string $allocatedBy Username
     * @return array Allocation results
     */
    public function allocateCameras($invoiceId, $cameraIds, $allocatedBy = 'system') {
        $this->db->beginTransaction();
        
        try {
            // Get invoice details
            $invoice = $this->db->fetchOne(
                "SELECT i.*, le.pricing_type, le.pricing_model 
                 FROM invoices i
                 JOIN legal_entities le ON i.legal_entity_id = le.id
                 WHERE i.id = :id",
                ['id' => $invoiceId]
            );
            
            if (!$invoice) {
                throw new Exception("Invoice not found");
            }
            
            if ($invoice['invoice_status'] === 'paid' || $invoice['invoice_status'] === 'cancelled') {
                throw new Exception("Cannot allocate cameras to a {$invoice['invoice_status']} invoice");
            }
            
            $allocations = [];
            $totalAmount = 0;
            
            // Get all cameras with their details
            $cameras = $this->db->fetchAll(
                "SELECT ci.*, s.store_name, s.legal_entity_id, s.id as store_id
                 FROM camera_installations ci
                 JOIN stores s ON ci.store_id = s.id
                 WHERE ci.id IN (" . implode(',', array_map('intval', $cameraIds)) . ")
                 AND s.legal_entity_id = :legal_entity_id
                 AND ci.removal_date IS NULL",
                ['legal_entity_id' => $invoice['legal_entity_id']]
            );
            
            if (count($cameras) !== count($cameraIds)) {
                throw new Exception("Some cameras not found or not active");
            }
            
            // Calculate pricing for each camera
            $cameraCount = count($cameras);
            $pricing = $this->pricingService->getPricingForEntity(
                $invoice['legal_entity_id'],
                $cameraCount,
                $invoice['invoice_date']
            );

            // Allocate each camera
            foreach ($cameras as $index => $camera) {
                // Determine pricing tier and price for this camera
                if ($invoice['pricing_model'] === 'volume_based') {
                    // All cameras same price based on tier
                    $tier = $pricing['tier_name'] ?? 'tier_1-49';

                    // For volume-based pricing, rate_to_use is the per-camera rate
                    if (isset($pricing['rate_per_camera'])) {
                        $priceCharged = $pricing['rate_per_camera'];
                    } elseif (isset($pricing['rate_to_use'])) {
                        // rate_to_use is already the per-camera rate for volume-based pricing
                        $priceCharged = $pricing['rate_to_use'];
                    } else {
                        $priceCharged = 0;
                    }
                } else {
                    // First + Additional model
                    if ($index === 0) {
                        $tier = 'first_camera';
                        $priceCharged = $pricing['first_camera_rate'] ?? 0;
                    } else {
                        $tier = 'additional';
                        $priceCharged = $pricing['additional_camera_rate'] ?? 0;
                    }
                }
                
                $allocationId = $this->db->insert('invoice_camera_allocations', [
                    'invoice_id' => $invoiceId,
                    'camera_installation_id' => $camera['id'],
                    'store_id' => $camera['store_id'],
                    'legal_entity_id' => $camera['legal_entity_id'],
                    'camera_serial' => null, // Cameras don't have serial numbers in this system
                    'camera_type' => $camera['camera_type'] ?? 'main',
                    'store_name' => $camera['store_name'],
                    'allocated_date' => date('Y-m-d H:i:s'),
                    'price_charged' => $priceCharged,
                    'pricing_tier' => $tier,
                    'allocated_by' => $allocatedBy
                ]);
                
                $allocations[] = [
                    'allocation_id' => $allocationId,
                    'camera_id' => $camera['id'],
                    'price_charged' => $priceCharged,
                    'tier' => $tier
                ];
                
                $totalAmount += $priceCharged;
            }
            
            // Update invoice amount
            $this->db->update('invoices', [
                'invoice_amount' => $totalAmount
            ], 'id = :id', ['id' => $invoiceId]);
            
            // Update generation log
            $this->db->query(
                "UPDATE invoice_generation_log 
                 SET camera_count = :count, total_amount = :amount
                 WHERE invoice_id = :id
                 ORDER BY generation_date DESC LIMIT 1",
                [
                    'count' => $cameraCount,
                    'amount' => $totalAmount,
                    'id' => $invoiceId
                ]
            );
            
            $this->db->commit();
            
            return [
                'success' => true,
                'invoice_id' => $invoiceId,
                'camera_count' => $cameraCount,
                'total_amount' => $totalAmount,
                'allocations' => $allocations
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Remove a camera from an invoice
     */
    public function removeCameraAllocation($allocationId, $reason, $removedBy = 'system') {
        $this->db->beginTransaction();

        try {
            // Get allocation details
            $allocation = $this->db->fetchOne(
                "SELECT * FROM invoice_camera_allocations WHERE id = :id",
                ['id' => $allocationId]
            );

            if (!$allocation) {
                throw new Exception("Allocation not found");
            }

            // Check invoice status
            $invoice = $this->db->fetchOne(
                "SELECT invoice_status FROM invoices WHERE id = :id",
                ['id' => $allocation['invoice_id']]
            );

            if ($invoice['invoice_status'] === 'paid' || $invoice['invoice_status'] === 'cancelled') {
                throw new Exception("Cannot remove cameras from a {$invoice['invoice_status']} invoice");
            }

            // Mark as removed (don't delete - keep history)
            $this->db->update('invoice_camera_allocations', [
                'removed_date' => date('Y-m-d H:i:s'),
                'removed_reason' => $reason,
                'removed_by' => $removedBy
            ], 'id = :id', ['id' => $allocationId]);

            // Recalculate invoice amount
            $this->recalculateInvoiceAmount($allocation['invoice_id']);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Get all camera allocations for an invoice
     */
    public function getInvoiceAllocations($invoiceId, $includeRemoved = false) {
        $sql = "SELECT ica.*, ci.camera_type as current_camera_type, s.store_name as current_store_name
                FROM invoice_camera_allocations ica
                LEFT JOIN camera_installations ci ON ica.camera_installation_id = ci.id
                LEFT JOIN stores s ON ica.store_id = s.id
                WHERE ica.invoice_id = :invoice_id";

        if (!$includeRemoved) {
            $sql .= " AND ica.removed_date IS NULL";
        }

        $sql .= " ORDER BY ica.allocated_date";

        return $this->db->fetchAll($sql, ['invoice_id' => $invoiceId]);
    }

    /**
     * Generate invoice number
     * Format: INV-### (sequential)
     */
    private function generateInvoiceNumber($invoiceDate) {
        // Get the highest invoice number
        $latest = $this->db->fetchOne(
            "SELECT invoice_number
             FROM invoices
             WHERE invoice_number LIKE 'INV-%'
             ORDER BY CAST(SUBSTRING(invoice_number, 5) AS UNSIGNED) DESC
             LIMIT 1"
        );

        if ($latest && $latest['invoice_number']) {
            // Extract number from INV-###
            $lastNumber = (int)substr($latest['invoice_number'], 4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return "INV-" . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate next generation date based on payment frequency
     */
    private function calculateNextGenerationDate($invoiceDate, $paymentFrequency) {
        $date = new DateTime($invoiceDate);

        switch ($paymentFrequency) {
            case 'monthly':
                $date->modify('+1 month');
                break;
            case 'quarterly':
                $date->modify('+3 months');
                break;
            case 'annual':
                $date->modify('+1 year');
                break;
            default:
                return null;
        }

        return $date->format('Y-m-d');
    }

    /**
     * Determine pricing tier for a camera
     */
    private function determinePricingTier($cameraNumber, $totalCameras, $pricingModel) {
        if ($pricingModel === 'first_plus_additional') {
            return $cameraNumber === 1 ? 'first_camera' : 'additional';
        }

        // Volume-based tiers
        if ($totalCameras <= 49) return 'tier_1-49';
        if ($totalCameras <= 149) return 'tier_50-149';
        if ($totalCameras <= 249) return 'tier_150-249';
        return 'tier_250+';
    }

    /**
     * Calculate price for a single camera
     */
    private function calculateCameraPrice($pricing, $tier, $pricingModel) {
        if ($pricingModel === 'first_plus_additional') {
            return $tier === 'first_camera'
                ? $pricing['first_camera_price']
                : $pricing['additional_camera_price'];
        }

        // Volume-based: all cameras same price
        return $pricing['price_per_camera'];
    }

    /**
     * Recalculate invoice amount based on current allocations
     */
    private function recalculateInvoiceAmount($invoiceId) {
        $total = $this->db->fetchOne(
            "SELECT SUM(price_charged) as total
             FROM invoice_camera_allocations
             WHERE invoice_id = :id AND removed_date IS NULL",
            ['id' => $invoiceId]
        );

        $this->db->update('invoices', [
            'invoice_amount' => $total['total'] ?? 0
        ], 'id = :id', ['id' => $invoiceId]);
    }

    /**
     * Log invoice generation
     */
    private function logGeneration($invoiceId, $details) {
        $this->db->insert('invoice_generation_log', [
            'invoice_id' => $invoiceId,
            'generation_type' => $details['generation_type'],
            'generation_date' => date('Y-m-d H:i:s'),
            'triggered_by' => $details['triggered_by'] ?? null,
            'trigger_reason' => $details['trigger_reason'] ?? null,
            'camera_count' => $details['camera_count'] ?? null,
            'total_amount' => $details['total_amount'] ?? null,
            'notes' => $details['notes'] ?? null
        ]);
    }
}

