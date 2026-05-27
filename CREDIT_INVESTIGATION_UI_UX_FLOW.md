# Credit Investigation Form - UI/UX Flow & Architecture

## Frontend-to-Backend Flow Visualization

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        ADMIN1 DASHBOARD                                 │
│                                                                          │
│  Loan Applicants Table                                                   │
│  ┌──────────┬──────────┬──────────┬──────────────────────┐              │
│  │ App ID   │ Name     │ Amount   │ Status               │              │
│  ├──────────┼──────────┼──────────┼──────────────────────┤              │
│  │APP-001   │John Doe  │₱50,000   │Credit Investigation  │  👈 CLICK   │
│  │APP-002   │Jane Doe  │₱75,000   │Pre-Approval          │             │
│  └──────────┴──────────┴──────────┴──────────────────────┘             │
│                                      │                                  │
│                                      ▼                                  │
│  ┌─────────────────────────────────────────────────────────┐            │
│  │         LOAN DETAILS MODAL (Bootstrap Modal)            │            │
│  │                                                         │            │
│  │  ✓ Applicant Information                               │            │
│  │    • Name: John Doe                                    │            │
│  │    • Email: john@example.com                           │            │
│  │    • Contact: 09XX-XXX-XXXX                            │            │
│  │                                                         │            │
│  │  ✓ Loan Information                                    │            │
│  │    • Loan Type: Individual                             │            │
│  │    • Applied Amount: ₱50,000                           │            │
│  │                                                         │            │
│  │  ✓ Credit Investigation Section                        │            │
│  │  ┌──────────────────────────────────────────────┐      │            │
│  │  │ Investigation Results                         │      │            │
│  │  │                                               │      │            │
│  │  │ Investigation Status * [Completed ▼]        │      │            │
│  │  │ Select the outcome of investigation          │      │            │
│  │  │ ⚠️  Status is required                        │      │            │
│  │  │                                               │      │            │
│  │  │ Loan Adjustment                              │      │            │
│  │  │ Loan Type: Individual                        │      │            │
│  │  │ Final Loan Amount (₱) * [50000]             │      │            │
│  │  │ Range: ₱10,000 - ₱100,000                    │      │            │
│  │  │ ✓ Amount valid: ₱50,000                      │      │            │
│  │  │                                               │      │            │
│  │  │ Loan Term (months) * [12 months ▼]          │      │            │
│  │  │ Options: 6, 12, 18, 24, 36 months            │      │            │
│  │  │                                               │      │            │
│  │  │ 📊 Monthly Payment Estimate:                │      │            │
│  │  │    ₱4,527.77                                 │      │            │
│  │  │    Based on 6% annual interest rate          │      │            │
│  │  │                                               │      │            │
│  │  │ Notes & Remarks                              │      │            │
│  │  │ Pre-Defined Suggestions [- Select - ▼]     │      │            │
│  │  │ • Applicant passed all credit checks        │      │            │
│  │  │ • Credit score is satisfactory              │      │            │
│  │  │ • No outstanding debts found                │      │            │
│  │  │                                               │      │            │
│  │  │ Additional Remarks                           │      │            │
│  │  │ [Text area - 0 / 500 characters]            │      │            │
│  │  │                                               │      │            │
│  │  │ [🔒 Submit Investigation]  [↻ Reset Form]  │      │            │
│  │  │                                               │      │            │
│  │  │ ✓ Form Validation Summary (if needed)       │      │            │
│  │  │   • Status: Completed ✓                     │      │            │
│  │  │   • Amount: ₱50,000 ✓                       │      │            │
│  │  │   • Term: 12 months ✓                       │      │            │
│  │  └──────────────────────────────────────────────┘      │            │
│  │                                                         │            │
│  └─────────────────────────────────────────────────────────┘            │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Submission Flow with Error Handling

```
START: User clicks "Submit Investigation"
  │
  ▼
FRONTEND VALIDATION (JavaScript)
  │
  ├─ Check Application ID exists? ─────────────┬─ NO ──→ ❌ Show Error
  │                                             │
  ├─ Check Status selected? ─────────────────────┼─ NO ──→ ❌ Show Error
  │                                             │
  ├─ Check Amount is valid number? ──────────────┼─ NO ──→ ❌ Show Error
  │                                             │
  ├─ Check Amount within range? ─────────────────┼─ NO ──→ ❌ Show Error
  │                                             │
  ├─ Check Term Length selected? ────────────────┼─ NO ──→ ❌ Show Error
  │                                             │
  └─ ALL PASS ─────────────────────────────────┘
    │
    ├─ Log form data to console
    ├─ Disable submit button
    ├─ Show spinner: "⟳ Processing..."
    │
    ▼
SEND FETCH REQUEST (POST)
    │
    body = FormData {
        action: 'submit_credit_investigation',
        application_id: 1234567890,
        credit_status: 'Completed',
        final_loan_amount: 50000.00,
        term_length: '12',
        remarks: 'Applicant passed all checks'
    }
    │
    ▼
BACKEND PROCESSING (PHP)
    │
    ├─ Log submission start
    ├─ Parse POST parameters
    ├─ Validate all parameters not null
    │
    ├─ TRY:
    │  ├─ Fetch current application data
    │  │  └─ SELECT FROM loan_applications & users1
    │  │
    │  ├─ Prepare UPDATE query
    │  │  └─ UPDATE loan_applications SET
    │  │    credit_investigation_status = ?,
    │  │    final_loan_amount = ?,
    │  │    term_length = ?,
    │  │    updated_at = ?
    │  │    WHERE application_id = ?
    │  │
    │  ├─ Execute UPDATE
    │  │  ├─ PARAMETER BINDING (FIXED):
    │  │  │  Type: "sdisi" (string, double, int, string, int)
    │  │  │  Values: status, amount, term(as int), timestamp, id
    │  │  │
    │  │  └─ Rows affected: 1 ✓
    │  │
    │  ├─ Log activity via logActivity()
    │  │  └─ INSERT INTO activity_logs
    │  │
    │  ├─ Send Email Notification
    │  │  ├─ IF Status = 'Completed'
    │  │  │  └─ Send GREEN "Approved" email
    │  │  │
    │  │  ├─ IF Status = 'Failed'
    │  │  │  └─ Send RED "Under Review" email
    │  │  │
    │  │  └─ IF Status = 'Pending'
    │  │     └─ No email sent
    │  │
    │  ├─ Insert Remarks (FIXED - 4 param binding)
    │  │  ├─ IF remarks NOT empty:
    │  │  │  ├─ Get current timestamp (Asia/Manila)
    │  │  │  ├─ INSERT INTO remarks
    │  │  │  │  VALUES (?, ?, ?, ?)
    │  │  │  │  PARAMETERS: app_id, remarks, created_at, admin_name
    │  │  │  │  TYPE: "isss" (int, string, string, string)
    │  │  │  │
    │  │  │  └─ Rows affected: 1 ✓
    │  │  │
    │  │  └─ Log "Remarks inserted successfully"
    │  │
    │  ├─ Create Status Notification
    │  │  └─ INSERT INTO notifications (user_id, message)
    │  │
    │  ├─ ob_clean() - Clear any buffered output
    │  ├─ header('Content-Type: application/json; charset=utf-8')
    │  └─ echo json_encode(['success' => true, 'message' => '...'])
    │
    ├─ CATCH (Exception $e):
    │  ├─ Log error: $e->getMessage()
    │  ├─ ob_clean()
    │  ├─ header('Content-Type: application/json; charset=utf-8')
    │  └─ echo json_encode(['success' => false, 'message' => $e->getMessage()])
    │
    ▼
FRONTEND RESPONSE HANDLING
    │
    ├─ Check response.ok
    │  └─ IF NOT OK ──→ throw Error
    │
    ├─ Check Content-Type header
    │  └─ IF NOT 'application/json' ──→ throw Error
    │
    ├─ Parse response.json()
    │  └─ CATCH JSON parse error ──→ throw Error
    │
    ├─ Check data.success
    │  │
    │  ├─ IF TRUE:
    │  │  ├─ Show SUCCESS message ✓
    │  │  ├─ Wait 2 seconds
    │  │  ├─ closeLoanDetailsModal()
    │  │  ├─ refreshLoanApplicantsTable()
    │  │  │
    │  │  ▼
    │  │  END: Return to Dashboard
    │  │
    │  └─ IF FALSE:
    │     ├─ Show ERROR message ✗
    │     ├─ Display data.message
    │     │
    │     ▼
    │     END: Keep modal open for retry
    │
    ├─ CATCH any error:
    │  ├─ Log error details to console
    │  ├─ Show ERROR message with error.message
    │  │
    │  ▼
    │  END: Keep modal open for retry
    │
    ├─ FINALLY:
    │  ├─ Re-enable submit button
    │  └─ Restore original button text
    │
    ▼
END
```

---

## Database State Changes

### Before Submission

```
loan_applications (APP-001):
├─ application_id: APP-001
├─ user_id: 1
├─ amount_applied: ₱50,000
├─ credit_investigation_status: 'Pending'  ◀─ WILL CHANGE
├─ final_loan_amount: NULL                 ◀─ WILL CHANGE
├─ term_length: NULL                       ◀─ WILL CHANGE
├─ updated_at: 2025-11-18 10:30:00
└─ status: 'Under Review'

remarks (for APP-001):
└─ (empty - no remarks yet)

activity_logs (for APP-001):
└─ Latest: "Submitted for pre-approval"
```

### After Submission (Status: Completed, Amount: ₱50,000, Term: 12)

```
loan_applications (APP-001):
├─ application_id: APP-001
├─ user_id: 1
├─ amount_applied: ₱50,000
├─ credit_investigation_status: 'Completed'  ✓ UPDATED
├─ final_loan_amount: ₱50,000.00             ✓ UPDATED
├─ term_length: '12'                         ✓ UPDATED
├─ updated_at: 2025-11-19 14:45:23           ✓ UPDATED
└─ status: 'Under Review'

remarks (for APP-001):
├─ remark_id: 1
├─ application_id: 1001
├─ remarks: 'Applicant passed all checks'   ✓ INSERTED
├─ admin_name: 'Admin Name'                 ✓ INSERTED
└─ created_at: 2025-11-19 14:45:23          ✓ INSERTED

activity_logs (for APP-001):
├─ Latest 1: "Credit investigation completed: Status set to 'Completed',
              Final Amount: ₱50,000.00, Term Length: 12 months"
└─ Latest 2: (previous entries preserved)
```

---

## Component Relationships

```
┌────────────────────────────────────────────────────────────────┐
│                  CREDIT INVESTIGATION FORM                      │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│  Form Container (id="creditInvestigationForm")                │
│  │                                                            │
│  ├─ Hidden Inputs                                             │
│  │  ├─ ciApplicationId (passed from modal data)               │
│  │  └─ ciLoanType (passed from modal data)                   │
│  │                                                            │
│  ├─ Investigation Results Section                            │
│  │  └─ ciStatus (SELECT dropdown)                            │
│  │     ├─ Options: Completed, Failed, Pending                │
│  │     ├─ Change Event → initializeSuggestions()             │
│  │     ├─ Change Event → validateForm()                      │
│  │     └─ Change Event → calculateMonthlyPayment()           │
│  │                                                            │
│  ├─ Loan Adjustment Section                                  │
│  │  ├─ ciLoanType (Display-only)                             │
│  │  │  └─ Updates loanTypeInfo text based on type            │
│  │  │                                                        │
│  │  ├─ ciFinalAmount (NUMBER input)                          │
│  │  │  ├─ min/max set by loanTypeLimits[loanType]            │
│  │  │  ├─ Input Event → updateFieldValidation()             │
│  │  │  ├─ Input Event → calculateMonthlyPayment()           │
│  │  │  ├─ Input Event → validateForm()                      │
│  │  │  └─ Displays: amountValidation, amountRangeInfo       │
│  │  │                                                        │
│  │  ├─ ciTermLength (SELECT dropdown)                        │
│  │  │  ├─ Options: 6, 12, 18, 24, 36 months                 │
│  │  │  ├─ Change Event → calculateMonthlyPayment()          │
│  │  │  ├─ Change Event → validateForm()                     │
│  │  │  └─ Displays: ciTermValidation text                   │
│  │  │                                                        │
│  │  └─ Monthly Payment Display (calculated)                  │
│  │     ├─ Formula: (amount × rate × term) / (1 - ...)       │
│  │     └─ Based on interest_rates table lookup              │
│  │                                                            │
│  ├─ Notes & Remarks Section                                  │
│  │  ├─ suggestionDropdown (SELECT)                           │
│  │  │  ├─ Populated from suggestedRemarks[status]            │
│  │  │  ├─ Options change based on status selection           │
│  │  │  └─ onChange → Auto-fills ciRemarks                   │
│  │  │                                                        │
│  │  └─ ciRemarks (TEXTAREA)                                  │
│  │     ├─ maxlength="500"                                    │
│  │     ├─ Input Event → Update remarksCounter               │
│  │     └─ Displays: remarksCounter (0/500)                  │
│  │                                                            │
│  ├─ Action Buttons                                           │
│  │  ├─ ciSubmitBtn (type="submit")                           │
│  │  │  ├─ onClick → Prevent duplicate (isSubmitting flag)    │
│  │  │  ├─ Triggers frontend validation                       │
│  │  │  ├─ Shows spinner on submit                           │
│  │  │  └─ Disables during processing                        │
│  │  │                                                        │
│  │  └─ ciResetBtn (type="button")                            │
│  │     ├─ onClick → form.reset()                             │
│  │     └─ Clears all validation messages                     │
│  │                                                            │
│  ├─ Message Boxes                                            │
│  │  ├─ ciStatusValidation (error/success message)            │
│  │  ├─ amountValidation (error/success message)              │
│  │  ├─ ciTermValidation (error/success message)              │
│  │  ├─ ciMessageBox (Success/Error from server)             │
│  │  └─ formValidationSummary (All field validations)         │
│  │                                                            │
│  └─ Submit Event Listener                                    │
│     ├─ preventDefault()                                      │
│     ├─ Validate all fields                                   │
│     ├─ Build FormData object                                 │
│     ├─ POST to admin1_dashboard.php                         │
│     ├─ Handle response (success/error)                       │
│     └─ Close modal & refresh table on success               │
│                                                              │
└────────────────────────────────────────────────────────────────┘
```

---

## Key Variables & Constants

### Frontend (JavaScript)

```javascript
// Form state
let isSubmitting = false;  // Prevent duplicate submissions

// Global data
window.currentApplicationId;    // Set when modal opens
window.currentInterestRate;     // Fetched from database
window.currentLoanType;         // From modal data

// Configuration Objects
const suggestedRemarks = {
    'Completed': ['Applicant passed...', 'Credit score...', ...],
    'Failed': ['Credit score below...', 'Outstanding debts...', ...],
    'Pending': ['Awaiting documentation...', 'Verification...', ...]
};

const loanTypeLimits = {
    'Individual': { min: 10000, max: 100000 },
    'Cooperative': { min: 300000, max: 1000000 }
};
```

### Backend (PHP)

```php
// From session
$adminId        = $admin['id'];              // Current admin user ID
$adminName      = $admin['first_name'] . ' ' . $admin['last_name'];
$adminRole      = 'Admin1';

// From form submission
$applicationId  = intval($_POST['application_id']);
$creditStatus   = trim($_POST['credit_status']);      // Completed|Failed|Pending
$finalLoanAmount = floatval($_POST['final_loan_amount']);
$termLength     = trim($_POST['term_length']);        // 6|12|18|24|36
$remarks        = trim($_POST['remarks']);
```

---

## Error Scenarios & Recovery

### Scenario 1: Missing Form Fields

```
User Action: Submit without filling fields
│
├─ Frontend Validation catches error
│  ├─ ciStatus empty? → "Investigation Status is required"
│  ├─ ciFinalAmount empty? → "Final Amount must be a valid number"
│  ├─ ciTermLength empty? → "Loan Term Length is required"
│  │
│  └─ Display error message in ciMessageBox
│     └─ User corrects and resubmits
│
Result: Form stays open, errors displayed, no backend call made
```

### Scenario 2: Amount Out of Range

```
User Action: Enter ₱5,000 for Individual loan (min: ₱10,000)
│
├─ Frontend Validation catches error
│  └─ Amount < 10000 → "Final Amount must be between ₱10,000 - ₱100,000"
│
├─ Display error in amountValidation
│  └─ Red error message under input field
│
└─ User corrects amount to ₱50,000 → Resubmit
```

### Scenario 3: Database Error (Rare)

```
User Action: Submit form
│
├─ Backend prepare() fails
│  └─ Throw Exception("Database prepare error: ...")
│
├─ Catch block logs error to debug_log.txt
├─ ob_clean() clears any output
├─ Send JSON error response
│
├─ Frontend receives {'success': false, 'message': 'Error: ...'}
│  └─ Display error to user in ciMessageBox
│
└─ User can retry or admin can check logs
```

### Scenario 4: Email Send Failure (Non-blocking)

```
User Action: Submit form with status='Completed'
│
├─ Database updates successfully
├─ Activity log created
│
├─ Email sending fails (try-catch around PHPMailer)
│  ├─ Error logged to debug_log.txt
│  └─ Does NOT throw exception (non-critical)
│
├─ Remarks insertion continues
├─ Notification created
│
├─ Backend still returns {'success': true}
│  └─ User sees success message
│
└─ Admin can check logs to verify why email failed
```

---

## Console Logging Examples

### Successful Submission (Check Browser Console)

```javascript
// Frontend
Form Submission Data: {
  applicationId: "1001",
  creditStatus: "Completed",
  finalAmount: 50000,
  termLength: "12",
  minVal: 10000,
  maxVal: 100000
}

// Backend (check error_log.txt)
=== CREDIT INVESTIGATION SUBMISSION START ===
POST Data: {...}
Parsed values - ID: 1001, Status: Completed, Amount: 50000, Term: 12
...database updates...
Remarks inserted successfully for application_id: 1001
Creating notification with message: Credit investigation completed...
=== CREDIT INVESTIGATION SUBMISSION SUCCESS ===
```

### Validation Error (Check Browser Console)

```javascript
Validation Errors: Array(2)
  0: "Investigation Status is required"
  1: "Loan Term Length is required"
```

---

## Testing Commands

### View all error logs

```bash
tail -f debug_log.txt | grep "Credit Investigation"
```

### Check latest submission

```bash
tail -50 debug_log.txt | grep "CREDIT INVESTIGATION"
```

### Monitor remarks insertion

```bash
tail -20 debug_log.txt | grep "Remarks"
```

### Check email logs (if configured)

```bash
tail -20 debug_log.txt | grep "email"
```

---

**Status:** ✅ PRODUCTION READY
**Last Update:** November 19, 2025
