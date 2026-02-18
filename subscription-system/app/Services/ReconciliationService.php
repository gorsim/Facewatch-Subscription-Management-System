<?php
/**
 * Reconciliation Service
 * Checks invoice amounts against expected amounts based on camera counts and pricing
 */

namespace App\Services;

use App\Database;

class ReconciliationService {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Reconcile all invoices
     * Returns array of reconciliation results
     */
    public function reconcileAll() {
        $sql = "
            SELECT
                i.id as invoice_id,
                i.invoice_number,
                i.invoice_date,
                i.invoice_amount as actual_amount,
                i.main_cameras as invoiced_main,
                i.additional_cameras as invoiced_additional,
                le.id as legal_entity_id,
                le.legal_entity_name,
                lec.main_camera_rate,
                lec.additional_camera_rate,
                (SELECT SUM(CASE WHEN ci.camera_type = 'main' THEN 1 ELSE 0 END)
                 FROM camera_installations ci
                 JOIN stores s ON ci.store_id = s.id
                 WHERE s.legal_entity_id = le.id
                 AND ci.installation_date <= i.invoice_date
                 AND (ci.removal_date IS NULL OR ci.removal_date > i.invoice_date)) as installed_main,
                (SELECT SUM(CASE WHEN ci.camera_type = 'additional' THEN 1 ELSE 0 END)
                 FROM camera_installations ci
                 JOIN stores s ON ci.store_id = s.id
                 WHERE s.legal_entity_id = le.id
                 AND ci.installation_date <= i.invoice_date
                 AND (ci.removal_date IS NULL OR ci.removal_date > i.invoice_date)) as installed_additional
            FROM invoices i
            JOIN legal_entities le ON i.legal_entity_id = le.id
            LEFT JOIN legal_entity_contracts lec ON le.id = lec.legal_entity_id
                AND lec.effective_date <= i.invoice_date
                AND lec.id = (
                    SELECT id FROM legal_entity_contracts
                    WHERE legal_entity_id = le.id
                    AND effective_date <= i.invoice_date
                    ORDER BY effective_date DESC LIMIT 1
                )
            ORDER BY i.invoice_date DESC
        ";
        
        $invoices = $this->db->fetchAll($sql);
        $results = [];
        
        foreach ($invoices as $invoice) {
            $results[] = $this->reconcileInvoice($invoice);
        }
        
        return $results;
    }
    
    /**
     * Reconcile a single invoice
     */
    private function reconcileInvoice($invoice) {
        // Calculate expected amount
        $expectedAmount = 
            ($invoice['invoiced_main'] * $invoice['main_camera_rate']) +
            ($invoice['invoiced_additional'] * $invoice['additional_camera_rate']);
        
        // Calculate variance
        $amountVariance = $invoice['actual_amount'] - $expectedAmount;
        
        // Check installation variance
        $installationVarianceMain = $invoice['invoiced_main'] - ($invoice['installed_main'] ?? 0);
        $installationVarianceAdditional = $invoice['invoiced_additional'] - ($invoice['installed_additional'] ?? 0);
        
        // Determine status
        $status = 'ok';
        if (abs($amountVariance) > 0.01) {
            $status = $amountVariance < 0 ? 'under_charged' : 'over_charged';
        }
        if ($installationVarianceMain != 0 || $installationVarianceAdditional != 0) {
            $status = 'installation_mismatch';
        }
        
        return [
            'invoice_id' => $invoice['invoice_id'],
            'invoice_number' => $invoice['invoice_number'],
            'legal_entity_name' => $invoice['legal_entity_name'],
            'invoice_date' => $invoice['invoice_date'],
            'actual_amount' => $invoice['actual_amount'],
            'expected_amount' => round($expectedAmount, 2),
            'amount_variance' => round($amountVariance, 2),
            'invoiced_main' => $invoice['invoiced_main'],
            'invoiced_additional' => $invoice['invoiced_additional'],
            'installed_main' => $invoice['installed_main'] ?? 0,
            'installed_additional' => $invoice['installed_additional'] ?? 0,
            'installation_variance_main' => $installationVarianceMain,
            'installation_variance_additional' => $installationVarianceAdditional,
            'status' => $status,
        ];
    }
    
    /**
     * Get reconciliation issues (variances)
     */
    public function getIssues() {
        $all = $this->reconcileAll();
        return array_filter($all, function($item) {
            return $item['status'] !== 'ok';
        });
    }
    
    /**
     * Save reconciliation results to database
     */
    public function saveReconciliation($results) {
        foreach ($results as $result) {
            // Check if already exists
            $existing = $this->db->fetchOne(
                "SELECT id FROM reconciliation_log WHERE invoice_id = :invoice_id AND reconciliation_date = CURDATE()",
                ['invoice_id' => $result['invoice_id']]
            );
            
            if (!$existing) {
                $this->db->insert('reconciliation_log', [
                    'invoice_id' => $result['invoice_id'],
                    'legal_entity_id' => $result['legal_entity_id'] ?? null,
                    'reconciliation_date' => date('Y-m-d'),
                    'expected_amount' => $result['expected_amount'],
                    'actual_amount' => $result['actual_amount'],
                    'variance' => $result['amount_variance'],
                    'status' => $result['status'],
                    'invoiced_main_cameras' => $result['invoiced_main'],
                    'invoiced_additional_cameras' => $result['invoiced_additional'],
                    'installed_main_cameras' => $result['installed_main'],
                    'installed_additional_cameras' => $result['installed_additional'],
                ]);
            }
        }
    }
}

