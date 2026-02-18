<?php
/**
 * Subscriber Standing Data Importer Service
 * Imports subscriber master data including rates and contract details
 */

namespace App\Services;

use App\Models\Subscriber;
use App\Database;

class SubscriberImporter {
    private $subscriber;
    private $db;
    private $errors = [];
    private $imported = 0;
    private $updated = 0;
    
    public function __construct() {
        $this->subscriber = new Subscriber();
        $this->db = Database::getInstance();
    }
    
    /**
     * Import legal entities from CSV file
     *
     * Expected CSV columns:
     * - Legal Entity Name (required) OR Subscriber Name (legacy)
     * - Legal Entity ID (optional)
     * - Xero Company Name (optional)
     * - Payment Frequency (Annual/Quarterly/Monthly) - defaults to Annual
     * - Pricing Type (default/custom) - defaults to default
     * - Installation Date (optional)
     * - Category (optional)
     * - Sales Credit (optional)
     *
     * Note: All entities are imported with default pricing tiers.
     * Use the admin UI to configure custom pricing if needed.
     */
    public function import($filePath) {
        $this->errors = [];
        $this->imported = 0;
        $this->updated = 0;
        
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

        // Debug: Log the column mapping
        error_log("SubscriberImporter: Column map = " . print_r($columnMap, true));
        error_log("SubscriberImporter: Headers = " . print_r($headers, true));

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

            // Use exact matches first, then partial matches
            // This prevents "legal_entity_id" from matching "legal_entity_name"

            // Legal Entity ID (exact match)
            if ($header === 'legal_entity_id' || $header === 'legal entity id') {
                $map['legal_entity_id'] = $index;
            }
            // Legal Entity Name (exact match)
            elseif ($header === 'legal_entity_name' || $header === 'legal entity name') {
                $map['legal_entity_name'] = $index;
            }
            // Xero Company Name (exact match)
            elseif ($header === 'xero_company_name' || $header === 'xero company name' || $header === 'xero customer name') {
                $map['xero_company_name'] = $index;
            }
            // Payment Frequency (exact match)
            elseif ($header === 'payment_frequency' || $header === 'payment frequency' || $header === 'billing frequency') {
                $map['payment_frequency'] = $index;
            }
            // Pricing Type (exact match)
            elseif ($header === 'pricing_type' || $header === 'pricing type') {
                $map['pricing_type'] = $index;
            }
            // Installation Date (exact match)
            elseif ($header === 'installation_date' || $header === 'installation date') {
                $map['installation_date'] = $index;
            }
            // Category (exact match)
            elseif ($header === 'category') {
                $map['category'] = $index;
            }
            // Sales Credit (exact match)
            elseif ($header === 'sales_credit' || $header === 'sales credit') {
                $map['sales_credit'] = $index;
            }
            // Legacy: Subscriber Name (partial match for backwards compatibility)
            elseif (strpos($header, 'subscriber name') !== false || strpos($header, 'customer name') !== false) {
                $map['subscriber_name'] = $index;
            }
            // Legacy: FCST Lookup Code (partial match)
            elseif (strpos($header, 'fcst') !== false || strpos($header, 'lookup code') !== false) {
                $map['fcst_lookup_code'] = $index;
            }
        }

        return $map;
    }

    private function importRow($row, $columnMap, $lineNumber) {
        // Extract data
        $legalEntityId = isset($columnMap['legal_entity_id']) ? trim($row[$columnMap['legal_entity_id']] ?? '') : '';
        $legalEntityName = isset($columnMap['legal_entity_name']) ? trim($row[$columnMap['legal_entity_name']] ?? '') : '';
        $xeroCompanyName = isset($columnMap['xero_company_name']) ? trim($row[$columnMap['xero_company_name']] ?? '') : '';

        // Legacy support
        $subscriberName = isset($columnMap['subscriber_name']) ? trim($row[$columnMap['subscriber_name']] ?? '') : '';
        $fcstLookupCode = isset($columnMap['fcst_lookup_code']) ? trim($row[$columnMap['fcst_lookup_code']] ?? '') : '';

        // Payment frequency and pricing type
        $paymentFrequency = isset($columnMap['payment_frequency']) ? trim($row[$columnMap['payment_frequency']] ?? 'annual') : 'annual';
        $pricingType = isset($columnMap['pricing_type']) ? trim($row[$columnMap['pricing_type']] ?? 'default') : 'default';

        // Other fields
        $installationDate = isset($columnMap['installation_date']) ? ($row[$columnMap['installation_date']] ?? null) : null;
        $category = isset($columnMap['category']) ? trim($row[$columnMap['category']] ?? '') : '';
        $salesCredit = isset($columnMap['sales_credit']) ? trim($row[$columnMap['sales_credit']] ?? '') : '';

        // Determine which name to use (prefer legal_entity_name, fallback to subscriber_name)
        $entityName = $legalEntityName ?: $subscriberName;

        // Debug: Log extracted values
        error_log("SubscriberImporter Line {$lineNumber}: ID={$legalEntityId}, Name={$entityName}, Xero={$xeroCompanyName}, Freq={$paymentFrequency}, PricingType={$pricingType}");

        // Validate required fields
        if (empty($entityName)) {
            throw new \Exception("Missing legal entity name or subscriber name");
        }

        // Normalize payment frequency
        $paymentFrequency = strtolower(trim($paymentFrequency));
        if (!in_array($paymentFrequency, ['annual', 'quarterly', 'monthly'])) {
            $paymentFrequency = 'annual'; // Default to annual
        }

        // Normalize pricing type
        $pricingType = strtolower(trim($pricingType));
        if (!in_array($pricingType, ['default', 'custom'])) {
            $pricingType = 'default'; // Default to default pricing
        }

        // Parse installation date
        $installationDate = $installationDate ? $this->parseDate($installationDate) : null;

        // Check if legal entity already exists (by legal_entity_id or name)
        $existing = null;
        if ($legalEntityId) {
            $existing = $this->db->fetchOne(
                "SELECT id FROM legal_entities WHERE legal_entity_id = :id",
                ['id' => $legalEntityId]
            );
        }
        if (!$existing && $entityName) {
            $existing = $this->db->fetchOne(
                "SELECT id FROM legal_entities WHERE legal_entity_name = :name",
                ['name' => $entityName]
            );
        }

        if ($existing) {
            // Update existing legal entity
            $this->db->update('legal_entities', [
                'legal_entity_id' => $legalEntityId ?: null,
                'legal_entity_name' => $entityName,
                'xero_company_name' => $xeroCompanyName ?: null,
                'payment_frequency' => $paymentFrequency,
                'pricing_type' => $pricingType,
                'installation_date' => $installationDate,
                'category' => $category ?: null,
                'sales_credit' => $salesCredit ?: null,
            ], 'id = :id', ['id' => $existing['id']]);

            $this->updated++;
        } else {
            // Create new legal entity with default pricing
            $entityId = $this->db->insert('legal_entities', [
                'legal_entity_id' => $legalEntityId ?: null,
                'legal_entity_name' => $entityName,
                'xero_company_name' => $xeroCompanyName ?: null,
                'payment_frequency' => $paymentFrequency,
                'pricing_type' => $pricingType,
                'installation_date' => $installationDate,
                'category' => $category ?: null,
                'sales_credit' => $salesCredit ?: null,
            ]);

            $this->imported++;
        }
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

        return null; // Return null if can't parse
    }

    private function parseAmount($amountString) {
        // Remove currency symbols, commas, spaces, and decimal points
        $amount = preg_replace('/[£$,\s.]/', '', $amountString);
        return (int) $amount;
    }

    private function logImport($filePath) {
        // Log to legal_entity_imports table
        $this->db->insert('legal_entity_imports', [
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
}


