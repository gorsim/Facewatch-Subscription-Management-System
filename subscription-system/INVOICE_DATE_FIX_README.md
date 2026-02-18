# Invoice Date Calculation Fix

## Problems Fixed

### 1. Date Calculation Loop Bug (CRITICAL)

**Problem:** The `calculateNextDate()` method had a critical bug where it was adding months ONE AT A TIME in a loop, and trying to fix overflow by going to "last day of previous month". This caused an infinite loop:
- Oct 31 + 1 month = Nov 30 (Nov doesn't have 31 days)
- Code sees day changed from 31 to 30
- Code runs "last day of previous month" = Oct 31 (goes BACK!)
- Next loop iteration: Oct 31 + 1 month = Nov 30 again
- Result: ALL forecast invoices had the same date!

**Solution:** Completely rewrote `calculateNextDate()` to:
- Calculate the target month and year mathematically (not using DateTime loops)
- Determine the last day of the target month
- Use the original day, or last day of month if original day doesn't exist
- No loops, no DateTime overflow issues

**Result:** Invoices now maintain consistent progressive dates:
- Monthly on 31st: Oct 31 → Nov 30 → Dec 31 → Jan 31 → Feb 28/29 → Mar 31
- Quarterly on 31st: Jan 31 → Apr 30 → Jul 31 → Oct 31
- Annual on 31st: Jan 31 2025 → Jan 31 2026 → Jan 31 2027

### 2. Auto-Generation Not Converting Forecast Invoices

**Problem:** The auto-generation system was trying to generate NEW invoices from forecast invoices, rather than converting forecast invoices to actual invoices when their date arrives.

**Solution:** Modified `InvoiceAutoGenerationService::runAutoGeneration()` to:
- Check if an invoice is a forecast (`is_forecast = 1`)
- If yes, convert it to an actual invoice (set `is_forecast = 0`, `invoice_status = 'draft'`)
- If no, generate the next repeat invoice as before

Added new method `convertForecastToActual()` that:
- Updates the forecast invoice to make it actual
- Sets status to 'draft' so you can review and issue it
- Logs the conversion in the generation log

**Result:** Forecast invoices are now automatically converted to draft invoices when their date arrives, ready for you to review and issue.

## Files Modified

1. **subscription-system/app/Services/InvoiceAutoGenerationService.php**
   - Fixed `calculateNextDate()` method (lines 359-399)
   - Modified `runAutoGeneration()` to handle forecasts (lines 31-101)
   - Added `convertForecastToActual()` method (lines 177-200)

2. **subscription-system/cron/auto-generate-invoices.php**
   - Updated output to show "Converted" count

## New Scripts

1. **subscription-system/scripts/test_auto_generation.php**
   - Test the auto-generation system manually
   - Usage: `php scripts/test_auto_generation.php [date]`
   - Example: `php scripts/test_auto_generation.php 2025-12-01`

2. **subscription-system/scripts/regenerate_forecasts_with_fixed_dates.php**
   - Deletes all existing forecast invoices
   - Regenerates them with the fixed date calculation
   - Usage: `php scripts/regenerate_forecasts_with_fixed_dates.php`

## What You Need to Do

### Step 1: Regenerate All Forecast Invoices

Run this command to delete and regenerate all forecast invoices with the correct dates:

```bash
cd subscription-system
/Applications/MAMP/bin/php/php8.3.30/bin/php scripts/regenerate_forecasts_with_fixed_dates.php
```

This will:
- Delete all existing forecast invoices (they have wrong dates)
- Regenerate them with the fixed date calculation
- Preserve the same day of month across all periods

### Step 2: Set Up the Cron Job (if not already done)

The auto-generation cron job should run daily to convert forecast invoices to actual invoices. Add this to your crontab:

```bash
0 2 * * * cd /path/to/subscription-system && /Applications/MAMP/bin/php/php8.3.30/bin/php cron/auto-generate-invoices.php >> logs/auto-generation.log 2>&1
```

This runs every day at 2am and logs the results to `logs/auto-generation.log`.

### Step 3: Test the Auto-Generation

You can manually test the auto-generation at any time:

```bash
cd subscription-system
/Applications/MAMP/bin/php/php8.3.30/bin/php scripts/test_auto_generation.php
```

Or test with a specific date:

```bash
/Applications/MAMP/bin/php/php8.3.30/bin/php scripts/test_auto_generation.php 2025-12-01
```

## How It Works Now

1. **When you create an invoice** (e.g., INV-135 on Oct 31, 2025):
   - System generates forecast invoices through to 31/3/31
   - Forecasts maintain the same day: Nov 30, Dec 31, Jan 31, Feb 28, Mar 31, etc.

2. **Each day at 2am** (or when you run the cron manually):
   - System checks for forecast invoices where `next_generation_date <= today`
   - Converts them from forecast to draft status
   - You can then review, allocate cameras if needed, and mark as issued

3. **When you mark an invoice as issued**:
   - It becomes available for reconciliation to Xero
   - The next forecast invoice is ready to be converted when its date arrives

## Example Timeline

- **Oct 31, 2025**: Create INV-135 (actual invoice)
  - System generates forecasts: INV-136 (Nov 30), INV-137 (Dec 31), INV-138 (Jan 31), etc.
  
- **Nov 30, 2025**: Cron runs
  - Converts INV-136 from forecast to draft
  - You review and mark as issued
  
- **Dec 31, 2025**: Cron runs
  - Converts INV-137 from forecast to draft
  - You review and mark as issued

And so on...

