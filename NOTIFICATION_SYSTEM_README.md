# CYCLOAN Notification System Documentation

## Overview

The CYCLOAN notification system provides real-time notifications for users and admins across the platform. Notifications are triggered for key events like loan calculations, applications, status changes, payments, and document uploads.

## System Architecture

### Components

1. **notification_manager.php** - Core notification class with all business logic
2. **notification_center.php** - Frontend notification UI component (bell icon & dropdown)
3. **notifications.php** - Full notifications page for viewing all notifications
4. **Database Table** - `notifications` table in `cycloan_db`

### Database Schema

```sql
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','warning','success','error','reminder') DEFAULT 'info',
  `category` enum('loan','payment','document','account','system') DEFAULT 'system',
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `action_url` varchar(500) DEFAULT NULL,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_type` (`type`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_user_unread` (`user_id`, `is_read`, `created_at`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users1` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

## Usage Guide

### Including in Your Page

To add notifications to any page, include the notification center in the header:

```php
<?php
require_once 'notification_manager.php';
?>

<!-- Include in your header -->
<?php include 'notification_center.php'; ?>
```

### Notification Types

1. **Type** - Visual indicator of severity:

   - `info` - Blue, informational message
   - `success` - Green, successful action
   - `warning` - Yellow, warning message
   - `error` - Red, error message
   - `reminder` - Gray, reminder

2. **Category** - Classification of notification:

   - `loan` - Loan-related events
   - `payment` - Payment-related events
   - `document` - Document-related events
   - `account` - Account-related events
   - `system` - System-related events

3. **Priority** - Urgency level:
   - `low` - Non-urgent
   - `normal` - Standard
   - `high` - Important
   - `urgent` - Critical

## API Functions

### NotificationManager Class

#### Creating Notifications

```php
$notificationManager = new NotificationManager($conn);

// Generic notification
$notificationManager->createNotification(
    $user_id,           // User ID
    $title,             // Notification title
    $message,           // Notification message
    $type,              // 'info', 'success', 'warning', 'error', 'reminder'
    $category,          // 'loan', 'payment', 'document', 'account', 'system'
    $priority,          // 'low', 'normal', 'high', 'urgent'
    $action_url,        // Optional URL to action
    $expires_at         // Optional expiration datetime
);
```

#### Specialized Notification Functions

```php
// Loan Calculation Notification
$notificationManager->notifyLoanCalculation(
    $user_id,
    $loan_type,          // e.g., 'Individual', 'Business'
    $amount,             // Loan amount
    $term_length,        // Term in months
    $repayment_frequency // 'Monthly', 'Quarterly', 'Annually'
);

// Loan Application Submission
$notificationManager->notifyLoanApplicationSubmitted(
    $user_id,
    $application_id,
    $loan_type,
    $amount,
    $admin_ids           // Array of admin IDs to notify
);

// Loan Status Change
$notificationManager->notifyLoanStatusChange(
    $user_id,
    $application_id,
    $new_status,         // 'Active', 'Pending', 'Closed', 'Rejected'
    $admin_ids           // Array of admin IDs
);

// Payment Due Reminder
$notificationManager->notifyPaymentDue(
    $user_id,
    $loan_id,
    $due_amount,
    $due_date,           // DateTime string
    $admin_ids
);

// Document Upload
$notificationManager->notifyDocumentUploaded(
    $user_id,
    $document_type,      // e.g., 'Proof of Income'
    $application_id,
    $admin_ids
);

// Credit Investigation Update
$notificationManager->notifyCreditInvestigationUpdate(
    $user_id,
    $application_id,
    $investigation_status,  // 'Pending', 'Completed', 'Failed'
    $admin_ids
);
```

#### Retrieving Notifications

```php
// Get unread notifications
$unread = $notificationManager->getUnreadNotifications($user_id, $limit = 10);

// Get all notifications
$all = $notificationManager->getAllNotifications($user_id, $limit = 50, $offset = 0);

// Get unread count
$count = $notificationManager->getUnreadCount($user_id);

// Get admin user IDs
$admin_ids = $notificationManager->getAdminUserIds();
```

#### Managing Notifications

```php
// Mark single notification as read
$notificationManager->markAsRead($notification_id, $user_id);

// Mark all notifications as read
$notificationManager->markAllAsRead($user_id);

// Delete notification
$notificationManager->deleteNotification($notification_id, $user_id);
```

## REST API Endpoints

### GET Requests

#### Get Unread Notifications

```
GET /notification_manager.php?action=get_unread
Response: { success: true, notifications: [...] }
```

#### Get Unread Count

```
GET /notification_manager.php?action=get_unread_count
Response: { success: true, count: 5 }
```

#### Mark as Read

```
GET /notification_manager.php?action=mark_as_read&notification_id=123
Response: { success: true }
```

#### Mark All as Read

```
GET /notification_manager.php?action=mark_all_as_read
Response: { success: true }
```

### POST Requests

All POST requests require JSON payload and return JSON responses.

#### Loan Calculation Notification

```json
POST /notification_manager.php
{
  "action": "notify_loan_calculation",
  "data": {
    "loan_type": "Individual",
    "amount": 50000,
    "term_length": 12,
    "repayment_frequency": "Monthly"
  }
}
```

#### Loan Application Notification

```json
POST /notification_manager.php
{
  "action": "notify_loan_application",
  "data": {
    "application_id": "APP001",
    "loan_type": "Business",
    "amount": 100000
  }
}
```

#### Status Change Notification

```json
POST /notification_manager.php
{
  "action": "notify_status_change",
  "data": {
    "application_id": "APP001",
    "new_status": "Active"
  }
}
```

#### Payment Due Notification

```json
POST /notification_manager.php
{
  "action": "notify_payment_due",
  "data": {
    "loan_id": 1,
    "due_amount": 5000,
    "due_date": "2024-12-15"
  }
}
```

#### Document Upload Notification

```json
POST /notification_manager.php
{
  "action": "notify_document_uploaded",
  "data": {
    "document_type": "Proof of Income",
    "application_id": "APP001"
  }
}
```

#### Credit Investigation Notification

```json
POST /notification_manager.php
{
  "action": "notify_credit_investigation",
  "data": {
    "application_id": "APP001",
    "investigation_status": "Completed"
  }
}
```

## Frontend Integration

### JavaScript Functions

The notification center provides several JavaScript functions:

```javascript
// Load notifications from server
loadNotifications();

// Toggle notification dropdown
toggleNotificationDropdown();

// Close notification dropdown
closeNotificationDropdown();

// Mark notification as read
markNotificationAsRead(notificationId);

// Update badge with unread count
updateNotificationBadge();

// View all notifications
viewAllNotifications(); // Redirects to notifications.php
```

### CSS Classes

Notification styling uses these classes:

- `.notification-bell` - Bell icon
- `.notification-badge` - Unread count badge
- `.notification-dropdown` - Dropdown container
- `.notification-item` - Individual notification
- `.notification-item.unread` - Unread notification styling
- `.notification-item-icon` - Icon container
- `.notification-item-content` - Content container

## Implementation Examples

### Example 1: Loan Calculator Notification

In `user_dashboard.php`, when a loan is calculated:

```javascript
function createLoanCalculationNotification(
  loanType,
  amount,
  termLength,
  repaymentFrequency
) {
  const notificationData = {
    loan_type: loanType,
    amount: amount,
    term_length: termLength,
    repayment_frequency: repaymentFrequency,
  };

  fetch("notification_manager.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      action: "notify_loan_calculation",
      data: notificationData,
    }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        loadNotifications(); // Refresh notification bell
      }
    });
}
```

### Example 2: Loan Application Submission

In `loan_register_process.php`:

```php
<?php
require_once 'notification_manager.php';

$notificationManager = new NotificationManager($conn);
$adminIds = $notificationManager->getAdminUserIds();

// After successful application insertion:
$notificationManager->notifyLoanApplicationSubmitted(
    $userId,
    $applicationId,
    $loanType,
    $loanAmount,
    $adminIds
);
?>
```

### Example 3: Status Change Notification

In `Superadmin_dashboard.php`:

```php
<?php
require_once 'notification_manager.php';

$notificationManager = new NotificationManager($conn);

// After updating loan status:
$notificationManager->notifyLoanStatusChange(
    $userId,
    $applicationId,
    'Active',
    $notificationManager->getAdminUserIds()
);
?>
```

## Features

### Auto-Refresh

- Notifications auto-refresh every 30 seconds
- Badge count updates in real-time

### Read Status Tracking

- Marks notifications as read
- Tracks read timestamp

### Priority Sorting

- High-priority notifications appear first
- Sorted by timestamp within priority

### Expiration

- Notifications can have expiration dates
- Expired notifications automatically hidden

### Categories

- Easily filter notifications by category
- Visual distinction by type

### Action URLs

- Direct links to related items
- Click notification to navigate

## Best Practices

1. **Always include admin IDs** when notifying about user actions that need admin attention
2. **Use appropriate priority levels** - urgent for critical, normal for standard
3. **Set expiration dates** for time-sensitive notifications
4. **Include action URLs** to help users navigate to relevant pages
5. **Use meaningful titles** - users scan the title first
6. **Keep messages concise** - limit to 2-3 lines when possible
7. **Test notifications** in different scenarios
8. **Monitor notification performance** - check database performance with indexed queries

## Troubleshooting

### Notifications Not Appearing

1. Check `notifications` table exists with correct schema
2. Verify user is logged in (check `$_SESSION['user_id']`)
3. Check browser console for JavaScript errors
4. Verify database connection in `CYCLOAN_db.php`

### Badge Count Wrong

1. Run: `SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0`
2. Check if notifications are being marked as read properly
3. Verify expiration dates are set correctly

### Performance Issues

1. Ensure database indexes exist on `user_id`, `is_read`, `created_at`
2. Limit queries with pagination
3. Archive old notifications periodically

## Future Enhancements

1. Email notifications for critical events
2. SMS notifications for urgent items
3. Notification preferences/settings per user
4. Notification grouping/threading
5. Real-time WebSocket notifications
6. Notification sound/desktop alerts
7. Notification templates
8. Bulk notification creation

## Security Considerations

1. **Authorization** - All queries check `user_id` ownership
2. **SQL Injection** - All queries use prepared statements
3. **XSS Prevention** - HTML is escaped in output
4. **Data Validation** - All inputs validated before storage
5. **Rate Limiting** - Consider implementing rate limits for API

## Database Maintenance

### Clean Up Expired Notifications

```sql
DELETE FROM notifications
WHERE expires_at IS NOT NULL
AND expires_at < NOW();
```

### Archive Old Notifications

```sql
DELETE FROM notifications
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
AND is_read = 1;
```

### Backup Notifications

```bash
mysqldump -u user -p database notifications > notifications_backup.sql
```

## Files Modified/Created

### New Files

- `notification_manager.php` - Core notification class
- `notification_center.php` - Frontend notification UI
- `notifications.php` - Notifications page
- `NOTIFICATION_SYSTEM_README.md` - This documentation

### Modified Files

- `user_dashboard.php` - Added notification center & loan calculator notifications
- Database schema updated with `notifications` table

## Support & Questions

For issues or questions about the notification system:

1. Check this documentation
2. Review example implementations
3. Check error logs in `debug.log`
4. Verify database connection and schema
5. Test with browser developer tools

---

**Version:** 1.0  
**Last Updated:** November 2025  
**Maintained by:** CYCLOAN Development Team
