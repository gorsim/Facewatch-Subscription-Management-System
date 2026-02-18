<?php
/**
 * CameraInstallation Model
 * Represents individual camera installations at stores
 */

namespace App\Models;

class CameraInstallation extends Model {
    protected $table = 'camera_installations';
    
    public function getByStore($storeId, $includeRemoved = false) {
        $sql = "
            SELECT ci.*, s.store_name, s.store_id
            FROM camera_installations ci
            JOIN stores s ON ci.store_id = s.id
            WHERE ci.store_id = :store_id
        ";
        
        if (!$includeRemoved) {
            $sql .= " AND ci.removal_date IS NULL";
        }
        
        $sql .= " ORDER BY ci.installation_date DESC, ci.id DESC";
        
        return $this->fetchAll($sql, ['store_id' => $storeId]);
    }
    
    public function getByLegalEntity($legalEntityId, $includeRemoved = false) {
        $sql = "
            SELECT 
                ci.*,
                s.store_name,
                s.store_id,
                le.legal_entity_name
            FROM camera_installations ci
            JOIN stores s ON ci.store_id = s.id
            JOIN legal_entities le ON s.legal_entity_id = le.id
            WHERE le.id = :legal_entity_id
        ";
        
        if (!$includeRemoved) {
            $sql .= " AND ci.removal_date IS NULL";
        }
        
        $sql .= " ORDER BY ci.installation_date DESC, ci.id DESC";
        
        return $this->fetchAll($sql, ['legal_entity_id' => $legalEntityId]);
    }
    
    public function getCountByStore($storeId, $asOfDate = null) {
        if ($asOfDate === null) {
            $asOfDate = date('Y-m-d');
        }

        $sql = "
            SELECT
                COUNT(CASE WHEN camera_type = 'main' THEN 1 END) as main_cameras,
                COUNT(CASE WHEN camera_type = 'additional' THEN 1 END) as additional_cameras,
                COUNT(*) as total_cameras
            FROM camera_installations
            WHERE store_id = :store_id
            AND installation_date <= :as_of_date
            AND (removal_date IS NULL OR removal_date > :as_of_date2)
        ";

        return $this->fetchOne($sql, [
            'store_id' => $storeId,
            'as_of_date' => $asOfDate,
            'as_of_date2' => $asOfDate
        ]);
    }
    
    public function getCountByLegalEntity($legalEntityId, $asOfDate = null) {
        if ($asOfDate === null) {
            $asOfDate = date('Y-m-d');
        }
        
        $sql = "
            SELECT 
                COUNT(CASE WHEN ci.camera_type = 'main' THEN 1 END) as main_cameras,
                COUNT(CASE WHEN ci.camera_type = 'additional' THEN 1 END) as additional_cameras,
                COUNT(*) as total_cameras
            FROM camera_installations ci
            JOIN stores s ON ci.store_id = s.id
            WHERE s.legal_entity_id = :legal_entity_id
            AND ci.installation_date <= :as_of_date
            AND (ci.removal_date IS NULL OR ci.removal_date > :as_of_date)
        ";
        
        return $this->fetchOne($sql, [
            'legal_entity_id' => $legalEntityId,
            'as_of_date' => $asOfDate
        ]);
    }
}

