-- Migration 023: Remove Camera Snapshot System
-- Date: 2026-02-16
-- Purpose: Drop the deprecated camera_counts_monthly table and related structures
-- The system now uses camera_installations table exclusively for tracking cameras

USE facewatch_subscriptions;

-- ============================================================================
-- Drop the deprecated camera_counts_monthly table
-- ============================================================================

-- Drop the table if it exists
DROP TABLE IF EXISTS camera_counts_monthly;

-- ============================================================================
-- Clean up camera_imports table (remove snapshot-related imports)
-- ============================================================================

-- Remove any camera snapshot imports from the import log
-- (Keep camera installation imports)
DELETE FROM camera_imports 
WHERE filename LIKE '%Snapshot:%';

-- ============================================================================
-- Verification
-- ============================================================================

-- Show that the table is gone
SELECT 
    TABLE_NAME,
    TABLE_ROWS,
    CREATE_TIME
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = 'facewatch_subscriptions'
AND TABLE_NAME = 'camera_counts_monthly';

-- Should return empty result set

SELECT 'Migration 023 completed successfully - camera_counts_monthly table removed' AS status;

