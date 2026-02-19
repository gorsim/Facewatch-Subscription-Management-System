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
    private $updated = 0;
    private $skipped = 0;
    private $movementsDetected = 0;
    private $invoicesAutoUpdated = 0;
    private $invoicesFlaggedForXero = 0;
    private $importSessionId = null;

    // Track cameras being removed and installed in this import
    private $removals = []; // safr_code => removal data
    private $installations = []; // safr_code => installation data

    public function __construct() {
        $this->cameraInstallation = new CameraInstallation();
        $this->store = new Store();
        $this->invoice = new Invoice();
        $this->db = Database::getInstance();
    }

    public function import($filePath) {
        $this->errors = [];
        $this->imported = 0;
        $this->updated = 0;
        $this->skipped = 0;
        $this->movementsDetected = 0;
        $this->invoicesAutoUpdated = 0;
        $this->invoicesFlaggedForXero = 0;
        $this->removals = [];
        $this->installations = [];

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
            // PHASE 1: Import all rows and collect removal/installation data
            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;

                try {
                    $this->importRow($row, $columnMap, $lineNumber);
                } catch (\Exception $e) {
                    $this->errors[] = "Line {$lineNumber}: " . $e->getMessage();
                }
            }

            // PHASE 2: Detect camera movements
            $this->detectCameraMovements();

            // PHASE 3: Log import with movement stats
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
            // Camera Name
            elseif ($header === 'camera_name' || $header === 'camera name' || $header === 'name') {
                $map['camera_name'] = $index;
            }
            // SAFR Code
            elseif ($header === 'safr_code' || $header === 'safr code' || $header === 'safr') {
                $map['safr_code'] = $index;
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
        $removalDateRaw = isset($columnMap['removal_date']) ? trim($row[$columnMap['removal_date']] ?? '') : '';
        $cameraType = isset($columnMap['camera_type']) ? trim($row[$columnMap['camera_type']] ?? 'main') : 'main';

        // Debug logging
        error_log("CameraInstallationImporter Line {$lineNumber}: StoreID={$storeId}, StoreName={$storeName}, InstallDate={$installationDate}, RemovalDate={$removalDateRaw}, Type={$cameraType}");

        // Validate required fields - need store AND at least one date (installation OR removal)
        if (empty($storeId) && empty($storeName)) {
            throw new \Exception("Missing required field: Store ID or Store Name");
        }
        if (empty($installationDate) && empty($removalDateRaw)) {
            throw new \Exception("Missing required field: Installation Date or Removal Date");
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
        $installationDate = !empty($installationDate) ? $this->parseDate($installationDate) : null;
        $removalDate = !empty($removalDateRaw) ? $this->parseDate($removalDateRaw) : null;

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

        // Extract optional fields
        $cameraName = isset($columnMap['camera_name']) ? trim($row[$columnMap['camera_name']] ?? '') : null;
        $safrCode = isset($columnMap['safr_code']) ? trim($row[$columnMap['safr_code']] ?? '') : null;
        $notes = isset($columnMap['notes']) ? trim($row[$columnMap['notes']] ?? '') : null;

        // Check if camera with this SAFR code already exists
        $existingCamera = null;
        if (!empty($safrCode)) {
            $existingCamera = $this->db->fetchOne(
                "SELECT * FROM camera_installations WHERE safr_code = :safr_code ORDER BY id DESC LIMIT 1",
                ['safr_code' => $safrCode]
            );
        }

        // Handle removal-only rows (no installation date)
        if (empty($installationDate) && !empty($removalDate)) {
            // This is a removal - must have existing camera
            if (!$existingCamera) {
                throw new \Exception("Cannot remove camera {$safrCode} - not found in database");
            }

            // Update only the removal date and store
            $this->db->update('camera_installations', [
                'removal_date' => $removalDate,
                'store_id' => $store['id'],
                'notes' => !empty($notes) ? $notes : $existingCamera['notes']
            ], 'id = :id', ['id' => $existingCamera['id']]);

            $installationId = $existingCamera['id'];
            $this->updated++;
            error_log("CameraInstallationImporter: Updated removal date for camera {$safrCode} (ID: {$installationId})");

        } else {
            // This is an installation (with or without removal date)
            $cameraData = [
                'store_id' => $store['id'],
                'installation_date' => $installationDate,
                'removal_date' => $removalDate,
                'camera_type' => $cameraType,
                'camera_name' => !empty($cameraName) ? $cameraName : null,
                'safr_code' => !empty($safrCode) ? $safrCode : null,
                'invoice_id' => $invoiceId,
                'notes' => !empty($notes) ? $notes : null,
            ];

            if ($existingCamera) {
                // Update existing camera instead of creating duplicate
                $this->db->update('camera_installations', $cameraData, 'id = :id', ['id' => $existingCamera['id']]);
                $installationId = $existingCamera['id'];
                $this->updated++;
                error_log("CameraInstallationImporter: Updated existing camera {$safrCode} (ID: {$installationId})");
            } else {
                // Create new camera installation
                $installationId = $this->cameraInstallation->create($cameraData);
                $this->imported++;
                error_log("CameraInstallationImporter: Created new camera {$safrCode} (ID: {$installationId})");
            }
        }

        // Track removals and installations for movement detection
        if (!empty($safrCode)) {
            // If this row has a removal date (and no installation date), it's a removal
            if (!empty($removalDate) && empty($installationDate)) {
                $this->removals[$safrCode] = [
                    'installation_id' => $installationId,
                    'store_id' => $store['id'],
                    'store_name' => $store['store_name'],
                    'removal_date' => $removalDate,
                    'camera_name' => $cameraName,
                    'safr_code' => $safrCode
                ];
            }
            // If this row has an installation date (and no removal date), it's an installation
            elseif (!empty($installationDate) && empty($removalDate)) {
                $this->installations[$safrCode] = [
                    'installation_id' => $installationId,
                    'store_id' => $store['id'],
                    'store_name' => $store['store_name'],
                    'installation_date' => $installationDate,
                    'camera_name' => $cameraName,
                    'safr_code' => $safrCode
                ];
            }
        }
    }


    /**
     * Detect camera movements by matching removals and installations
     * Creates movement records and updates invoice allocations
     */
    private function detectCameraMovements() {
        // Find cameras that were both removed and installed (movements)
        foreach ($this->removals as $safrCode => $removal) {
            if (isset($this->installations[$safrCode])) {
                $installation = $this->installations[$safrCode];

                // This is a camera movement!
                $this->movementsDetected++;

                // Create movement record
                $movementId = $this->db->insert('camera_movements', [
                    'camera_installation_id' => $installation['installation_id'],
                    'safr_code' => $safrCode,
                    'camera_name' => $installation['camera_name'] ?? $removal['camera_name'],
                    'from_store_id' => $removal['store_id'],
                    'from_store_name' => $removal['store_name'],
                    'to_store_id' => $installation['store_id'],
                    'to_store_name' => $installation['store_name'],
                    'removal_date' => $removal['removal_date'],
                    'installation_date' => $installation['installation_date'],
                    'import_session_id' => $this->importSessionId,
                    'detected_by' => $_SESSION['user']['username'] ?? 'system'
                ]);

                // Find affected invoices via invoice_camera_allocations
                $affectedInvoices = $this->db->fetchAll("
                    SELECT DISTINCT
                        i.id,
                        i.invoice_number,
                        i.status,
                        i.invoice_date,
                        ica.store_id as allocated_store_id,
                        s.store_name as allocated_store_name
                    FROM invoices i
                    JOIN invoice_camera_allocations ica ON i.id = ica.invoice_id
                    JOIN stores s ON ica.store_id = s.id
                    WHERE ica.store_id = :from_store_id
                    AND i.invoice_date <= :removal_date
                    ORDER BY i.status, i.invoice_date
                ", [
                    'from_store_id' => $removal['store_id'],
                    'removal_date' => $removal['removal_date']
                ]);

                $affectedCount = 0;
                $autoUpdated = 0;
                $flaggedForXero = 0;

                foreach ($affectedInvoices as $invoice) {
                    $affectedCount++;

                    // Determine action based on invoice status
                    if ($invoice['status'] === 'draft') {
                        // Auto-update draft invoices
                        $this->db->update('invoice_camera_allocations', [
                            'store_id' => $installation['store_id']
                        ], 'invoice_id = :invoice_id AND store_id = :old_store_id', [
                            'invoice_id' => $invoice['id'],
                            'old_store_id' => $removal['store_id']
                        ]);

                        $actionTaken = 'auto_updated';
                        $requiresXero = false;
                        $autoUpdated++;
                        $this->invoicesAutoUpdated++;

                    } else {
                        // Flag issued/reconciled invoices for manual Xero correction
                        $actionTaken = 'flagged_for_xero';
                        $requiresXero = true;
                        $flaggedForXero++;
                        $this->invoicesFlaggedForXero++;
                    }

                    // Record the impact
                    $this->db->insert('camera_movement_invoice_impacts', [
                        'camera_movement_id' => $movementId,
                        'invoice_id' => $invoice['id'],
                        'invoice_number' => $invoice['invoice_number'],
                        'invoice_status' => $invoice['status'],
                        'invoice_date' => $invoice['invoice_date'],
                        'old_store_id' => $removal['store_id'],
                        'old_store_name' => $removal['store_name'],
                        'new_store_id' => $installation['store_id'],
                        'new_store_name' => $installation['store_name'],
                        'action_taken' => $actionTaken,
                        'requires_xero_correction' => $requiresXero,
                        'action_notes' => $requiresXero
                            ? "Invoice {$invoice['invoice_number']} is {$invoice['status']} - requires manual Xero correction"
                            : "Draft invoice automatically updated"
                    ]);
                }

                // Update movement record with counts
                $this->db->update('camera_movements', [
                    'affected_invoices_count' => $affectedCount,
                    'draft_invoices_updated' => $autoUpdated,
                    'issued_invoices_flagged' => $flaggedForXero,
                    'xero_correction_status' => $flaggedForXero > 0 ? 'pending' : 'not_required'
                ], 'id = :id', ['id' => $movementId]);
            }
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
        $this->importSessionId = $this->db->insert('camera_installation_imports', [
            'filename' => basename($filePath),
            'import_date' => date('Y-m-d H:i:s'),
            'import_type' => 'incremental',
            'rows_imported' => $this->imported,
            'rows_failed' => count($this->errors),
            'movements_detected' => $this->movementsDetected,
            'invoices_auto_updated' => $this->invoicesAutoUpdated,
            'invoices_flagged_for_xero' => $this->invoicesFlaggedForXero,
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

    public function getMovementsDetected() {
        return $this->movementsDetected;
    }

    public function getInvoicesAutoUpdated() {
        return $this->invoicesAutoUpdated;
    }

    public function getInvoicesFlaggedForXero() {
        return $this->invoicesFlaggedForXero;
    }
}

