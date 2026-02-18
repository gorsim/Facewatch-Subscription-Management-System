# 🚀 Quick Start Guide

Get your Facewatch Subscription Management System running in 5 minutes!

## Option 1: Automated Setup (Recommended)

```bash
cd subscription-system
./SETUP.sh
```

The script will:
1. Create the database
2. Run migrations
3. Load initial data (pricing tiers, admin user)
4. Create configuration file
5. Set up directories

Then start the server:
```bash
cd public
php -S localhost:8000
```

## Option 2: Manual Setup

### Step 1: Create Database
```bash
mysql -u root -p
```

```sql
CREATE DATABASE facewatch_subscriptions CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
exit;
```

### Step 2: Run Migrations
```bash
mysql -u root -p facewatch_subscriptions < database/migrations/001_create_initial_schema.sql
mysql -u root -p facewatch_subscriptions < database/seeds/001_initial_data.sql
```

### Step 3: Configure
```bash
cp .env.example .env
```

Edit `.env` with your database credentials:
```
DB_HOST=localhost
DB_NAME=facewatch_subscriptions
DB_USER=root
DB_PASS=your_password
```

### Step 4: Create Uploads Directory
```bash
mkdir -p uploads
chmod 755 uploads
```

### Step 5: Start Server
```bash
cd public
php -S localhost:8000
```

## Access the System

1. Open browser: **http://localhost:8000**
2. Login with:
   - Username: `admin`
   - Password: `changeme123`

## First Tasks

### 1. Import Your Data

**Import Xero Invoices:**
1. Go to "Import Data" → "Xero Invoices"
2. Upload your Xero CSV export
3. System creates subscribers automatically

**Import Camera Data:**
1. Go to "Import Data" → "Camera Data"
2. Select the month
3. Upload camera counts CSV

### 2. Explore the Dashboard

- View total subscribers and revenue
- Check overdue invoices
- See reconciliation issues
- Monitor prepayment balances

### 3. Run Reports

- **Prepayments Report** - Current prepayment balances by subscriber
- **Reconciliation Report** - Invoice variances and installation mismatches
- **Revenue Report** - Historical revenue by year

## CSV Format Examples

### Xero Invoices CSV
```csv
Contact Name,Invoice Number,Invoice Date,Due Date,Amount,Status,Payment Date
Acme Corp,INV-001,2024-01-15,2024-02-15,12000.00,PAID,2024-02-10
Beta Ltd,INV-002,2024-01-20,2024-02-20,8500.00,UNPAID,
```

### Camera Data CSV
```csv
Customer Name,Main Cameras,Additional Cameras
Acme Corp,45,5
Beta Ltd,30,0
```

## Troubleshooting

**Database connection error?**
- Check `.env` file has correct credentials
- Ensure MySQL is running: `mysql.server status`
- Verify database exists: `mysql -u root -p -e "SHOW DATABASES;"`

**Can't upload files?**
- Check `uploads/` directory exists
- Verify permissions: `chmod 755 uploads`

**Import fails?**
- Check CSV format matches examples above
- Ensure subscriber names match exactly
- View error details in Import History table

## What's Included

✅ Complete database schema (15 tables)
✅ Subscriber management
✅ Invoice tracking with Xero import
✅ Prepayment calculations (annual/quarterly/monthly)
✅ Camera installation tracking
✅ Reconciliation engine
✅ Dashboard with statistics
✅ Multiple reports
✅ User authentication

## Next Steps

1. **Change default password** - Go to user settings
2. **Import your historical data** - Xero invoices and camera counts
3. **Review reconciliation** - Check for any variances
4. **Set up regular imports** - Weekly/monthly data updates
5. **Customize pricing** - Update pricing tiers if needed

## Need Help?

- Check `README.md` for detailed documentation
- Review database schema in `database/migrations/`
- Examine example data in `database/seeds/`

---

**System Version:** 1.0  
**Built for:** Facewatch Ltd  
**Support:** Your development team

