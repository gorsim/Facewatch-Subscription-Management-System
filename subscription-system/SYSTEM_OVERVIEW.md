# 📊 Facewatch Subscription Management System - Overview

## System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         WEB BROWSER                              │
│                    http://localhost:8000                         │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                    PHP WEB APPLICATION                           │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │   Views      │  │   Services   │  │   Models     │          │
│  │              │  │              │  │              │          │
│  │ • Dashboard  │  │ • Prepayment │  │ • Subscriber │          │
│  │ • Customers  │  │   Calculator │  │ • Invoice    │          │
│  │ • Invoices   │  │ • Xero       │  │ • User       │          │
│  │ • Reports    │  │   Importer   │  │ • Pricing    │          │
│  │ • Import     │  │ • Camera     │  │ • Camera     │          │
│  │ • Login      │  │   Importer   │  │   Count      │          │
│  │              │  │ • Reconcile  │  │              │          │
│  └──────────────┘  └──────────────┘  └──────────────┘          │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                    MySQL DATABASE                                │
│                                                                  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │ Subscribers  │  │  Invoices    │  │ Prepayments  │          │
│  │ Contracts    │  │  Camera      │  │ Cash Flow    │          │
│  │ Pricing      │  │  Counts      │  │ Reconcile    │          │
│  │ Users        │  │  Imports     │  │ Audit Log    │          │
│  └──────────────┘  └──────────────┘  └──────────────┘          │
│                                                                  │
│                    15 Tables Total                               │
└─────────────────────────────────────────────────────────────────┘
```

## Data Flow

### 1. Import Xero Invoices
```
CSV File → XeroImporter → Subscribers + Invoices → Database
                              ↓
                    Auto-create subscribers if new
                    Skip duplicates
                    Log import history
```

### 2. Import Camera Data
```
CSV File → CameraImporter → Camera Counts → Database
                              ↓
                    Validate subscriber exists
                    Update or insert counts
                    Log import history
```

### 3. Calculate Prepayments
```
Invoices → PrepaymentCalculator → Prepayments → Database
             ↓
    Annual: Value / 365 × (365 - days)
    Quarterly: Value / 91.25 × (91.25 - days)
    Monthly: Straight to P&L
```

### 4. Reconciliation
```
Invoices + Camera Counts + Pricing → ReconciliationService → Reconciliation Log
                                           ↓
                              Check: Actual vs Expected Amount
                              Check: Invoiced vs Installed Cameras
                              Flag: ok / under / over / mismatch
```

## User Journey

### First Time Setup
1. Run `./SETUP.sh` (creates database, loads data)
2. Start server: `php -S localhost:8000`
3. Login: admin / changeme123
4. Change password (recommended)

### Daily Workflow
1. **Import Xero Data** (weekly/monthly)
   - Export invoices from Xero to CSV
   - Upload via Import Data → Xero Invoices
   - System creates/updates subscribers and invoices

2. **Import Camera Data** (monthly)
   - Export camera counts from primary system
   - Upload via Import Data → Camera Data
   - Select month for the data
   - System updates camera counts

3. **Review Dashboard**
   - Check total subscribers
   - Monitor unpaid/overdue invoices
   - Review reconciliation issues
   - Check prepayment balance

4. **Run Reports**
   - Prepayments Report → For accounting
   - Reconciliation Report → Check variances
   - Revenue Report → Historical analysis

## Key Calculations

### Prepayment Formula
```
For Annual Invoices:
  Days in Period = 365
  Days Since Invoice = Today - Invoice Date
  Days Remaining = 365 - Days Since Invoice
  Prepayment Balance = Invoice Amount × (Days Remaining / 365)
  P&L Credit = Invoice Amount - Prepayment Balance

For Quarterly:
  Same formula but Days in Period = 91.25

For Monthly:
  Prepayment = 0 (goes straight to P&L)
```

### Reconciliation Logic
```
Expected Amount = (Main Cameras × Main Rate) + (Additional Cameras × Additional Rate)
Actual Amount = Invoice Amount from Xero
Variance = Actual - Expected

Status:
  If Variance = 0 → ✅ OK
  If Variance < 0 → ⚠️ Under-charged
  If Variance > 0 → ⚠️ Over-charged
  
Installation Check:
  If Invoiced Cameras ≠ Installed Cameras → ⚠️ Installation Mismatch
```

## Database Tables

### Core Business Tables
- **legal_entities** - Customer master data (formerly subscribers)
- **stores** - Store locations for each legal entity
- **camera_installations** - Individual camera installation records
- **invoices** - Invoice records (actual and forecast)
- **invoice_camera_allocations** - Links invoices to specific cameras
- **invoice_generation_log** - Tracks invoice generation events
- **invoice_status_history** - Audit trail of invoice status changes

### Pricing Tables
- **camera_pricing** - Camera pricing rules
- **legal_entity_pricing** - Entity-specific pricing overrides
- **legal_entity_independent_pricing** - Independent pricing model overrides
- **default_independent_pricing** - Default independent pricing rates
- **pricing_tiers** - Volume discount tiers
- **legal_entity_rate_history** - Historical rate changes for audit

### Import & Matching Tables
- **camera_imports** - Camera import history
- **camera_installation_imports** - Installation import history
- **legal_entity_imports** - Entity import history
- **store_imports** - Store import history
- **xero_imports** - Xero import history
- **xero_import_sessions** - Xero import sessions for matching
- **xero_imported_invoices** - Imported Xero invoices awaiting matching
- **matching_rules** - Smart invoice matching rules
- **matching_audit_log** - Matching decision audit trail

### Reconciliation Tables
- **reconciliation_log** - Invoice reconciliation variance tracking

### System Tables
- **users** - User accounts
- **user_sessions** - Active sessions
- **user_activity_log** - User actions audit trail
- **audit_log** - System-wide change tracking

### Legacy/Unused Tables
- **prepayments** - Legacy (prepayments calculated on-the-fly)
- **cash_flow_forecast** - Legacy (forecasts calculated dynamically)
- **legal_entity_contracts** - Legacy (pricing now in legal_entity_pricing)
- **inflation_rates** - Legacy (inflation hardcoded at 3%)

## Security Features

✅ **Authentication**
- Password hashing (bcrypt)
- Session management
- Role-based access (admin, manager, viewer)

✅ **Data Protection**
- SQL injection prevention (prepared statements)
- Input validation
- Error handling

✅ **Audit Trail**
- All imports logged
- User activity tracked
- Changes recorded

## Performance Optimizations

✅ **Database**
- Indexed foreign keys
- Composite indexes for common queries
- Generated columns for calculations

✅ **Application**
- Singleton database connection
- Efficient queries with joins
- Minimal dependencies

## File Organization

```
subscription-system/
├── app/                    # Application code
│   ├── Database.php       # DB connection
│   ├── Models/            # Data models
│   └── Services/          # Business logic
├── config/                # Configuration
├── database/              # Schema & seeds
├── public/                # Web root
│   └── index.php         # Entry point
├── views/                 # HTML templates
│   ├── auth/             # Login
│   ├── dashboard/        # Dashboard
│   ├── customers/        # Subscribers
│   ├── invoices/         # Invoices
│   ├── imports/          # Import pages
│   ├── reports/          # Reports
│   └── layouts/          # Header/footer
├── uploads/               # CSV uploads
├── .env                   # Environment config
├── README.md             # Documentation
├── QUICKSTART.md         # Setup guide
├── SETUP.sh              # Auto setup
└── TESTING_CHECKLIST.md  # Testing guide
```

## Next Steps

1. ✅ **Setup** - Run SETUP.sh
2. ✅ **Login** - Access the system
3. ✅ **Import** - Load your data
4. ✅ **Test** - Verify calculations
5. ✅ **Use** - Daily operations

## Support

- **Documentation**: README.md
- **Quick Start**: QUICKSTART.md
- **Testing**: TESTING_CHECKLIST.md
- **Morning Brief**: MORNING_BRIEFING.md

---

**System Status**: ✅ Ready for Production Testing  
**Version**: 1.0  
**Built**: February 2026  
**For**: Facewatch Ltd

