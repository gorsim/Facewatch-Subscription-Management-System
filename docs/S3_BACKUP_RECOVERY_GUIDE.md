# Facewatch SAFR Camera Stock Control - S3 Backup & Recovery Guide

## Overview
The system automatically creates timestamped backups of all stock count data in AWS S3 for disaster recovery and audit purposes.

---

## Backup File Naming Convention

### Timestamped Backups
**Pattern:** `physical_state_YYYYMMDD_HHMMSS.json`

**Examples:**
- `physical_state_20260124_143022.json` → 24 Jan 2026, 14:30:22
- `physical_state_20260105_091545.json` → 5 Jan 2026, 09:15:45

### Other Files
- `physical_state_2026-01-24.json` → Current state for specific date
- `physical_state.json` → Most recent save (any date)
- `dates_index.json` → Index of all stocktake dates

---

## S3 Storage Location

**Path:** `s3://[BUCKET_NAME]/uploads/`

**Configuration:**
- **Region:** eu-west-2 (London)
- **Bucket:** Defined in `S3_BUCKET` environment variable
- **Prefix:** `uploads/`

---

## When Backups Are Created

Automatic backups are created on every:
- Manual save ("Save and Refresh" button)
- Auto-save (after data changes)
- CSV upload
- New stocktake creation

---

## Finding Backups in AWS Console

1. Navigate to [AWS S3 Console](https://s3.console.aws.amazon.com/s3/)
2. Select your bucket
3. Open `uploads/` folder
4. Filter by `physical_state_2026` to see all 2026 backups
5. Sort by **Last Modified** for most recent first

---

## AWS CLI Commands

### List All Backups
```bash
aws s3 ls s3://[BUCKET_NAME]/uploads/ | grep "physical_state_[0-9]"
```

### List Backups for Specific Date
```bash
# Example: January 24, 2026
aws s3 ls s3://[BUCKET_NAME]/uploads/ | grep "physical_state_20260124"
```

### Count Total Backups
```bash
aws s3 ls s3://[BUCKET_NAME]/uploads/ | grep -c "physical_state_[0-9]"
```

### Download Specific Backup
```bash
aws s3 cp s3://[BUCKET_NAME]/uploads/physical_state_20260124_143022.json ./backup.json
```

---

## Data Recovery Procedure

### Scenario: Restore Lost Physical Count Data

**Step 1: Identify the Backup**
- Determine the date and approximate time of the good data
- Construct filename: `physical_state_YYYYMMDD_HHMMSS.json`
- List available backups for that date using AWS CLI or Console

**Step 2: Download the Backup**
```bash
aws s3 cp s3://[BUCKET_NAME]/uploads/physical_state_20260124_143022.json ./recovery.json
```

**Step 3: Verify the Backup**
- Open `recovery.json` in a text editor
- Verify `physical_counts` field contains expected data
- Check `theoretical_stock` field is populated
- Confirm `date` and `description` fields are correct

**Step 4: Restore the Data**
```bash
# Replace the current state file with the backup
aws s3 cp ./recovery.json s3://[BUCKET_NAME]/uploads/physical_state_2026-01-24.json
```

**Step 5: Verify in Application**
- Refresh the browser
- Navigate to the restored date
- Verify all physical counts are correct

---

## Data Loss Prevention Features

The system includes multiple safeguards to prevent accidental data loss:

### Server-Side Protection
- Blocks saving if theoretical stock is empty (except new stocktakes)
- Prevents overwriting >50 counts with <10 counts
- Blocks >90% count reductions
- Validates physical counts vs theoretical stock

### Client-Side Warnings
- Pre-save validation checks existing data
- Warns before reducing counts by >50%
- Confirmation dialog shows count summary
- Detailed error messages with recovery guidance

### Automatic Backups
- Every save creates timestamped backup
- Backups retained indefinitely (manual cleanup required)
- Format: `physical_state_YYYYMMDD_HHMMSS.json`

---

## Backup Retention & Cleanup

### Current Policy
- Backups are retained indefinitely
- No automatic cleanup
- Manual cleanup required to manage storage costs

### Recommended Retention
- Keep all backups for current month
- Keep daily backups for previous 3 months
- Keep weekly backups for previous 12 months
- Archive older backups to Glacier for compliance

### Cleanup Script (Future Enhancement)
Contact DevOps team to implement automated cleanup script if needed.

---

## S3 Versioning (Recommended)

### Enable Versioning for Additional Protection
1. Navigate to S3 bucket in AWS Console
2. Go to **Properties** tab
3. Find **Bucket Versioning** section
4. Click **Edit** → Select **Enable**
5. Click **Save changes**

**Benefits:**
- Keeps all previous versions of every file
- Allows recovery from accidental overwrites
- Provides additional layer of protection beyond timestamped backups

---

## Troubleshooting

### Issue: Cannot Find Backup for Specific Time
**Solution:** Backups are created when saves occur. If no save happened at that time, no backup exists. Check surrounding times (±30 minutes).

### Issue: Backup File is Empty or Corrupted
**Solution:** Try the next most recent backup. Multiple backups exist for redundancy.

### Issue: AWS CLI Access Denied
**Solution:** Verify IAM credentials have `s3:GetObject` and `s3:PutObject` permissions for the bucket.

### Issue: Restored Data Not Appearing
**Solution:** 
1. Clear browser cache
2. Hard refresh (Ctrl+Shift+R or Cmd+Shift+R)
3. Verify correct date is selected in dropdown
4. Check browser console for errors

---

## Support Contacts

**Technical Issues:** DevOps Team  
**Data Recovery Requests:** System Administrator  
**AWS Access Issues:** IT Security Team

---

**Document Version:** 1.0  
**Last Updated:** January 2026  
**System:** Facewatch SAFR Camera Stock Control  
**Environment:** Production (eu-west-2)

