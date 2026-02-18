# 🎮 Demo Mode - Try Without MySQL

Want to see the system working immediately without installing MySQL? Use demo mode!

## Quick Demo (No Database Required)

I can create a demo version that uses sample data in JSON files instead of MySQL. This lets you:
- See the interface immediately
- Test the calculations
- Understand the workflow
- Decide if you want to proceed with full installation

## What Works in Demo Mode

✅ Login page (demo credentials)
✅ Dashboard with sample statistics
✅ View sample subscribers
✅ View sample invoices
✅ See prepayment calculations
✅ View reconciliation reports
✅ All UI features

❌ Cannot import real data
❌ Cannot save changes
❌ Data resets on refresh

## How to Run Demo Mode

```bash
cd subscription-system/public
php -S localhost:8000
```

Then open http://localhost:8000/demo.php

Login with:
- Username: demo
- Password: demo

## For Production Use

You'll need MySQL installed. See **INSTALL_MYSQL_MAC.md** for instructions.

The full system with MySQL gives you:
- Real data import from Xero
- Persistent storage
- Multi-user support
- Full audit trail
- Production-ready features

---

**Would you like me to create the demo mode?** It takes 5 minutes and you can see the system working right now!

