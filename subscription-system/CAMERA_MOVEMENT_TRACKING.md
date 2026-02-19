# Camera Movement Tracking & Xero Correction System

## Overview

This system automatically detects when cameras are moved between stores during CSV imports and:
1. Creates an audit trail of all camera movements
2. Automatically updates invoice allocations for draft invoices
3. Flags issued/reconciled invoices that need manual Xero corrections
4. Generates exportable reports for Xero updates

## How It Works

### 1. Detection During Import

When you upload an incremental camera installation CSV file with removal dates, the system:

- **Identifies Removals**: Rows with `removal_date` but no `installation_date`
- **Identifies Installations**: Rows with `installation_date` but no `removal_date`
- **Matches Movements**: Cameras with the same `safr_code` appearing in both removals and installations

### 2. Automatic Invoice Updates

For each detected movement, the system:

**Draft Invoices** (Status: "draft"):
- ✅ Automatically updates invoice allocations to the new store
- No manual action required

**Issued/Reconciled Invoices** (Status: "issued" or "reconciled_to_xero"):
- ⚠️ Flags for manual Xero correction
- Creates detailed impact records
- Generates exportable correction report

### 3. Audit Trail

All movements are recorded in the `camera_movements` table with:
- SAFR code and camera name
- From/To store details
- Removal and installation dates
- Number of invoices affected
- Xero correction status

## CSV File Format

Your incremental camera installation CSV should include:

```csv
Store ID,Store Name,Installation Date,Camera Name,SAFR Code,Removal Date
```

**Example - Camera Movement:**
```csv
,459 Manchester B&M,,,CA154A,25/10/2024    ← Removal from old store
,Scunthorpe Parishes Sports Direct,22/01/2026,Main,CA154A,    ← Installation at new store
```

## Using the System

### 1. Import Camera Data

1. Go to **Imports** page
2. Select **Camera Installations (Incremental)**
3. Upload your CSV file
4. Review the import results

If movements are detected, you'll see:
```
✅ Successfully imported X camera installations
📦 Camera Movements Detected: Y
• Z draft invoices automatically updated
• W issued invoices flagged for Xero correction
```

### 2. View Camera Movements

Click **"View Camera Movements"** button or navigate to:
**Cameras → Movements**

This page shows:
- All detected camera movements
- Which stores were involved
- How many invoices were affected
- Xero correction status

### 3. Export Xero Corrections

1. Go to **Cameras → Movements**
2. Click **"Export Xero Corrections"** button
3. Download CSV file with all pending corrections

The export includes:
- Movement dates
- SAFR codes and camera names
- From/To store details
- Invoice numbers and dates
- Action required

### 4. Mark Corrections as Complete

After making corrections in Xero:
1. View the movement details
2. Update the Xero correction status to "Corrected"
3. Add notes about what was done

## Database Tables

### `camera_movements`
Tracks each camera movement between stores:
- Movement details (from/to stores, dates)
- Impact statistics (invoices affected)
- Xero correction status

### `camera_movement_invoice_impacts`
Tracks which invoices were affected by each movement:
- Invoice details (number, status, date)
- Old vs new store allocation
- Action taken (auto-updated or flagged)
- Xero correction tracking

### `camera_installation_imports`
Enhanced with movement statistics:
- `movements_detected` - Number of movements in this import
- `invoices_auto_updated` - Draft invoices updated automatically
- `invoices_flagged_for_xero` - Issued invoices needing correction

## Benefits

1. **Automatic Detection**: No manual tracking of camera movements
2. **Audit Trail**: Complete history of all movements
3. **Smart Updates**: Draft invoices updated automatically
4. **Xero Integration**: Clear reports for manual corrections
5. **Compliance**: Full audit trail for financial records

## Example Workflow

1. **Camera Moved**: Camera CA154A moved from Store A to Store B on 25/10/2024
2. **CSV Upload**: Include removal row for Store A and installation row for Store B
3. **System Detects**: Matches SAFR code CA154A in both rows
4. **Auto-Update**: Updates 3 draft invoices to allocate to Store B
5. **Flag for Xero**: Flags 2 issued invoices for manual correction
6. **Export Report**: Download CSV with correction details
7. **Update Xero**: Make manual corrections in Xero
8. **Mark Complete**: Update status to "Corrected" in system

## Files Modified

- `app/Services/CameraInstallationImporter.php` - Movement detection logic
- `views/cameras/movements.php` - Movement report page
- `views/imports/index.php` - Import results with movement stats
- `database/migrations/008_camera_movements_tracking.sql` - Database schema
- `public/index.php` - Routing for movement pages

