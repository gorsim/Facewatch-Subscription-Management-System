# 🚀 MAMP Setup Guide - Super Easy!

## Step-by-Step Instructions

### 1️⃣ Download MAMP (2 minutes)

1. Go to: **https://www.mamp.info/en/downloads/**
2. Click the big **"Download"** button
3. Choose **MAMP** (free version, not MAMP PRO)
4. Download for macOS

### 2️⃣ Install MAMP (2 minutes)

1. Open the downloaded file (MAMP_MAMP_PRO_xxx.pkg)
2. Click **"Continue"** through the installer
3. Click **"Install"**
4. Enter your Mac password when prompted
5. Click **"Close"** when done

### 3️⃣ Start MAMP (30 seconds)

1. Open **MAMP** from Applications folder
2. Click the big **"Start"** button
3. Wait for the lights to turn green:
   - ✅ Apache Server (green)
   - ✅ MySQL Server (green)
4. A webpage will open automatically (you can close it)

**IMPORTANT**: Keep MAMP running! Don't close it.

### 4️⃣ Run the Setup Script (1 minute)

Open Terminal and run:

```bash
cd ~/intent/workspaces/system-create/repo/subscription-system
./SETUP_MAMP.sh
```

You should see:
```
✓ Database created
✓ Schema created successfully
✓ Initial data loaded
✅ Setup Complete!
```

### 5️⃣ Start the Application (30 seconds)

In the same Terminal window:

```bash
cd public
php -S localhost:8000
```

You should see:
```
PHP 8.x Development Server (http://localhost:8000) started
```

**Keep this Terminal window open!**

### 6️⃣ Open in Browser

1. Open your web browser
2. Go to: **http://localhost:8000**
3. You should see the login page!

**Login with:**
- Username: `admin`
- Password: `changeme123`

---

## 🎉 Success!

You should now see the Facewatch Subscription Management System dashboard!

---

## 📊 What You Can Do Now

1. **Explore the Dashboard** - See statistics and alerts
2. **View Subscribers** - Click "Subscribers" in the menu
3. **Import Test Data** - Try importing a sample CSV
4. **View Reports** - Check out the prepayment and reconciliation reports

---

## 🐛 Troubleshooting

### "Can't connect to database"

**Solution**: Make sure MAMP is running
1. Open MAMP application
2. Click "Start" if it's not running
3. Both Apache and MySQL should be green

### "Setup script fails"

**Solution**: Check MAMP is installed correctly
1. Look for `/Applications/MAMP` folder
2. Make sure MAMP is running
3. Try running the script again

### "Port 8000 already in use"

**Solution**: Use a different port
```bash
php -S localhost:8001
```
Then open: http://localhost:8001

### "Page not found"

**Solution**: Make sure you're in the right directory
```bash
cd ~/intent/workspaces/system-create/repo/subscription-system/public
php -S localhost:8000
```

---

## 🔄 Stopping and Starting

### To Stop:
1. Press `Ctrl+C` in the Terminal (stops PHP server)
2. Click "Stop" in MAMP (stops MySQL)

### To Start Again:
1. Open MAMP and click "Start"
2. Run: `cd ~/intent/workspaces/system-create/repo/subscription-system/public`
3. Run: `php -S localhost:8000`
4. Open: http://localhost:8000

---

## 📝 Quick Reference

**MAMP MySQL Details:**
- Host: `localhost`
- Port: `8889`
- Username: `root`
- Password: `root`

**Application URL:**
- http://localhost:8000

**Default Login:**
- Username: `admin`
- Password: `changeme123`

**Database Name:**
- `facewatch_subscriptions`

---

## ✅ Checklist

- [ ] MAMP downloaded and installed
- [ ] MAMP started (green lights)
- [ ] Setup script ran successfully
- [ ] PHP server started
- [ ] Opened http://localhost:8000
- [ ] Logged in successfully
- [ ] Saw the dashboard

---

**Need help?** Let me know which step you're stuck on!

