<?php
/**
 * Invoice Reconciliation Service
 * Calculates expected invoice amounts and reconciliation status
 * Based on Xero Customer Number + Invoice Date matching
 */

namespace App\Services;

use App\Database;

class InvoiceReconciliationService {
    private $db;
    private $pricingService;
    private $tolerance = 1.00; // £1 tolerance for matching

    public function __construct() {
        $this->db = Database::getInstance();
        $this->pricingService = new PricingService();
    }
    
    /**
     * Reconcile a single invoice by ID
     * Calculates expected amount and updates reconciliation fields
     * 
     * @param int $invoiceId
     * @return array Reconciliation result
     */
    public function reconcileInvoice($invoiceId) {
        // Get invoice with legal entity details
        $invoice = $this->db->fetchOne(
            "SELECT i.*, le.xero_company_name, le.legal_entity_name
             FROM invoices i
             JOIN legal_entities le ON i.legal_entity_id = le.id
             WHERE i.id = :id",
            ['id' => $invoiceId]
        );

        if (!$invoice) {
            return ['error' => 'Invoice not found'];
        }

        // Calculate expected amount from camera allocations
        $expectedAmount = $this->calculateExpectedAmount($invoiceId);

        // Calculate variance (for reconciled invoices with Xero amounts)
        $variance = $invoice['invoice_amount'] - $expectedAmount;

        // Determine reconciliation status
        $status = $this->determineStatus($variance);

        // Update invoice with reconciliation data AND set invoice_amount to match camera allocations
        // The invoice amount should always equal the sum of allocated cameras
        $this->db->query(
            "UPDATE invoices
             SET invoice_amount = :invoice_amount,
                 expected_amount = :expected_amount,
                 variance = :variance,
                 reconciliation_status = :status
             WHERE id = :id",
            [
                'invoice_amount' => $expectedAmount,
                'expected_amount' => $expectedAmount,
                'variance' => $variance,
                'status' => $status,
                'id' => $invoiceId
            ]
        );

        // Update all forecast invoices linked to this invoice
        $this->updateForecastInvoices($invoiceId, $expectedAmount);
        
        return [
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoice['invoice_number'],
            'invoice_date' => $invoice['invoice_date'],
            'actual_amount' => $invoice['invoice_amount'],
            'expected_amount' => $expectedAmount,
            'variance' => $variance,
            'status' => $status
        ];
    }
    
    /**
     * Calculate expected invoice amount from camera allocations
     * 
     * @param int $invoiceId
     * @return float Expected amount
     */
    public function calculateExpectedAmount($invoiceId) {
        $total = $this->db->fetchOne(
            "SELECT COALESCE(SUM(price_charged), 0) as total
             FROM invoice_camera_allocations
             WHERE invoice_id = :invoice_id
             AND removed_date IS NULL",
            ['invoice_id' => $invoiceId]
        );
        
        return (float) ($total['total'] ?? 0);
    }
    
    /**
     * Determine reconciliation status based on variance
     *
     * @param float $variance
     * @return string Status: matched, under_charged, over_charged, pending
     */
    private function determineStatus($variance) {
        if (abs($variance) <= $this->tolerance) {
            return 'matched';
        } elseif ($variance < 0) {
            return 'under_charged';
        } else {
            return 'over_charged';
        }
    }

    /**
     * Update all forecast invoices linked to a parent invoice
     * When the parent invoice amount changes, all forecast invoices should update to match
     *
     * @param int $parentInvoiceId The parent invoice ID
     * @param float $newAmount The new invoice amount
     * @return int Number of forecast invoices updated
     */
    private function updateForecastInvoices($parentInvoiceId, $newAmount) {
        // Get parent invoice details
        $parent = $this->db->fetchOne(
            "SELECT * FROM invoices WHERE id = :id",
            ['id' => $parentInvoiceId]
        );

        if (!$parent) {
            return 0;
        }

        // Get all forecast invoices for this parent
        $forecasts = $this->db->fetchAll(
            "SELECT id, invoice_date, forecast_year FROM invoices
             WHERE parent_invoice_id = :parent_id
             AND is_forecast = 1
             ORDER BY forecast_year ASC",
            ['parent_id' => $parentInvoiceId]
        );

        if (empty($forecasts)) {
            return 0;
        }

        // Get current camera allocations from parent invoice
        $parentCameras = $this->db->fetchAll(
            "SELECT
                camera_installation_id,
                store_id,
                legal_entity_id,
                camera_serial,
                camera_type,
                store_name,
                pricing_tier
             FROM invoice_camera_allocations
             WHERE invoice_id = :invoice_id
             AND removed_date IS NULL",
            ['invoice_id' => $parentInvoiceId]
        );

        $cameraCount = count($parentCameras);

        // Update each forecast invoice
        foreach ($forecasts as $forecast) {
            // Calculate forecast price with inflation and future pricing
            $forecastPrice = $this->calculateForecastPrice(
                $newAmount,
                $forecast['invoice_date'],
                $parent['invoice_date'],
                $parent['legal_entity_id'],
                $cameraCount
            );

            // Update invoice amount
            $this->db->query(
                "UPDATE invoices
                 SET invoice_amount = :invoice_amount,
                     expected_amount = :expected_amount
                 WHERE id = :id",
                [
                    'invoice_amount' => $forecastPrice,
                    'expected_amount' => $forecastPrice,
                    'id' => $forecast['id']
                ]
            );

            // Remove all existing camera allocations from this forecast
            $this->db->query(
                "DELETE FROM invoice_camera_allocations
                 WHERE invoice_id = :invoice_id",
                ['invoice_id' => $forecast['id']]
            );

            // Calculate price per camera for this forecast
            $pricePerCamera = $cameraCount > 0 ? $forecastPrice / $cameraCount : 0;

            // Add current camera allocations from parent
            foreach ($parentCameras as $camera) {
                $this->db->insert('invoice_camera_allocations', [
                    'invoice_id' => $forecast['id'],
                    'camera_installation_id' => $camera['camera_installation_id'],
                    'store_id' => $camera['store_id'],
                    'legal_entity_id' => $camera['legal_entity_id'],
                    'camera_serial' => $camera['camera_serial'],
                    'camera_type' => $camera['camera_type'],
                    'store_name' => $camera['store_name'],
                    'price_charged' => $pricePerCamera,
                    'pricing_tier' => $camera['pricing_tier'] ?? 'tier_1-49',
                    'allocated_date' => date('Y-m-d H:i:s'),
                    'allocated_by' => 'forecast_sync_system'
                ]);
            }
        }

        // Log the update
        $count = count($forecasts);
        if ($count > 0) {
            error_log("Updated $count forecast invoices for parent invoice $parentInvoiceId with $cameraCount cameras (with inflation)");
        }

        return $count;
    }

    /**
     * Calculate forecast price with inflation and future pricing tiers
     *
     * @param float $basePrice Current invoice amount
     * @param string $forecastDate Date of forecast invoice
     * @param string $baseDate Date of parent invoice
     * @param int $legalEntityId Legal entity ID
     * @param int $cameraCount Number of cameras on this invoice
     * @return float Forecast price with inflation or future pricing applied
     */
    private function calculateForecastPrice($basePrice, $forecastDate, $baseDate, $legalEntityId, $cameraCount) {
        // Get TOTAL installed cameras for the legal entity (not just this invoice)
        // This determines the pricing tier
        $totalCameras = $this->db->fetchOne("
            SELECT COUNT(DISTINCT ci.id) as total
            FROM camera_installations ci
            JOIN stores s ON ci.store_id = s.id
            WHERE s.legal_entity_id = :legal_entity_id
            AND ci.installation_date <= :forecast_date
            AND (ci.removal_date IS NULL OR ci.removal_date > :forecast_date2)
        ", [
            'legal_entity_id' => $legalEntityId,
            'forecast_date' => $forecastDate,
            'forecast_date2' => $forecastDate
        ]);

        $totalCameraCount = (int)($totalCameras['total'] ?? $cameraCount);

        // If no cameras will be installed by the forecast date, use current camera count
        // This handles future forecasts where cameras haven't been installed yet
        if ($totalCameraCount === 0) {
            $totalCameraCount = $cameraCount;
        }

        // Safety check: if still 0, just return base price
        if ($totalCameraCount === 0 || $cameraCount === 0) {
            error_log("Warning: Zero cameras for forecast date $forecastDate, using base price: £" . number_format($basePrice, 2));
            return $basePrice;
        }

        // Try to get future pricing from database for this date and TOTAL camera count
        try {
            $futurePricing = $this->pricingService->getPricingForEntity(
                $legalEntityId,
                $totalCameraCount,
                $forecastDate
            );

            // Calculate price for THIS invoice's cameras using the tier rate
            $futurePrice = $futurePricing['rate_to_use'] * $cameraCount;

            // Log if we're using future pricing
            error_log("Using future pricing for forecast date $forecastDate: £" . number_format($futurePrice, 2) . " (tier: {$futurePricing['tier_name']} based on $totalCameraCount total cameras, $cameraCount on invoice)");

            return $futurePrice;
        } catch (\Exception $e) {
            // No future pricing found, use current price (no inflation)
            // Inflation should be handled by adding future pricing tiers to the database
            error_log("No future pricing found for $forecastDate, using current price: £" . number_format($basePrice, 2));

            return $basePrice;
        }
    }
    
    /**
     * Reconcile all invoices in the system
     * 
     * @return array Summary of reconciliation results
     */
    public function reconcileAll() {
        $invoices = $this->db->fetchAll("SELECT id FROM invoices");
        
        $results = [
            'total' => count($invoices),
            'matched' => 0,
            'under_charged' => 0,
            'over_charged' => 0,
            'pending' => 0
        ];
        
        foreach ($invoices as $invoice) {
            $result = $this->reconcileInvoice($invoice['id']);
            if (isset($result['status'])) {
                $results[$result['status']]++;
            }
        }
        
        return $results;
    }
    
    /**
     * Get reconciliation report for a specific month
     * 
     * @param string $month Month in YYYY-MM format
     * @return array List of invoices with reconciliation details
     */
    public function getMonthlyReport($month) {
        $startDate = $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));

        return $this->db->fetchAll(
            "SELECT i.*, le.legal_entity_name, le.xero_company_name
             FROM invoices i
             JOIN legal_entities le ON i.legal_entity_id = le.id
             WHERE i.invoice_date BETWEEN :start AND :end
             ORDER BY i.invoice_date, le.legal_entity_name",
            ['start' => $startDate, 'end' => $endDate]
        );
    }
}

