# 🍎 Installing MySQL on Mac

## Option 1: Homebrew (Recommended - Easiest)

### Install Homebrew (if not already installed)
```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
```

### Install MySQL
```bash
brew install mysql
```

### Start MySQL
```bash
brew services start mysql
```

### Secure MySQL (set root password)
```bash
mysql_secure_installation
```

When prompted:
- Set root password: Choose a secure password
- Remove anonymous users: Yes
- Disallow root login remotely: Yes
- Remove test database: Yes
- Reload privilege tables: Yes

### Test Connection
```bash
mysql -u root -p
```

## Option 2: MySQL Installer (Official)

1. Download from: https://dev.mysql.com/downloads/mysql/
2. Choose "macOS DMG Archive"
3. Download and run the installer
4. Follow the installation wizard
5. Note the temporary root password shown
6. Start MySQL from System Preferences

## Option 3: MAMP (Easiest for Beginners)

MAMP includes MySQL, PHP, and Apache in one package.

1. Download from: https://www.mamp.info/en/downloads/
2. Install MAMP
3. Start MAMP
4. MySQL runs automatically on port 8889

**For MAMP, update your .env file:**
```
DB_HOST=localhost
DB_PORT=8889
DB_USER=root
DB_PASS=root
```

## After MySQL is Installed

### Run the Setup
```bash
cd subscription-system
./SETUP.sh
```

Or manually:

```bash
# Create database
mysql -u root -p -e "CREATE DATABASE facewatch_subscriptions CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Run migrations
mysql -u root -p facewatch_subscriptions < database/migrations/001_create_initial_schema.sql

# Load seed data
mysql -u root -p facewatch_subscriptions < database/seeds/001_initial_data.sql

# Create .env file
cp .env.example .env
# Edit .env with your MySQL password

# Start the server
cd public
php -S localhost:8000
```

## Troubleshooting

**"Command not found: mysql"**
- MySQL not in PATH
- For Homebrew: `brew link mysql`
- For official installer: Add to PATH: `export PATH="/usr/local/mysql/bin:$PATH"`

**"Access denied for user 'root'"**
- Wrong password
- Reset: `mysql_secure_installation`

**"Can't connect to MySQL server"**
- MySQL not running
- Start: `brew services start mysql` or use MAMP

## Quick Recommendation

**For beginners**: Use MAMP (easiest, includes everything)
**For developers**: Use Homebrew (most flexible)

---

Once MySQL is installed, come back and run `./SETUP.sh` or follow the manual steps above!

