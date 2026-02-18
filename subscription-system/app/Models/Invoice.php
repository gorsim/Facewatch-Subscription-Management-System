<?php
/**
 * Invoice Model
 * Updated to use legal_entity_id instead of subscriber_id
 */

namespace App\Models;

class Invoice extends Model {
    protected $table = 'invoices';

    public function getByLegalEntity($legalEntityId) {
        $sql = "
            SELECT * FROM {$this->table}
            WHERE legal_entity_id = :legal_entity_id
            ORDER BY invoice_date DESC
        ";
        return $this->fetchAll($sql, ['legal_entity_id' => $legalEntityId]);
    }

    // Backward compatibility alias
    public function getBySubscriber($subscriberId) {
        return $this->getByLegalEntity($subscriberId);
    }



    public function getTotalRevenue($startDate = null, $endDate = null) {
        $sql = "SELECT SUM(invoice_amount) as total FROM {$this->table} WHERE 1=1";
        $params = [];

        if ($startDate) {
            $sql .= " AND invoice_date >= :start_date";
            $params['start_date'] = $startDate;
        }

        if ($endDate) {
            $sql .= " AND invoice_date <= :end_date";
            $params['end_date'] = $endDate;
        }

        $result = $this->fetchOne($sql, $params);
        return $result['total'] ?? 0;
    }

    public function getAllWithLegalEntities() {
        $sql = "
            SELECT
                i.*,
                le.legal_entity_name,
                le.legal_entity_id
            FROM {$this->table} i
            JOIN legal_entities le ON i.legal_entity_id = le.id
            ORDER BY i.invoice_date DESC
        ";
        return $this->fetchAll($sql);
    }
}

