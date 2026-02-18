<?php
/**
 * Xero CSV Importer Service
 * Imports invoice data from Xero CSV exports
 *
 * UPDATED: Now uses Legal Entity ID instead of Contact Name
 */

namespace App\Services;

use App\Models\Invoice;
use App\Models\LegalEntity;
use App\Database;

class XeroImporter {
    private $invoice;
    private $legalEntity;
    private $db;
    private $errors = [];
    private $imported = 0;
    private $skipped = 0;

    public function __construct() {
        $this->invoice = new Invoice();
        $this->legalEntity = new LegalEntity();
        $this->db = Database::getInstance();
    }

    /**
     * Import invoices from CSV file
     *
     * Expected CSV columns:
     * - Legal Entity ID (e.g., LE0001) OR Xero Company Name
     * - Legal Entity Name (for verification)
     * - Invoice Number
     * - Invoice Date
     * - Due Date
     * - Amount (VAT exclusive)
     * - Status (PAID/UNPAID)
     * - Payment Date (if paid)
     */
    public function import($filePath) {
        $this->errors = [];
        $this->imported = 0;
        $this->skipped = 0;
        
        if (!file_exists($filePath)) {
            $this->errors[] = "File not found: {$filePath}";
            return false;
        }
        
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $this->errors[] = "Could not open file: {$filePath}";
            return false;
        }
        
        // Read header row
        $headers = fgetcsv($handle);
        if (!$headers) {
            $this->errors[] = "Invalid CSV file - no headers found";
            fclose($handle);
            return false;
        }
        
        // Map headers to column indexes
        $columnMap = $this->mapColumns($headers);
        
        $lineNumber = 1;
        $this->db->beginTransaction();
        
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;
                
                try {
                    $this->importRow($row, $columnMap, $lineNumber);
                } catch (\Exception $e) {
                    $this->errors[] = "Line {$lineNumber}: " . $e->getMessage();
                }
            }

            // Log import (must be done before commit, while transaction is still active)
            $this->logImport($filePath);

            $this->db->commit();
            fclose($handle);

            return true;
            
        } catch (\Exception $e) {
            $this->db->rollback();
            fclose($handle);
            $this->errors[] = "Import failed: " . $e->getMessage();
            return false;
        }
    }
    
    private function mapColumns($headers) {
        $map = [];

        foreach ($headers as $index => $header) {
            $header = strtolower(trim($header));

            // Xero Company Name (exact match first) - PRIMARY
            if ($header === 'xero_company_name' || $header === 'xero company name') {
                $map['xero_company_name'] = $index;
            }
            // Legal Entity ID (exact match first)
            elseif ($header === 'legal_entity_id' || $header === 'legal entity id' || $header === 'entity id') {
                $map['legal_entity_id'] = $index;
            }
            // Legal Entity Name (exact match first)
            elseif ($header === 'legal_entity_name' || $header === 'legal entity name' || $header === 'entity name') {
                $map['legal_entity_name'] = $index;
            }
            // Contact Name (backward compatibility)
            elseif ($header === 'contact_name' || $header === 'contact name' || strpos($header, 'contact') !== false || strpos($header, 'customer') !== false) {
                $map['contact_name'] = $index;
            }
            // Invoice Number (exact match first)
            elseif ($header === 'invoice_number' || $header === 'invoice number' || $header === 'invoice #') {
                $map['invoice_number'] = $index;
            }
            // Invoice Date (exact match first)
            elseif ($header === 'invoice_date' || $header === 'invoice date' || $header === 'date') {
                $map['invoice_date'] = $index;
            }
            // Due Date (exact match first)
            elseif ($header === 'due_date' || $header === 'due date') {
                $map['due_date'] = $index;
            }
            // Amount (exact match first)
            elseif ($header === 'amount' || $header === 'amount due' || $header === 'total') {
                $map['amount'] = $index;
            }
            // Status (exact match first)
            elseif ($header === 'status') {
                $map['status'] = $index;
            }
            // Payment Date (exact match first)
            elseif ($header === 'payment_date' || $header === 'payment date' || $header === 'paid date') {
                $map['payment_date'] = $index;
            }
            // Main Camera Rate
            elseif ($header === 'main_camera_rate' || $header === 'main camera rate' || $header === 'main cam rate') {
                $map['main_camera_rate'] = $index;
            }
            // Additional Camera Rate
            elseif ($header === 'additional_camera_rate' || $header === 'additional camera rate' || $header === 'additional cam rate') {
                $map['additional_camera_rate'] = $index;
            }
            // Payment Frequency
            elseif ($header === 'payment_frequency' || $header === 'payment frequency' || $header === 'billing frequency') {
                $map['payment_frequency'] = $index;
            }
        }

        // Debug logging
        error_log("XeroImporter: Column map = " . print_r($map, true));
        error_log("XeroImporter: Headers = " . print_r($headers, true));

        return $map;
    }
    
    private function importRow($row, $columnMap, $lineNumber) {
        // Extract data - Support multiple formats
        $xeroCompanyName = isset($columnMap['xero_company_name']) ? trim($row[$columnMap['xero_company_name']] ?? '') : '';
        $legalEntityId = isset($columnMap['legal_entity_id']) ? trim($row[$columnMap['legal_entity_id']] ?? '') : '';
        $legalEntityName = isset($columnMap['legal_entity_name']) ? trim($row[$columnMap['legal_entity_name']] ?? '') : '';
        $contactName = isset($columnMap['contact_name']) ? trim($row[$columnMap['contact_name']] ?? '') : '';

        $invoiceNumber = isset($columnMap['invoice_number']) ? trim($row[$columnMap['invoice_number']] ?? '') : '';
        $invoiceDate = isset($columnMap['invoice_date']) ? trim($row[$columnMap['invoice_date']] ?? '') : '';
        $dueDate = isset($columnMap['due_date']) ? trim($row[$columnMap['due_date']] ?? '') : '';
        $amount = isset($columnMap['amount']) ? ($row[$columnMap['amount']] ?? 0) : 0;
        $status = isset($columnMap['status']) ? trim($row[$columnMap['status']] ?? 'unpaid') : 'unpaid';
        $paymentDate = isset($columnMap['payment_date']) ? trim($row[$columnMap['payment_date']] ?? '') : '';

        // Debug logging
        error_log("XeroImporter Line {$lineNumber}: XeroCompany={$xeroCompanyName}, InvoiceNum={$invoiceNumber}, Date={$invoiceDate}, Amount={$amount}, Status={$status}");

        // Extract rate data (if available)
        $mainCameraRate = isset($columnMap['main_camera_rate']) ? ($row[$columnMap['main_camera_rate']] ?? null) : null;
        $additionalCameraRate = isset($columnMap['additional_camera_rate']) ? ($row[$columnMap['additional_camera_rate']] ?? null) : null;
        $paymentFrequency = isset($columnMap['payment_frequency']) ? ($row[$columnMap['payment_frequency']] ?? 'monthly') : 'monthly';

        // Validate required fields
        if (empty($invoiceNumber) || empty($invoiceDate)) {
            throw new \Exception("Missing required fields (invoice number or date)");
        }

        // Find legal entity - PRIORITY: xero_company_name, then legal_entity_id, then contact_name
        $legalEntity = null;

        if (!empty($xeroCompanyName)) {
            // PRIMARY: Find by Xero Company Name
            $legalEntity = $this->db->fetchOne(
                "SELECT * FROM legal_entities WHERE xero_company_name = :name",
                ['name' => $xeroCompanyName]
            );
            if (!$legalEntity) {
                throw new \Exception("Legal Entity not found with Xero Company Name: {$xeroCompanyName}");
            }
        } elseif (!empty($legalEntityId)) {
            // SECONDARY: Find by Legal Entity ID
            $legalEntity = $this->findLegalEntityById($legalEntityId, $legalEntityName);
        } elseif (!empty($contactName)) {
            // BACKWARD COMPATIBILITY: use contact name
            $legalEntity = $this->findOrCreateLegalEntity($contactName);
        } else {
            throw new \Exception("Missing Xero Company Name, Legal Entity ID, or Contact Name");
        }

        // Create or update contract if rates are provided
        if ($mainCameraRate !== null || $additionalCameraRate !== null) {
            $this->createOrUpdateContract($legalEntity['id'], $invoiceDate, $mainCameraRate, $additionalCameraRate, $paymentFrequency);
        }

        // Check if invoice already exists
        $existing = $this->invoice->fetchOne(
            "SELECT id FROM invoices WHERE invoice_number = :number",
            ['number' => $invoiceNumber]
        );

        if ($existing) {
            $this->skipped++;
            $this->errors[] = "Line {$lineNumber}: Duplicate invoice number '{$invoiceNumber}' - already exists in system";
            return; // Skip duplicates
        }

        // Parse dates
        $invoiceDate = $this->parseDate($invoiceDate);
        $dueDate = $this->parseDate($dueDate);
        $paymentDate = $paymentDate ? $this->parseDate($paymentDate) : null;

        // Parse amount (remove currency symbols, commas)
        $amount = $this->parseAmount($amount);

        // Determine payment status
        $paymentStatus = strtolower($status) === 'paid' ? 'paid' : 'unpaid';

        // Insert invoice - UPDATED to use legal_entity_id
        $invoiceId = $this->invoice->create([
            'legal_entity_id' => $legalEntity['id'],
            'invoice_number' => $invoiceNumber,
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate,
            'invoice_amount' => $amount,
            'payment_status' => $paymentStatus,
            'payment_date' => $paymentDate,
            'payment_frequency' => 'annual', // Default, can be updated later
            'is_vat_exclusive' => true,
        ]);

        // DISABLED: Auto-allocation during import
        // Camera allocation should be done manually after import using the allocate page
        // This prevents duplicate allocations and gives you control over which cameras
        // are allocated to which invoices
        //
        // To allocate cameras:
        // 1. Import invoices from Xero (this step)
        // 2. Go to the invoice detail page
        // 3. Click "Allocate Cameras" button
        // 4. Select which cameras to allocate
        //
        // Note: Reconciliation will happen automatically after manual allocation

        $this->imported++;
    }
    
    private function findLegalEntityById($legalEntityId, $legalEntityName = '') {
        // Find by legal_entity_id
        $entity = $this->legalEntity->fetchOne(
            "SELECT * FROM legal_entities WHERE legal_entity_id = :id",
            ['id' => $legalEntityId]
        );

        if ($entity) {
            return $entity;
        }

        // Not found - create new legal entity
        $id = $this->legalEntity->create([
            'legal_entity_id' => $legalEntityId,
            'legal_entity_name' => $legalEntityName ?: $legalEntityId,
        ]);

        return $this->legalEntity->find($id);
    }

    // Backward compatibility method
    private function findOrCreateLegalEntity($name) {
        $entities = $this->legalEntity->where('legal_entity_name', $name);

        if (!empty($entities)) {
            return $entities[0];
        }

        // Create new legal entity
        $id = $this->legalEntity->create([
            'legal_entity_name' => $name,
        ]);

        return $this->legalEntity->find($id);
    }
    
    private function parseDate($dateString) {
        // Try various date formats
        $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'm-d-Y'];
        
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $dateString);
            if ($date) {
                return $date->format('Y-m-d');
            }
        }
        
        return date('Y-m-d'); // Fallback to today
    }
    
    private function parseAmount($amountString) {
        // Remove currency symbols, commas, spaces
        $amount = preg_replace('/[£$,\s]/', '', $amountString);
        return (float) $amount;
    }

    private function createOrUpdateContract($legalEntityId, $invoiceDate, $mainRate, $additionalRate, $frequency) {
        // Check if contract already exists for this legal entity
        $existing = $this->db->fetchOne(
            "SELECT id FROM legal_entity_contracts WHERE legal_entity_id = :id ORDER BY effective_date DESC LIMIT 1",
            ['id' => $legalEntityId]
        );

        // Parse rates
        $mainRate = $this->parseAmount($mainRate ?? 0);
        $additionalRate = $this->parseAmount($additionalRate ?? 0);

        // Normalize payment frequency
        $frequency = strtolower(trim($frequency));
        if (!in_array($frequency, ['annual', 'quarterly', 'monthly'])) {
            $frequency = 'monthly'; // Default
        }

        $effectiveDate = $this->parseDate($invoiceDate);

        if ($existing) {
            // Update existing contract
            $this->db->update('legal_entity_contracts', [
                'main_camera_rate' => $mainRate,
                'additional_camera_rate' => $additionalRate,
                'payment_frequency' => $frequency,
                'effective_date' => $effectiveDate,
            ], ['id' => $existing['id']]);
        } else {
            // Create new contract
            $this->db->insert('legal_entity_contracts', [
                'legal_entity_id' => $legalEntityId,
                'main_camera_rate' => $mainRate,
                'additional_camera_rate' => $additionalRate,
                'payment_frequency' => $frequency,
                'effective_date' => $effectiveDate,
            ]);
        }
    }

    private function logImport($filePath) {
        // Log to xero_imports table
        $this->db->insert('xero_imports', [
            'filename' => basename($filePath),
            'import_date' => date('Y-m-d H:i:s'),
            'rows_imported' => $this->imported,
            'rows_failed' => $this->skipped,
            'status' => count($this->errors) > 0 ? 'partial' : 'success',
            'error_log' => json_encode($this->errors),
        ]);
    }
    
    public function getErrors() {
        return $this->errors;
    }
    
    public function getImported() {
        return $this->imported;
    }
    
    public function getSkipped() {
        return $this->skipped;
    }
}

