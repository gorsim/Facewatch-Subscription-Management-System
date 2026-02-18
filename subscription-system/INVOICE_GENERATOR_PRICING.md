# Invoice Generator - Real Pricing Implementation

## Overview
The Invoice Generator now uses **real pricing calculations** from the PricingService instead of placeholder £100/camera amounts.

## What Changed

### 1. Real-Time Pricing Calculation (JavaScript)
**File**: `subscription-system/views/invoices/generator.php`

**Changes**:
- Added `data-legal-entity-id` and `data-camera-type` attributes to camera checkboxes
- Updated `calculateAmount()` function to:
  - Count main vs additional cameras
  - Make AJAX call to pricing endpoint
  - Display real calculated amount

**How It Works**:
1. User selects cameras
2. JavaScript counts main/additional cameras
3. AJAX call to `calculate_pricing.php` with:
   - Legal Entity ID
   - Total camera count
   - Main camera count
   - Additional camera count
4. Server returns calculated amount
5. Amount displayed in "Calculated Amount" field

### 2. Pricing Calculation Endpoint (AJAX)
**File**: `subscription-system/views/invoices/calculate_pricing.php` (NEW)

**Purpose**: Calculate invoice amount for selected cameras

**Process**:
1. Receives: legal_entity_id, camera_count, main_cameras, additional_cameras
2. Gets legal entity details (payment frequency, pricing model)
3. Calls `PricingService->getPricingForEntity()`
4. Returns calculated amount as JSON

**Pricing Models Supported**:
- **Volume-based**: Rate per camera × camera count
- **First + Additional**: First camera full price + additional cameras discounted

### 3. Invoice Creation with Real Pricing
**File**: `subscription-system/views/invoices/create_from_generator.php`

**Changes**:
- Added `PricingService` import
- Counts main vs additional cameras
- Uses `PricingService->getPricingForEntity()` to calculate amount
- Supports both pricing models:
  - Volume-based: `rate_to_use × camera_count`
  - First + Additional: `total_cost` from pricing service
- Allocates cameras with:
  - `price_charged`: Price per camera (total ÷ count)
  - `pricing_tier`: Tier name from pricing service
  - `camera_type`: main or additional

## Pricing Service Integration

The system uses `App\Services\PricingService` which:

1. **Checks pricing type** for the legal entity:
   - `custom`: Entity-specific pricing from `legal_entity_pricing` table
   - `first_plus_additional`: First camera + additional model
   - `volume_based`: Default volume-based pricing

2. **Gets appropriate pricing tier**:
   - Based on camera count
   - Based on effective date
   - Based on payment frequency (monthly/quarterly/annual)

3. **Returns pricing information**:
   - `rate_to_use`: Rate per camera for this tier
   - `total_cost`: Total cost (for first+additional model)
   - `tier_name`: Name of the pricing tier
   - `payment_frequency`: monthly/quarterly/annual

## Example Pricing Scenarios

### Scenario 1: Volume-Based Pricing
- Legal Entity: Standard customer
- Cameras: 75 cameras (all main)
- Pricing Tier: 50-149 cameras
- Payment Frequency: Annual
- Rate: £3,417.00 per camera per annum
- **Invoice Amount**: £3,417.00 × 75 = **£256,275.00**

### Scenario 2: First + Additional Pricing
- Legal Entity: Independent customer
- Cameras: 5 cameras (1 main, 4 additional)
- First Camera: £3,540.00 per annum
- Additional: £2,500.00 per annum each
- **Invoice Amount**: £3,540 + (4 × £2,500) = **£13,540.00**

### Scenario 3: Quarterly Payment
- Legal Entity: Standard customer
- Cameras: 200 cameras
- Pricing Tier: 150-249 cameras
- Payment Frequency: Quarterly
- Rate: £824.50 per camera per quarter
- **Invoice Amount**: £824.50 × 200 = **£164,900.00**

## How to Use

### Step 1: Select Cameras
1. Go to Invoice Generator tab
2. Filter by cutoff date (default: last month end)
3. Select cameras using checkboxes
4. Or use "Select All" button for a legal entity

### Step 2: Review Calculated Amount
- As you select cameras, the "Calculated Amount" updates automatically
- Amount is based on:
  - Legal entity's pricing tier
  - Total camera count
  - Payment frequency
  - Pricing model (volume or first+additional)

### Step 3: Create Invoice
1. Review the calculated amount
2. Optionally override the amount if needed
3. Set invoice date (defaults to last month end)
4. Click "Create Invoice"

### Step 4: Invoice Created
- Invoice is created with status "draft"
- All selected cameras are allocated
- Each camera has `price_charged` = total amount ÷ camera count
- Invoice can be edited before marking as "issued"

## Benefits

✅ **Accurate Pricing**: Uses real pricing tiers from database
✅ **Automatic Calculation**: No manual calculation needed
✅ **Multiple Pricing Models**: Supports volume-based and first+additional
✅ **Payment Frequency**: Correctly calculates monthly/quarterly/annual amounts
✅ **Transparent**: Shows calculated amount before creating invoice
✅ **Override Option**: Can manually override if needed

## Files Modified

1. `subscription-system/views/invoices/generator.php` - Added real-time calculation
2. `subscription-system/views/invoices/calculate_pricing.php` - NEW pricing endpoint
3. `subscription-system/views/invoices/create_from_generator.php` - Real pricing on creation

## Testing

1. **Test Volume-Based Pricing**:
   - Select cameras from a standard legal entity
   - Verify amount matches: rate × camera count

2. **Test First + Additional Pricing**:
   - Select cameras from an independent legal entity
   - Verify amount matches: first camera + (additional × rate)

3. **Test Different Payment Frequencies**:
   - Monthly: Lower amount per period
   - Quarterly: Medium amount per period
   - Annual: Full year amount

4. **Test Override**:
   - Calculate amount
   - Enter override amount
   - Verify override is used instead of calculated

