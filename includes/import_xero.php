<?php
/**
 * Xero Invoice Import Functions
 */

/**
 * Import Xero invoices from CSV file
 * 
 * Expected CSV columns:
 * - Invoice Number
 * - Customer Name
 * - Invoice Date
 * - Due Date
 * - Amount (VAT exclusive)
 * - Status
 */
function import_xero_csv($filepath, $user_id) {
    $result = parse_csv($filepath, true);
    
    if (!$result['success']) {
        return $result;
    }
    
    $data = $result['data'];
    $stats = [
        'total' => count($data),
        'imported' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => []
    ];
    
    // Log import start
    $import_log_id = log_import_start($user_id, 'xero_invoices', $filepath);
    
    db_begin_transaction();
    
    try {
        foreach ($data as $row_num => $row) {
            $line_num = $row_num + 2; // +2 for header and 0-index
            
            // Validate required fields
            if (empty($row['Invoice Number']) || empty($row['Customer Name']) || empty($row['Invoice Date'])) {
                $stats['errors'][] = "Line $line_num: Missing required fields";
                $stats['skipped']++;
                continue;
            }
            
            // Find or create subscriber
            $subscriber = find_or_create_subscriber($row['Customer Name']);
            
            if (!$subscriber) {
                $stats['errors'][] = "Line $line_num: Could not create subscriber for '{$row['Customer Name']}'";
                $stats['skipped']++;
                continue;
            }
            
            // Parse dates
            $invoice_date = parse_date($row['Invoice Date']);
            $due_date = !empty($row['Due Date']) ? parse_date($row['Due Date']) : null;
            
            if (!$invoice_date) {
                $stats['errors'][] = "Line $line_num: Invalid invoice date '{$row['Invoice Date']}'";
                $stats['skipped']++;
                continue;
            }
            
            // Parse amount
            $amount = parse_currency($row['Amount']);
            
            if ($amount === false) {
                $stats['errors'][] = "Line $line_num: Invalid amount '{$row['Amount']}'";
                $stats['skipped']++;
                continue;
            }
            
            // Determine payment status
            $payment_status = 'pending';
            if (!empty($row['Status'])) {
                $status_lower = strtolower($row['Status']);
                if (strpos($status_lower, 'paid') !== false) {
                    $payment_status = 'paid';
                } elseif (strpos($status_lower, 'void') !== false || strpos($status_lower, 'cancelled') !== false) {
                    $payment_status = 'cancelled';
                }
            }
            
            // Check if invoice already exists
            $existing = db_query_one(
                "SELECT id FROM invoices WHERE invoice_number = ?",
                [$row['Invoice Number']]
            );
            
            if ($existing) {
                // Update existing invoice
                $sql = "UPDATE invoices SET
                        subscriber_id = ?,
                        invoice_date = ?,
                        due_date = ?,
                        invoice_amount = ?,
                        payment_status = ?,
                        updated_at = NOW()
                        WHERE id = ?";
                
                db_execute($sql, [
                    $subscriber['id'],
                    $invoice_date,
                    $due_date,
                    $amount,
                    $payment_status,
                    $existing['id']
                ]);
                
                log_audit($user_id, 'invoices', $existing['id'], 'update', null, [
                    'invoice_number' => $row['Invoice Number'],
                    'amount' => $amount,
                    'source' => 'xero_import'
                ]);
                
                $stats['updated']++;
            } else {
                // Insert new invoice
                // Note: main_cameras and additional_cameras will need to be set manually or from another import
                $sql = "INSERT INTO invoices 
                        (subscriber_id, invoice_number, invoice_date, due_date, invoice_amount, 
                         payment_status, payment_frequency, main_cameras, additional_cameras)
                        VALUES (?, ?, ?, ?, ?, ?, 'annual', 0, 0)";
                
                db_execute($sql, [
                    $subscriber['id'],
                    $row['Invoice Number'],
                    $invoice_date,
                    $due_date,
                    $amount,
                    $payment_status
                ]);
                
                $invoice_id = db_last_insert_id();
                
                log_audit($user_id, 'invoices', $invoice_id, 'insert', null, [
                    'invoice_number' => $row['Invoice Number'],
                    'amount' => $amount,
                    'source' => 'xero_import'
                ]);
                
                $stats['imported']++;
            }
        }
        
        db_commit();
        log_import_complete($import_log_id, $stats);
        
        $stats['success'] = true;
        $stats['message'] = "Import complete: {$stats['imported']} new, {$stats['updated']} updated, {$stats['skipped']} skipped";
        
    } catch (Exception $e) {
        db_rollback();
        log_import_error($import_log_id, $e->getMessage());
        
        $stats['success'] = false;
        $stats['message'] = 'Import failed: ' . $e->getMessage();
    }
    
    return $stats;
}

