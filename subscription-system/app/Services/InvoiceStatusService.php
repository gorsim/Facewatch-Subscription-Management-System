<?php
/**
 * Invoice Status Service
 * Handles invoice status transitions and validation
 */

namespace App\Services;

use App\Database;
use Exception;

class InvoiceStatusService {
    private $db;
    
    // Valid status transitions
    private $validTransitions = [
        'draft' => ['issued', 'cancelled'],
        'issued' => ['reconciled_to_xero', 'cancelled'],
        'reconciled_to_xero' => [], // Cannot change once reconciled
        'cancelled' => [], // Cannot change once cancelled
        'merged' => [] // Cannot change once merged
    ];
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Change invoice status
     * 
     * @param int $invoiceId
     * @param string $newStatus
     * @param string $changedBy User making the change
     * @param string $notes Optional notes about the change
     * @return bool Success
     */
    public function changeStatus($invoiceId, $newStatus, $changedBy, $notes = null) {
        // Get current invoice
        $invoice = $this->db->fetchOne(
            "SELECT * FROM invoices WHERE id = :id",
            ['id' => $invoiceId]
        );
        
        if (!$invoice) {
            throw new Exception("Invoice not found");
        }
        
        $oldStatus = $invoice['invoice_status'];
        
        // Validate transition
        if (!$this->isValidTransition($oldStatus, $newStatus)) {
            throw new Exception("Invalid status transition from '$oldStatus' to '$newStatus'");
        }
        
        $this->db->beginTransaction();
        
        try {
            // Update invoice status
            $this->db->update('invoices', [
                'invoice_status' => $newStatus
            ], 'id = :id', ['id' => $invoiceId]);
            
            // If reconciling to Xero, record reconciliation date
            if ($newStatus === 'reconciled_to_xero') {
                $this->db->update('invoices', [
                    'reconciled_date' => date('Y-m-d'),
                    'reconciled_by' => $changedBy
                ], 'id = :id', ['id' => $invoiceId]);
            }
            
            // Record status change in history
            $this->db->insert('invoice_status_history', [
                'invoice_id' => $invoiceId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changed_by' => $changedBy,
                'notes' => $notes
            ]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Check if a status transition is valid
     */
    public function isValidTransition($fromStatus, $toStatus) {
        if (!isset($this->validTransitions[$fromStatus])) {
            return false;
        }
        
        return in_array($toStatus, $this->validTransitions[$fromStatus]);
    }
    
    /**
     * Get available status transitions for an invoice
     */
    public function getAvailableTransitions($invoiceId) {
        $invoice = $this->db->fetchOne(
            "SELECT invoice_status FROM invoices WHERE id = :id",
            ['id' => $invoiceId]
        );
        
        if (!$invoice) {
            return [];
        }
        
        return $this->validTransitions[$invoice['invoice_status']] ?? [];
    }
    
    /**
     * Get status change history for an invoice
     */
    public function getStatusHistory($invoiceId) {
        return $this->db->fetchAll(
            "SELECT * FROM invoice_status_history
             WHERE invoice_id = :invoice_id
             ORDER BY changed_at DESC",
            ['invoice_id' => $invoiceId]
        );
    }
    
    /**
     * Check if invoice can be edited
     */
    public function canEdit($invoiceId) {
        $invoice = $this->db->fetchOne(
            "SELECT invoice_status FROM invoices WHERE id = :id",
            ['id' => $invoiceId]
        );
        
        if (!$invoice) {
            return false;
        }
        
        // Only draft invoices can be edited
        return $invoice['invoice_status'] === 'draft';
    }
    
    /**
     * Check if invoice can be deleted
     */
    public function canDelete($invoiceId) {
        $invoice = $this->db->fetchOne(
            "SELECT invoice_status FROM invoices WHERE id = :id",
            ['id' => $invoiceId]
        );
        
        if (!$invoice) {
            return false;
        }
        
        // Only draft invoices can be deleted
        return $invoice['invoice_status'] === 'draft';
    }
    
    /**
     * Reconcile invoice to Xero
     */
    public function reconcileToXero($invoiceId, $xeroInvoiceId, $xeroInvoiceNumber, $xeroInvoiceAmount, $reconciledBy) {
        // Get current invoice
        $invoice = $this->db->fetchOne(
            "SELECT * FROM invoices WHERE id = :id",
            ['id' => $invoiceId]
        );

        if (!$invoice) {
            throw new Exception("Invoice not found");
        }

        $oldStatus = $invoice['invoice_status'];

        // Validate transition
        if (!$this->isValidTransition($oldStatus, 'reconciled_to_xero')) {
            throw new Exception("Invalid status transition from '$oldStatus' to 'reconciled_to_xero'");
        }

        $this->db->beginTransaction();

        try {
            // Calculate variance between system amount and Xero amount
            $variance = $invoice['invoice_amount'] - $xeroInvoiceAmount;

            // Update invoice status and Xero details
            $this->db->update('invoices', [
                'invoice_status' => 'reconciled_to_xero',
                'xero_invoice_id' => $xeroInvoiceId,
                'xero_invoice_number' => $xeroInvoiceNumber,
                'reconciled_date' => date('Y-m-d'),
                'reconciled_by' => $reconciledBy
            ], 'id = :id', ['id' => $invoiceId]);

            // Record status change in history
            $varianceNote = '';
            if (abs($variance) > 0.01) {
                $varianceNote = sprintf(
                    " (Variance: £%.2f - System: £%.2f, Xero: £%.2f)",
                    $variance,
                    $invoice['invoice_amount'],
                    $xeroInvoiceAmount
                );
            }

            $this->db->insert('invoice_status_history', [
                'invoice_id' => $invoiceId,
                'old_status' => $oldStatus,
                'new_status' => 'reconciled_to_xero',
                'changed_by' => $reconciledBy,
                'notes' => "Reconciled to Xero invoice $xeroInvoiceNumber" . $varianceNote
            ]);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}

