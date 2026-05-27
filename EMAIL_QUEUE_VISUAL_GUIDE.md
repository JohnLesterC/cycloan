# Email Consolidation System - Visual Workflows

## System Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                    ADMIN DASHBOARD                               │
│              (admin2_dashboard.php)                             │
└─────────────────────────────────────────────────────────────────┘
                           │
        ┌──────────────────┼──────────────────┐
        │                  │                  │
        ▼                  ▼                  ▼
   ┌────────┐         ┌────────┐         ┌────────┐
   │Reject  │         │Approve │         │Add     │
   │Doc 1   │         │Doc 2   │         │Remark  │
   └────────┘         └────────┘         └────────┘
        │                  │                  │
        │ Before:          │ Before:         │ Before:
        │ Send email 1     │ Send email 2    │ Send email 3
        │ immediately      │ immediately     │ immediately
        │                  │                 │
        └──────────────────┼─────────────────┘
                           │ After:
                           │ Queue event
                           ▼
        ┌──────────────────────────────────────┐
        │        EMAIL QUEUE (Database)        │
        │                                      │
        │  Collects events for 5 minutes:    │
        │  ├─ event_id: 1                    │
        │  ├─ event_id: 2                    │
        │  └─ event_id: 3                    │
        │  batch_id: BATCH_APP_001           │
        │  status: pending                    │
        └──────────────────────────────────────┘
                           │
                   Wait 5 minutes
                           │
                           ▼
        ┌──────────────────────────────────────┐
        │   Cron Job (every 5 minutes)         │
        │  process_email_queue.php             │
        │                                      │
        │  1. Find pending batch               │
        │  2. Get all events from queue        │
        │  3. Consolidate into email data      │
        │  4. Call sendConsolidatedEmail()     │
        │  5. Mark as 'sent'                   │
        └──────────────────────────────────────┘
                           │
                           ▼
        ┌──────────────────────────────────────┐
        │      sendConsolidatedEmail()          │
        │    (Existing function)               │
        │                                      │
        │  - Gets user email                   │
        │  - Builds email body with all info   │
        │  - Sends via PHPMailer               │
        │  - Logs result                       │
        └──────────────────────────────────────┘
                           │
                           ▼
        ┌──────────────────────────────────────┐
        │         USER EMAIL INBOX             │
        │                                      │
        │  1 Email with:                       │
        │  ✓ All doc statuses                  │
        │  ✓ Rejection reasons                 │
        │  ✓ Pre-approval decision             │
        │  ✓ Admin remarks                     │
        │  ✓ Next steps                        │
        └──────────────────────────────────────┘
```

---

## Timeline Comparison

### BEFORE (Current - Problem)

```
5:00:00 - Admin clicks "Reject" for Doc 1
5:00:01 - 📧 Email sent to user (Doc 1 rejected)
5:00:05 - Admin clicks "Reject" for Doc 2
5:00:06 - 📧 Email sent to user (Doc 2 rejected)
5:00:10 - Admin clicks "Reject" for Doc 3
5:00:11 - 📧 Email sent to user (Doc 3 rejected)
5:00:15 - Admin completes "Pre-Approval"
5:00:16 - 📧 Email sent to user (Pre-Approval)
5:00:20 - Admin adds remark
5:00:21 - 📧 Email sent to user (New Remark)

Result: 5 EMAILS IN 20 SECONDS ❌
User spam folder: YES
Mail filter issues: LIKELY
```

### AFTER (Solution - Expected)

```
5:00:00 - Admin clicks "Reject" for Doc 1
5:00:01 - 📋 Event queued (no email)
5:00:05 - Admin clicks "Reject" for Doc 2
5:00:06 - 📋 Event queued (no email)
5:00:10 - Admin clicks "Reject" for Doc 3
5:00:11 - 📋 Event queued (no email)
5:00:15 - Admin completes "Pre-Approval"
5:00:16 - 📋 Event queued (no email)
5:00:20 - Admin adds remark
5:00:21 - 📋 Event queued (no email)
5:05:00 - 🕐 Cron job runs
5:05:02 - 📊 Consolidates 5 events
5:05:03 - 📧 1 EMAIL sent (all updates)

Result: 1 EMAIL IN ~5 MINUTES ✅
User spam folder: NO
Mail filter issues: UNLIKELY
Professional appearance: YES
```

---

## Data Flow Diagram

```
EVENT GENERATION
   │
   ├─ Admin rejects document
   │  └─ addToEmailQueue()
   │     └─ INSERT INTO email_queue
   │
   ├─ Admin sets pre-approval decision
   │  └─ addToEmailQueue()
   │     └─ INSERT INTO email_queue
   │
   └─ Admin adds remarks
      └─ addToEmailQueue()
         └─ INSERT INTO email_queue

              ↓

EMAIL_QUEUE TABLE (Database)
   │
   ├─ event_id: 1, type: document_rejected
   ├─ event_id: 2, type: document_rejected
   ├─ event_id: 3, type: pre_approval_status
   └─ batch_id: BATCH_APP_001, status: pending

              ↓ (every 5 minutes)

CRON JOB (process_email_queue.php)
   │
   ├─ Query: pending batches ready to send
   ├─ For each batch:
   │  ├─ Get all events from email_queue
   │  ├─ Parse event_data JSON
   │  ├─ Build consolidated updates array
   │  └─ Call sendConsolidatedUpdateEmail()
   │
   └─ Update email_batch_log: status = 'sent'

              ↓

sendConsolidatedUpdateEmail()
   │
   ├─ Get user email from loan_applications
   ├─ Build email HTML content
   │  ├─ Document summary
   │  ├─ Rejection reasons
   │  ├─ Pre-approval decision
   │  ├─ All remarks
   │  └─ Next steps
   ├─ Create email subject
   └─ Send via PHPMailer

              ↓

EMAIL SENT
   │
   ├─ User receives 1 professional email
   ├─ Contains all updates
   └─ Marked as sent in database
```

---

## Event Processing State Machine

```
┌────────────────────────────────────────────────────┐
│              EVENT LIFECYCLE                        │
└────────────────────────────────────────────────────┘

START (Event Created)
   │
   ▼
┌─────────────────────────┐
│ IN QUEUE                 │ ← email_queue table
│ processed: FALSE         │   waiting for batch
│ batch_id: BATCH_xxx      │   processing
└──────────────┬──────────┘
               │
               │ (5 minutes elapsed)
               │
               ▼
┌─────────────────────────┐
│ CONSOLIDATING           │ ← process_email_queue.php
│ Building email from      │   running
│ all events in batch      │
└──────────────┬──────────┘
               │
               │ (email built)
               │
               ▼
┌─────────────────────────┐
│ SENDING                 │ ← sendConsolidatedEmail()
│ Via PHPMailer           │   executing
│ To: user@email.com      │
└──────────────┬──────────┘
               │
         ┌─────┴──────┐
         │ Success    │ Failure
         ▼            ▼
    ┌────────┐   ┌────────────┐
    │ SENT   │   │ FAILED     │
    │status: │   │ retry_count│
    │'sent'  │   │ +1         │
    └────────┘   └──────┬─────┘
         │              │
         │              │ < max_retries?
         │              │
         │         ┌────┴────┐
         │         │ NO      │ YES
         │         ▼         ▼
         │      PERMANENT   (retry next cycle)
         │      FAILURE
         │      status:'failed'
         │
         ▼
    END (Clean up old records)
    (after 30 days)
```

---

## Database Schema Diagram

```
USERS1 (existing)
├─ id (PK)
├─ email
├─ first_name
├─ last_name
└─ ...

LOAN_APPLICATIONS (existing)
├─ application_id (PK)
├─ user_id (FK → users1.id)
├─ ...

EMAIL_QUEUE (NEW)
├─ queue_id (PK) ──────────────┐
├─ application_id (FK) ────────┤─────► Connected to same app
├─ user_id (FK)────────────────┤
├─ event_type
├─ event_data (JSON)
├─ batch_id (FK) ──────────────┐
├─ processed (FALSE/TRUE)      │
├─ created_at                  │
└─ sent_at                     │
                               │
EMAIL_BATCH_LOG (NEW)         │
├─ batch_id (PK) ──────────────┘
├─ application_id (FK)
├─ user_id (FK)
├─ batch_start_time
├─ status (pending/sent/failed)
├─ event_count
├─ sent_timestamp
└─ error_message

EMAIL_QUEUE_SETTINGS (NEW)
├─ setting_id (PK)
├─ setting_key (UNIQUE)
├─ setting_value
├─ description
└─ data_type
```

---

## Queue Window Concept

```
Timeline (5-minute batch window)

5:00:00 ──────────────────── Batch Created
         │
5:00:01  ├─ Event 1 added (Doc rejected)
         │
5:00:05  ├─ Event 2 added (Doc rejected)
         │
5:00:10  ├─ Event 3 added (Pre-approval)
         │
5:00:15  ├─ Event 4 added (Remark)
         │
5:00:20  ├─ Event 5 added (Note)
         │
5:04:59  ├─ Batch still collecting...
         │
5:05:00  ├─ Window closed (5 minutes elapsed)
         │
5:05:05 ──────────────────── Processing starts
        │
        └─ 5 events consolidated
        └─ 1 email built
        └─ Email sent
        └─ Batch marked 'sent'

═════════════════════════════════════════════

If new event arrives after batch closes:

5:05:30  ├─ Event 6 added (New rejection)
         │
5:05:30  ├─ New Batch Created (for Event 6)
         │
5:10:30  └─ New batch ready (5 min later)
         └─ Consolidated (just Event 6)
         └─ Email sent
```

---

## Configuration Impact Matrix

```
Setting                    Impact on:           Default
──────────────────────────────────────────────────────
batch_delay_minutes        When to process       5 min
                          Email speed           (1-10 min)

batch_window_seconds       How long to           300 sec
                          collect events        (5 min)

enable_queue              Queue on/off           1 (on)

max_batch_size            Max events            50
                          per batch

max_retry_attempts        Retry failed          3
                          emails


EXAMPLES:

Scenario 1: Faster emails (1 min)
  batch_delay_minutes = 1
  ↓
  Process queue every 1 minute instead of 5

Scenario 2: More consolidation (10 min)
  batch_window_seconds = 600
  ↓
  Collect events for 10 minutes instead of 5

Scenario 3: Large batches
  batch_window_seconds = 600
  max_batch_size = 100
  ↓
  Collect many events, send once daily
```

---

## Error Handling Flow

```
Process Email Queue
   │
   ▼
Get pending batch
   │
   ├─ Not found? → Log & Exit
   │
   └─ Found
      │
      ▼
   Get batch events
      │
      ├─ Empty? → Mark as sent & Exit
      │
      └─ Found events
         │
         ▼
      Build consolidated email
         │
         ├─ Error? → Mark as failed + log
         │
         └─ Success
            │
            ▼
         Send email
            │
            ├─ Failed? → Mark as failed + increment retry
            │           ↓
            │           Check retry count
            │           ├─ < max_retries? → Try next cycle
            │           └─ >= max_retries? → Mark permanent failure
            │
            └─ Success → Mark as sent


Summary:
✓ All errors logged
✓ Failed batches automatically retried
✓ Permanent failures logged with reason
✓ No emails lost
```

---

## User Experience Timeline

```
User's Experience (from their perspective)

─────────────────────────────────────────

Before System:
5:00 - Inbox: 📧📧📧 (3 emails about same app)
5:00 - Inbox: 📧📧 (2 more emails)
5:00 - Inbox: 📧 (1 more email)
Result: 6 emails in 1 minute 😱 Looks like spam

After System:
5:00 - Dashboard: Notices being updated ✓
5:05 - Inbox: 📧 (1 email with everything)
Result: 1 professional email in 5 minutes 😊 Much better

─────────────────────────────────────────
```

---

## Integration Points

```
admin2_dashboard.php
   │
   ├─ Document Status Update
   │  └─ addToEmailQueue() instead of sendConsolidatedEmail()
   │
   ├─ Pre-Approval Decision
   │  └─ addToEmailQueue() instead of sendConsolidatedEmail()
   │
   └─ Remark Addition
      └─ addToEmailQueue() instead of sendEmail()

Email Queue Database
   ├─ Stores events
   ├─ Tracks batches
   └─ Configuration

process_email_queue.php (Cron)
   ├─ Runs every 5 min
   ├─ Reads pending batches
   ├─ Consolidates events
   └─ Calls sendConsolidatedEmail()

sendConsolidatedEmail() (Existing)
   ├─ Unchanged
   ├─ Builds email content
   ├─ Sends via PHPMailer
   └─ Same as before
```

---

This visual guide should help understand how the system works!
