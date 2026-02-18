<?php
/**
 * Camera Installation CSV Importer Service
 * Imports incremental camera installation data
 * 
 * Expected CSV Format:
 * Store ID, Store Name, Installation Date, Camera Type, Invoice Number, Removal Date
 */

namespace App\Services;

use App\Models\CameraInstallation;
use App\Models\Store;
use App\Models\Invoice;
use App\Database;

class CameraInstallationImporter {
    private $cameraInstallation;
    private $store;
    private $invoice;
    private $db;
    private $errors = [];
    private $imported = 0;
    private $skipped = 0;
    
    public function __construct() {
        $this->cameraInstallation = new CameraInstallation();
        $this->store = new Store();
        $this->invoice = new Invoice();
        $this->db = Database::getInstance();
    }
    
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

            // Store ID (exact match first)
            if ($header === 'store_id' || $header === 'store id') {
                $map['store_id'] = $index;
            }
            // Store Name (exact match first)
            elseif ($header === 'store_name' || $header === 'store name') {
                $map['store_name'] = $index;
            }
            // Installation Date (exact match first)
            elseif ($header === 'installation_date' || $header === 'installation date' || $header === 'install date') {
                $map['installation_date'] = $index;
            }
            // Removal Date (exact match first)
            elseif ($header === 'removal_date' || $header === 'removal date' || $header === 'remove date') {
                $map['removal_date'] = $index;
            }
            // Camera Type (exact match first)
            elseif ($header === 'camera_type' || $header === 'camera type' || $header === 'type') {
                $map['camera_type'] = $index;
            }
            // Invoice Number
            elseif ($header === 'invoice_number' || $header === 'invoice number' || $header === 'invoice') {
                $map['invoice_number'] = $index;
            }
            // Notes
            elseif ($header === 'notes') {
                $map['notes'] = $index;
            }
        }

        // Debug logging
        error_log("CameraInstallationImporter: Column map = " . print_r($map, true));
        error_log("CameraInstallationImporter: Headers = " . print_r($headers, true));

        return $map;
    }
    
    private function importRow($row, $columnMap, $lineNumber) {
        // Extract data
        $storeId = isset($columnMap['store_id']) ? trim($row[$columnMap['store_id']] ?? '') : '';
        $storeName = isset($columnMap['store_name']) ? trim($row[$columnMap['store_name']] ?? '') : '';
        $installationDate = isset($columnMap['installation_date']) ? trim($row[$columnMap['installation_date']] ?? '') : '';
        $cameraType = isset($columnMap['camera_type']) ? trim($row[$columnMap['camera_type']] ?? 'main') : 'main';

        // Debug logging
        error_log("CameraInstallationImporter Line {$lineNumber}: StoreID={$storeId}, StoreName={$storeName}, Date={$installationDate}, Type={$cameraType}");

        // Validate required fields
        if ((empty($storeId) && empty($storeName)) || empty($installationDate)) {
            throw new \Exception("Missing required fields (Store ID/Name or Installation Date)");
        }

        // Find store by ID or Name
        $store = null;
        if (!empty($storeId)) {
            $store = $this->store->findByStoreId($storeId);
        }
        if (!$store && !empty($storeName)) {
            $store = $this->db->fetchOne(
                "SELECT * FROM stores WHERE store_name = :name",
                ['name' => $storeName]
            );
        }

        if (!$store) {
            throw new \Exception("Store not found: " . ($storeId ?: $storeName));
        }
        
        // Normalize camera type
        $cameraType = strtolower(trim($cameraType));
        if (!in_array($cameraType, ['main', 'additional'])) {
            $cameraType = 'main'; // Default
        }
        
        // Parse dates
        $installationDate = $this->parseDate($installationDate);
        $removalDate = isset($columnMap['removal_date']) ? $this->parseDate($row[$columnMap['removal_date']] ?? null) : null;
        
        // Find invoice if invoice number provided
        $invoiceId = null;
        if (isset($columnMap['invoice_number'])) {
            $invoiceNumber = $row[$columnMap['invoice_number']] ?? '';
            if (!empty($invoiceNumber)) {
                $invoice = $this->invoice->fetchOne(
                    "SELECT id FROM invoices WHERE invoice_number = :number",
                    ['number' => $invoiceNumber]
                );
                if ($invoice) {
                    $invoiceId = $invoice['id'];
                }
            }
        }
        
        // Create camera installation
        $this->cameraInstallation->create([
            'store_id' => $store['id'],
            'installation_date' => $installationDate,
            'removal_date' => $removalDate,
            'camera_type' => $cameraType,
            'invoice_id' => $invoiceId,
            'notes' => isset($columnMap['notes']) ? ($row[$columnMap['notes']] ?? null) : null,
        ]);
        
        $this->imported++;
    }
    
    private function parseDate($dateString) {
        if (empty($dateString)) {
            return null;
        }
        
        $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'm-d-Y'];
        
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $dateString);
            if ($date) {
                return $date->format('Y-m-d');
            }
        }
        
        return null;
    }
    
    private function logImport($filePath) {
        $this->db->insert('camera_installation_imports', [
            'filename' => basename($filePath),
            'import_date' => date('Y-m-d H:i:s'),
            'import_type' => 'incremental',
            'rows_imported' => $this->imported,
            'rows_failed' => count($this->errors),
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

