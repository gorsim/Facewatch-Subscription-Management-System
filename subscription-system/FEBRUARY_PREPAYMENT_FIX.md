# February 28 Prepayment Calculation Fix

## Problem

Prepayment balances were not changing correctly between January 31, 2026 and February 28, 2026. Individual invoice prepayment balances remained the same when they should have decreased.

### Example
- **Invoice**: INV-016, £3,540, dated 30/12/2025
- **Jan 31, 2026**: Prepayment showed £2,668.75
- **Feb 28, 2026**: Prepayment showed £2,668.75 (WRONG - should decrease!)

### Root Cause

The `getMonthsBetween()` method in `PrepaymentCalculator.php` was using PHP's `DateTime::diff()` which has issues with end-of-month dates.

**The Bug**:
```php
// OLD CODE (BUGGY)
private function getMonthsBetween($start, $end) {
    $interval = $start->diff($end);
    return ($interval->y * 12) + $interval->m;
}
```

When calculating from **Dec 30** to **Feb 28**:
- PHP's `diff()` returns: 1 month and 29 days
- The method returned: **1 month** (should be **2 months**)

This happened because Feb 28 is only 29 days after Jan 30, which `diff()` considers less than a full month.

## Solution

Modified `getMonthsBetween()` to compare the **first day of each month** instead of the actual dates:

```php
// NEW CODE (FIXED)
private function getMonthsBetween($start, $end) {
    // Clone dates and set to first day of month for accurate month counting
    $startMonth = clone $start;
    $startMonth->modify('first day of this month');
    
    $endMonth = clone $end;
    $endMonth->modify('first day of this month');
    
    $interval = $startMonth->diff($endMonth);
    return ($interval->y * 12) + $interval->m;
}
```

Now:
- Dec 2025 → Jan 2026 = **1 month** ✅
- Dec 2025 → Feb 2026 = **2 months** ✅
- Jan 2026 → Feb 2026 = **1 month** ✅

## Verification

### Test Results (Invoice: £3,540, dated 30/12/2025)

| Date | Months Since | Prepayment Balance | P&L Credit |
|------|--------------|-------------------|------------|
| Dec 31, 2025 | 0 | £3,392.50 | £147.50 |
| Jan 31, 2026 | 1 | £3,097.50 | £442.50 |
| **Feb 28, 2026** | **2** | **£2,802.50** | **£737.50** |
| Mar 31, 2026 | 3 | £2,507.50 | £1,032.50 |

✅ **Prepayment now decreases correctly each month!**

### Calculation Method (0.5/1/12th/0.5)

- **Month 0** (Dec): 0.5/12 × £3,540 = £147.50 to P&L
- **Month 1** (Jan): 1/12 × £3,540 = £295.00 to P&L
- **Month 2** (Feb): 1/12 × £3,540 = £295.00 to P&L
- **Month 3** (Mar): 1/12 × £3,540 = £295.00 to P&L

Cumulative P&L after Feb: £147.50 + £295.00 + £295.00 = **£737.50** ✅

## Impact

This fix affects:
- ✅ **Prepayment Report** - Individual invoice balances now change correctly
- ✅ **Revenue Report** - P&L credits are accurate for February
- ✅ **All invoices** - Any invoice with dates near month-end boundaries

## Files Modified

1. **subscription-system/app/Services/PrepaymentCalculator.php**
   - Lines 141-161: Fixed `getMonthsBetween()` method

## Testing

To verify the fix is working:

1. Go to **Reports → Prepayments**
2. Select **January 31, 2026** - note the prepayment balances
3. Select **February 28, 2026** - balances should be **lower** than January
4. Check the **Revenue Report** - Feb 2026 P&L Credit should match the difference

The system auto-recalculates all invoices before displaying reports, so the fix takes effect immediately.

## Technical Notes

- This issue only affected months with different day counts (e.g., Jan 31 → Feb 28)
- The fix ensures calendar months are counted correctly regardless of day-of-month
- No database changes required - this is a calculation-only fix
- All historical data will be recalculated correctly with the new method

---

**Fixed**: 2026-02-18  
**File**: `subscription-system/app/Services/PrepaymentCalculator.php`  
**Method**: `getMonthsBetween()`

