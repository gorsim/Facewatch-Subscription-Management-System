# Legal Entity Import Update Summary

## What Changed

The legal entity import system has been updated to work with the new **volume-based pricing tier system**. The old simplified rate fields have been removed.

## Old CSV Format (Deprecated)

```csv
Subscriber Name,Main Cam Rate,Additional Cam Rate,Payment Frequency
Test Company,2500,2000,Annual
```

**Problems with old format:**
- ❌ Only supported two flat rates (main + additional)
- ❌ No volume-based discounts
- ❌ No support for custom pricing tiers
- ❌ Rates had to be manually updated each year

## New CSV Format

```csv
Legal Entity Name,Legal Entity ID,Xero Company Name,Payment Frequency,Pricing Type,Installation Date,Category,Sales Credit
Test Company Ltd,TEST001,Test Company Limited,Annual,default,2024-01-15,Retail,John Smith
```

### Required Columns
- **Legal Entity Name** OR **Subscriber Name** - Customer name (at least one required)

### Optional Columns
- **Legal Entity ID** - Unique identifier
- **Xero Company Name** - Name used in Xero invoicing
- **Payment Frequency** - Annual, Quarterly, or Monthly (defaults to Annual)
- **Pricing Type** - "default" or "custom" (defaults to "default")
- **Installation Date** - Date format: YYYY-MM-DD or DD/MM/YYYY
- **Category** - Business category (e.g., Retail, Hospitality)
- **Sales Credit** - Sales person credit

## How It Works Now

### 1. Import Creates Entities with Default Pricing
All imported entities automatically use the **default volume-based pricing tiers**:

| Camera Range | Annual Rate | Quarterly Rate | Monthly Rate |
|--------------|-------------|----------------|--------------|
| 1-49         | £2,700      | £675           | £225         |
| 50-149       | £2,500      | £625           | £208.33      |
| 150-249      | £2,300      | £575           | £191.67      |
| 250-349      | £2,100      | £525           | £175         |
| 350-499      | £1,900      | £475           | £158.33      |
| 500+         | £1,700      | £425           | £141.67      |

### 2. Custom Pricing (If Needed)
For entities that need custom pricing (like B&M or Frasers):

1. Import the entity with `Pricing Type = default`
2. Go to **Admin → Pricing Tiers Dashboard**
3. Click "Manage Custom Pricing" for that entity
4. Add custom pricing tiers with effective dates

## Files Changed

### 1. `app/Services/SubscriberImporter.php`
- ✅ Removed `main_camera_rate` and `additional_camera_rate` fields
- ✅ Added `pricing_type` field (default/custom)
- ✅ Removed old `createOrUpdateContract()` method
- ✅ Updated to use new pricing tier system

### 2. `views/imports/index.php`
- ✅ Updated UI to show new CSV format
- ✅ Added warning about deprecated rate fields
- ✅ Added link to pricing tiers admin page

### 3. Test Files
- ✅ Created `test_data/legal_entities_import_sample.csv` - Sample CSV
- ✅ Created `test_import.php` - Test script to verify import

## Testing

Run the test script:
```bash
cd subscription-system
/Applications/MAMP/bin/php/php8.3.30/bin/php test_import.php
```

Expected output:
```
✅ Import successful!
   - Imported: 3 new entities
   - Updated: 0 existing entities
```

## Migration Path

### For Existing CSV Files
If you have old CSV files with "Main Cam Rate" and "Additional Cam Rate":

1. **Remove these columns** from your CSV
2. **Keep** Payment Frequency column
3. **Add** Pricing Type column (set to "default" for most entities)
4. Import the file - entities will use volume-based pricing automatically

### For Entities with Special Rates
If an entity had custom rates before:

1. Import with `Pricing Type = custom`
2. Use the admin UI to configure custom pricing tiers
3. Set effective dates for pricing changes

## Benefits

✅ **Automatic volume discounts** - Larger customers pay less per camera  
✅ **Easier to manage** - Update pricing tiers once, applies to all entities  
✅ **Historical pricing** - Track pricing changes over time with effective dates  
✅ **Flexible** - Support both default and custom pricing per entity  
✅ **Future-proof** - Easy to add new pricing tiers or adjust rates annually

## Next Steps

1. ✅ Import system updated
2. ⏭️ Test with real customer data
3. ⏭️ Update any existing import scripts/processes
4. ⏭️ Train staff on new import format

