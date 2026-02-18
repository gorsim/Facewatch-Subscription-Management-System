-- Migration: Add match_score and match_breakdown columns to xero_imported_invoices
-- Date: 2026-02-17
-- Purpose: Store detailed matching information for historical session viewing

ALTER TABLE xero_imported_invoices
ADD COLUMN match_score DECIMAL(5, 2) NULL COMMENT 'Match score percentage (0-100)' AFTER match_confidence,
ADD COLUMN match_breakdown JSON NULL COMMENT 'Detailed breakdown of match scoring' AFTER match_score;

-- Update existing records to copy match_confidence to match_score
UPDATE xero_imported_invoices
SET match_score = match_confidence
WHERE match_confidence IS NOT NULL;

