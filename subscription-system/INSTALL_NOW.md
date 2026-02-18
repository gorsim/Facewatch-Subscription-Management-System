# 🚀 Install MySQL Now - Step by Step

## Step 1: Fix Homebrew Permissions

Run this command in your terminal (it will ask for your password):

```bash
sudo chown -R Simonnonroot /opt/homebrew
```

## Step 2: Install MySQL

```bash
brew install mysql
```

This will take 2-3 minutes.

## Step 3: Start MySQL

```bash
brew services start mysql
```

## Step 4: Secure MySQL (Optional but Recommended)

```bash
mysql_secure_installation
```

When prompted:
- Would you like to setup VALIDATE PASSWORD component? **No** (press N)
- New password: **Choose a password** (remember this!)
- Re-enter new password: **Same password**
- Remove anonymous users? **Yes** (Y)
- Disallow root login remotely? **Yes** (Y)
- Remove test database? **Yes** (Y)
- Reload privilege tables? **Yes** (Y)

## Step 5: Run the Setup Script

```bash
cd subscription-system
./SETUP.sh
```

When prompted:
- MySQL username: **root**
- MySQL password: **[the password you just set]**

## Step 6: Start the Application

```bash
cd public
php -S localhost:8000
```

## Step 7: Open in Browser

Open: **http://localhost:8000**

Login with:
- Username: **admin**
- Password: **changeme123**

---

## 🎉 That's It!

You should now see the Facewatch Subscription Management System dashboard!

## Troubleshooting

**"Access denied for user 'root'"**
- You entered the wrong password
- Try running `mysql_secure_installation` again

**"Can't connect to MySQL server"**
- MySQL isn't running
- Run: `brew services start mysql`

**"Command not found: mysql"**
- MySQL didn't install properly
- Try: `brew reinstall mysql`

---

## Alternative: Use MAMP (Easier)

If you're having trouble with Homebrew, MAMP is easier:

1. Download from: https://www.mamp.info/en/downloads/
2. Install and start MAMP
3. MySQL runs automatically (no password needed)
4. Update `.env` file:
   ```
   DB_HOST=localhost
   DB_PORT=8889
   DB_USER=root
   DB_PASS=root
   ```
5. Run the setup script

---

**Need help?** Let me know which step you're stuck on!

