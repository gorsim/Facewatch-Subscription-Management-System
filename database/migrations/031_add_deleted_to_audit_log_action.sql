-- Migration: Add 'deleted' to matching_audit_log action ENUM
-- Date: 2026-02-17
-- Purpose: Allow logging of Xero invoice deletions in audit log

ALTER TABLE matching_audit_log
MODIFY COLUMN action ENUM('auto_matched', 'suggested', 'accepted', 'rejected', 'manual', 'deleted') NOT NULL;

