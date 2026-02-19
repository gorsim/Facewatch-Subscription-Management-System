-- Migration 003: Migrate Existing Data to New Structure
-- This migration moves existing subscriber/camera data to the new store-based structure

-- ============================================================================
-- STEP 1: Generate legal_entity_id for existing legal entities
-- ============================================================================

-- Create a temporary sequence for generating IDs
SET @counter = 0;

UPDATE legal_entities 
SET legal_entity_id = CONCAT('LE', LPAD(@counter := @counter + 1, 4, '0'))
WHERE legal_entity_id IS NULL;

-- ============================================================================
-- STEP 2: Create stores from existing legal entities
-- ============================================================================

-- For now, create one store per legal entity (1:1 mapping)
-- Users can split these into multiple stores later via the UI or imports

INSERT INTO stores (
    legal_entity_id,
    store_id,
    store_name,
    installation_date,
    termination_date,
    category,
    notes,
    created_at,
    updated_at
)
SELECT 
    le.id,
    CONCAT(le.legal_entity_id, '-001') as store_id,
    CONCAT(le.legal_entity_name, ' - Main Store') as store_name,
    le.installation_date,
    le.termination_date,
    le.category,
    CONCAT('Auto-created from legal entity: ', le.legal_entity_name) as notes,
    le.created_at,
    le.updated_at
FROM legal_entities le;

-- ============================================================================
-- STEP 3: Update camera_counts_monthly with store_id
-- ============================================================================

-- Link existing camera counts to the newly created stores
UPDATE camera_counts_monthly ccm
JOIN legal_entities le ON ccm.subscriber_id = le.id
JOIN stores s ON s.legal_entity_id = le.id
SET ccm.store_id = s.id;

-- Remove the old subscriber_id column
ALTER TABLE camera_counts_monthly
    DROP FOREIGN KEY camera_counts_ibfk_1;

ALTER TABLE camera_counts_monthly
    DROP COLUMN subscriber_id,
    ADD CONSTRAINT fk_camera_count_store 
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE;

-- ============================================================================
-- STEP 4: Create camera_installations from camera_counts_monthly
-- ============================================================================

-- This creates individual camera installation records from the cumulative counts
-- We'll create installations dated at the first camera count date for each store

INSERT INTO camera_installations (
    store_id,
    installation_date,
    camera_type,
    notes
)
SELECT 
    ccm.store_id,
    ccm.month_date,
    'main' as camera_type,
    'Migrated from cumulative camera counts' as notes
FROM camera_counts_monthly ccm
CROSS JOIN (
    SELECT 1 as n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 
    UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10
    UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15
    UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20
) numbers
WHERE numbers.n <= ccm.cumulative_main_cameras
AND ccm.month_date = (
    SELECT MIN(month_date) 
    FROM camera_counts_monthly 
    WHERE store_id = ccm.store_id
);

-- Create additional camera installations
INSERT INTO camera_installations (
    store_id,
    installation_date,
    camera_type,
    notes
)
SELECT 
    ccm.store_id,
    ccm.month_date,
    'additional' as camera_type,
    'Migrated from cumulative camera counts' as notes
FROM camera_counts_monthly ccm
CROSS JOIN (
    SELECT 1 as n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 
    UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10
    UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15
    UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20
) numbers
WHERE numbers.n <= ccm.cumulative_additional_cameras
AND ccm.month_date = (
    SELECT MIN(month_date) 
    FROM camera_counts_monthly 
    WHERE store_id = ccm.store_id
);

-- ============================================================================
-- STEP 5: Update import history tables
-- ============================================================================

-- Rename subscriber_imports to legal_entity_imports
RENAME TABLE subscriber_imports TO legal_entity_imports;

-- ============================================================================
-- COMPLETE
-- ============================================================================

-- Verify the migration
SELECT 
    'Legal Entities' as entity_type,
    COUNT(*) as count
FROM legal_entities

UNION ALL

SELECT 
    'Stores' as entity_type,
    COUNT(*) as count
FROM stores

UNION ALL

SELECT 
    'Camera Installations' as entity_type,
    COUNT(*) as count
FROM camera_installations

UNION ALL

SELECT 
    'Monthly Camera Counts' as entity_type,
    COUNT(*) as count
FROM camera_counts_monthly

UNION ALL

SELECT 
    'Invoices' as entity_type,
    COUNT(*) as count
FROM invoices;

