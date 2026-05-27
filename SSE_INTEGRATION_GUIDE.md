# SSE Real-Time Notification System - Integration Guide

**For Developers Integrating SSE into Other Parts of CYCLOAN**

---

## 📍 Integration Points

### 1. Creating Notifications Programmatically

**Location:** Any PHP file that performs a significant action

```php
<?php
require_once 'NotificationStream.php';

// After completing an action (e.g., approving a loan)
$stream = new NotificationStream($conn, $_SESSION['user_id'], $_SESSION['role']);

// Example 1: Approve loan
if ($_POST['action'] === 'approve_loan') {
    // ... approval logic ...

    // Create notification
    $stream->createNotification([
        'title' => 'Loan Approved',
        'message' => 'Loan application #' . $applicationId . ' has been approved',
        'notification_type' => 'loan_approved',
        'module' => 'loan_application',
        'action' => 'approve',
        'priority' => 'high',
        'recipient_role' => 'admin2',  // All admin2 users get this
        'sender_id' => $_SESSION['user_id'],
        'related_id' => $applicationId
    ]);
}

// Example 2: Payment received
if ($_POST['action'] === 'record_payment') {
    // ... payment logic ...

    $stream->createNotification([
        'title' => 'Payment Received',
        'message' => '₱' . $amount . ' payment received for Loan #' . $loanId,
        'notification_type' => 'payment_received',
        'module' => 'payment',
        'action' => 'create',
        'priority' => 'high',
        'recipient_role' => 'admin2',
        'sender_id' => $_SESSION['user_id'],
        'related_id' => $loanId
    ]);
}

// Example 3: Document verified
if ($_POST['action'] === 'verify_document') {
    // ... verification logic ...

    $stream->createNotification([
        'title' => 'Document Verified',
        'message' => $documentName . ' has been verified and approved',
        'notification_type' => 'document_verified',
        'module' => 'document',
        'action' => 'approve',
        'priority' => 'normal',
        'recipient_id' => $applicantAdminId,  // Specific user
        'sender_id' => $_SESSION['user_id'],
        'related_id' => $applicationId
    ]);
}
?>
```

### 2. Listening to Notifications in JavaScript

**Location:** `admin2_dashboard.php` (already implemented, but you can extend)

```javascript
// Add custom event listeners to SSE connection
sseEventSource.addEventListener("notification", (event) => {
  const notification = JSON.parse(event.data);

  // Handle different notification types
  switch (notification.type) {
    case "loan_approved":
      console.log("New loan approved:", notification.title);
      // Could trigger: refreshLoanTable(), playSound(), etc.
      break;

    case "payment_received":
      console.log("Payment received:", notification.message);
      // Could trigger: updateDashboardStats(), showAnimation(), etc.
      break;

    case "document_verified":
      console.log("Document verified:", notification.title);
      break;

    default:
      console.log("Notification:", notification.title);
  }
});
```

### 3. Adding New Notification Types

**Location:** `NotificationStream.php`

```php
// Add to $iconMap array (line ~25)
private $iconMap = [
    'loan_created' => 'fa-file-contract',
    'loan_updated' => 'fa-edit',
    'loan_approved' => 'fa-check-circle',
    'loan_rejected' => 'fa-times-circle',
    'payment_received' => 'fa-money-bill-wave',
    'payment_overdue' => 'fa-exclamation-triangle',
    'document_uploaded' => 'fa-file-upload',
    'document_verified' => 'fa-file-check',
    'status_changed' => 'fa-sync-alt',
    'assignment_received' => 'fa-inbox',
    'comment_added' => 'fa-comment',
    'reminder_sent' => 'fa-bell',
    'system_alert' => 'fa-exclamation-circle',
    'user_login' => 'fa-sign-in-alt',
    'password_reset' => 'fa-key',
    'profile_updated' => 'fa-user-edit',
    'interest_rate_changed' => 'fa-chart-line',
    'MY_NEW_TYPE' => 'fa-icon-name'  // ADD HERE
];

// Add to action URL mapping (line ~50)
public function getNotificationActionUrl($notification) {
    // ... existing mappings ...

    $urlMap = [
        'loan_created' => 'admin2_dashboard.php?tab=loan-applicants&id=' . $relatedId,
        'loan_updated' => 'admin2_dashboard.php?tab=loan-applicants&id=' . $relatedId,
        'MY_NEW_TYPE' => 'my_custom_page.php?id=' . $relatedId,  // ADD HERE
    ];
}
```

### 4. Querying Notification History

**Location:** Any reporting/monitoring page

```php
<?php
require_once 'NotificationStream.php';

$stream = new NotificationStream($conn, $_SESSION['user_id'], $_SESSION['role']);

// Get recent notifications
$history = $stream->getNotificationHistory(50);

// Get unread count
$unreadCount = $stream->getUnreadCount();

// Display
?>
<div class="notification-history">
    <h3>Recent Notifications (<?php echo $unreadCount; ?> unread)</h3>
    <ul>
        <?php foreach ($history as $notif): ?>
            <li class="<?php echo $notif['is_read'] ? 'read' : 'unread'; ?>">
                <strong><?php echo htmlspecialchars($notif['title']); ?></strong>
                <p><?php echo htmlspecialchars($notif['message']); ?></p>
                <small><?php echo $notif['created_at']; ?></small>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
```

### 5. Notification Badge in Header

**Location:** Navigation/Header template

```html
<!-- Add to header/navigation -->
<a href="notifications.php" class="notification-link">
  <i class="fas fa-bell"></i>
  <span class="notification-badge" id="notificationBadge" style="display:none;"
    >0</span
  >
</a>

<!-- JavaScript to update badge -->
<script>
  // Listen to notification changes
  sseEventSource.addEventListener("notification", (event) => {
    const badge = document.getElementById("notificationBadge");
    const count = parseInt(badge.textContent) + 1;
    badge.textContent = count;
    badge.style.display = count > 0 ? "inline-block" : "none";
  });
</script>
```

### 6. Database Operations

**Location:** Admin/Maintenance scripts

```php
<?php
require_once 'NotificationStream.php';

$stream = new NotificationStream($conn, $_SESSION['user_id'], $_SESSION['role']);

// Mark notification as read
$stream->markNotificationAsRead($notificationId);

// Cleanup old notifications (run daily)
$deleted = $stream->cleanupOldNotifications();
echo "Deleted " . $deleted . " old notifications";

// Get statistics
$unread = $stream->getUnreadCount();
echo "Unread: " . $unread;
?>
```

---

## 🔌 Common Integration Scenarios

### Scenario 1: Add Notification When Form Submitted

```php
<?php
// process_loan_approval.php

require_once 'CYCLOAN_db.php';
require_once 'NotificationStream.php';

$stream = new NotificationStream($conn, $_SESSION['user_id'], $_SESSION['role']);

// Process the approval
$applicationId = sanitizeInt($_POST['application_id']);
// ... update database ...

// Create notification
$stream->createNotification([
    'title' => 'Loan Status Updated',
    'message' => 'Loan application ' . $applicationId . ' status has been changed',
    'notification_type' => 'status_changed',
    'module' => 'loan_application',
    'priority' => 'normal',
    'recipient_role' => 'admin2',
    'sender_id' => $_SESSION['user_id'],
    'related_id' => $applicationId
]);

// Response
echo json_encode([
    'success' => true,
    'message' => 'Approval processed',
    'notification_sent' => true
]);
?>
```

### Scenario 2: Add Notification Only for Errors/Alerts

```php
<?php
// process_payment.php

require_once 'NotificationStream.php';

$stream = new NotificationStream($conn, $_SESSION['user_id'], $_SESSION['role']);

// Check for overdue
if ($days_overdue > 30) {
    $stream->createNotification([
        'title' => 'ALERT: Payment Overdue',
        'message' => 'Loan #' . $loanId . ' is ' . $days_overdue . ' days overdue',
        'notification_type' => 'payment_overdue',
        'module' => 'payment',
        'priority' => 'critical',
        'recipient_role' => 'admin2',
        'sender_id' => null,  // System notification
        'related_id' => $loanId
    ]);
}
?>
```

### Scenario 3: Broadcast to All Users

```php
<?php
// system_maintenance.php

require_once 'NotificationStream.php';

$stream = new NotificationStream($conn, null, null);  // System user

// Broadcast notification
$stream->createNotification([
    'title' => 'System Maintenance',
    'message' => 'System will be under maintenance on Sunday 2am-4am',
    'notification_type' => 'system_alert',
    'module' => 'system',
    'priority' => 'high',
    'recipient_id' => null,      // No specific user
    'recipient_role' => null,    // No specific role - broadcast to all
    'sender_id' => 1,            // Admin ID
    'related_id' => null
]);
?>
```

### Scenario 4: Notify Specific User

```php
<?php
// assign_loan_to_admin.php

require_once 'NotificationStream.php';

$stream = new NotificationStream($conn, $_SESSION['user_id'], $_SESSION['role']);

$targetAdminId = 42;  // Specific user

$stream->createNotification([
    'title' => 'New Loan Assigned',
    'message' => 'Loan application #APP-5678 assigned to you',
    'notification_type' => 'assignment_received',
    'module' => 'loan_application',
    'priority' => 'high',
    'recipient_id' => $targetAdminId,     // Specific user only
    'recipient_role' => null,              // Not broadcast
    'sender_id' => $_SESSION['user_id'],
    'related_id' => $loanApplicationId
]);
?>
```

---

## 🎨 Frontend Integration Examples

### Display Notification Badge on Page Load

```html
<!-- In any page header/navigation -->
<script>
  document.addEventListener("DOMContentLoaded", function () {
    // Fetch and display unread count
    fetch("admin2_dashboard.php?action=get_unread_notifications")
      .then((r) => r.json())
      .then((data) => {
        if (data.count > 0) {
          document.querySelector(".notification-badge").textContent =
            data.count;
          document.querySelector(".notification-badge").style.display =
            "inline-block";
        }
      });
  });
</script>
```

### Auto-Refresh Page on Critical Notification

```javascript
// In dashboard
sseEventSource.addEventListener("notification", (event) => {
  const notification = JSON.parse(event.data);

  if (notification.priority === "critical") {
    // Show warning dialog
    if (
      confirm(
        notification.title + "\n\n" + notification.message + "\n\nRefresh page?"
      )
    ) {
      location.reload();
    }
  }
});
```

### Sound Alert on High Priority

```javascript
// In dashboard
sseEventSource.addEventListener("notification", (event) => {
  const notification = JSON.parse(event.data);

  if (
    notification.priority === "high" ||
    notification.priority === "critical"
  ) {
    // Play sound
    const audio = new Audio("sounds/notification.mp3");
    audio.play();
  }
});
```

---

## 📋 AJAX Endpoint Reference

### Get Unread Count

```javascript
fetch("admin2_dashboard.php?action=get_unread_notifications")
  .then((r) => r.json())
  .then((data) => console.log("Unread:", data.count));
```

### Get Notification History

```javascript
fetch("admin2_dashboard.php?action=get_notification_history&limit=20")
  .then((r) => r.json())
  .then((data) => console.log("History:", data.notifications));
```

### Mark as Read

```javascript
fetch("admin2_dashboard.php", {
  method: "POST",
  headers: { "Content-Type": "application/json" },
  body: JSON.stringify({
    action: "mark_notification_read",
    notification_id: 123,
    csrf_token: getCsrfToken(),
  }),
})
  .then((r) => r.json())
  .then((data) => console.log("Marked as read"));
```

---

## 🔧 Configuration for Integration

### Add Notification Types to admin2_dashboard.php

```javascript
// If you need to map new types in frontend
const notificationTypeMap = {
  loan_created: "Loan Created",
  loan_updated: "Loan Updated",
  payment_received: "Payment Received",
  MY_NEW_TYPE: "My Custom Type", // ADD HERE
};
```

### Add Icons in NotificationStream.php

```php
private $iconMap = [
    // ... existing ...
    'MY_NEW_TYPE' => 'fa-custom-icon'  // Font Awesome icon name
];
```

---

## ✅ Integration Checklist

- [ ] Import NotificationStream.php in handler files
- [ ] Add $stream->createNotification() calls after important actions
- [ ] Test notifications appear in real-time on dashboard
- [ ] Verify correct icons display for notification types
- [ ] Check action links work correctly
- [ ] Test role-based targeting works
- [ ] Verify desktop notifications show when enabled
- [ ] Monitor database for notification growth
- [ ] Setup cleanup cron job
- [ ] Document new notification types
- [ ] Train admins on notification system

---

## 🚀 Integration Workflow

```
1. Action Triggered
   └─> (e.g., approve loan, receive payment)

2. Process Action
   └─> Update database
   └─> Perform validation

3. Create Notification
   └─> $stream->createNotification([...])
   └─> Notification inserted in DB

4. Server Pushes to Clients
   └─> SSE handler detects new notification
   └─> Sends "notification" event

5. Client Receives
   └─> Browser receives EventSource event
   └─> JavaScript renders notification UI
   └─> Shows toast + desktop notification

6. User Sees Real-Time Update
   └─> Notification appears immediately
   └─> User can click action link
   └─> Related table refreshes automatically
```

---

## 📞 Integration Support

### Questions?

1. Refer to `SSE_REALTIME_NOTIFICATION_SYSTEM.md` for detailed API
2. Check `SSE_NOTIFICATION_QUICK_START.md` for examples
3. Review inline documentation in source files
4. Test in development environment first

### Common Issues

- **Notification not showing:** Check recipient_id/recipient_role matches current user
- **Icon not displaying:** Verify icon name in iconMap (must be valid Font Awesome class)
- **Action link not working:** Check URL format in getNotificationActionUrl()
- **Priority not visible:** Ensure priority is one of: 'critical', 'high', 'normal', 'low'

---

**Last Updated:** November 16, 2025  
**Version:** 1.0.0  
**Status:** ✅ Production Ready
