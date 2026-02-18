# 🎯 Quick Command Reference

## Start the Application

```bash
# Start the web server
cd subscription-system/public
/Applications/MAMP/bin/php/php8.3.30/bin/php -S localhost:8000
```

Then open: **http://localhost:8000**

---

## Stop the Application

Press `Ctrl+C` in the terminal where the server is running

---

## Reset the Database

```bash
cd subscription-system
./SETUP_MAMP.sh
```

This will:
- Drop and recreate the database
- Run all migrations
- Load initial data (pricing tiers, admin user)

---

## Check if MAMP MySQL is Running

```bash
ps aux | grep mysqld | grep -v grep
```

Should show MySQL running on port 8889

---

## Access MySQL Directly

```bash
/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot --socket=/Applications/MAMP/tmp/mysql/mysql.sock facewatch_subscriptions
```

---

## View Database Tables

```bash
/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot --socket=/Applications/MAMP/tmp/mysql/mysql.sock facewatch_subscriptions -e "SHOW TABLES;"
```

---

## Export Database Backup

```bash
/Applications/MAMP/Library/bin/mysql80/bin/mysqldump -u root -proot --socket=/Applications/MAMP/tmp/mysql/mysql.sock facewatch_subscriptions > backup_$(date +%Y%m%d).sql
```

---

## Import Database Backup

```bash
/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot --socket=/Applications/MAMP/tmp/mysql/mysql.sock facewatch_subscriptions < backup_20260214.sql
```

---

## Default Credentials

- **Username:** admin
- **Password:** changeme123

⚠️ Change this after first login!

---

## File Locations

- **Application:** `subscription-system/`
- **Database Migrations:** `database/migrations/`
- **Database Seeds:** `database/seeds/`
- **Uploads:** `subscription-system/uploads/`
- **Config:** `subscription-system/.env`

---

## Useful Paths

- **MAMP MySQL:** `/Applications/MAMP/Library/bin/mysql80/bin/mysql`
- **MAMP PHP:** `/Applications/MAMP/bin/php/php8.3.30/bin/php`
- **MySQL Socket:** `/Applications/MAMP/tmp/mysql/mysql.sock`
- **MySQL Port:** 8889

---

*Quick reference for Facewatch Subscription Management System*

