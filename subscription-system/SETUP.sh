#!/bin/bash
# Quick Setup Script for Facewatch Subscription Management System

echo "🎯 Facewatch Subscription Management System - Quick Setup"
echo "=========================================================="
echo ""

# Check if MySQL is available
if ! command -v mysql &> /dev/null; then
    echo "❌ MySQL not found. Please install MySQL first."
    exit 1
fi

echo "✓ MySQL found"
echo ""

# Get database credentials
read -p "MySQL username [root]: " DB_USER
DB_USER=${DB_USER:-root}

read -sp "MySQL password: " DB_PASS
echo ""
echo ""

DB_NAME="facewatch_subscriptions"

# Create database
echo "📦 Creating database..."
mysql -u "$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null

if [ $? -eq 0 ]; then
    echo "✓ Database created: $DB_NAME"
else
    echo "❌ Failed to create database. Check your credentials."
    exit 1
fi

# Run migrations
echo ""
echo "🔧 Running database migrations..."
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < database/migrations/001_create_initial_schema.sql 2>/dev/null

if [ $? -eq 0 ]; then
    echo "✓ Schema created successfully"
else
    echo "❌ Failed to run migrations"
    exit 1
fi

# Load seed data
echo ""
echo "🌱 Loading initial data..."
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < database/seeds/001_initial_data.sql 2>/dev/null

if [ $? -eq 0 ]; then
    echo "✓ Initial data loaded (pricing tiers, admin user)"
else
    echo "❌ Failed to load seed data"
    exit 1
fi

# Create .env file
echo ""
echo "⚙️  Creating configuration file..."
cat > .env << EOF
# Database Configuration
DB_HOST=localhost
DB_NAME=$DB_NAME
DB_USER=$DB_USER
DB_PASS=$DB_PASS

# Application Configuration
APP_ENV=development
APP_DEBUG=true
APP_TIMEZONE=Europe/London
EOF

echo "✓ Configuration file created (.env)"

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

