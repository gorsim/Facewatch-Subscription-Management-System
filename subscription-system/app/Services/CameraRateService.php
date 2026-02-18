<?php
/**
 * Camera Rate Service
 * Manages versioned camera rates with inflation adjustments and volume discounts
 */

namespace App\Services;

use App\Database;

class CameraRateService {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get the applicable camera rates for a legal entity on a specific date
     * 
     * @param int $legalEntityId
     * @param string $asOfDate Date in Y-m-d format
     * @return array ['main_camera_rate' => float, 'additional_camera_rate' => float, 'discount_percentage' => float]
     */
    public function getRatesForDate($legalEntityId, $asOfDate) {
        $rate = $this->db->fetchOne(
            "SELECT main_camera_rate, additional_camera_rate, discount_percentage,
                    base_main_rate, base_additional_rate, total_cameras
             FROM legal_entity_rate_history
             WHERE legal_entity_id = :legal_entity_id
             AND effective_date <= :as_of_date
             AND (end_date IS NULL OR end_date > :as_of_date2)
             ORDER BY effective_date DESC
             LIMIT 1",
            [
                'legal_entity_id' => $legalEntityId,
                'as_of_date' => $asOfDate,
                'as_of_date2' => $asOfDate
            ]
        );
        
        if (!$rate) {
            // Fallback to current rates from legal_entities table
            $entity = $this->db->fetchOne(
                "SELECT main_camera_rate, additional_camera_rate 
                 FROM legal_entities 
                 WHERE id = :id",
                ['id' => $legalEntityId]
            );
            
            return [
                'main_camera_rate' => (float) ($entity['main_camera_rate'] ?? 0),
                'additional_camera_rate' => (float) ($entity['additional_camera_rate'] ?? 0),
                'discount_percentage' => 0.00,
                'total_cameras' => 0
            ];
        }
        
        return [
            'main_camera_rate' => (float) $rate['main_camera_rate'],
            'additional_camera_rate' => (float) $rate['additional_camera_rate'],
            'discount_percentage' => (float) $rate['discount_percentage'],
            'base_main_rate' => (float) $rate['base_main_rate'],
            'base_additional_rate' => (float) $rate['base_additional_rate'],
            'total_cameras' => (int) $rate['total_cameras']
        ];
    }
    
    /**
     * Get current rates for a legal entity (as of today)
     */
    public function getCurrentRates($legalEntityId) {
        return $this->getRatesForDate($legalEntityId, date('Y-m-d'));
    }
    
    /**
     * Calculate the appropriate discount tier based on camera count
     * 
     * @param int $cameraCount Total number of cameras
     * @return array Discount tier information
     */
    public function getDiscountTier($cameraCount) {
        $tier = $this->db->fetchOne(
            "SELECT * FROM volume_discount_tiers
             WHERE is_active = 1
             AND min_cameras <= :camera_count
             AND (max_cameras IS NULL OR max_cameras >= :camera_count2)
             AND effective_date <= CURDATE()
             ORDER BY effective_date DESC, min_cameras DESC
             LIMIT 1",
            [
                'camera_count' => $cameraCount,
                'camera_count2' => $cameraCount
            ]
        );
        
        if (!$tier) {
            return [
                'tier_name' => 'Standard',
                'discount_percentage' => 0.00,
                'min_cameras' => 0,
                'max_cameras' => null
            ];
        }
        
        return [
            'tier_name' => $tier['tier_name'],
            'discount_percentage' => (float) $tier['discount_percentage'],
            'min_cameras' => (int) $tier['min_cameras'],
            'max_cameras' => $tier['max_cameras'] ? (int) $tier['max_cameras'] : null
        ];
    }
    
    /**
     * Calculate discounted rates based on camera count
     * 
     * @param int $cameraCount
     * @param float $baseMainRate
     * @param float $baseAdditionalRate
     * @return array ['main_rate' => float, 'additional_rate' => float, 'discount_percentage' => float, 'tier_name' => string]
     */
    public function calculateDiscountedRates($cameraCount, $baseMainRate = null, $baseAdditionalRate = null) {
        // Get base rates if not provided
        if ($baseMainRate === null || $baseAdditionalRate === null) {
            $baseRates = $this->getBaseRates();
            $baseMainRate = $baseMainRate ?? $baseRates['main_camera_rate'];
            $baseAdditionalRate = $baseAdditionalRate ?? $baseRates['additional_camera_rate'];
        }
        
        // Get discount tier
        $tier = $this->getDiscountTier($cameraCount);
        $discountMultiplier = 1 - ($tier['discount_percentage'] / 100);
        
        return [
            'main_rate' => round($baseMainRate * $discountMultiplier, 2),
            'additional_rate' => round($baseAdditionalRate * $discountMultiplier, 2),
            'discount_percentage' => $tier['discount_percentage'],
            'tier_name' => $tier['tier_name'],
            'base_main_rate' => $baseMainRate,
            'base_additional_rate' => $baseAdditionalRate
        ];
    }
    
    /**
     * Get current base rates (standard pricing before discounts)
     */
    public function getBaseRates($asOfDate = null) {
        $asOfDate = $asOfDate ?? date('Y-m-d');

        $rates = $this->db->fetchOne(
            "SELECT main_camera_rate, additional_camera_rate
             FROM base_camera_rates
             WHERE effective_date <= :as_of_date
             ORDER BY effective_date DESC
             LIMIT 1",
            ['as_of_date' => $asOfDate]
        );

        return [
            'main_camera_rate' => (float) ($rates['main_camera_rate'] ?? 1642.00),
            'additional_camera_rate' => (float) ($rates['additional_camera_rate'] ?? 1095.00)
        ];
    }

    /**
     * Update rates for a legal entity based on current camera count
     * Creates a new rate history entry if rates have changed
     *
     * @param int $legalEntityId
     * @param string $changeReason Reason for rate change
     * @param string $effectiveDate Date the new rate becomes effective (default: today)
     * @return array Result of the update
     */
    public function updateRatesForEntity($legalEntityId, $changeReason = 'manual', $effectiveDate = null) {
        $effectiveDate = $effectiveDate ?? date('Y-m-d');

        // Get current camera count
        $cameraCount = $this->db->fetchOne(
            "SELECT COUNT(*) as total
             FROM camera_installations ci
             JOIN stores s ON ci.store_id = s.id
             WHERE s.legal_entity_id = :legal_entity_id
             AND ci.removal_date IS NULL",
            ['legal_entity_id' => $legalEntityId]
        );
        $totalCameras = (int) ($cameraCount['total'] ?? 0);

        // Get base rates
        $baseRates = $this->getBaseRates($effectiveDate);

        // Calculate discounted rates
        $newRates = $this->calculateDiscountedRates(
            $totalCameras,
            $baseRates['main_camera_rate'],
            $baseRates['additional_camera_rate']
        );

        // Get current rates
        $currentRates = $this->getCurrentRates($legalEntityId);

        // Check if rates have changed
        $ratesChanged = (
            abs($currentRates['main_camera_rate'] - $newRates['main_rate']) > 0.01 ||
            abs($currentRates['additional_camera_rate'] - $newRates['additional_rate']) > 0.01
        );

        if (!$ratesChanged) {
            return [
                'success' => true,
                'changed' => false,
                'message' => 'Rates unchanged',
                'current_rates' => $currentRates
            ];
        }

        // Close previous rate (set end_date)
        $this->db->query(
            "UPDATE legal_entity_rate_history
             SET end_date = :end_date
             WHERE legal_entity_id = :legal_entity_id
             AND end_date IS NULL",
            [
                'legal_entity_id' => $legalEntityId,
                'end_date' => $effectiveDate
            ]
        );

        // Insert new rate
        $this->db->insert('legal_entity_rate_history', [
            'legal_entity_id' => $legalEntityId,
            'effective_date' => $effectiveDate,
            'end_date' => null,
            'main_camera_rate' => $newRates['main_rate'],
            'additional_camera_rate' => $newRates['additional_rate'],
            'total_cameras' => $totalCameras,
            'discount_percentage' => $newRates['discount_percentage'],
            'base_main_rate' => $newRates['base_main_rate'],
            'base_additional_rate' => $newRates['base_additional_rate'],
            'change_reason' => $changeReason,
            'created_by' => 'system'
        ]);

        // Update legal_entities table for quick access
        $this->db->query(
            "UPDATE legal_entities
             SET main_camera_rate = :main_rate,
                 additional_camera_rate = :additional_rate
             WHERE id = :id",
            [
                'main_rate' => $newRates['main_rate'],
                'additional_rate' => $newRates['additional_rate'],
                'id' => $legalEntityId
            ]
        );

        return [
            'success' => true,
            'changed' => true,
            'message' => 'Rates updated successfully',
            'previous_rates' => $currentRates,
            'new_rates' => $newRates,
            'total_cameras' => $totalCameras,
            'tier_name' => $newRates['tier_name']
        ];
    }

    /**
     * Apply inflation adjustment to base rates
     * This should be run on January 1st each year
     *
     * @param float $inflationRate Percentage (e.g., 3.5 for 3.5%)
     * @param string $effectiveDate Date the inflation takes effect (default: Jan 1st of current year)
     * @return array Result of the inflation adjustment
     */
    public function applyInflationAdjustment($inflationRate, $effectiveDate = null) {
        $effectiveDate = $effectiveDate ?? date('Y') . '-01-01';

        // Get current base rates
        $currentBaseRates = $this->getBaseRates(date('Y-m-d', strtotime($effectiveDate . ' -1 day')));

        // Calculate new base rates
        $inflationMultiplier = 1 + ($inflationRate / 100);
        $newMainRate = round($currentBaseRates['main_camera_rate'] * $inflationMultiplier, 2);
        $newAdditionalRate = round($currentBaseRates['additional_camera_rate'] * $inflationMultiplier, 2);

        // Insert new base rates
        $this->db->insert('base_camera_rates', [
            'effective_date' => $effectiveDate,
            'main_camera_rate' => $newMainRate,
            'additional_camera_rate' => $newAdditionalRate,
            'notes' => "Inflation adjustment: {$inflationRate}% applied"
        ]);

        // Log the inflation adjustment
        $this->db->insert('inflation_adjustments', [
            'adjustment_date' => $effectiveDate,
            'inflation_rate' => $inflationRate,
            'previous_main_rate' => $currentBaseRates['main_camera_rate'],
            'new_main_rate' => $newMainRate,
            'previous_additional_rate' => $currentBaseRates['additional_camera_rate'],
            'new_additional_rate' => $newAdditionalRate,
            'applied_by' => 'system',
            'notes' => "Annual inflation adjustment"
        ]);

        // Update all legal entities with new rates
        $entities = $this->db->fetchAll("SELECT id FROM legal_entities");
        $entitiesUpdated = 0;

        foreach ($entities as $entity) {
            $result = $this->updateRatesForEntity($entity['id'], 'inflation', $effectiveDate);
            if ($result['changed']) {
                $entitiesUpdated++;
            }
        }

        // Update the inflation log with count
        $this->db->query(
            "UPDATE inflation_adjustments
             SET entities_affected = :count
             WHERE adjustment_date = :date
             ORDER BY id DESC
             LIMIT 1",
            [
                'count' => $entitiesUpdated,
                'date' => $effectiveDate
            ]
        );

        return [
            'success' => true,
            'inflation_rate' => $inflationRate,
            'effective_date' => $effectiveDate,
            'previous_main_rate' => $currentBaseRates['main_camera_rate'],
            'new_main_rate' => $newMainRate,
            'previous_additional_rate' => $currentBaseRates['additional_camera_rate'],
            'new_additional_rate' => $newAdditionalRate,
            'entities_updated' => $entitiesUpdated
        ];
    }

    /**
     * Get rate history for a legal entity
     *
     * @param int $legalEntityId
     * @return array Array of historical rates
     */
    public function getRateHistory($legalEntityId) {
        return $this->db->fetchAll(
            "SELECT *
             FROM legal_entity_rate_history
             WHERE legal_entity_id = :legal_entity_id
             ORDER BY effective_date DESC",
            ['legal_entity_id' => $legalEntityId]
        );
    }

    /**
     * Get all volume discount tiers
     */
    public function getDiscountTiers() {
        return $this->db->fetchAll(
            "SELECT * FROM volume_discount_tiers
             WHERE is_active = 1
             ORDER BY min_cameras ASC"
        );
    }

    /**
     * Check if a legal entity's rates need updating due to camera count change
     *
     * @param int $legalEntityId
     * @return array ['needs_update' => bool, 'current_tier' => string, 'new_tier' => string]
     */
    public function checkRateUpdateNeeded($legalEntityId) {
        $currentRates = $this->getCurrentRates($legalEntityId);

        // Get current camera count
        $cameraCount = $this->db->fetchOne(
            "SELECT COUNT(*) as total
             FROM camera_installations ci
             JOIN stores s ON ci.store_id = s.id
             WHERE s.legal_entity_id = :legal_entity_id
             AND ci.removal_date IS NULL",
            ['legal_entity_id' => $legalEntityId]
        );
        $totalCameras = (int) ($cameraCount['total'] ?? 0);

        // Get what the tier should be
        $currentTier = $this->getDiscountTier($currentRates['total_cameras'] ?? 0);
        $newTier = $this->getDiscountTier($totalCameras);

        $needsUpdate = ($currentTier['tier_name'] !== $newTier['tier_name']);

        return [
            'needs_update' => $needsUpdate,
            'current_tier' => $currentTier['tier_name'],
            'new_tier' => $newTier['tier_name'],
            'current_cameras' => $currentRates['total_cameras'] ?? 0,
            'actual_cameras' => $totalCameras,
            'current_discount' => $currentTier['discount_percentage'],
            'new_discount' => $newTier['discount_percentage']
        ];
    }
}
