<?php
/**
 * Camera Allocation Service
 * Allocates cameras to invoices and tracks which cameras are invoiced
 */

namespace App\Services;

use App\Database;

class CameraAllocationService {
    private $db;
    private $pricingService;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->pricingService = new PricingService();
    }

    /**
     * Allocate cameras to an invoice
     * Creates allocation records for each store under the legal entity
     * Uses the new PricingService to determine rates based on total camera count
     *
     * @param int $invoiceId
     * @param string $allocationDate Date to count cameras as of (defaults to invoice date)
     * @return array Allocation summary
     */
    public function allocateCamerasToInvoice($invoiceId, $allocationDate = null) {
        // Get invoice details
        $invoice = $this->db->fetchOne(
            "SELECT i.*, le.id as legal_entity_id, le.legal_entity_name, le.payment_frequency
             FROM invoices i
             JOIN legal_entities le ON i.legal_entity_id = le.id
             WHERE i.id = :id",
            ['id' => $invoiceId]
        );

        if (!$invoice) {
            return ['error' => 'Invoice not found'];
        }

        // Use invoice date if allocation date not specified
        if (!$allocationDate) {
            $allocationDate = $invoice['invoice_date'];
        }

        // Get all stores for this legal entity
        $stores = $this->db->fetchAll(
            "SELECT * FROM stores
             WHERE legal_entity_id = :legal_entity_id
             AND (termination_date IS NULL OR termination_date >= :allocation_date)",
            [
                'legal_entity_id' => $invoice['legal_entity_id'],
                'allocation_date' => $allocationDate
            ]
        );

        // STEP 1: Count total cameras across ALL stores for this legal entity
        $totalCameraCount = 0;
        foreach ($stores as $store) {
            $counts = $this->getCameraCountsAsOf($store['id'], $allocationDate);
            $totalCameraCount += $counts['total_cameras'];
        }

        // STEP 2: Get pricing tier based on total camera count
        $pricing = $this->pricingService->getPricingForEntity(
            $invoice['legal_entity_id'],
            $totalCameraCount,
            $allocationDate
        );

        // STEP 3: Allocate cameras to each store using the tier rate
        $allocations = [];
        $totalAmount = 0;

        foreach ($stores as $store) {
            $allocation = $this->allocateStoreToInvoice(
                $invoiceId,
                $store['id'],
                $allocationDate,
                $pricing  // Pass the pricing info to the store allocation
            );

            if ($allocation) {
                $allocations[] = $allocation;
                $totalAmount += $allocation['subtotal'];
            }
        }

        return [
            'invoice_id' => $invoiceId,
            'legal_entity_name' => $invoice['legal_entity_name'],
            'allocation_date' => $allocationDate,
            'total_cameras' => $totalCameraCount,
            'pricing_tier' => $pricing['tier_name'],
            'pricing_type' => $pricing['pricing_type'],
            'rate_per_camera' => $pricing['rate_to_use'],
            'stores_count' => count($allocations),
            'total_amount' => $totalAmount,
            'allocations' => $allocations
        ];
    }

    /**
     * Allocate a single store's cameras to an invoice
     * Uses the pricing tier rate determined at the legal entity level
     *
     * @param int $invoiceId
     * @param int $storeId
     * @param string $allocationDate
     * @param array $pricing Pricing information from PricingService
     * @return array|null Allocation details or null if no cameras
     */
    private function allocateStoreToInvoice($invoiceId, $storeId, $allocationDate, $pricing) {
        // Get camera counts for this store as of allocation date
        $counts = $this->getCameraCountsAsOf($storeId, $allocationDate);

        // Skip if no cameras
        if ($counts['total_cameras'] == 0) {
            return null;
        }

        // The pricing passed in already accounts for the entity's pricing model
        // (either volume-based or first_plus_additional)
        // Extract the rates from the pricing array
        if (isset($pricing['first_camera_rate'])) {
            // Entity uses "first camera + additional" pricing model
            $mainCameraRate = $pricing['first_camera_rate'];
            $additionalCameraRate = $pricing['additional_camera_rate'];
            $subtotal = $pricing['total_cost'];
        } else {
            // Entity uses volume-based pricing (all cameras same rate)
            $mainCameraRate = $pricing['rate_to_use'];
            $additionalCameraRate = $pricing['rate_to_use'];
            $subtotal = $counts['total_cameras'] * $pricing['rate_to_use'];
        }

        // Check if allocation already exists
        $existing = $this->db->fetchOne(
            "SELECT id FROM invoice_camera_allocations
             WHERE invoice_id = :invoice_id AND store_id = :store_id",
            ['invoice_id' => $invoiceId, 'store_id' => $storeId]
        );

        if ($existing) {
            // Update existing allocation
            $this->db->query(
                "UPDATE invoice_camera_allocations
                 SET allocation_date = :allocation_date,
                     main_cameras = :main_cameras,
                     additional_cameras = :additional_cameras,
                     main_camera_rate = :main_camera_rate,
                     additional_camera_rate = :additional_camera_rate,
                     subtotal = :subtotal
                 WHERE id = :id",
                [
                    'allocation_date' => $allocationDate,
                    'main_cameras' => $counts['main_cameras'],
                    'additional_cameras' => $counts['additional_cameras'],
                    'main_camera_rate' => $mainCameraRate,
                    'additional_camera_rate' => $additionalCameraRate,
                    'subtotal' => $subtotal,
                    'id' => $existing['id']
                ]
            );
            $allocationId = $existing['id'];
        } else {
            // Create new allocation
            $allocationId = $this->db->insert('invoice_camera_allocations', [
                'invoice_id' => $invoiceId,
                'store_id' => $storeId,
                'allocation_date' => $allocationDate,
                'main_cameras' => $counts['main_cameras'],
                'additional_cameras' => $counts['additional_cameras'],
                'main_camera_rate' => $mainCameraRate,
                'additional_camera_rate' => $additionalCameraRate,
                'subtotal' => $subtotal
            ]);
        }

        return [
            'allocation_id' => $allocationId,
            'store_id' => $storeId,
            'main_cameras' => $counts['main_cameras'],
            'additional_cameras' => $counts['additional_cameras'],
            'total_cameras' => $counts['total_cameras'],
            'main_camera_rate' => $mainCameraRate,
            'additional_camera_rate' => $additionalCameraRate,
            'subtotal' => $subtotal
        ];
    }

    /**
     * Get camera counts for a store as of a specific date
     */
    private function getCameraCountsAsOf($storeId, $asOfDate) {
        $counts = $this->db->fetchOne(
            "SELECT
                SUM(CASE WHEN camera_type = 'main' THEN 1 ELSE 0 END) as main_cameras,
                SUM(CASE WHEN camera_type = 'additional' THEN 1 ELSE 0 END) as additional_cameras,
                COUNT(*) as total_cameras
             FROM camera_installations
             WHERE store_id = ?
             AND installation_date <= ?
             AND (removal_date IS NULL OR removal_date > ?)",
            [$storeId, $asOfDate, $asOfDate]
        );

        return [
            'main_cameras' => (int) ($counts['main_cameras'] ?? 0),
            'additional_cameras' => (int) ($counts['additional_cameras'] ?? 0),
            'total_cameras' => (int) ($counts['total_cameras'] ?? 0)
        ];
    }

    /**
     * DEPRECATED: This method is no longer used.
     * We now use PricingService to get tier-based pricing at the legal entity level.
     *
     * Get camera rates for a store as of a specific date
     * Now uses the CameraRateService to get historical rates with volume discounts
     */
    private function getCameraRates($storeId, $asOfDate) {
        // This method is kept for backward compatibility but is no longer used
        // The new flow uses PricingService in allocateCamerasToInvoice()

        $store = $this->db->fetchOne(
            "SELECT legal_entity_id FROM stores WHERE id = :id",
            ['id' => $storeId]
        );

        if (!$store) {
            return ['main_camera_rate' => 0, 'additional_camera_rate' => 0];
        }

        // Get pricing from the new service
        $counts = $this->getCameraCountsAsOf($storeId, $asOfDate);
        $pricing = $this->pricingService->getPricingForEntity(
            $store['legal_entity_id'],
            $counts['total_cameras'],
            $asOfDate
        );

        return [
            'main_camera_rate' => $pricing['rate_to_use'],
            'additional_camera_rate' => $pricing['rate_to_use']
        ];
    }

    /**
     * Get allocation details for an invoice
     */
    public function getAllocationsForInvoice($invoiceId) {
        return $this->db->fetchAll(
            "SELECT ica.*, s.store_name, s.store_id as store_code
             FROM invoice_camera_allocations ica
             JOIN stores s ON ica.store_id = s.id
             WHERE ica.invoice_id = :invoice_id
             ORDER BY s.store_name",
            ['invoice_id' => $invoiceId]
        );
    }

    /**
     * Get individual camera allocations for an invoice
     */
    public function getIndividualCameraAllocations($invoiceId) {
        return $this->db->fetchAll(
            "SELECT ica.*,
                    ci.camera_type,
                    ci.camera_name,
                    ci.safr_code,
                    ci.installation_date,
                    ci.removal_date,
                    s.store_name,
                    s.store_id as store_code
             FROM invoice_camera_allocations ica
             JOIN camera_installations ci ON ica.camera_installation_id = ci.id
             JOIN stores s ON ci.store_id = s.id
             WHERE ica.invoice_id = :invoice_id
             AND ica.camera_installation_id IS NOT NULL
             ORDER BY s.store_name, ci.installation_date, ci.camera_type",
            ['invoice_id' => $invoiceId]
        );
    }

    /**
     * Remove a specific camera allocation
     */
    public function removeAllocation($allocationId) {
        return $this->db->delete(
            'invoice_camera_allocations',
            'id = :id',
            ['id' => $allocationId]
        );
    }

    /**
     * Delete all allocations for an invoice
     */
    public function clearAllocations($invoiceId) {
        return $this->db->delete(
            'invoice_camera_allocations',
            'invoice_id = :invoice_id',
            ['invoice_id' => $invoiceId]
        );
    }
}
