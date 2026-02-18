<?php
/**
 * LegalEntity Model (formerly Subscriber)
 * Represents a legal entity that can have multiple stores
 */

namespace App\Models;

class LegalEntity extends Model {
    protected $table = 'legal_entities';

    public function getWithContract($id) {
        $sql = "
            SELECT le.*, lec.*
            FROM legal_entities le
            LEFT JOIN legal_entity_contracts lec ON le.id = lec.legal_entity_id
            WHERE le.id = :id
            ORDER BY lec.effective_date DESC
            LIMIT 1
        ";
        return $this->fetchOne($sql, ['id' => $id]);
    }

    public function getAllWithContracts() {
        $sql = "
            SELECT
                le.*,
                lec.payment_frequency,
                lec.main_camera_rate,
                lec.additional_camera_rate,
                (SELECT COUNT(*) FROM stores WHERE legal_entity_id = le.id) as store_count,
                (SELECT COUNT(*)
                 FROM camera_installations ci
                 JOIN stores s ON ci.store_id = s.id
                 WHERE s.legal_entity_id = le.id
                 AND ci.removal_date IS NULL) as total_cameras
            FROM legal_entities le
            LEFT JOIN legal_entity_contracts lec ON le.id = lec.legal_entity_id
                AND lec.id = (
                    SELECT id FROM legal_entity_contracts
                    WHERE legal_entity_id = le.id
                    ORDER BY effective_date DESC LIMIT 1
                )
            ORDER BY le.legal_entity_name
        ";
        return $this->fetchAll($sql);
    }

    public function search($term) {
        $sql = "
            SELECT * FROM {$this->table}
            WHERE legal_entity_name LIKE :term
            OR legal_entity_id LIKE :term
            OR fcst_lookup_code LIKE :term
            ORDER BY legal_entity_name
        ";
        return $this->fetchAll($sql, ['term' => "%{$term}%"]);
    }

    public function getStores($legalEntityId) {
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
}

// Keep Subscriber as an alias for backward compatibility
class Subscriber extends LegalEntity {
    // This allows existing code to continue working
}

