-- Migration 029: Add Forecast Invoice Status
-- Date: 16 February 2026
-- Purpose: Add 'forecast' status for auto-generated future invoices

-- Add 'forecast' to invoice_status enum
ALTER TABLE invoices 
MODIFY COLUMN invoice_status ENUM(
    'draft', 
    'issued', 
    'reconciled_to_xero', 
    'cancelled', 
    'merged',
    'forecast'
) DEFAULT 'draft';

-- Add is_forecast flag for quick filtering
ALTER TABLE invoices 
ADD COLUMN is_forecast BOOLEAN DEFAULT FALSE AFTER invoice_status;

-- Add forecast_year to track which year of forecast this is (1, 2, 3)
ALTER TABLE invoices
ADD COLUMN forecast_year INT NULL AFTER is_forecast
COMMENT 'Which year of forecast (1=next year, 2=year after, 3=third year)';

-- Add parent_invoice_id to link forecasts to their original invoice
ALTER TABLE invoices
ADD COLUMN parent_invoice_id INT NULL AFTER forecast_year
COMMENT 'Links forecast invoices to their parent invoice';

-- Add foreign key constraint
ALTER TABLE invoices
ADD CONSTRAINT fk_parent_invoice
FOREIGN KEY (parent_invoice_id) REFERENCES invoices(id)
ON DELETE SET NULL;

-- Add index for parent lookups
CREATE INDEX idx_invoices_parent ON invoices(parent_invoice_id);

-- Update existing invoices to set is_forecast flag
UPDATE invoices 
SET is_forecast = (invoice_status = 'forecast')
WHERE is_forecast IS NULL;

-- Add indexes for performance
CREATE INDEX idx_invoices_is_forecast ON invoices(is_forecast);
CREATE INDEX idx_invoices_status_forecast ON invoices(invoice_status, is_forecast);
CREATE INDEX idx_invoices_forecast_year ON invoices(forecast_year);

-- Update invoice_status_history to support forecast status
-- (No changes needed - enum already supports any status value)

-- Add comment to table
ALTER TABLE invoices 
COMMENT = 'Invoices including actual and forecast (future) invoices for reporting';

