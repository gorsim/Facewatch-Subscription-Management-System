# Xero Reconciliation System - Changes Summary

## Overview
The reconciliation system has been updated to compare **System Invoice Amount** vs **Xero Invoice Amount** instead of comparing against an "expected amount" calculated from cameras.

## What Changed

### 1. Database Changes
- **New Column**: `xero_invoice_amount` added to `invoices` table
- **Purpose**: Stores the actual invoice amount from Xero for comparison

### 2. Reconciliation Form Updates
**File**: `subscription-system/views/invoices/reconcile_form.php`

**New Field Added**:
- Xero Invoice Amount (required)
- Shows your system amount for easy comparison
- Validates that amount is a positive number

**What You Enter**:
1. Xero Invoice ID (from URL)
2. Xero Invoice Number (from invoice page)
3. **Xero Invoice Amount** (NEW - the total amount shown in Xero)

### 3. Reconciliation Service Updates
**File**: `subscription-system/app/Services/InvoiceStatusService.php`

**Changes**:
- Now accepts `xeroInvoiceAmount` parameter
- Saves the Xero amount to the database
- Calculates variance and includes it in the status history notes

### 4. Invoice View Updates
**File**: `subscription-system/views/invoices/view.php`

**New Reconciliation Summary**:
- **Only shows for reconciled invoices** (not drafts or issued invoices)
- Compares:
  - System Invoice Amount (what you created)
  - Xero Invoice Amount (what's in Xero)
  - Variance (the difference)
- Shows variance as both amount and percentage
- Color-coded:
  - ✅ Green = Matched (variance ≤ £0.01)
  - 🔴 Red = System higher than Xero (over-charged in system)
  - 🟠 Orange = System lower than Xero (under-charged in system)

## How to Use

### Step 1: Run the Migration
Visit: `http://localhost:8080/add_xero_invoice_amount_column.php`

This adds the `xero_invoice_amount` column to your database.

### Step 2: Reconcile Invoices
1. Mark invoice as "Issued" first
2. Click "Reconcile to Xero"
3. Enter:
   - Xero Invoice ID
   - Xero Invoice Number
   - **Xero Invoice Amount** (copy from Xero)
4. Click "Reconcile"

### Step 3: View Reconciliation
- The invoice view will now show the "Xero Reconciliation" section
- This compares your system amount vs Xero amount
- Any variance is highlighted with an explanation

## Benefits

✅ **Accurate Comparison**: Compares actual Xero amounts, not calculated estimates
✅ **Clear Visibility**: See exactly where your system and Xero differ
✅ **No More Confusion**: Draft invoices don't show reconciliation (it's irrelevant)
✅ **Actionable Alerts**: Clear warnings when amounts don't match

## Example Scenarios

### Scenario 1: Perfect Match
- System Amount: £500.00
- Xero Amount: £500.00
- Result: ✅ Green "Matched" - no action needed

### Scenario 2: System Higher
- System Amount: £550.00
- Xero Amount: £500.00
- Result: 🔴 Red "+£50.00" - Xero is under-charged

### Scenario 3: System Lower
- System Amount: £450.00
- Xero Amount: £500.00
- Result: 🟠 Orange "-£50.00" - Xero is over-charged

## Files Modified

1. `subscription-system/public/add_xero_invoice_amount_column.php` (NEW - migration)
2. `subscription-system/views/invoices/reconcile_form.php` (updated form)
3. `subscription-system/app/Services/InvoiceStatusService.php` (updated service)
4. `subscription-system/views/invoices/view.php` (updated display)

## Next Steps

1. Run the migration
2. Test reconciling an invoice
3. Verify the reconciliation summary displays correctly

