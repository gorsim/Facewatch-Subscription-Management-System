<?php
/**
 * Pricing Tier Model
 */

namespace App\Models;

class PricingTier extends Model {
    protected $table = 'pricing_tiers';
    
    /**
     * Get pricing tier for a specific camera count and date
     */
    public function getPricingForCameras($cameraCount, $effectiveDate = null) {
        if ($effectiveDate === null) {
            $effectiveDate = date('Y-m-d');
        }
        
        $sql = "
            SELECT * FROM {$this->table}
            WHERE min_cameras <= :camera_count
            AND (max_cameras >= :camera_count OR max_cameras IS NULL)
            AND effective_date <= :effective_date
            ORDER BY effective_date DESC
            LIMIT 1
        ";
        
        return $this->fetchOne($sql, [
            'camera_count' => $cameraCount,
            'effective_date' => $effectiveDate
        ]);
    }
    
    /**
     * Get all pricing tiers for a specific date
     */
    public function getPricingForDate($effectiveDate = null) {
        if ($effectiveDate === null) {
            $effectiveDate = date('Y-m-d');
        }
        
        $sql = "
            SELECT * FROM {$this->table}
            WHERE effective_date <= :effective_date
            AND id IN (
                SELECT MAX(id) FROM pricing_tiers
                WHERE effective_date <= :effective_date
                GROUP BY min_cameras, max_cameras
            )
            ORDER BY min_cameras
        ";
        
        return $this->fetchAll($sql, ['effective_date' => $effectiveDate]);
    }
    
    /**
     * Get all unique effective dates
     */
    public function getEffectiveDates() {
        $sql = "SELECT DISTINCT effective_date FROM {$this->table} ORDER BY effective_date DESC";
        return $this->fetchAll($sql);
    }
}

