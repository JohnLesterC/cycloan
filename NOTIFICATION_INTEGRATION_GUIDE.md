# CYCLOAN Complete Notification System - Integration Guide

## Overview

This guide provides everything needed to integrate the complete notification system into CYCLOAN admin pages and dashboards. The system includes:

✅ **Notification Widget** - Bell icon with dropdown for quick access
✅ **Notification Center** - Full-featured notification dashboard
✅ **Notification API** - Backend endpoints for all operations
✅ **Role-Based Access** - Admin1, Admin2, SuperAdmin, Users
✅ **Real-Time Updates** - Auto-refresh unread counts
✅ **Responsive Design** - Mobile, tablet, desktop
✅ **Accessibility** - ARIA labels, keyboard navigation
✅ **Security** - Session-based authentication

---

## Quick Start (5 minutes)

### Step 1: Include CSS & JavaScript

Add to the `<head>` of your admin pages:

```html
<!-- Notification System CSS -->
<link
  rel="stylesheet"
  href="CSS/notification_system.css?v=<?php echo time(); ?>"
/>
```

Add to the `<body>` before closing `</body>`:

```html
<!-- Font Awesome Icons (if not already included) -->
<link
  rel="stylesheet"
  href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
/>
```

### Step 2: Add Widget to Header

In your admin page's header (typically after profile section):

```php
<?php
    require_once 'notification_widget.php';
    echo renderNotificationWidget($conn, $_SESSION['user_id'], $_SESSION['role'] ?? 'user');
?>
```

### Step 3: Link to Notification Center

Add to your navigation menu:

```php
<a href="notifications_enhanced.php" class="nav-link">
    <i class="fas fa-bell"></i> Notifications
</a>
```

### Step 4: Done! ✅

The notification widget will now appear in your header with:

- Bell icon with unread count badge
- Clickable dropdown showing recent notifications
- Options to mark as read, delete, or view all
- Auto-refresh every 30 seconds

---

## File Structure

```
CYCLOAN/
├── notification_widget.php                 # Widget component (include in pages)
├── notification_api.php                    # Backend API (handles AJAX)
├── notifications_enhanced.php              # Full notification center page
├── NotificationManager.php                 # Database operations class
├── CSS/
│   └── notification_system.css             # All styling
├── JAVASCRIPT/
│   └── notification_handlers.js            # Additional JS (optional)
└── [admin pages]/
    ├── admin1_dashboard.php                # Include widget
    ├── admin2_dashboard.php                # Include widget
    ├── Superadmin_dashboard.php            # Include widget
    ├── applicant.php                       # Include widget
    ├── active_records.php                  # Include widget
    ├── pending_records.php                 # Include widget
    ├── closed_records.php                  # Include widget
    ├── reports_record.php                  # Include widget
    ├── history_activity.php                # Include widget
    └── manage_credit_points.php            # Include widget
```

---

## Integration Steps for Each Page

### For Admin 1 Dashboard

```php
<?php
    session_start();
    require_once 'CYCLOAN_db.php';
    require_once 'notification_widget.php';

    // ... your existing code ...
?>
<!DOCTYPE html>
<html>
<head>
    <!-- ... existing head content ... -->
    <link rel="stylesheet" href="CSS/notification_system.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- ... navigation ... -->
    <div class="header">
        <!-- Your profile section -->
        <div class="profile-container">
            <!-- ... profile code ... -->
        </div>

        <!-- ADD THIS: Notification Widget -->
        <?php echo renderNotificationWidget($conn, $_SESSION['user_id'], 'admin1'); ?>
    </div>

    <!-- ... rest of page ... -->
</body>
</html>
```

### For Admin 2 Dashboard

Same as Admin 1, but change role to `'admin2'`:

```php
<?php echo renderNotificationWidget($conn, $_SESSION['user_id'], 'admin2'); ?>
```

### For SuperAdmin Dashboard

```php
<?php echo renderNotificationWidget($conn, $_SESSION['user_id'], 'superadmin'); ?>
```

### For User Dashboard

```php
<?php echo renderNotificationWidget($conn, $_SESSION['user_id'], 'user'); ?>
```

### For Applicant Page

```php
<?php echo renderNotificationWidget($conn, $_SESSION['user_id'], $_SESSION['role']); ?>
```

**Repeat for:**

- active_records.php
- pending_records.php
- closed_records.php
- reports_record.php
- history_activity.php
- manage_credit_points.php
- add_admin.php

---

## Sending Notifications from Your Code

### Using the NotificationManager Class

```php
<?php
    require_once 'NotificationManager.php';

    $notificationManager = new NotificationManager($conn);

    // Send to single admin
    $notificationManager->sendNotification(
        $admin_id,              // User ID
        'admin1',               // Role
        'applicant_update',     // Type
        'New Applicant',        // Title
        'Applicant John Doe has applied for a loan',  // Message
        'applicant.php',        // Link
        'View Application',     // Button text
        'high'                  // Priority
    );

    // Broadcast to all admins of a role
    $notificationManager->broadcastToAdminRole(
        'admin1',
        'loan_status',
        'Loan Status Update',
        'Multiple loans have been updated',
        'active_records.php',
        'View Details'
    );

    // Notify about specific events
    $notificationManager->notifyApplicantUpdate($user_id, $applicant_name, $app_id, 'approved');
    $notificationManager->notifyLoanStatusUpdate($app_id, $loan_id, 'pending', 'active', $admin_name);
    $notificationManager->notifyPaymentReminder($payment_id, $user_name, $amount, $due_date);
    $notificationManager->notifyAuditTrail($admin_id, 'action_name', 'Module', 'Description');
?>
```

---

## API Endpoints

### Get Unread Count

```
GET /notification_api.php?action=get_unread_count
```

**Response:**

```json
{
  "success": true,
  "count": 5,
  "timestamp": "2025-11-13 10:30:00"
}
```

### Get Recent Notifications

```
GET /notification_api.php?action=get_recent&limit=5
```

**Response:**

```json
{
  "success": true,
  "notifications": [
    {
      "notification_id": 1,
      "title": "New Application",
      "message": "John Doe has submitted an application",
      "type_display_name": "Applicant Update",
      "created_at": "2025-11-13 10:00:00",
      "is_read": 0,
      "icon_class": "fas fa-user-check",
      "color_class": "success"
    }
  ],
  "count": 1
}
```

### Mark as Read

```
POST /notification_api.php
Body: action=mark_read&notification_id=1
```

### Mark All as Read

```
POST /notification_api.php
Body: action=mark_all_read
```

### Delete Notification

```
POST /notification_api.php
Body: action=delete&notification_id=1
```

### Get All Notifications (Paginated)

```
GET /notification_api.php?action=get_all&page=1&per_page=20&type=applicant_update&status=unread&search=John
```

---

## Notification Types

The system supports 8 notification types:

| Type                   | Display Name         | Icon                      | Color   | Use Case                         |
| ---------------------- | -------------------- | ------------------------- | ------- | -------------------------------- |
| `applicant_update`     | Applicant Update     | `fa-user-check`           | success | New applications, status changes |
| `loan_status`          | Loan Status          | `fa-file-invoice-dollar`  | info    | Loan state changes               |
| `payment_reminder`     | Payment Reminder     | `fa-money-bill`           | warning | Payment due/overdue              |
| `credit_investigation` | Credit Investigation | `fa-search`               | info    | Credit check updates             |
| `admin_alert`          | Admin Alert          | `fa-exclamation-triangle` | error   | System alerts                    |
| `system_message`       | System Message       | `fa-bell`                 | info    | General notifications            |
| `audit_trail`          | Audit Trail          | `fa-list`                 | info    | Activity logging                 |
| `credit_rate`          | Credit Rate Update   | `fa-star`                 | success | Interest rate changes            |

---

## Customization

### Change Colors

Edit `CSS/notification_system.css`:

```css
.notification-bell-btn:hover {
  background-color: rgba(27, 94, 32, 0.1); /* Change this */
  color: #1b5e20; /* And this */
}
```

### Change Refresh Interval

In `notification_widget.php`, find:

```javascript
// Refresh counts every 30 seconds
setInterval(function () {
  // ...
}, 30000); // Change 30000 to your desired milliseconds
```

### Add Custom Notification Type

1. Add to database:

```sql
INSERT INTO notification_types (type_name, type_display_name, icon_class, color_class)
VALUES ('custom_type', 'Custom Type', 'fas fa-star', 'success');
```

2. Use in code:

```php
$notificationManager->sendNotification($user_id, $role, 'custom_type', 'Title', 'Message');
```

---

## Security Features

✅ **Session Authentication** - All endpoints require login
✅ **User Isolation** - Users only see their own notifications
✅ **Role-Based Access** - SuperAdmin sees all, others see only relevant
✅ **Prepared Statements** - Protection against SQL injection
✅ **Input Sanitization** - HTML encoding for all output
✅ **CSRF Protection** - Ready for token implementation

---

## Performance Optimization

### Database Indexes

Ensure these indexes exist:

```sql
CREATE INDEX idx_notifications_user_id ON user_notifications(user_id);
CREATE INDEX idx_notifications_is_read ON user_notifications(is_read);
CREATE INDEX idx_notifications_created_at ON user_notifications(created_at);
CREATE INDEX idx_notifications_type ON user_notifications(type_id);
```

### Pagination

By default, 20 notifications per page. Adjust:

```php
$per_page = 50;  // Change in notification_api.php
```

### Auto-Archive Old Notifications

Add to a cron job:

```php
DELETE FROM user_notifications
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

---

## Troubleshooting

### Widget not appearing

- ✅ Check CSS file is included
- ✅ Verify `notification_widget.php` path is correct
- ✅ Ensure `NotificationManager.php` exists
- ✅ Check browser console for JavaScript errors

### Notifications not showing

- ✅ Verify database tables exist
- ✅ Check user is logged in (`$_SESSION['user_id']`)
- ✅ Ensure notifications are being sent via code
- ✅ Check database permissions

### 404 error on API

- ✅ Verify `notification_api.php` exists
- ✅ Check file permissions
- ✅ Ensure PHP is enabled

### Dropdown not opening

- ✅ Check CSS is loaded (Network tab)
- ✅ Verify JavaScript isn't throwing errors
- ✅ Check z-index conflicts with other elements

---

## Browser Support

| Browser         | Support          |
| --------------- | ---------------- |
| Chrome 90+      | ✅ Full          |
| Firefox 88+     | ✅ Full          |
| Safari 14+      | ✅ Full          |
| Edge 90+        | ✅ Full          |
| IE 11           | ❌ Not supported |
| Mobile browsers | ✅ Full          |

---

## Next Steps

1. **Integrate into all admin pages** - Copy widget code to each page
2. **Set up notification triggers** - Add calls to send notifications from key events
3. **Train admins** - Show how to use notification center
4. **Monitor performance** - Check database performance with many notifications
5. **Gather feedback** - Improve based on user experience

---

## Support & Updates

For questions or issues, check:

1. This integration guide
2. NotificationManager.php documentation
3. notification_api.php comments
4. CYCLOAN_SYSTEM_FLOWCHARTS.md

---

**Version:** 1.0
**Last Updated:** November 13, 2025
**Status:** Production Ready ✅
