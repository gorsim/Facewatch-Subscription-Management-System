<?php
/**
 * Store Model
 * Represents a physical store location belonging to a legal entity
 */

namespace App\Models;

class Store extends Model {
    protected $table = 'stores';

    public function getByLegalEntity($legalEntityId) {
        $sql = "
            SELECT
                s.*,
                (SELECT COUNT(*)
                 FROM camera_installations ci
                 WHERE ci.store_id = s.id
                 AND ci.removal_date IS NULL) as active_cameras
            FROM stores s
            WHERE s.legal_entity_id = :legal_entity_id
            ORDER BY s.store_name
        ";
        return $this->fetchAll($sql, ['legal_entity_id' => $legalEntityId]);
    }

    public function getWithLegalEntity($id) {
        $sql = "
            SELECT s.*, le.legal_entity_name, le.legal_entity_id
            FROM stores s
            JOIN legal_entities le ON s.legal_entity_id = le.id
            WHERE s.id = :id
        ";
        return $this->fetchOne($sql, ['id' => $id]);
    }
    
    public function getCameraInstallations($storeId) {
        $sql = "
            SELECT 
                ci.*,
                i.invoice_number,
                i.invoice_date
            FROM camera_installations ci
            LEFT JOIN invoices i ON ci.invoice_id = i.id
            WHERE ci.store_id = :store_id
            ORDER BY ci.installation_date DESC, ci.id DESC
        ";
        return $this->fetchAll($sql, ['store_id' => $storeId]);
    }
    
    public function getActiveCameraCount($storeId) {
        $sql = "
            SELECT 
                COUNT(CASE WHEN camera_type = 'main' THEN 1 END) as main_cameras,
                COUNT(CASE WHEN camera_type = 'additional' THEN 1 END) as additional_cameras,
                COUNT(*) as total_cameras
            FROM camera_installations
            WHERE store_id = :store_id
            AND removal_date IS NULL
        ";
        return $this->fetchOne($sql, ['store_id' => $storeId]);
    }
    
    public function getCumulativeCounts($storeId, $monthDate = null) {
        if ($monthDate === null) {
            $monthDate = date('Y-m-01'); // First day of current month
        }
        
        $sql = "
            SELECT *
            FROM camera_counts_monthly
            WHERE store_id = :store_id
            AND month_date = :month_date
        ";
        return $this->fetchOne($sql, [
            'store_id' => $storeId,
            'month_date' => $monthDate
        ]);
    }
    
    public function findByStoreId($storeId) {
        $sql = "SELECT * FROM {$this->table} WHERE store_id = :store_id";
        return $this->fetchOne($sql, ['store_id' => $storeId]);
    }
}

