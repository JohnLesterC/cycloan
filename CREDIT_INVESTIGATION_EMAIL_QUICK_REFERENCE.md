# 🎯 CREDIT INVESTIGATION EMAIL - QUICK REFERENCE CARD

## 30-Second Setup

```
1. Select Status      → Pending / Completed / Failed
2. Enter Final Amount → Approved loan amount
3. Add Remarks        → Investigation findings
4. Select Term        → 6/12/18/24/36 months
5. Submit             → Email sent! ✓
```

---

## What Applicant Sees

| Item | Source | How to Control |
|---|---|---|
| **Status** | Your selection | Click "Investigation Status" dropdown |
| **Amount Approved** | Your entry | Enter in "Final Loan Amount" field |
| **Loan Term** | Your selection | Select in "Loan Term" dropdown |
| **Investigation Notes** | Your remarks | Type in "Remarks" text field |

---

## Remarks Templates (Copy & Paste)

### ✅ APPROVED
```
Investigation completed successfully. All documents verified. 
You are approved for ₱[AMOUNT] with [TERM] month term. 
Visit our office within 7 days to finalize.
```

### ✅ APPROVED (With Adjustment)
```
Approved for ₱[AMOUNT] (adjusted from ₱[REQUESTED]). 
Based on verified income. [TERM] month term. 
Visit office to sign agreement.
```

### ❌ FAILED
```
Unable to complete investigation at this time. 
Please provide [SPECIFIC DOCUMENTS] within [DAYS] days.
Reply with documents to resume investigation.
```

### ⏳ PENDING
```
Investigation under review. Currently verifying [WHAT].
You will hear from us within [DAYS] business days.
No action needed at this time.
```

---

## Database Fields (Admin Reference)

```
📊 What gets sent to applicant:

Field               Database Source        You Control?
─────────────────────────────────────────────────────
Application ID      loan_applications.id   No (auto)
Status              POST data              ✓ YES
Amount Requested    loan_applications      No (auto)
Amount Approved     final_loan_amount      ✓ YES
Loan Term           term_length            ✓ YES
Investigation Notes remarks table          ✓ YES
Admin Name          Session user           No (auto)
```

---

## Common Mistakes & Fixes

| ❌ Problem | ✅ Solution |
|---|---|
| Email not sending | Check Final Amount is entered |
| Notes not in email | Verify remarks field has text |
| Wrong applicant | Double-check Application ID |
| Empty email | All required fields must have values |
| Multiple emails | Normal if resubmitted multiple times |

---

## Status Guide

```
PENDING
  → Investigation still in progress
  → Tell applicant: expect update in X days
  → No approval amount shown yet
  
COMPLETED
  → Shows: Status + Approved Amount + Term
  → Tell applicant: visit office to finalize
  → Approval amount MUST be entered
  
FAILED  
  → Shows: Status + Investigation Notes
  → Suggest: next steps or alternatives
  → No approval amount shown
```

---

## Email Preview (What They See)

```
═══════════════════════════════════════════
📋 INVESTIGATION DETAILS

Application ID:           #APP-20251120-0042
Investigation Status:     Completed ✓
Amount Requested:         ₱50,000.00
🎯 Approved Amount:       ₱45,000.00
Loan Term:                12 months

📝 INVESTIGATION NOTES & REMARKS

Investigation completed. Good credit standing.
— Ken Sollosa on Nov 20, 2025 2:36 PM

Ready for office visit and document signing.
— Admin on Nov 20, 2025 3:00 PM

═══════════════════════════════════════════
```

---

## Field Mapping (for reference)

```php
// What you enter in admin form:
$_POST['credit_investigation_status']  // → Investigation Status in email
$_POST['final_loan_amount']             // → Approved Amount in email
$_POST['term_length']                   // → Loan Term in email
$_POST['remarks']                       // → Investigation Notes in email

// What's auto-filled:
$_POST['amount_applied']                // → Amount Requested in email
$user_email                             // → Email recipient
$user_first_name, $user_last_name       // → Greeting
$_SESSION['admin_name']                 // → Admin signature
```

---

## 🚀 Quick Start

**For a NEW Investigation:**
1. Go to Admin Dashboard
2. Find application → Click Edit
3. Set Status: "Pending"
4. Add Note: "Investigation started"
5. Submit

**To COMPLETE Investigation:**
1. Find same application
2. Change Status: "Completed"
3. Enter Final Loan Amount: e.g., 45000
4. Select Term: "12 months"
5. Add Note: "Approved with good credit standing"
6. Submit → Applicant gets email! ✓

**To REJECT Investigation:**
1. Find same application
2. Change Status: "Failed"
3. Clear Final Loan Amount (or leave as is)
4. Add Note: "Unable to verify income documents"
5. Submit → Applicant gets rejection + next steps

---

## Testing Checklist

- [ ] Update application status
- [ ] Enter final loan amount
- [ ] Add investigation remarks
- [ ] Select loan term
- [ ] Submit form
- [ ] Check email received within 2 minutes
- [ ] Verify remarks show in email
- [ ] Verify amounts are correct
- [ ] Verify status displays correctly

---

## Troubleshooting (One-Liner)

```
Email didn't send?
→ Check: Status set + Final Amount entered + Remarks added

Notes missing from email?
→ Check: Remarks field not empty when submitted

Wrong address showing?
→ Check: Application ID correct in dashboard

Applicant didn't receive?
→ Check: Email in profile + check spam folder
```

---

## Key Points

✓ **Status** = What stage investigation is at  
✓ **Amount** = How much you're approving  
✓ **Term** = How long the loan is for  
✓ **Notes** = Explain your decision to applicant  

---

## Need Help?

Check these files:
- Full guide: `CREDIT_INVESTIGATION_EMAIL_USER_GUIDE.md`
- Tech details: `CREDIT_INVESTIGATION_EMAIL_ENHANCEMENT.md`
- Code location: `admin1_dashboard.php` (line ~1489)

---

**Print This Card & Keep at Your Desk!** 🖨️
