<?php
/**
 * Store CSV Importer Service
 * Imports store data from CSV files
 * 
 * Expected CSV Format:
 * Legal Entity ID, Legal Entity Name, Store ID, Store Name, Installation Date, Category
 */

namespace App\Services;

use App\Models\Store;
use App\Models\LegalEntity;
use App\Database;

class StoreImporter {
    private $store;
    private $legalEntity;
    private $db;
    private $errors = [];
    private $imported = 0;
    private $updated = 0;
    private $skipped = 0;
    
    public function __construct() {
        $this->store = new Store();
        $this->legalEntity = new LegalEntity();
        $this->db = Database::getInstance();
    }
    
    public function import($filePath) {
        $this->errors = [];
        $this->imported = 0;
        $this->updated = 0;
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

            // Legal Entity ID (exact match first, then partial)
            if ($header === 'legal_entity_id' || $header === 'legal entity id' || $header === 'entity id') {
                $map['legal_entity_id'] = $index;
            }
            // Legal Entity Name (exact match first, then partial)
            elseif ($header === 'legal_entity_name' || $header === 'legal entity name') {
                $map['legal_entity_name'] = $index;
            }
            // Store ID (exact match first, then partial)
            elseif ($header === 'store_id' || $header === 'store id') {
                $map['store_id'] = $index;
            }
            // Store Name (exact match first, then partial)
            elseif ($header === 'store_name' || $header === 'store name') {
                $map['store_name'] = $index;
            }
            // Installation Date (exact match first, then partial)
            elseif ($header === 'installation_date' || $header === 'installation date' || $header === 'install date') {
                $map['installation_date'] = $index;
            }
            // Termination Date (exact match first, then partial)
            elseif ($header === 'termination_date' || $header === 'termination date' || $header === 'term date') {
                $map['termination_date'] = $index;
            }
            // Category
            elseif ($header === 'category') {
                $map['category'] = $index;
            }
            // Address Line 1
            elseif ($header === 'address_line1' || $header === 'address line 1' || (strpos($header, 'address') !== false && strpos($header, '1') !== false)) {
                $map['address_line1'] = $index;
            }
            // Address Line 2
            elseif ($header === 'address_line2' || $header === 'address line 2' || (strpos($header, 'address') !== false && strpos($header, '2') !== false)) {
                $map['address_line2'] = $index;
            }
            // City
            elseif ($header === 'city' || $header === 'town') {
                $map['city'] = $index;
            }
            // Postcode
            elseif ($header === 'postcode' || $header === 'postal code' || $header === 'postal') {
                $map['postcode'] = $index;
            }
        }

        // Debug logging
        error_log("StoreImporter: Column map = " . print_r($map, true));
        error_log("StoreImporter: Headers = " . print_r($headers, true));

        return $map;
    }
    
    private function importRow($row, $columnMap, $lineNumber) {
        // Extract data
        $legalEntityId = isset($columnMap['legal_entity_id']) ? trim($row[$columnMap['legal_entity_id']] ?? '') : '';
        $storeId = isset($columnMap['store_id']) ? trim($row[$columnMap['store_id']] ?? '') : '';
        $storeName = isset($columnMap['store_name']) ? trim($row[$columnMap['store_name']] ?? '') : '';

        // Debug logging
        error_log("StoreImporter Line {$lineNumber}: LegalEntityID={$legalEntityId}, StoreID={$storeId}, StoreName={$storeName}");

        // Validate required fields
        if (empty($legalEntityId) || empty($storeName)) {
            throw new \Exception("Missing required fields (Legal Entity ID or Store Name)");
        }

        // Find legal entity
        $legalEntity = $this->legalEntity->fetchOne(
            "SELECT id FROM legal_entities WHERE legal_entity_id = :id",
            ['id' => $legalEntityId]
        );

        if (!$legalEntity) {
            throw new \Exception("Legal Entity not found: {$legalEntityId}");
        }

        // Auto-generate store_id if not provided
        if (empty($storeId)) {
            // Generate store_id from legal_entity_id and a counter
            $count = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM stores WHERE legal_entity_id = :id",
                ['id' => $legalEntity['id']]
            );
            $storeId = $legalEntityId . '-S' . str_pad(($count['count'] + 1), 3, '0', STR_PAD_LEFT);
        }

        // Check if store already exists (by store_id or store_name)
        $existing = $this->store->findByStoreId($storeId);
        if (!$existing) {
            // Also check by store name
            $existing = $this->db->fetchOne(
                "SELECT id FROM stores WHERE store_name = :name AND legal_entity_id = :legal_entity_id",
                ['name' => $storeName, 'legal_entity_id' => $legalEntity['id']]
            );
        }
        
        $data = [
            'legal_entity_id' => $legalEntity['id'],
            'store_id' => $storeId,
            'store_name' => $storeName,
            'installation_date' => isset($columnMap['installation_date']) ? $this->parseDate($row[$columnMap['installation_date']] ?? null) : null,
            'termination_date' => isset($columnMap['termination_date']) ? $this->parseDate($row[$columnMap['termination_date']] ?? null) : null,
            'category' => isset($columnMap['category']) ? ($row[$columnMap['category']] ?? null) : null,
        ];
        
        if ($existing) {
            // Update existing store
            $this->store->update($existing['id'], $data);
            $this->updated++;
        } else {
            // Create new store
            $this->store->create($data);
            $this->imported++;
        }
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
        $this->db->insert('store_imports', [
            'filename' => basename($filePath),
            'import_date' => date('Y-m-d H:i:s'),
            'rows_imported' => $this->imported,
            'rows_updated' => $this->updated,
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
    
    public function getUpdated() {
        return $this->updated;
    }
    
    public function getSkipped() {
        return $this->skipped;
    }
}

