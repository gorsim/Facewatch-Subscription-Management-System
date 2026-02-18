<?php
/**
 * Pricing Service
 * Handles pricing tier lookups and calculations based on camera counts
 */

namespace App\Services;

use App\Database;

class PricingService {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get the pricing tier for a legal entity based on camera count and date
     *
     * @param int $legalEntityId
     * @param int $cameraCount Total number of cameras
     * @param string $asOfDate Date in Y-m-d format
     * @return array Pricing information including tier details
     */
    public function getPricingForEntity($legalEntityId, $cameraCount, $asOfDate = null) {
        if ($asOfDate === null) {
            $asOfDate = date('Y-m-d');
        }

        // Get legal entity to check pricing type and model
        $entity = $this->db->fetchOne(
            "SELECT pricing_type, pricing_model, payment_frequency FROM legal_entities WHERE id = :id",
            ['id' => $legalEntityId]
        );

        if (!$entity) {
            throw new \Exception("Legal entity not found: $legalEntityId");
        }

        if ($entity['pricing_type'] === 'custom') {
            // Use entity-specific custom pricing
            return $this->getCustomPricing($legalEntityId, $cameraCount, $asOfDate, $entity['payment_frequency']);
        } elseif ($entity['pricing_model'] === 'first_plus_additional') {
            // Use "first camera + additional" pricing model
            return $this->getFirstPlusAdditionalPricing($legalEntityId, $cameraCount, $asOfDate, $entity['payment_frequency']);
        } else {
            // Use default volume-based pricing
            return $this->getDefaultPricing($cameraCount, $asOfDate, $entity['payment_frequency']);
        }
    }
    
    /**
     * Get custom pricing for a specific entity
     */
    private function getCustomPricing($legalEntityId, $cameraCount, $asOfDate, $paymentFrequency) {
        $tier = $this->db->fetchOne(
            "SELECT * FROM legal_entity_pricing
             WHERE legal_entity_id = :legal_entity_id
             AND effective_date <= :as_of_date
             AND min_cameras <= :camera_count
             AND (max_cameras IS NULL OR max_cameras >= :camera_count2)
             ORDER BY effective_date DESC, min_cameras DESC
             LIMIT 1",
            [
                'legal_entity_id' => $legalEntityId,
                'as_of_date' => $asOfDate,
                'camera_count' => $cameraCount,
                'camera_count2' => $cameraCount
            ]
        );
        
        if (!$tier) {
            // Fallback to default pricing if no custom tier found
            return $this->getDefaultPricing($cameraCount, $asOfDate, $paymentFrequency);
        }
        
        return [
            'pricing_type' => 'custom',
            'tier_name' => $tier['min_cameras'] . '-' . ($tier['max_cameras'] ?? '∞') . ' cameras',
            'min_cameras' => (int) $tier['min_cameras'],
            'max_cameras' => $tier['max_cameras'] ? (int) $tier['max_cameras'] : null,
            'price_per_annum' => (float) $tier['price_per_annum'],
            'price_per_quarter' => (float) $tier['price_per_quarter'],
            'price_per_month' => (float) $tier['price_per_month'],
            'effective_date' => $tier['effective_date'],
            'payment_frequency' => $paymentFrequency,
            'rate_to_use' => $this->getRateForFrequency($tier, $paymentFrequency),
            'notes' => $tier['notes']
        ];
    }
    
    /**
     * Get default volume-based pricing
     */
    private function getDefaultPricing($cameraCount, $asOfDate, $paymentFrequency) {
        $tier = $this->db->fetchOne(
            "SELECT * FROM camera_pricing
             WHERE effective_date <= :as_of_date
             AND min_cameras <= :camera_count
             AND (max_cameras IS NULL OR max_cameras >= :camera_count2)
             ORDER BY effective_date DESC, min_cameras DESC
             LIMIT 1",
            [
                'as_of_date' => $asOfDate,
                'camera_count' => $cameraCount,
                'camera_count2' => $cameraCount
            ]
        );
        
        if (!$tier) {
            throw new \Exception("No pricing tier found for $cameraCount cameras on $asOfDate");
        }
        
        return [
            'pricing_type' => 'default',
            'tier_name' => $tier['min_cameras'] . '-' . ($tier['max_cameras'] ?? '∞') . ' cameras',
            'min_cameras' => (int) $tier['min_cameras'],
            'max_cameras' => $tier['max_cameras'] ? (int) $tier['max_cameras'] : null,
            'price_per_annum' => (float) $tier['price_per_annum'],
            'price_per_quarter' => (float) $tier['price_per_quarter'],
            'price_per_month' => (float) $tier['price_per_month'],
            'effective_date' => $tier['effective_date'],
            'payment_frequency' => $paymentFrequency,
            'rate_to_use' => $this->getRateForFrequency($tier, $paymentFrequency),
            'notes' => $tier['notes'] ?? ''
        ];
    }
    
    /**
     * Get the correct rate based on payment frequency
     */
    private function getRateForFrequency($tier, $frequency) {
        switch ($frequency) {
            case 'monthly':
                return (float) $tier['price_per_month'];
            case 'quarterly':
                return (float) $tier['price_per_quarter'];
            case 'annual':
            default:
                return (float) $tier['price_per_annum'];
        }
    }

    /**
     * Get "first camera + additional" pricing for a legal entity
     * First camera is full price, additional cameras are discounted
     * This is the "Independent Pricing Model" for smaller entities
     */
    private function getFirstPlusAdditionalPricing($legalEntityId, $cameraCount, $asOfDate, $paymentFrequency) {
        // Try to get entity-specific pricing override first
        $pricing = $this->db->fetchOne(
            "SELECT * FROM legal_entity_independent_pricing
             WHERE legal_entity_id = :legal_entity_id
             AND effective_date <= :as_of_date
             ORDER BY effective_date DESC
             LIMIT 1",
            [
                'legal_entity_id' => $legalEntityId,
                'as_of_date' => $asOfDate
            ]
        );

        // If no entity-specific pricing, use default independent pricing
        if (!$pricing) {
            $pricing = $this->db->fetchOne(
                "SELECT * FROM default_independent_pricing
                 WHERE effective_date <= :as_of_date
                 ORDER BY effective_date DESC
                 LIMIT 1",
                ['as_of_date' => $asOfDate]
            );
        }

        if (!$pricing) {
            throw new \Exception("No independent pricing found for entity $legalEntityId on $asOfDate");
        }

        // Calculate total cost: first camera + (additional cameras * additional rate)
        $firstCameraRate = $this->getRateForFrequency([
            'price_per_annum' => $pricing['first_camera_price_per_annum'],
            'price_per_quarter' => $pricing['first_camera_price_per_quarter'],
            'price_per_month' => $pricing['first_camera_price_per_month']
        ], $paymentFrequency);

        $additionalCameraRate = $this->getRateForFrequency([
            'price_per_annum' => $pricing['additional_camera_price_per_annum'],
            'price_per_quarter' => $pricing['additional_camera_price_per_quarter'],
            'price_per_month' => $pricing['additional_camera_price_per_month']
        ], $paymentFrequency);

        $additionalCameras = max(0, $cameraCount - 1);
        $totalCost = $firstCameraRate + ($additionalCameras * $additionalCameraRate);

        // Safety check: prevent division by zero
        $averageRate = $cameraCount > 0 ? ($totalCost / $cameraCount) : $firstCameraRate;

        return [
            'pricing_type' => 'first_plus_additional',
            'pricing_model' => 'Independent: First camera + additional',
            'tier_name' => 'First camera + additional',
            'camera_count' => $cameraCount,
            'first_camera_rate' => $firstCameraRate,
            'additional_camera_rate' => $additionalCameraRate,
            'additional_cameras' => $additionalCameras,
            'rate_to_use' => $averageRate, // Average rate per camera for compatibility
            'total_cost' => $totalCost,
            'effective_date' => $pricing['effective_date'],
            'payment_frequency' => $paymentFrequency,
            'notes' => $pricing['notes'] ?? ''
        ];
    }
}

