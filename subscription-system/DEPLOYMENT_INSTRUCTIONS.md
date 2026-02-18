# Facewatch Subscription Management System - AWS Deployment Instructions

## Overview
This document provides step-by-step instructions for deploying the Facewatch Subscription Management System to AWS.

## Prerequisites
- AWS Account with appropriate permissions
- GitHub repository access: https://github.com/gorsim/Facewatch-Subscription-Management-System.git
- Branch to deploy: `subscription-management-system`

## System Requirements

### Server Specifications
- **Recommended**: AWS EC2 t3.medium or larger
- **OS**: Ubuntu 22.04 LTS or Amazon Linux 2023
- **PHP**: 8.1 or higher
- **Database**: MySQL 8.0 or AWS RDS MySQL
- **Web Server**: Apache 2.4 or Nginx

### Required PHP Extensions
```bash
php-mysql
php-mbstring
php-xml
php-curl
php-zip
php-gd
php-intl
```

## Deployment Steps

### 1. Set Up EC2 Instance

```bash
# Launch EC2 instance (t3.medium recommended)
# - AMI: Ubuntu 22.04 LTS
# - Security Group: Allow HTTP (80), HTTPS (443), SSH (22)
# - Storage: 20GB minimum
```

### 2. Install Required Software

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Apache
sudo apt install apache2 -y

# Install PHP 8.1 and extensions
sudo apt install php8.1 php8.1-mysql php8.1-mbstring php8.1-xml \
  php8.1-curl php8.1-zip php8.1-gd php8.1-intl libapache2-mod-php8.1 -y

# Install MySQL client (if using RDS)
sudo apt install mysql-client -y

# Install Git
sudo apt install git -y
```

### 3. Set Up Database

**Option A: AWS RDS (Recommended for Production)**
```bash
# Create RDS MySQL instance via AWS Console:
# - Engine: MySQL 8.0
# - Instance class: db.t3.micro (minimum)
# - Storage: 20GB
# - Enable automated backups
# - Note the endpoint, username, and password
```

**Option B: Local MySQL**
```bash
sudo apt install mysql-server -y
sudo mysql_secure_installation
```

### 4. Clone Repository

```bash
# Navigate to web root
cd /var/www

# Clone repository
sudo git clone https://github.com/gorsim/Facewatch-Subscription-Management-System.git facewatch-subscription

# Switch to deployment branch
cd facewatch-subscription
sudo git checkout subscription-management-system

# Set permissions
sudo chown -R www-data:www-data /var/www/facewatch-subscription
sudo chmod -R 755 /var/www/facewatch-subscription
```

### 5. Configure Database Connection

```bash
# Edit database configuration
sudo nano /var/www/facewatch-subscription/subscription-system/app/Database.php
```

Update the database credentials:
```php
private static $host = 'your-rds-endpoint.amazonaws.com'; // or 'localhost'
private static $dbname = 'facewatch_subscription';
private static $username = 'your_db_username';
private static $password = 'your_db_password';
```

### 6. Create Database and Import Schema

```bash
# Connect to MySQL
mysql -h your-rds-endpoint.amazonaws.com -u your_username -p

# Or for local MySQL:
# sudo mysql
```

```sql
-- Create database
CREATE DATABASE facewatch_subscription CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Exit MySQL
EXIT;
```

```bash
# Import all migrations in order
cd /var/www/facewatch-subscription/subscription-system/database/migrations

# Run migrations in order (001 through 031)
for file in $(ls -v *.sql); do
    echo "Running migration: $file"
    mysql -h your-rds-endpoint.amazonaws.com -u your_username -p facewatch_subscription < $file
done
```

### 7. Configure Apache Virtual Host

```bash
# Create virtual host configuration
sudo nano /etc/apache2/sites-available/facewatch-subscription.conf
```

Add the following configuration:
```apache
<VirtualHost *:80>
    ServerName your-domain.com
    ServerAlias www.your-domain.com

    DocumentRoot /var/www/facewatch-subscription/subscription-system/public

    <Directory /var/www/facewatch-subscription/subscription-system/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/facewatch-error.log
    CustomLog ${APACHE_LOG_DIR}/facewatch-access.log combined
</VirtualHost>
```

```bash
# Enable the site and required modules
sudo a2ensite facewatch-subscription.conf
sudo a2enmod rewrite
sudo a2dissite 000-default.conf

# Restart Apache
sudo systemctl restart apache2
```

### 8. Set Up SSL Certificate (Production)

```bash
# Install Certbot
sudo apt install certbot python3-certbot-apache -y

# Obtain SSL certificate
sudo certbot --apache -d your-domain.com -d www.your-domain.com

# Auto-renewal is configured automatically
```

### 9. Configure File Permissions

```bash
# Set correct ownership
sudo chown -R www-data:www-data /var/www/facewatch-subscription

# Set directory permissions
sudo find /var/www/facewatch-subscription -type d -exec chmod 755 {} \;

# Set file permissions
sudo find /var/www/facewatch-subscription -type f -exec chmod 644 {} \;
```

### 10. Configure PHP Settings

```bash
# Edit PHP configuration
sudo nano /etc/php/8.1/apache2/php.ini
```

Update these settings:
```ini
upload_max_filesize = 10M
post_max_size = 10M
memory_limit = 256M
max_execution_time = 300
date.timezone = Europe/London
```

```bash
# Restart Apache to apply changes
sudo systemctl restart apache2
```

### 11. Set Up Automated Backups

**Database Backups**
```bash
# Create backup script
sudo nano /usr/local/bin/backup-facewatch-db.sh
```

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/facewatch"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="facewatch_subscription"
DB_USER="your_username"
DB_PASS="your_password"
DB_HOST="your-rds-endpoint.amazonaws.com"

mkdir -p $BACKUP_DIR

mysqldump -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/facewatch_$DATE.sql.gz

# Keep only last 30 days of backups
find $BACKUP_DIR -name "facewatch_*.sql.gz" -mtime +30 -delete
```

```bash
# Make executable
sudo chmod +x /usr/local/bin/backup-facewatch-db.sh

# Add to crontab (daily at 2 AM)
sudo crontab -e
# Add this line:
0 2 * * * /usr/local/bin/backup-facewatch-db.sh
```

**Optional: Sync backups to S3**
```bash
# Install AWS CLI
sudo apt install awscli -y

# Configure AWS credentials
aws configure

# Add to backup script:
aws s3 sync /var/backups/facewatch s3://your-backup-bucket/facewatch-db/
```

### 12. Configure Monitoring (Optional but Recommended)

**CloudWatch Logs**
```bash
# Install CloudWatch agent
wget https://s3.amazonaws.com/amazoncloudwatch-agent/ubuntu/amd64/latest/amazon-cloudwatch-agent.deb
sudo dpkg -i amazon-cloudwatch-agent.deb

# Configure to monitor Apache logs
sudo /opt/aws/amazon-cloudwatch-agent/bin/amazon-cloudwatch-agent-config-wizard
```

**Application Health Check**
```bash
# Create health check endpoint
sudo nano /var/www/facewatch-subscription/subscription-system/public/health.php
```

```php
<?php
header('Content-Type: application/json');
echo json_encode(['status' => 'healthy', 'timestamp' => date('c')]);
```

### 13. Security Hardening

```bash
# Install and configure firewall
sudo apt install ufw -y
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable

# Disable directory listing (already in Apache config)
# Hide PHP version
sudo nano /etc/php/8.1/apache2/php.ini
# Set: expose_php = Off

# Restart Apache
sudo systemctl restart apache2
```

### 14. Post-Deployment Verification

**Test the application:**
1. Navigate to `http://your-domain.com` (or `https://` if SSL configured)
2. Verify the login page loads
3. Test database connectivity by logging in
4. Check that all pages load correctly
5. Test CSV import functionality
6. Verify invoice generation works
7. Check reports display correctly

**Check logs for errors:**
```bash
# Apache error log
sudo tail -f /var/log/apache2/facewatch-error.log

# Apache access log
sudo tail -f /var/log/apache2/facewatch-access.log

# PHP error log
sudo tail -f /var/log/apache2/error.log
```

### 15. Set Up Deployment Updates

**Create update script:**
```bash
sudo nano /usr/local/bin/update-facewatch.sh
```

```bash
#!/bin/bash
cd /var/www/facewatch-subscription

# Pull latest changes
sudo -u www-data git fetch origin
sudo -u www-data git checkout subscription-management-system
sudo -u www-data git pull origin subscription-management-system

# Run any new migrations
cd subscription-system/database/migrations
for file in $(ls -v *.sql); do
    echo "Checking migration: $file"
    # Add logic to track which migrations have been run
done

# Clear any caches if needed
# (Add cache clearing commands here if you implement caching)

# Restart Apache
sudo systemctl restart apache2

echo "Deployment updated successfully!"
```

```bash
# Make executable
sudo chmod +x /usr/local/bin/update-facewatch.sh
```

**To deploy updates:**
```bash
sudo /usr/local/bin/update-facewatch.sh
```

## Environment-Specific Configuration

### Production Environment
- Use AWS RDS for database
- Enable SSL/HTTPS
- Configure CloudWatch monitoring
- Set up automated backups to S3
- Use Elastic Load Balancer if scaling needed
- Consider using AWS Secrets Manager for credentials

### Staging Environment
- Can use smaller EC2 instance (t3.small)
- Can use local MySQL instead of RDS
- Use separate database from production
- Clone production data periodically for testing

## Troubleshooting

### Common Issues

**1. Database Connection Failed**
```bash
# Check database credentials in Database.php
# Verify RDS security group allows EC2 instance
# Test connection manually:
mysql -h your-rds-endpoint.amazonaws.com -u username -p
```

**2. Permission Denied Errors**
```bash
# Reset permissions
sudo chown -R www-data:www-data /var/www/facewatch-subscription
sudo chmod -R 755 /var/www/facewatch-subscription
```

**3. Apache Not Starting**
```bash
# Check configuration syntax
sudo apache2ctl configtest

# Check error logs
sudo tail -f /var/log/apache2/error.log
```

**4. CSV Import Not Working**
```bash
# Check upload directory permissions
sudo chmod 755 /var/www/facewatch-subscription/subscription-system/public/uploads
sudo chown www-data:www-data /var/www/facewatch-subscription/subscription-system/public/uploads
```

## Support Contacts

- **Repository**: https://github.com/gorsim/Facewatch-Subscription-Management-System
- **Branch**: subscription-management-system
- **Developer**: Simon Gordon (simon.gordon@facewatch.co.uk)

## Rollback Procedure

If deployment fails:
```bash
cd /var/www/facewatch-subscription
sudo -u www-data git log --oneline -10  # Find previous commit
sudo -u www-data git checkout <previous-commit-hash>
sudo systemctl restart apache2
```

## Additional Notes

- The system uses PHP sessions - ensure session directory is writable
- CSV imports are stored in `public/uploads/` - ensure this directory exists and is writable
- Database migrations are in `database/migrations/` - run in numerical order
- The system includes 31 migrations (001 through 031) that must all be run
- Latest features include SAFR Code tracking and improved prepayment calculations

---

**Last Updated**: February 2026
**Version**: 1.0
**Deployment Branch**: subscription-management-system


