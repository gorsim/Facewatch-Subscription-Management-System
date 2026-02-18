#!/bin/bash
# MAMP Setup Script for Facewatch Subscription Management System

echo "🎯 Facewatch Subscription Management System - MAMP Setup"
echo "=========================================================="
echo ""

# MAMP MySQL path - try MySQL 8.0 first, then 5.7
if [ -f "/Applications/MAMP/Library/bin/mysql80/bin/mysql" ]; then
    MYSQL="/Applications/MAMP/Library/bin/mysql80/bin/mysql"
elif [ -f "/Applications/MAMP/Library/bin/mysql57/bin/mysql" ]; then
    MYSQL="/Applications/MAMP/Library/bin/mysql57/bin/mysql"
else
    echo "❌ MAMP MySQL not found"
    echo ""
    echo "Please ensure:"
    echo "1. MAMP is installed in /Applications/MAMP"
    echo "2. MAMP is running (both Apache and MySQL should be green)"
    echo ""
    exit 1
fi

echo "✓ MAMP MySQL found"
echo ""

DB_NAME="facewatch_subscriptions"
DB_USER="root"
DB_PASS="root"

# Create database
echo "📦 Creating database..."
$MYSQL -u "$DB_USER" -p"$DB_PASS" --socket=/Applications/MAMP/tmp/mysql/mysql.sock -e "DROP DATABASE IF EXISTS $DB_NAME; CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null

if [ $? -eq 0 ]; then
    echo "✓ Database created: $DB_NAME"
else
    echo "❌ Failed to create database. Is MAMP running?"
    exit 1
fi

# Run migrations
echo ""
echo "🔧 Running database migrations..."
$MYSQL -u "$DB_USER" -p"$DB_PASS" --socket=/Applications/MAMP/tmp/mysql/mysql.sock "$DB_NAME" < ../database/migrations/001_create_initial_schema.sql

if [ $? -eq 0 ]; then
    echo "✓ Schema created successfully"
else
    echo "❌ Failed to run migrations"
    exit 1
fi

# Load seed data
echo ""
echo "🌱 Loading initial data..."
$MYSQL -u "$DB_USER" -p"$DB_PASS" --socket=/Applications/MAMP/tmp/mysql/mysql.sock "$DB_NAME" < ../database/seeds/001_initial_data.sql 2>/dev/null

if [ $? -eq 0 ]; then
    echo "✓ Initial data loaded (pricing tiers, admin user)"
else
    echo "❌ Failed to load seed data"
    exit 1
fi

# Create uploads directory
mkdir -p uploads
chmod 755 uploads
echo "✓ Uploads directory created"

echo ""
echo "=========================================================="
echo "✅ Setup Complete!"
echo "=========================================================="
echo ""
echo "🚀 To start the application:"
echo "   cd public"
echo "   php -S localhost:8000"
echo ""
echo "🌐 Then open: http://localhost:8000"
echo ""
echo "🔐 Default login:"
echo "   Username: admin"
echo "   Password: changeme123"
echo ""
echo "⚠️  IMPORTANT: Change the default password after first login!"
echo ""
echo "📚 Next steps:"
echo "   1. Login to the system"
echo "   2. Import Xero invoices (Import Data → Xero Invoices)"
echo "   3. Import camera data (Import Data → Camera Data)"
echo "   4. View dashboard and reports"
echo ""

