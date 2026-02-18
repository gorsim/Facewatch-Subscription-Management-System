# 🔧 MAMP Troubleshooting - MySQL Not Running

## The Issue

MAMP appears to be open, but MySQL isn't actually running yet.

## Solution: Start MySQL in MAMP

### Option 1: Using MAMP (Regular)

1. **Open MAMP** (the gray elephant icon)
2. Look for the **"Start Servers"** button
3. Click it and wait
4. You should see:
   - Apache Server: **Running** (green)
   - MySQL Server: **Running** (green)

### Option 2: Using MAMP PRO

1. **Open MAMP PRO**
2. Click **"Start"** in the top right
3. Wait for both servers to start
4. Both should show as **running**

## How to Verify MySQL is Running

Open Terminal and run:
```bash
ps aux | grep mysqld | grep -v grep
```

If MySQL is running, you'll see output like:
```
Simonnonroot  12345  ... /Applications/MAMP/Library/bin/mysqld ...
```

If you see nothing, MySQL isn't running.

## Common Issues

### "Servers won't start"

**Solution 1: Check ports**
- MAMP uses port 8889 for MySQL
- Make sure nothing else is using this port

**Solution 2: Reset MAMP**
1. Stop all servers in MAMP
2. Quit MAMP completely
3. Reopen MAMP
4. Click "Start Servers" again

**Solution 3: Check MAMP logs**
1. In MAMP, go to: File → View Logs → MySQL
2. Look for error messages
3. Common issues:
   - Port already in use
   - Permissions problem
   - Corrupted data files

### "I see MAMP but can't find Start button"

**MAMP (Regular):**
- Big "Start Servers" button in the main window

**MAMP PRO:**
- "Start" button in top right corner
- Or use the menu: File → Start Servers

## Alternative: Use Built-in MySQL Commands

If MAMP GUI isn't working, try starting MySQL manually:

```bash
/Applications/MAMP/Library/bin/mysql80/bin/mysqld_safe --port=8889 &
```

## Once MySQL is Running

Come back and run:
```bash
cd ~/intent/workspaces/system-create/repo/subscription-system
./SETUP_MAMP.sh
```

---

## Quick Checklist

- [ ] MAMP application is open
- [ ] Clicked "Start" or "Start Servers"
- [ ] Apache shows as running (green)
- [ ] MySQL shows as running (green)
- [ ] Verified with: `ps aux | grep mysqld`
- [ ] Ready to run setup script

---

**Still stuck?** Take a screenshot of your MAMP window and let me know what you see!

