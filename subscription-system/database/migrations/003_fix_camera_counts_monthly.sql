-- Migration: Fix camera_counts_monthly table
-- Remove subscriber_id column (old schema)
-- This table should only use store_id

-- Check if subscriber_id column exists and remove it
ALTER TABLE camera_counts_monthly 
DROP COLUMN IF EXISTS subscriber_id;

-- Ensure store_id is properly set up
-- (It should already exist from migration 002, but let's make sure)
ALTER TABLE camera_counts_monthly 
MODIFY COLUMN store_id INT UNSIGNED NOT NULL;

-- Add index on store_id if it doesn't exist
CREATE INDEX IF NOT EXISTS idx_store_id ON camera_counts_monthly(store_id);

-- Add index on month_date if it doesn't exist  
CREATE INDEX IF NOT EXISTS idx_month_date ON camera_counts_monthly(month_date);

-- Add composite index for lookups
CREATE INDEX IF NOT EXISTS idx_store_month ON camera_counts_monthly(store_id, month_date);

