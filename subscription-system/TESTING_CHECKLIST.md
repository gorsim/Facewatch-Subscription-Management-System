# 🧪 Testing Checklist

Use this checklist to verify the system is working correctly.

## ✅ Setup Verification

- [ ] Database created successfully
- [ ] Migrations ran without errors
- [ ] Seed data loaded (admin user, pricing tiers)
- [ ] `.env` file configured
- [ ] Server starts without errors
- [ ] Can access http://localhost:8000

## ✅ Authentication

- [ ] Login page displays correctly
- [ ] Can login with admin/changeme123
- [ ] Invalid credentials show error
- [ ] Logout works
- [ ] Redirects to login when not authenticated

## ✅ Dashboard

- [ ] Dashboard loads without errors
- [ ] Statistics cards display (subscribers, revenue, prepayments, unpaid)
- [ ] Quick actions buttons work
- [ ] Navigation menu works

## ✅ Import - Xero Invoices

### Test CSV Content
Create a test file `test_xero.csv`:
```csv
Contact Name,Invoice Number,Invoice Date,Due Date,Amount,Status,Payment Date
Test Customer 1,INV-001,2024-01-15,2024-02-15,12000.00,PAID,2024-02-10
Test Customer 2,INV-002,2024-01-20,2024-02-20,8500.00,UNPAID,
Test Customer 1,INV-003,2024-02-01,2024-03-01,12000.00,UNPAID,
```

### Tests
- [ ] Import page loads
- [ ] Can select CSV file
- [ ] Upload succeeds
- [ ] Shows "Successfully imported X invoices"
- [ ] Import history shows the import
- [ ] Subscribers created automatically
- [ ] Invoices appear in invoice list
- [ ] Re-importing same file skips duplicates

## ✅ Import - Camera Data

### Test CSV Content
Create a test file `test_cameras.csv`:
```csv
Customer Name,Main Cameras,Additional Cameras
Test Customer 1,45,5
Test Customer 2,30,0
```

### Tests
- [ ] Import page loads
- [ ] Can select month
- [ ] Can select CSV file
- [ ] Upload succeeds
- [ ] Shows "Successfully imported X records"
- [ ] Import history shows the import
- [ ] Camera counts saved correctly

## ✅ Subscribers Page

- [ ] Subscriber list loads
- [ ] Shows all imported subscribers
- [ ] Search works
- [ ] Displays camera counts
- [ ] Shows pricing rates
- [ ] Payment frequency displayed

## ✅ Invoices Page

- [ ] Invoice list loads
- [ ] Shows all imported invoices
- [ ] Filter by "All" works
- [ ] Filter by "Unpaid" works
- [ ] Filter by "Overdue" works
- [ ] Filter by "Paid" works
- [ ] Displays correct amounts
- [ ] Shows payment status badges

## ✅ Reports - Prepayments

- [ ] Report loads
- [ ] Shows current prepayment balances
- [ ] Calculations look correct
- [ ] Total prepayment displayed
- [ ] Days remaining calculated
- [ ] P&L recognized amount shown

### Manual Verification
For an annual invoice of £12,000 from 15 Jan 2024:
- Days in period: 365
- Days since invoice (as of today): Calculate manually
- Days remaining: 365 - days since
- Prepayment: £12,000 / 365 × days remaining
- P&L: £12,000 - prepayment

Check the report matches your calculation!

## ✅ Reports - Reconciliation

- [ ] Report loads
- [ ] Shows variances (if any)
- [ ] Actual vs expected amounts displayed
- [ ] Installation mismatches shown
- [ ] Status badges correct (under/over charged)

### Test Reconciliation
1. Import invoice for Test Customer 1: £12,000
2. Set invoice to have 50 main cameras, 0 additional
3. Import camera data: 45 main, 5 additional
4. Check reconciliation report shows:
   - Expected: 50 × rate + 0 × rate
   - Actual: £12,000
   - Variance: Difference
   - Installation mismatch: Yes (50 vs 45+5)

## ✅ Reports - Revenue

- [ ] Report loads
- [ ] Shows revenue by year
- [ ] Totals calculated correctly

## ✅ Prepayment Calculations

### Test Case 1: Annual Invoice
- Invoice: £12,000
- Date: 1 Jan 2024
- Frequency: Annual
- Check on 1 Jul 2024 (181 days later):
  - Days remaining: 365 - 181 = 184
  - Prepayment: £12,000 / 365 × 184 = £6,049.32
  - P&L: £12,000 - £6,049.32 = £5,950.68

### Test Case 2: Quarterly Invoice
- Invoice: £3,000
- Date: 1 Jan 2024
- Frequency: Quarterly
- Check on 1 Feb 2024 (31 days later):
  - Days remaining: 91.25 - 31 = 60.25
  - Prepayment: £3,000 / 91.25 × 60.25 = £1,981.92
  - P&L: £3,000 - £1,981.92 = £1,018.08

### Test Case 3: Monthly Invoice
- Invoice: £1,000
- Date: 1 Jan 2024
- Frequency: Monthly
- Check anytime:
  - Prepayment: £0 (goes straight to P&L)
  - P&L: £1,000

## ✅ Error Handling

- [ ] Invalid CSV format shows error
- [ ] Missing required fields shows error
- [ ] Database connection error handled
- [ ] File upload errors handled
- [ ] Non-existent subscriber in camera import shows error

## ✅ Security

- [ ] Can't access pages without login
- [ ] Password is hashed in database (not plain text)
- [ ] SQL injection prevented (try `' OR '1'='1` in search)
- [ ] Session expires on logout

## ✅ Performance

- [ ] Pages load quickly (< 2 seconds)
- [ ] Large CSV imports complete (100+ rows)
- [ ] Dashboard with many invoices loads
- [ ] Reports generate quickly

## 🐛 Common Issues & Solutions

**Import shows 0 imported:**
- Check CSV format matches examples
- Verify column headers are recognized
- Check for empty rows

**Prepayments show £0:**
- Ensure payment_frequency is set (not NULL)
- Check invoice dates are valid
- Verify invoice amounts are numeric

**Reconciliation shows no data:**
- Import both invoices AND camera data
- Ensure subscriber names match exactly
- Check dates align (camera data before/on invoice date)

**Can't login:**
- Verify seed data loaded: `mysql -u root -p facewatch_subscriptions -e "SELECT * FROM users;"`
- Check password is 'changeme123'
- Clear browser cache/cookies

## 📊 Success Criteria

The system is working correctly if:
- ✅ All imports complete successfully
- ✅ Dashboard shows accurate statistics
- ✅ Prepayment calculations match manual calculations
- ✅ Reconciliation detects variances correctly
- ✅ Reports display data accurately
- ✅ No PHP errors in browser console
- ✅ No MySQL errors in terminal

## 🎯 Next Steps After Testing

1. **Import real data** - Start with a small subset
2. **Verify calculations** - Compare with your Excel spreadsheet
3. **Adjust if needed** - Tweak formulas or logic
4. **Full import** - Load all historical data
5. **Production deployment** - Move to AWS when ready

---

**Happy Testing!** 🧪

If you find any issues, check the error messages carefully - they usually tell you exactly what's wrong!

