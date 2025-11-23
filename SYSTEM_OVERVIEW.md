# Camera Stock Control System - User Guide

## Overview
The Camera Stock Control System is a web-based application designed to manage and track SAFR camera inventory. It helps you reconcile delivered cameras against installed cameras, perform physical counts, and maintain accurate stock records.

## Key Features

### 📊 Stock Management
- **Upload CSV Files**: Import delivered and installed camera data
- **Generate Stock Lists**: Create comprehensive stock reports for any date
- **Physical Counting**: Track which cameras have been physically verified
- **Missing Items Tracking**: Automatically identify cameras that are delivered but not installed or counted

### 🔍 Filtering & Search
- **Search by Camera Code**: Quickly find specific cameras
- **Filter by Camera Type**: View only specific camera models (e.g., SAFR-Camera, SAFR-Kiosk)
- **Filter by Location**: See cameras at specific locations
- **Filter by Purchase Order (PO)**: View cameras from specific orders
- **Show Missing Only**: Display only cameras that need attention

### 📱 Mobile-Friendly Features
- **Barcode Scanning**: Use your phone/tablet camera to scan camera barcodes
- **Batch Import**: Scan multiple cameras at once and import them together
- **Responsive Design**: Works on desktop, tablet, and mobile devices

### 📝 Data Management
- **Notes**: Add notes to individual cameras
- **Location Assignment**: Assign cameras to specific locations
- **Historical Data**: Access past stock counts and bring forward notes from previous dates
- **Auto-Save**: Changes are automatically saved as you work

## How to Use

### 1. Initial Setup (Admin Only)
1. **Login** with your credentials
2. **Upload CSV Files**:
   - Click "Choose File" under "Delivered Cameras CSV"
   - Select your delivered cameras file
   - Click "Choose File" under "Installed Cameras CSV"
   - Select your installed cameras file
   - Click "Upload Files"
3. **Generate Stock List**:
   - Once files are uploaded, the "GENERATE STOCK LIST" button turns green
   - Click the button to create your stock list

### 2. Performing a Physical Count
1. **Select Date**: Choose the date from "Past Counts" dropdown or generate a new list
2. **Check Cameras**: 
   - Tick the checkbox in the "Physical" column when you physically verify a camera
   - The "Missing" column automatically updates (shows cameras delivered but not installed/counted)
3. **Add Notes**: Enter any relevant information in the Notes field
4. **Assign Location**: Select the location from the dropdown
5. **Save**: Changes auto-save, or click "Save Changes" to save immediately

### 3. Using Filters
- **Search Box**: Type a camera code to filter the list
- **Cam Type**: Select a camera type from the dropdown
- **Location**: Select a location from the dropdown
- **PO**: Select a purchase order from the dropdown
- **Show Missing Only**: Check this to see only cameras that need attention
- **Clear Filters**: Click the ⋮ menu and select "Clear All Filters"

### 4. Barcode Scanning (Mobile)
1. Click "📋 Batch Import Scans"
2. Click "📷 Scan Barcode" to use your device camera
3. Point camera at the barcode (Code128 format)
4. Camera code is automatically detected and added to the list
5. Scan multiple cameras, then click "Import All"
6. All scanned cameras are marked as physically counted

### 5. Managing Past Counts
1. Click "📋 Past Counts" (admin only)
2. View all historical stock counts with dates and item counts
3. Delete old counts if needed (click "Delete" button)

## Understanding the Data

### Stock List Columns
- **Camera Code**: Unique 6-character identifier (e.g., CA1A73)
- **Cam Type**: Camera model (e.g., SAFR-Camera, SAFR-Kiosk)
- **PO**: Purchase Order number
- **Delivery Date**: When the camera was delivered
- **Installed**: Number of times this camera appears in installed list (0 or 1)
- **Physical**: Checkbox to mark when physically counted
- **Missing**: Calculated field (Delivered - Installed - Physical Count)
- **Notes**: Free text field for comments
- **Location**: Dropdown to assign camera location

### Totals Summary
At the bottom of the page, you'll see:
- **Total Delivered**: Total cameras in the delivered CSV
- **Total Installed**: Total cameras in the installed CSV
- **Total Physical Count**: Number of cameras you've physically verified
- **Total Missing**: Cameras that are delivered but not accounted for

**Note**: When filters are active, totals reflect only the filtered cameras.

## User Roles

### Admin
- Upload CSV files
- Generate stock lists
- Perform physical counts
- Add notes and locations
- Access past counts management
- Delete historical data

### User (Read-Only)
- View stock lists
- Use filters and search
- View totals
- Cannot upload files or modify data

## Tips & Best Practices

### 📌 Efficient Counting
1. Use filters to break down large lists into manageable sections
2. Filter by Location to count one area at a time
3. Use "Show Missing Only" to focus on problem items
4. Use barcode scanning for faster data entry on mobile devices

### 📌 Data Accuracy
1. Always upload both Delivered and Installed CSVs before generating
2. Bring forward notes from previous counts to maintain history
3. Add detailed notes for missing items to track investigation progress
4. Regularly save your work (or rely on auto-save)

### 📌 Reporting
1. Use the Past Counts dropdown to review historical data
2. Filter by PO to track specific orders
3. Export data by copying from the table (if needed)

## Technical Details

### CSV File Format
**Delivered Cameras CSV** should contain:
- Camera codes (6 characters starting with "CA")
- Camera type
- Purchase Order (PO)
- Delivery date
- Other relevant fields

**Installed Cameras CSV** should contain:
- Camera codes matching the delivered list

### Data Storage
- Files are stored in AWS S3 (cloud storage)
- Each date has its own state file
- Changes are saved automatically
- Historical data is preserved

### Browser Compatibility
- Works best on modern browsers (Chrome, Firefox, Safari, Edge)
- Mobile-optimized for iOS and Android
- Barcode scanning requires camera access permission

## Troubleshooting

### Button Won't Turn Green
- Ensure both CSV files are selected
- Check that files uploaded successfully (look for success message)
- Refresh the page and try again

### Totals Don't Match
- Check if filters are active (totals reflect filtered data)
- Ensure all data has loaded (wait for table to fully render)
- Verify CSV files contain correct data

### Barcode Scanner Not Working
- Grant camera permission when prompted
- Ensure good lighting on the barcode
- Hold camera steady and at correct distance
- Check that barcode is Code128 format

### Changes Not Saving
- Check internet connection
- Look for error messages in status area
- Try clicking "Save Changes" manually
- Refresh and check if changes persisted

## Support

For technical issues or questions:
- Contact your system administrator
- Check that you're using the latest version
- Report bugs with specific details (date, camera code, error message)

---

**Version**: 1.0
**Last Updated**: November 2025
**System**: Camera Stock Control for SAFR Cameras

