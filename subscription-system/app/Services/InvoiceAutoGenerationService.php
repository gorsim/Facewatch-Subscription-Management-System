<?php
/**
 * Invoice Auto-Generation Service
 * Handles automatic generation of repeat invoices on anniversary dates
 */

namespace App\Services;

use App\Database;
use DateTime;
use Exception;

class InvoiceAutoGenerationService {
    private $db;
    private $generationService;
    private $pricingService;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->generationService = new InvoiceGenerationService();
        $this->pricingService = new PricingService();
    }
    
    /**
     * Run the auto-generation process
     * Finds all invoices due for generation and creates new ones
     * 
     * @param string $asOfDate Date to check (defaults to today)
     * @return array Summary of generation results
     */
    public function runAutoGeneration($asOfDate = null) {
        if ($asOfDate === null) {
            $asOfDate = date('Y-m-d');
        }
        
        $results = [
            'checked' => 0,
            'generated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'invoices' => []
        ];
        
        // Find all invoices due for generation
        $dueInvoices = $this->findInvoicesDueForGeneration($asOfDate);
        $results['checked'] = count($dueInvoices);
        
        foreach ($dueInvoices as $parentInvoice) {
            try {
                // Check if already generated
                if ($this->hasBeenGenerated($parentInvoice['id'], $asOfDate)) {
                    $results['skipped']++;
                    $results['invoices'][] = [
                        'parent_id' => $parentInvoice['id'],
                        'parent_number' => $parentInvoice['invoice_number'],
                        'status' => 'skipped',
                        'reason' => 'Already generated for this period'
                    ];
                    continue;
                }
                
                // Generate new invoice
                $newInvoiceId = $this->generateNextInvoice($parentInvoice, $asOfDate);
                
                $results['generated']++;
                $results['invoices'][] = [
                    'parent_id' => $parentInvoice['id'],
                    'parent_number' => $parentInvoice['invoice_number'],
                    'new_id' => $newInvoiceId,
                    'status' => 'generated'
                ];
                
            } catch (Exception $e) {
                $results['errors']++;
                $results['invoices'][] = [
                    'parent_id' => $parentInvoice['id'],
                    'parent_number' => $parentInvoice['invoice_number'],
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
                
                error_log("Auto-generation error for invoice {$parentInvoice['invoice_number']}: " . $e->getMessage());
            }
        }
        
        return $results;
    }
    
    /**
     * Find invoices that are due for generation
     */
    private function findInvoicesDueForGeneration($asOfDate) {
        return $this->db->fetchAll(
            "SELECT i.*, le.legal_entity_name, le.payment_frequency
             FROM invoices i
             JOIN legal_entities le ON i.legal_entity_id = le.id
             WHERE i.next_generation_date <= :as_of_date
             AND i.invoice_status NOT IN ('cancelled', 'merged')
             AND le.termination_date IS NULL
             ORDER BY i.next_generation_date ASC",
            ['as_of_date' => $asOfDate]
        );
    }
    
    /**
     * Check if invoice has already been generated for this period
     */
    private function hasBeenGenerated($parentInvoiceId, $asOfDate) {
        $existing = $this->db->fetchOne(
            "SELECT id FROM invoices
             WHERE parent_invoice_id = :parent_id
             AND invoice_date >= :as_of_date
             LIMIT 1",
            [
                'parent_id' => $parentInvoiceId,
                'as_of_date' => $asOfDate
            ]
        );
        
        return $existing !== null;
    }
    
    /**
     * Generate the next invoice based on parent
     */
    private function generateNextInvoice($parentInvoice, $generationDate) {
        // Get current active cameras for this legal entity
        $cameras = $this->db->fetchAll(
            "SELECT ci.id
             FROM camera_installations ci
             JOIN stores s ON ci.store_id = s.id
             WHERE s.legal_entity_id = :legal_entity_id
             AND ci.removal_date IS NULL",
            ['legal_entity_id' => $parentInvoice['legal_entity_id']]
        );
        
        $cameraIds = array_column($cameras, 'id');
        
        // Create new invoice
        $newInvoiceId = $this->generationService->createInvoice(
            $parentInvoice['legal_entity_id'],
            $generationDate,
            [
                'is_auto_generated' => true,
                'parent_invoice_id' => $parentInvoice['id'],
                'created_by' => 'auto_generation_system',
                'notes' => "Auto-generated from invoice {$parentInvoice['invoice_number']}"
            ]
        );
        
        // Allocate cameras
        if (!empty($cameraIds)) {
            $this->generationService->allocateCameras(
                $newInvoiceId,
                $cameraIds,
                'auto_generation_system'
            );
        }
        
        return $newInvoiceId;
    }

    /**
     * Generate forecast invoices through to 31st March 2031
     * Called when a new invoice is created to project future renewals
     *
     * @param int $parentInvoiceId The invoice to forecast from
     * @param string|null $endDate End date for forecasts (default: 2031-03-31)
     * @return array Array of created forecast invoice IDs
     */
    public function generateForecastInvoices($parentInvoiceId, $endDate = '2031-03-31') {
        $parent = $this->db->fetchOne("
            SELECT * FROM invoices WHERE id = :id
        ", ['id' => $parentInvoiceId]);

        if (!$parent) {
            throw new Exception("Parent invoice not found: $parentInvoiceId");
        }

        // Don't generate forecasts for forecast invoices
        if ($parent['is_forecast']) {
            return [];
        }

        // Check if legal entity has termination date
        $legalEntity = $this->db->fetchOne("
            SELECT termination_date FROM legal_entities WHERE id = :id
        ", ['id' => $parent['legal_entity_id']]);

        $terminationDate = $legalEntity['termination_date'] ?? null;

        // Use the earlier of termination date or 31/3/31
        $finalEndDate = $endDate;
        if ($terminationDate && $terminationDate < $endDate) {
            $finalEndDate = $terminationDate;
        }

        $forecastIds = [];
        $period = 1;

        // Keep generating until we pass the end date
        while (true) {
            $forecastDate = $this->calculateNextDate($parent['invoice_date'], $period, $parent['payment_frequency']);

            // Stop if forecast date is after the final end date
            if ($forecastDate > $finalEndDate) {
                break;
            }

            try {
                $forecastId = $this->createForecastInvoice($parent, $forecastDate, $period);
                $forecastIds[] = $forecastId;
            } catch (Exception $e) {
                // Log error but continue with other forecasts
                error_log("Failed to create forecast invoice period $period: " . $e->getMessage());
            }

            $period++;

            // Safety check to prevent infinite loops
            // Max 240 periods (20 years monthly, 80 quarters, or 20 years annual)
            if ($period > 240) {
                error_log("WARNING: Stopped generating forecasts after 240 periods for invoice $parentInvoiceId");
                break;
            }
        }

        return $forecastIds;
    }

    /**
     * Create a single forecast invoice
     *
     * @param array $parent Parent invoice data
     * @param string $forecastDate Date for the forecast invoice
     * @param int $periodOffset Which period ahead (1, 2, 3...) - could be months, quarters, or years
     * @return int Created forecast invoice ID
     */
    private function createForecastInvoice($parent, $forecastDate, $periodOffset) {
        // Get cameras allocated to parent invoice with full details
        $cameras = $this->db->fetchAll("
            SELECT
                ica.camera_installation_id,
                ica.price_charged,
                ica.pricing_tier,
                ica.store_id,
                ica.legal_entity_id,
                ica.camera_serial,
                ica.camera_type,
                ica.store_name
            FROM invoice_camera_allocations ica
            WHERE ica.invoice_id = :invoice_id
        ", ['invoice_id' => $parent['id']]);

        $cameraInstallationIds = array_column($cameras, 'camera_installation_id');

        // Calculate forecast pricing with inflation
        $forecastPrice = $this->calculateForecastPrice(
            $parent['invoice_amount'],
            $forecastDate,
            $parent['invoice_date']
        );

        // Generate invoice number
        $invoiceNumber = $this->generateForecastInvoiceNumber();

        // Calculate next generation date
        $nextGenDate = $this->calculateNextDate($forecastDate, 1, $parent['payment_frequency']);

        // Create forecast invoice
        $invoiceId = $this->db->insert('invoices', [
            'invoice_number' => $invoiceNumber,
            'legal_entity_id' => $parent['legal_entity_id'],
            'invoice_date' => $forecastDate,
            'invoice_amount' => $forecastPrice,
            'payment_frequency' => $parent['payment_frequency'],
            'invoice_status' => 'forecast',
            'is_forecast' => true,
            'forecast_year' => $periodOffset,
            'parent_invoice_id' => $parent['id'],
            'next_generation_date' => $nextGenDate,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => 'auto_forecast_system'
        ]);

        // Allocate same cameras with forecast pricing
        if (!empty($cameraInstallationIds)) {
            $pricePerCamera = count($cameraInstallationIds) > 0 ? $forecastPrice / count($cameraInstallationIds) : 0;

            foreach ($cameras as $camera) {
                $this->db->insert('invoice_camera_allocations', [
                    'invoice_id' => $invoiceId,
                    'camera_installation_id' => $camera['camera_installation_id'],
                    'store_id' => $camera['store_id'],
                    'legal_entity_id' => $camera['legal_entity_id'],
                    'camera_serial' => $camera['camera_serial'],
                    'camera_type' => $camera['camera_type'],
                    'store_name' => $camera['store_name'],
                    'price_charged' => $pricePerCamera,
                    'pricing_tier' => $camera['pricing_tier'] ?? 'tier_1-49',
                    'allocated_date' => date('Y-m-d H:i:s'),
                    'allocated_by' => 'auto_forecast_system'
                ]);
            }
        }

        // Log the generation
        $this->db->insert('invoice_generation_log', [
            'invoice_id' => $invoiceId,
            'generation_type' => 'auto_repeat',
            'generation_date' => date('Y-m-d H:i:s'),
            'triggered_by' => 'auto_forecast_system',
            'camera_count' => count($cameraInstallationIds),
            'total_amount' => $forecastPrice,
            'notes' => "Forecast period {$periodOffset} from invoice {$parent['invoice_number']} (ID: {$parent['id']})"
        ]);

        return $invoiceId;
    }

    /**
     * Calculate forecast price with inflation
     *
     * @param float $basePrice Original invoice amount
     * @param string $forecastDate Date of forecast invoice
     * @param string $baseDate Date of original invoice
     * @return float Forecast price with inflation applied
     */
    private function calculateForecastPrice($basePrice, $forecastDate, $baseDate) {
        $forecastYear = (int)date('Y', strtotime($forecastDate));
        $baseYear = (int)date('Y', strtotime($baseDate));

        // Check if invoice renews after 1st January
        $forecastMonthDay = date('m-d', strtotime($forecastDate));

        if ($forecastMonthDay >= '01-01') {
            // Try to get scheduled pricing for this year
            // For now, we'll use 3% default inflation
            // TODO: Integrate with pricing schedules when available

            $yearsElapsed = $forecastYear - $baseYear;
            if ($yearsElapsed > 0) {
                // Apply 3% compound inflation
                return $basePrice * pow(1.03, $yearsElapsed);
            }
        }

        return $basePrice;
    }

    /**
     * Calculate next date based on payment frequency
     *
     * @param string $baseDate Starting date
     * @param int $periods Number of periods ahead
     * @param string $paymentFrequency Payment frequency (monthly, quarterly, annual)
     * @return string Next date
     */
    private function calculateNextDate($baseDate, $periods = 1, $paymentFrequency = 'annual') {
        $date = new DateTime($baseDate);

        switch ($paymentFrequency) {
            case 'monthly':
                $date->modify("+{$periods} month");
                break;
            case 'quarterly':
                $months = $periods * 3;
                $date->modify("+{$months} month");
                break;
            case 'annual':
            default:
                $date->modify("+{$periods} year");
                break;
        }

        return $date->format('Y-m-d');
    }

    /**
     * Generate invoice number for forecast invoices
     * Format: INV-### (sequential)
     */
    private function generateForecastInvoiceNumber() {
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
}

