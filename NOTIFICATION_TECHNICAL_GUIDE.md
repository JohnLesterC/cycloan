# Notification System - Technical Implementation Guide

## Overview

The notification system has been enhanced to notify users and admins about critical credit investigation milestones:

- Final loan amount approval
- Recommended loan term assignment

## Architecture

### Core Components

#### 1. NotificationManagerAPI Class (`notification_manager.php`)

Main class for creating and managing notifications.

**Key Methods:**

```php
// Create a notification
public function createNotification(
    $user_id,
    $title,
    $message,
    $type = 'info',
    $category = 'system',
    $priority = 'normal',
    $action_url = null,
    $expires_at = null
): bool

// Notify about final amount approval
public function notifyFinalAmountApproved(
    $user_id,
    $application_id,
    $final_amount,
    $admin_ids = array()
): bool

// Notify about term length assignment
public function notifyTermLengthAssigned(
    $user_id,
    $application_id,
    $term_length,
    $admin_ids = array()
): bool

// Get all admin IDs
public function getAdminUserIds(): array
```

### Database Schema

Uses existing `user_notifications` table:

```sql
CREATE TABLE user_notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    user_type VARCHAR(50) DEFAULT 'user',
    type_id INT,
    title VARCHAR(255),
    message TEXT,
    short_message VARCHAR(500),
    event_type VARCHAR(100),
    priority VARCHAR(50),
    action_url VARCHAR(500),
    is_read BOOLEAN DEFAULT FALSE,
    read_at DATETIME,
    expires_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME,
    FOREIGN KEY (type_id) REFERENCES notification_types(type_id),
    FOREIGN KEY (user_id) REFERENCES users1(id)
);
```

## Implementation Details

### Admin1 Dashboard Integration

**Location**: `admin1_dashboard.php` (Lines ~2400-2430)

**When Credit Investigation is Completed:**

```php
// After email is sent and database updated:
try {
    $notificationManager = new NotificationManagerAPI($conn);
    $adminIds = $notificationManager->getAdminUserIds();

    // Notify about final amount
    $notificationManager->notifyFinalAmountApproved(
        $currentApp['user_id'],
        $currentApp['application_id'],
        $finalLoanAmount,
        $adminIds
    );

    // Notify about term length
    $notificationManager->notifyTermLengthAssigned(
        $currentApp['user_id'],
        $currentApp['application_id'],
        $termLength,
        $adminIds
    );
} catch (Exception $notifError) {
    error_log("Warning: Notification creation failed: " . $notifError->getMessage());
}
```

### Notification Type Parameters

#### Type: 'success'

Used for positive outcomes like:

- Loan approved
- Amount finalized
- Term assigned

#### Category: 'loan'

Groups notifications by domain:

- loan: Application/loan related
- payment: Payment related
- document: Document related
- account: Account related
- system: System notifications

#### Priority Levels

- **high**: Requires immediate attention (e.g., final amount approval for user)
- **normal**: Standard priority (e.g., term assignment, admin notifications)
- **low**: Informational only

## API Endpoints

### POST Endpoints (via notification_manager.php)

#### Create Final Amount Approval Notification

```
POST /notification_manager.php
Content-Type: application/json

{
  "action": "notify_final_amount_approved",
  "data": {
    "application_id": "APP-2025-001",
    "final_amount": 50000,
    "user_id": 123  // Optional - defaults to session user_id
  }
}

Response: { "success": true }
```

#### Create Term Length Assignment Notification

```
POST /notification_manager.php
Content-Type: application/json

{
  "action": "notify_term_length_assigned",
  "data": {
    "application_id": "APP-2025-001",
    "term_length": 12,
    "user_id": 123  // Optional - defaults to session user_id
  }
}

Response: { "success": true }
```

## Notification Flow Sequence

### Step 1: Admin1 Completes Credit Investigation

```
Admin1 Form Submission
  ├── application_id
  ├── credit_status (e.g., "Completed")
  ├── final_loan_amount (₱50,000)
  └── term_length (12 months)
```

### Step 2: Database Update

```sql
UPDATE loan_applications
SET credit_investigation_status = 'Completed',
    final_loan_amount = 50000,
    term_length = '12',
    updated_at = NOW()
WHERE application_id = 'APP-2025-001'
```

### Step 3: Email Notification

User receives detailed approval email with:

- Application ID
- Status (Approved)
- Final Amount
- Loan Term
- Next steps

### Step 4: System Notifications

```
Notification 1: Final Amount Approved
├── To: User (Priority: High)
├── Title: "Final Loan Amount Approved"
└── Message: "Your final loan amount of ₱50,000.00 has been approved..."

Notification 2: Term Length Assigned
├── To: User (Priority: Normal)
├── Title: "Recommended Loan Term Assigned"
└── Message: "A loan term of 12 months has been recommended..."

Notification 3: Final Amount Approved (Admin1)
├── To: Admin1 (Priority: Normal)
└── Message: "Final loan amount of ₱50,000.00 has been approved..."

Notification 4: Term Length Assigned (Admin1)
├── To: Admin1 (Priority: Normal)
└── Message: "Term length of 12 months has been assigned..."

Notification 5-8: Same for Admin2, Superadmin, etc.
```

## File Dependencies

```
admin1_dashboard.php
├── notification_manager.php (NEW REQUIRE)
├── NotificationManager.php
├── CYCLOAN_db.php
└── phpmailer (for email)

admin2_dashboard.php
├── notification_manager.php (NEW REQUIRE)
├── NotificationManager.php
└── CYCLOAN_db.php

user_dashboard.php
├── NotificationManager.php
└── Notification Center Integration
```

## Error Handling Strategy

### Graceful Degradation

If notification creation fails:

1. Error is logged with context
2. Main operation (credit investigation) continues
3. Email is already sent, so user is informed
4. Admin sees error in logs but system remains stable

### Error Logging

```php
try {
    // Create notifications
} catch (Exception $notifError) {
    error_log("Warning: Notification failed: " . $notifError->getMessage());
    // Continue - don't interrupt main flow
}
```

## Monitoring & Debugging

### Check Notifications Created

```sql
SELECT * FROM user_notifications
WHERE application_id = 'APP-2025-001'
ORDER BY created_at DESC;
```

### Monitor Failed Notifications

```
grep "Notification" debug_log.txt | grep -i error
```

### Verify Admin IDs Retrieved

```sql
SELECT id FROM admins
UNION
SELECT id FROM superadmins;
```

## Security Considerations

1. **CSRF Protection**: Already handled by admin dashboard
2. **User Isolation**: Notifications are user-specific
3. **Admin Verification**: Only admin tables are queried for admin notifications
4. **Input Validation**: Amount and term_length are validated in admin1_dashboard
5. **SQL Injection Protection**: Prepared statements used throughout

## Performance Impact

- **Minimal**: Notification creation adds ~20-50ms per notification
- **Batch Processing**: Multiple admins notified in sequence, not parallel
- **Database**: Indexes on `user_id` and `application_id` should be in place

## Testing Scenarios

### Scenario 1: Successful Notification Creation

```
Input: Complete credit investigation with final amount and term
Expected:
  - 2 notifications for user (high priority)
  - 2 notifications per admin
  - All marked as success in logs
```

### Scenario 2: Admin Count = 0

```
Input: No admins in database
Expected:
  - User notifications created successfully
  - Admin array is empty, but no error
```

### Scenario 3: Database Connection Loss

```
Input: Notification creation when DB connection fails
Expected:
  - Exception caught and logged
  - Main credit investigation not affected
  - User still receives email
```

### Scenario 4: Malformed Request

```
Input: Missing required parameters
Expected:
  - Validation fails
  - Error logged
  - JSON error response sent
```

## Future Enhancements

1. **Email Notifications**: Forward to email in addition to in-app
2. **SMS Notifications**: Critical updates via SMS
3. **Push Notifications**: Mobile app integration
4. **Notification Digest**: Daily/weekly summary emails
5. **User Preferences**: Allow users to control notification frequency
6. **Webhook Support**: External system integration
7. **Batch Notifications**: Group related notifications
8. **Analytics**: Track notification engagement

## Troubleshooting

### Notifications Not Appearing for User

1. Check: Is `user_notifications` table populated?
2. Check: Is user_id correct?
3. Check: Is `expires_at` NULL or in future?
4. Check: Is `deleted_at` NULL?
5. Check: notification_types table has required types

### Notifications Not Sent to Admins

1. Check: Are there records in `admins` table?
2. Check: Are there records in `superadmins` table?
3. Check: getAdminUserIds() returns empty array?
4. Check: Database connection active when calling notification method?

### Notification Creation Fails Silently

1. Check: debug_log.txt for error messages
2. Check: Exception handling is working correctly
3. Check: Application continues despite error?
4. Check: Email was still sent to user?
