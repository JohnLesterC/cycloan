# Enhanced Decision Submission - Architecture Diagram

## System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    ADMIN2 DASHBOARD                             │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│             LOAN DETAILS MODAL (JavaScript)                      │
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  Applicant Information Section                           │  │
│  └──────────────────────────────────────────────────────────┘  │
│                           ↓                                     │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  Loan Details Section                                    │  │
│  └──────────────────────────────────────────────────────────┘  │
│                           ↓                                     │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  Financial Information Section                           │  │
│  └──────────────────────────────────────────────────────────┘  │
│                           ↓                                     │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  Documents Section                                       │  │
│  │  [Lists all documents with status]                       │  │
│  │  [Approve/Reject buttons for each]                       │  │
│  └──────────────────────────────────────────────────────────┘  │
│                           ↓                                     │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  Remarks Section                                         │  │
│  │  [Shows admin notes and history]                         │  │
│  └──────────────────────────────────────────────────────────┘  │
│                           ↓                                     │
│  ╔══════════════════════════════════════════════════════════╗  │
│  ║  ⚖️ MAKE YOUR DECISION (NEW ENHANCED PANEL) ║            ║  │
│  ╠══════════════════════════════════════════════════════════╣  │
│  ║                                                          ║  │
│  ║  Decision Status Selection:                             ║  │
│  ║  ○ ⏳ Keep Pending  ○ ✓ Approve  ○ ✕ Reject            ║  │
│  ║                                                          ║  │
│  ║  Decision Reasoning * (0/1000)                          ║  │
│  ║  ┌──────────────────────────────────────────────────┐  ║  │
│  ║  │ [Textarea for reasoning]                          │  ║  │
│  ║  └──────────────────────────────────────────────────┘  ║  │
│  ║                                                          ║  │
│  ║  [Clear]  [📤 Submit Decision & Send Email]            ║  │
│  ║                                                          ║  │
│  ╚══════════════════════════════════════════════════════════╝  │
│                           ↓                                     │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  Activity Logs Section                                   │  │
│  │  [Shows history of changes]                              │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
                              ↓
                   [Form Validation]
                   [CSRF Token Check]
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│         ADMIN2_DASHBOARD.PHP (Backend Handler)                  │
│                                                                 │
│  POST /admin2_dashboard.php                                    │
│  action = "update_status"                                      │
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ 1. Validate Input                                        │  │
│  │    - Sanitize strings                                    │  │
│  │    - Verify enums                                        │  │
│  │    - Check CSRF token                                    │  │
│  │    - Ensure reasoning provided (if needed)              │  │
│  └──────────────────────────────────────────────────────────┘  │
│                           ↓                                     │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ 2. Update Database                                       │  │
│  │    - Update loan_applications table                      │  │
│  │    - Set pre_approval_status = Approved/Rejected/Pending │  │
│  │    - Set updated_at = NOW()                              │  │
│  │    - Store approval_reason (if provided)                 │  │
│  └──────────────────────────────────────────────────────────┘  │
│                           ↓                                     │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ 3. Check Decision Type                                   │  │
│  │    ┌─────────────────────────────────────────────────┐  │  │
│  │    │ IF Status = Approved OR Rejected                │  │  │
│  │    │   → Email should be sent                        │  │  │
│  │    │ ELSE                                            │  │  │
│  │    │   → No email                                    │  │  │
│  │    └─────────────────────────────────────────────────┘  │  │
│  └──────────────────────────────────────────────────────────┘  │
│                           ↓                                     │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ 4. Create Activity Log                                   │  │
│  │    - Log admin action                                    │  │
│  │    - Include reasoning in log                            │  │
│  │    - Timestamp and user info                             │  │
│  └──────────────────────────────────────────────────────────┘  │
│                           ↓                                     │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ 5. Generate & Send Email                                 │  │
│  │    (ONLY for Approved/Rejected decisions)               │  │
│  │                                                          │  │
│  │    ┌─────────────────────────────────────────────────┐  │  │
│  │    │ sendConsolidatedUpdateEmail()                   │  │  │
│  │    ├─ Fetch applicant info                           │  │  │
│  │    ├─ Fetch all documents                            │  │  │
│  │    ├─ Fetch all remarks                              │  │  │
│  │    ├─ Determine template (approved/rejected/pending)  │  │  │
│  │    ├─ Generate HTML email                            │  │  │
│  │    ├─ Include decision reasoning                      │  │  │
│  │    ├─ Include admin info                             │  │  │
│  │    ├─ Include next steps                             │  │  │
│  │    └─ Send via PHPMailer (SMTP)                       │  │  │
│  │    └─────────────────────────────────────────────────┘  │  │
│  └──────────────────────────────────────────────────────────┘  │
│                           ↓                                     │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ 6. Create Notification                                   │  │
│  │    - Insert into notifications table                     │  │
│  │    - Set status message                                  │  │
│  │    - Queue for display                                   │  │
│  └──────────────────────────────────────────────────────────┘  │
│                           ↓                                     │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ 7. Return Response                                        │  │
│  │    {                                                     │  │
│  │      "success": true,                                    │  │
│  │      "message": "Status updated successfully"            │  │
│  │    }                                                     │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
                              ↓
        ┌─────────────────────┴─────────────────────┐
        ↓                                           ↓
┌──────────────────────────┐         ┌──────────────────────────┐
│   EMAIL NOTIFICATION     │         │  IN-APP NOTIFICATION     │
│                          │         │                          │
│ To: applicant@email.com  │         │ User Dashboard           │
│ From: CYCLOAN            │         │ Notification Center      │
│                          │         │                          │
│ Subject: ✅ Your Loan    │         │ "Status Updated to       │
│ Application Has Been     │         │  Approved!"              │
│ Pre-Approved             │         │                          │
│                          │         │ Shows immediately        │
│ Body:                    │         │ when applicant logs in   │
│ - Greeting               │         │                          │
│ - Decision (Approved)    │         │ Persists until read      │
│ - Reasoning              │         │                          │
│ - Documents summary      │         │ Links to application     │
│ - Next steps             │         │                          │
│ - Support info           │         │                          │
│                          │         │                          │
│ Arrives immediately      │         │ Real-time display        │
│ (within seconds)         │         │                          │
└──────────────────────────┘         └──────────────────────────┘
        ↓                                           ↓
        └─────────────────────┬─────────────────────┘
                              ↓
                   [APPLICANT NOTIFIED]
                   ✓ Email received
                   ✓ In-app notification
                   ✓ Can view decision
                   ✓ Understands reasoning
```

## Data Flow Diagram

```
ADMIN2 INPUT
└── Decision Selection (Pending/Approve/Reject)
└── Decision Reasoning (0-1000 chars)
└── Application ID (hidden)
└── CSRF Token (hidden)
    ↓
    ├─ Status = Approved
    │  ├─ ALL docs must be Approved ✓
    │  ├─ Email WILL be sent ✓
    │  └─ Applicant notified ✓
    │
    ├─ Status = Rejected
    │  ├─ Docs can have any status
    │  ├─ Email WILL be sent ✓
    │  └─ Applicant notified ✓
    │
    └─ Status = Pending
       ├─ Waiting for more info
       ├─ Email NOT sent ✗
       └─ Status recorded ✓
    ↓
BACKEND PROCESSING
├─ Validate inputs
├─ Update database
├─ Log activity
├─ Generate email (if needed)
├─ Send email (if needed)
└─ Create notification
    ↓
DATABASE UPDATES
├─ loan_applications
│  ├─ pre_approval_status = [New Status]
│  ├─ approval_reason = [Reasoning text]
│  └─ updated_at = NOW()
├─ activity_logs
│  └─ [New activity entry]
├─ notifications
│  └─ [New notification]
└─ remarks (if from earlier)
   └─ [Preserved for history]
    ↓
COMMUNICATIONS
├─ EMAIL
│  ├─ Subject: Customized by status
│  ├─ Body: Includes reasoning
│  ├─ Status: Approved/Rejected/Pending
│  ├─ Recipient: applicant@email.com
│  └─ Sent: Within seconds
└─ IN-APP NOTIFICATION
   ├─ Type: Status update
   ├─ Message: Decision made
   ├─ Priority: High
   └─ Action: View application
    ↓
APPLICANT EXPERIENCE
├─ Email arrives
├─ Sees decision status
├─ Reads reasoning
├─ Understands next steps
├─ Takes appropriate action
└─ Application progresses
```

## State Machine

```
                        ┌──────────────┐
                        │   INITIAL    │
                        │   STATE      │
                        └──────┬───────┘
                               ↓
                    [Open Loan Details]
                               ↓
                  ┌──────────────────────┐
                  │  DECISION PANEL      │
                  │  DISPLAYED           │
                  │  (Ready for input)   │
                  └──────┬───────────────┘
                  ↙      ↓      ↘
         [Pending]  [Approve]  [Reject]
                ↙      ↓       ↘
    ┌──────────────────────────────────────────┐
    │ ALL BRANCHES:                            │
    │ - Status selected ✓                      │
    │ - Reasoning entered ✓                    │
    │ - Click "Submit Decision & Send Email"   │
    └──────────────────┬───────────────────────┘
                       ↓
            ┌────────────────────────┐
            │  LOADING STATE         │
            │  "Submitting decision  │
            │   & sending email..."  │
            └────────────┬───────────┘
                         ↓
            ┌────────────────────────┐
            │  BACKEND PROCESSING    │
            │  - Validate            │
            │  - Update DB           │
            │  - Generate email      │
            │  - Send email          │
            │  - Create log          │
            └────────────┬───────────┘
                         ↓
         ┌───────────────────────────┐
         │   SUCCESS STATE           │
         │  ✅ Status: Approved/...   │
         │  ✅ Email: Sent            │
         │  ✅ DB: Updated            │
         │  ✅ Log: Created           │
         └───────────────┬───────────┘
                         ↓
         ┌────────────────────────────┐
         │  MODAL REFRESH             │
         │  - Fetch updated data      │
         │  - Display new status      │
         │  - Show success message    │
         └────────────────────────────┘
```

## Email Generation & Sending

```
sendConsolidatedUpdateEmail($conn, $applicationId, $consolidatedUpdates)
└─ Fetch applicant info (name, email, etc.)
│  ├─ SELECT * FROM users1 WHERE id = $userId
│  └─ Store in $applicant
├─ Fetch documents status
│  ├─ SELECT * FROM documents WHERE application_id = $appId
│  └─ Store in $allDocuments
├─ Fetch remarks history
│  ├─ SELECT * FROM remarks WHERE application_id = $appId
│  └─ Store in $allRemarks
├─ Determine template type
│  ├─ IF status = 'Approved' → 'approved'
│  ├─ ELSE IF status = 'Rejected' → 'rejected'
│  └─ ELSE → 'pending'
├─ Build email content
│  ├─ Header (greeting, status)
│  ├─ Decision section (reasoning, details)
│  ├─ Documents summary (status of each)
│  ├─ Admin info (who, when)
│  ├─ Remarks history (if any)
│  └─ Footer (support info, next steps)
├─ Generate HTML template
│  └─ generateEmailTemplate($name, $content)
├─ Send email via PHPMailer
│  ├─ $mail->isSMTP()
│  ├─ $mail->Host = 'smtp.gmail.com'
│  ├─ $mail->addAddress($to)
│  ├─ $mail->Subject = "✅ Your Loan Application Has Been Pre-Approved"
│  ├─ $mail->Body = $emailBody
│  └─ $mail->send()
└─ Return success/failure
   └─ true = email sent
   └─ false = email failed
```

## Validation Flow

```
USER SUBMITS FORM
     ↓
     ├─ CSRF Token Check
     │  ├─ Does token exist? → YES
     │  └─ Is token valid? → YES
     │                    → NO → Error: "Security validation failed"
     ├─ Status Selection Check
     │  ├─ Is status selected? → YES
     │  └─                    → NO → Error: "Please select a decision"
     ├─ IF Status = "Approved"
     │  ├─ Are ALL docs approved?
     │  │  ├─ YES → Continue
     │  │  └─ NO → Error: "All documents must be approved first"
     │  └─ Is reasoning provided?
     │     ├─ YES → Continue
     │     └─ NO → Error: "Reasoning required for Approved"
     ├─ IF Status = "Rejected"
     │  └─ Is reasoning provided?
     │     ├─ YES → Continue
     │     └─ NO → Error: "Reasoning required for Rejected"
     ├─ Character Limit Check
     │  ├─ Length ≤ 1000? → YES
     │  └─              → NO → Truncated (browser enforces maxlength)
     ↓
✅ ALL VALIDATIONS PASSED
     ↓
     Submit to backend for processing
```

## Summary

The enhanced decision submission system provides:

1. **Unified Interface**: Single panel for all decision-making
2. **Smart Validation**: Ensures complete, valid submissions
3. **Automatic Email**: No manual sending required
4. **Complete Communication**: Applicant receives timely notifications
5. **Full Audit Trail**: Activity logged for compliance
6. **Professional Design**: Modern, intuitive user experience

The entire process is automated and secure, providing admins with a streamlined workflow while ensuring applicants receive immediate, professional communication about their applications.
