# 🔍 Manual Invoice Matching - Complete Guide

## ✅ What's New

I've added a **Manual Matching** feature that lets you manually match any Xero invoice to any system invoice. This is perfect for:

- **Unmatched invoices** - When the automatic algorithm can't find a match
- **Low confidence matches** - When you want to choose a different invoice than the suggested one
- **Edge cases** - When you know better than the algorithm!

---

## 🎯 How Manual Matching Works

### When to Use Manual Matching

1. **Unmatched Xero Invoices** 
   - The algorithm couldn't find any match
   - You know which system invoice it should match to

2. **Wrong Suggested Match**
   - The algorithm suggested a match but it's incorrect
   - You want to choose a different invoice

3. **Low Confidence Matches**
   - The match score is <70% but you're not sure
   - You want to see all available options

---

## 📋 Step-by-Step Guide

### Scenario 1: Matching an Unmatched Invoice

1. **Go to Smart Match Results**
   - Upload your Xero CSV
   - View the matching results

2. **Find Unmatched Section**
   - Scroll to "❌ Unmatched Xero Invoices"
   - You'll see a table of invoices with no automatic match

3. **Click "🔍 Find Match"**
   - Click the button next to the invoice you want to match
   - You'll be taken to the Manual Match page

4. **Choose the Correct Invoice**
   - You'll see a table of ALL available system invoices
   - Each invoice shows a **match score** to help you choose
   - Invoices are sorted by score (best matches first)
   - Click the radio button next to the correct invoice

5. **Create the Match**
   - Click "✅ Create Manual Match"
   - The system calculates the match score
   - The match is saved and you're returned to results

### Scenario 2: Choosing a Different Match

1. **Review Suggested/Possible Matches**
   - Look at the suggested or possible matches
   - If you disagree with the suggestion...

2. **Click "🔍 Find Different Match"**
   - Available on possible matches (<70% confidence)
   - Opens the manual match page

3. **Choose the Correct Invoice**
   - See all available invoices with scores
   - Pick the one you know is correct
   - Create the match

---

## 🎨 What You'll See

### Manual Match Page Layout

```
┌─────────────────────────────────────────────────────┐
│ 🔍 Manual Match Invoice              [← Back]       │
├─────────────────────────────────────────────────────┤
│                                                      │
│ 📊 Xero Invoice to Match                            │
│ ┌──────────────────────────────────────────────┐   │
│ │ Invoice #: XERO-2026-001                      │   │
│ │ Contact: Tesco Stores Ltd                     │   │
│ │ Date: 01/02/2026                              │   │
│ │ Amount: £1,250.00                             │   │
│ └──────────────────────────────────────────────┘   │
│                                                      │
│ Select System Invoice to Match                      │
│ ┌──────────────────────────────────────────────┐   │
│ │ ○ INV-001 | Tesco Ltd | 01/02/26 | £1,250   │   │
│ │   Score: 95% ✅                               │   │
│ │   Entity: exact (40pts)                       │   │
│ │   Date: exact (30pts)                         │   │
│ │   Amount: exact (30pts)                       │   │
│ ├──────────────────────────────────────────────┤   │
│ │ ○ INV-002 | Tesco Express | 02/02/26 | £1,200│   │
│ │   Score: 75% 🟡                               │   │
│ │   Entity: high (35pts)                        │   │
│ │   Date: close (25pts)                         │   │
│ │   Amount: close (25pts)                       │   │
│ └──────────────────────────────────────────────┘   │
│                                                      │
│         [✅ Create Manual Match]  [Cancel]          │
└─────────────────────────────────────────────────────┘
```

### Key Features

1. **Xero Invoice Details** (Top)
   - Shows the Xero invoice you're trying to match
   - Blue background for easy identification

2. **Available Invoices Table**
   - Shows ALL system invoices with "issued" status
   - Excludes invoices already matched in this session
   - Sorted by match score (best first)

3. **Match Scores**
   - Each invoice shows its match score (0-100%)
   - Color coded:
     - 🟢 Green (70%+) - Good match
     - 🟡 Yellow (50-69%) - Medium match
     - 🟠 Orange (<50%) - Low match

4. **Match Breakdown**
   - Shows why the score is what it is
   - Entity match quality and points
   - Date match quality and points
   - Amount match quality and points

5. **Easy Selection**
   - Click anywhere on the row to select
   - Radio button automatically checks
   - Submit button creates the match

---

## 🔧 Technical Details

### What Happens When You Create a Manual Match

1. **Score Calculation**
   - System calculates the match score using the same algorithm
   - Even if you choose a low-scoring match, it's recorded

2. **Database Updates**
   - `xero_imported_invoices` table updated with:
     - `matched_invoice_id` - The system invoice ID
     - `match_confidence` - The calculated score
     - `match_status` - Set to "manual_matched"
     - `match_reason` - JSON breakdown of the score

3. **Audit Logging**
   - `matching_audit_log` table records:
     - Who made the match
     - When it was made
     - The score and breakdown
     - Action type: "manual"

4. **Session Updates**
   - Session matched count incremented
   - You're returned to the results page
   - The invoice moves from "Unmatched" to "Matched"

### Validation

- ✅ Only "issued" invoices can be matched
- ✅ Invoices already matched in this session are excluded
- ✅ Must select an invoice (required field)
- ✅ Score is calculated even for manual matches
- ✅ All actions are logged for audit trail

---

## 💡 Smart Features

### 1. Intelligent Sorting
Invoices are sorted by match score, so the best matches appear first. This helps you quickly find the right invoice.

### 2. Visual Scoring
Color-coded scores help you understand match quality at a glance:
- High score (green) = Probably correct
- Medium score (yellow) = Review carefully
- Low score (orange) = Double-check this is right

### 3. Detailed Breakdown
See exactly why each invoice got its score:
- Entity name similarity
- Date difference
- Amount difference

### 4. Click-to-Select
Click anywhere on the table row to select that invoice - no need to precisely click the tiny radio button!

### 5. No Duplicates
The system automatically excludes:
- Invoices already matched in this session
- Invoices with status other than "issued"

---

## 🎯 Use Cases

### Use Case 1: Name Variation
**Problem**: Xero has "Tesco Express Ltd" but your system has "Tesco Stores Limited"

**Solution**:
1. Algorithm might not match (different names)
2. Click "Find Match" on the unmatched invoice
3. See "Tesco Stores Limited" in the list with a medium score
4. You recognize it's the same company
5. Select it and create the match

### Use Case 2: Date Discrepancy
**Problem**: Xero invoice dated 01/02/2026, system invoice dated 15/02/2026 (different billing cycles)

**Solution**:
1. Algorithm might suggest a different invoice (closer date)
2. You know the 15/02 invoice is correct
3. Click "Find Different Match"
4. Select the 15/02 invoice
5. Create the match

### Use Case 3: Amount Difference
**Problem**: Xero shows £1,250.00, system shows £1,200.00 (discount applied)

**Solution**:
1. Algorithm might not match (amount too different)
2. You know it's the same invoice (discount was applied in Xero)
3. Use manual match to link them
4. Add a note in your records about the discount

---

## 🚀 Workflow Integration

Manual matching fits seamlessly into your workflow:

```
1. Upload Xero CSV
   ↓
2. Review automatic matches
   ↓
3. Accept perfect matches (100%)
   ↓
4. Review suggested matches (70-99%)
   ↓
5. For unmatched or uncertain:
   → Click "Find Match"
   → Choose correct invoice
   → Create manual match
   ↓
6. Reconcile all accepted matches
   ↓
7. Done! ✅
```

---

## 📊 Benefits

✅ **Complete Control** - You decide which invoices match  
✅ **Intelligent Suggestions** - Scores help you choose  
✅ **Full Visibility** - See all available options  
✅ **Audit Trail** - Every manual match is logged  
✅ **No Duplicates** - System prevents double-matching  
✅ **Easy to Use** - Click-to-select interface  
✅ **Score Transparency** - Know why each match is suggested  

---

## 🎉 You're Ready!

The manual matching feature is **fully functional** and ready to use!

**Try it:**
1. Go to Smart Match results
2. Find an unmatched invoice
3. Click "🔍 Find Match"
4. Choose an invoice and create the match!

---

## 🔗 Related Features

- **Smart Matching** - Automatic algorithm matches most invoices
- **Accept/Reject** - Quick actions for suggested matches
- **Bulk Reconcile** - Reconcile all matched invoices at once
- **Audit Log** - Complete history of all matching decisions

---

**Manual matching gives you the power to handle edge cases while still benefiting from the automatic matching for 90% of your invoices!** 🚀

