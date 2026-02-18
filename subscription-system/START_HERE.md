# 🚀 START HERE - Quick Setup with MAMP

You have MAMP installed! Here's what to do next:

## Step 1: Start MAMP (30 seconds)

1. **Open MAMP** from your Applications folder
   - Look for the gray elephant icon
   - You can use either "MAMP" or "MAMP PRO" - both work!

2. **Click "Start Servers"** or "Start"
   - Wait for the servers to start
   - You should see green lights or "Running" status for:
     - ✅ Apache
     - ✅ MySQL

3. **Keep MAMP running** - Don't close it!

## Step 2: Run the Setup Script (1 minute)

Open **Terminal** and copy/paste these commands:

```bash
cd ~/intent/workspaces/system-create/repo/subscription-system
./SETUP_MAMP.sh
```

You should see:
```
✓ MAMP MySQL found
✓ Database created: facewatch_subscriptions
✓ Schema created successfully
✓ Initial data loaded
✅ Setup Complete!
```

## Step 3: Start the Application (30 seconds)

In the same Terminal window:

```bash
cd public
php -S localhost:8000
```

You should see:
```
PHP Development Server started
```

**Keep this Terminal window open!**

## Step 4: Open in Browser

1. Open your web browser (Chrome, Safari, etc.)
2. Go to: **http://localhost:8000**
3. You should see a login page!

**Login with:**
- Username: `admin`
- Password: `changeme123`

---

## 🎉 That's It!

You should now see the Facewatch Subscription Management System dashboard!

---

## 🐛 Troubleshooting

### "MAMP MySQL not found"
**Solution:** Make sure MAMP is running
1. Open MAMP application
2. Click "Start" or "Start Servers"
3. Wait for green lights
4. Try the setup script again

### "Can't connect to database"
**Solution:** Check MAMP is running
- Both Apache and MySQL should be green/running
- Try stopping and starting MAMP again

### "Setup script permission denied"
**Solution:** Make it executable
```bash
chmod +x ~/intent/workspaces/system-create/repo/subscription-system/SETUP_MAMP.sh
```

### "Port 8000 already in use"
**Solution:** Use a different port
```bash
php -S localhost:8001
```
Then open: http://localhost:8001

---

## 📝 Quick Commands Reference

**Start MAMP:**
- Open MAMP app → Click "Start"

**Run Setup:**
```bash
cd ~/intent/workspaces/system-create/repo/subscription-system
./SETUP_MAMP.sh
```

**Start Application:**
```bash
cd ~/intent/workspaces/system-create/repo/subscription-system/public
php -S localhost:8000
```

**Open Application:**
- http://localhost:8000

---

## ✅ Checklist

- [ ] MAMP is running (green lights)
- [ ] Setup script completed successfully
- [ ] PHP server started (Terminal shows "Development Server started")
- [ ] Opened http://localhost:8000 in browser
- [ ] Saw the login page
- [ ] Logged in with admin/changeme123
- [ ] Saw the dashboard

---

**Ready?** Start MAMP now and let me know when it's running!

