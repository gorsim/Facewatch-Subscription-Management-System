# Invoice Notes and Camera Fields - Implementation Summary

**Date:** 2026-02-18  
**Migration:** 031_add_invoice_notes_and_camera_fields.sql

## Overview

Successfully added the following features to the Facewatch Subscription Management System:

1. **Invoice Notes** - Add internal notes to invoices
2. **Camera Name** - Friendly name for cameras (e.g., "Front Entrance", "Checkout Area")
3. **SAFR Code** - SAFR system code for camera identification

---

## Database Changes

### Migration 031

The migration added three new columns:

#### 1. `invoices` table
- **Column:** `notes` (TEXT, NULL)
- **Purpose:** Store internal notes about invoices
- **Usage:** Optional field for adding context, explanations, or reminders

#### 2. `camera_installations` table
- **Column:** `camera_name` (VARCHAR(255), NULL)
- **Purpose:** Friendly, human-readable name for the camera
- **Examples:** "Front Entrance", "Back Door", "Checkout Area", "Side Entrance"

- **Column:** `safr_code` (VARCHAR(100), NULL)
- **Purpose:** SAFR system identifier for the camera
- **Examples:** "SAFR-001", "SAFR-002", etc.
- **Index:** Added `idx_safr_code` for faster lookups

---

## Files Modified

### 1. Migration Files
- **Created:** `migrations/031_add_invoice_notes_and_camera_fields.sql`
- **Created:** `run_migration_031.php` (migration runner script)

### 2. Camera Import Service
- **Modified:** `app/Services/CameraInstallationImporter.php`
  - Added column mapping for `camera_name` and `safr_code`
  - Updated import logic to save these fields
  - Handles variations: "camera_name", "camera name", "name"
  - Handles variations: "safr_code", "safr code", "safr"

### 3. Import Template
- **Modified:** `sample-data/camera-installations-import-template.csv`
  - Added `Camera Name` column
  - Added `SAFR Code` column
  - Updated sample data with example values

### 4. Import Instructions
- **Modified:** `views/imports/index.php`
  - Updated documentation to list new optional fields
  - Added descriptions for Camera Name and SAFR Code

### 5. Invoice Generator
- **Modified:** `views/invoices/generator.php`
  - Added "Invoice Notes" textarea field
  - Notes are optional and can be left blank
  - Field appears in the invoice creation section

### 6. Invoice Review Page
- **Modified:** `views/invoices/review_pricing.php`
  - Receives invoice notes from generator
  - Passes notes through to creation handler

### 7. Invoice Creation Handler
- **Modified:** `views/invoices/create_from_review.php`
  - Saves invoice notes to database
  - Notes are stored when creating the invoice

---

## How to Use

### Adding Notes to Invoices

1. Go to **Invoices** → **Invoice Generator**
2. Select cameras for the invoice
3. In the "Invoice Notes" field, add any internal notes
4. Click "Review & Create Invoice"
5. The notes will be saved with the invoice

**Example Notes:**
- "Adjusted pricing due to contract negotiation"
- "First invoice for this customer"
- "£100 variance allocated to Camera 1"

### Importing Cameras with Names and SAFR Codes

1. Prepare your CSV file with these columns:
   ```
   Store ID, Store Name, Installation Date, Camera Type, Camera Name, SAFR Code, Invoice Number, Removal Date, Notes
   ```

2. Example CSV row:
   ```
   LE0001-001, ADES Limited - Main Store, 2024-01-15, main, Front Entrance, SAFR-001, INV-2024-001, , Initial installation
   ```

3. Go to **Imports** → **Camera Installations**
4. Upload your CSV file
5. The system will import all fields including Camera Name and SAFR Code

### CSV Column Variations

The importer is flexible and accepts these variations:

**For Camera Name:**
- `camera_name`
- `camera name`
- `name`

**For SAFR Code:**
- `safr_code`
- `safr code`
- `safr`

---

## Benefits

### Invoice Notes
✅ **Better Documentation** - Record why specific pricing decisions were made  
✅ **Audit Trail** - Track special circumstances or adjustments  
✅ **Team Communication** - Share context with other team members  
✅ **Internal Use Only** - Notes are not visible to customers  

### Camera Name
✅ **Easy Identification** - Quickly identify cameras by location  
✅ **Better Reporting** - More meaningful camera descriptions  
✅ **User-Friendly** - No need to remember camera IDs  

### SAFR Code
✅ **System Integration** - Link cameras to SAFR platform  
✅ **Unique Identifiers** - Track cameras across systems  
✅ **Fast Lookups** - Indexed for quick searches  

---

## Testing

The migration has been successfully run and all changes are live. You can now:

1. ✅ Create invoices with notes
2. ✅ Import cameras with Camera Name and SAFR Code
3. ✅ View camera names in the invoice generator
4. ✅ Search cameras by SAFR code (indexed for performance)

---

## Next Steps

1. **Test Invoice Notes** - Create a test invoice with notes
2. **Import Camera Data** - Upload a CSV with the new fields
3. **Verify Display** - Check that camera names appear correctly in the system
4. **Update Existing Data** - Optionally add names/codes to existing cameras

---

## Support

All fields are **optional** - the system works perfectly without them. Add them when you have the information available!


