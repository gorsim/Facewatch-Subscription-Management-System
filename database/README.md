# Database Setup Guide

## Overview
This directory contains the database schema and seed data for the Facewatch Subscription Management System.

## Prerequisites
- MySQL 8.0+ or MariaDB 10.5+
- Database user with CREATE, INSERT, UPDATE, DELETE privileges

## Quick Start

### 1. Create Database
```bash
mysql -u root -p
```

```sql
CREATE DATABASE facewatch_subscriptions CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'facewatch_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON facewatch_subscriptions.* TO 'facewatch_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 2. Run Migrations
```bash
mysql -u facewatch_user -p facewatch_subscriptions < migrations/001_create_initial_schema.sql
```

### 3. Load Seed Data
```bash
mysql -u facewatch_user -p facewatch_subscriptions < seeds/001_initial_data.sql
```

### 4. Verify Installation
```bash
mysql -u facewatch_user -p facewatch_subscriptions
```

```sql
-- Check tables were created
SHOW TABLES;

-- Check admin user was created
SELECT id, username, email, role FROM users;

-- Check pricing tiers were loaded
SELECT effective_date, tier_name, annual_price_per_camera 
FROM pricing_tiers 
ORDER BY effective_date, min_cameras;

-- Check inflation rates
SELECT * FROM inflation_rates ORDER BY effective_date;
```

## Database Structure

### Core Tables (12 total)

**Master Data:**
- `users` - System users and authentication
- `subscribers` - Customer master data
- `subscriber_contracts` - Contract terms and pricing
- `pricing_tiers` - Volume discount tiers (with history)
- `inflation_rates` - Annual inflation schedule

**Operational Data:**
- `camera_counts` - Monthly camera installation tracking
- `invoices` - Invoice data from Xero
- `prepayments` - Monthly prepayment calculations
- `cash_flow_forecast` - Expected payment predictions

**Reconciliation & Audit:**
- `reconciliation_log` - Invoice vs expected amount checks
- `camera_imports` - Camera data import history
- `xero_imports` - Xero data import history
- `audit_log` - Complete change tracking
- `user_sessions` - Active user sessions
- `user_activity_log` - User action tracking

## Default Admin Account

**Username:** `admin`  
**Email:** `simon.gordon@facewatch.co.uk`  
**Password:** `changeme123`

⚠️ **IMPORTANT:** Change this password immediately after first login!

## Configuration

### For Development (Mac)
```bash
# In your .env file or config
DB_HOST=localhost
DB_PORT=3306
DB_NAME=facewatch_subscriptions
DB_USER=facewatch_user
DB_PASS=your_secure_password
```

### For Production (AWS)
```bash
# In your .env file or config
DB_HOST=your-rds-endpoint.amazonaws.com
DB_PORT=3306
DB_NAME=facewatch_subscriptions
DB_USER=facewatch_user
DB_PASS=your_secure_password
DB_SSL=true
```

## Backup & Restore

### Backup
```bash
mysqldump -u facewatch_user -p facewatch_subscriptions > backup_$(date +%Y%m%d).sql
```

### Restore
```bash
mysql -u facewatch_user -p facewatch_subscriptions < backup_20260213.sql
```

## Troubleshooting

### Error: "Access denied for user"
- Check username and password
- Verify user has correct privileges
- Check host (localhost vs %)

### Error: "Unknown database"
- Make sure you created the database first
- Check database name spelling

### Error: "Table already exists"
- Database already initialized
- Drop and recreate database if starting fresh:
  ```sql
  DROP DATABASE facewatch_subscriptions;
  CREATE DATABASE facewatch_subscriptions CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  ```

## Next Steps

After database setup:
1. Configure PHP application database connection
2. Test connection with a simple PHP script
3. Import initial customer data from spreadsheet
4. Import Xero invoice data
5. Import camera installation data

