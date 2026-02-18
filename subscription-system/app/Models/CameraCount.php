<?php
/**
 * Camera Count Model
 */

namespace App\Models;

class CameraCount extends Model {
    protected $table = 'camera_counts';
    
    /**
     * Get camera counts for a subscriber
     */
    public function getBySubscriber($subscriberId) {
        $sql = "
            SELECT * FROM {$this->table}
            WHERE subscriber_id = :subscriber_id
            ORDER BY month_date DESC
        ";
        return $this->fetchAll($sql, ['subscriber_id' => $subscriberId]);
    }
    
    /**
     * Get camera count for a specific month
     */
    public function getForMonth($subscriberId, $monthDate) {
        $sql = "
            SELECT * FROM {$this->table}
            WHERE subscriber_id = :subscriber_id
            AND month_date = :month_date
            LIMIT 1
        ";
        return $this->fetchOne($sql, [
            'subscriber_id' => $subscriberId,
            'month_date' => $monthDate
        ]);
    }
    
    /**
     * Get latest camera count for a subscriber
     */
    public function getLatest($subscriberId) {
        $sql = "
            SELECT * FROM {$this->table}
            WHERE subscriber_id = :subscriber_id
            ORDER BY month_date DESC
            LIMIT 1
        ";
        return $this->fetchOne($sql, ['subscriber_id' => $subscriberId]);
    }
    
    /**
     * Get camera count history (monthly trend)
     */
    public function getHistory($subscriberId, $months = 12) {
        $sql = "
            SELECT * FROM {$this->table}
            WHERE subscriber_id = :subscriber_id
            ORDER BY month_date DESC
            LIMIT :months
        ";
        return $this->fetchAll($sql, [
            'subscriber_id' => $subscriberId,
            'months' => $months
        ]);
    }
}

