# Facewatch Subscription Management System

A PHP-based web application for managing customer subscriptions, invoicing, prepayments, and camera license tracking.

## Features

✅ **Subscriber Management** - Track all customers with contracts and pricing
✅ **Invoice Tracking** - Import from Xero, track payment status
✅ **Prepayment Calculations** - Automatic calculation using your exact formulas
✅ **Camera Installation Tracking** - Monitor invoiced vs installed cameras
✅ **Reconciliation** - Automatic variance detection
✅ **Reports** - Prepayments, cash flow, reconciliation, revenue
✅ **CSV Import** - Import Xero invoices and camera data
✅ **Dashboard** - Real-time statistics and alerts

## Requirements

- PHP 7.4 or higher
- MySQL 8.0 or higher
- Web server (Apache/Nginx) or PHP built-in server

## Quick Setup (5 minutes)

### 1. Database Setup

```bash
# Create database
mysql -u root -p -e "CREATE DATABASE facewatch_subscriptions CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Run migrations
mysql -u root -p facewatch_subscriptions < database/migrations/001_create_initial_schema.sql

# Load initial data (pricing tiers, admin user)
mysql -u root -p facewatch_subscriptions < database/seeds/001_initial_data.sql
```

### 2. Configuration

Copy the example config and update with your database credentials:

```bash
cp .env.example .env
```

Edit `.env`:
```
DB_HOST=localhost
DB_NAME=facewatch_subscriptions
DB_USER=root
DB_PASS=your_password
```

### 3. Start the Server

```bash
cd subscription-system/public
php -S localhost:8000
```

### 4. Login

Open http://localhost:8000 in your browser

**Default credentials:**
- Username: `admin`
- Password: `changeme123`

## First Steps

1. **Import Xero Invoices**
   - Go to Import Data → Xero Invoices
   - Upload your Xero CSV export
   - System will create subscribers automatically

2. **Import Camera Data**
   - Go to Import Data → Camera Data
   - Select the month
   - Upload camera counts CSV

3. **View Dashboard**
   - See statistics, overdue invoices, reconciliation issues
   - Monitor prepayment balances

4. **Run Reports**
   - Prepayments Report - Current balances
   - Reconciliation Report - Variances and mismatches
   - Revenue Report - Historical revenue

## CSV Import Formats

### Xero Invoices CSV
Required columns:
- Contact Name
- Invoice Number
- Invoice Date
- Due Date
- Amount
- Status (PAID/UNPAID)
- Payment Date (optional)

### Camera Data CSV
Required columns:
- Customer Name (must match subscriber name)
- Number of Cameras (or Main Cameras)
- Additional Cameras (optional)

## Database Schema

15 tables including:
- `subscribers` - Customer information
- `subscriber_contracts` - Pricing and terms
- `invoices` - Invoice records
- `camera_counts` - Monthly installation data
- `prepayments` - Calculated prepayment balances
- `reconciliation_log` - Variance tracking
- `pricing_tiers` - Tiered pricing by camera count
- `inflation_rates` - Annual inflation rates

## Business Logic

### Prepayment Calculation
```
Annual: Value / 365 × (365 - days since invoice)
Quarterly: Value / 91.25 × (91.25 - days since invoice)
Monthly: Goes straight to P&L (no prepayment)
```

### Reconciliation
Checks:
- Invoice amount vs (cameras × rate)
- Invoiced cameras vs installed cameras
- Flags: under_charged, over_charged, installation_mismatch

## File Structure

```
subscription-system/
├── app/
│   ├── Database.php           # Database connection
│   ├── Models/                # Data models
│   │   ├── Model.php
│   │   ├── Subscriber.php
│   │   └── Invoice.php
│   └── Services/              # Business logic
│       ├── PrepaymentCalculator.php
│       ├── XeroImporter.php
│       ├── CameraImporter.php
│       └── ReconciliationService.php
├── config/                    # Configuration
├── database/
│   ├── migrations/            # Database schema
│   └── seeds/                 # Initial data
├── public/
│   └── index.php             # Main entry point
├── uploads/                   # CSV uploads (auto-created)
└── views/                     # HTML templates
    ├── auth/
    ├── dashboard/
    ├── customers/
    ├── invoices/
    ├── imports/
    ├── reports/
    └── layouts/
```

## Security

- ✅ Password hashing with bcrypt
- ✅ Session-based authentication
- ✅ SQL injection prevention (prepared statements)
- ✅ Role-based access (admin, manager, viewer)
- ⚠️ Change default password on first login!

## Troubleshooting

**Can't connect to database?**
- Check `.env` credentials
- Ensure MySQL is running
- Verify database exists

**Import fails?**
- Check CSV format matches expected columns
- Ensure subscriber names match exactly
- Check error messages in import history

**Prepayments not calculating?**
- Ensure invoices have payment_frequency set
- Check invoice dates are valid
- Verify invoice amounts are numeric

## Next Steps / Enhancements

- [ ] User management interface
- [ ] Export reports to CSV/PDF
- [ ] Email notifications for overdue invoices
- [ ] Bulk prepayment recalculation
- [ ] API for integrations
- [ ] Automated Xero sync

## Support

For issues or questions, contact your development team.

---

**Version:** 1.0  
**Built for:** Facewatch Ltd  
**Date:** February 2026

