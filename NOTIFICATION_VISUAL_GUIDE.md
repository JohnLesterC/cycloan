# CYCLOAN Notification System - Visual Guide

## 🔔 Notification Bell Location

```
┌─────────────────────────────────────────────────────────────┐
│ Dashboard                              🔔 [5]  👤 Profile  │  ← Bell in top right
└─────────────────────────────────────────────────────────────┘
     [Number = Unread notification count]
```

## 📱 Notification Dropdown

```
┌────────────────────────────────────────┐
│ Notifications                    ×     │
├────────────────────────────────────────┤
│ ✅ Loan Calculation Completed          │
│    You calculated an Individual        │
│    loan for ₱50,000...                 │
│    5 minutes ago                       │
│                                    View│
├────────────────────────────────────────┤
│ 🔔 New Application Received            │
│    A new loan application has          │
│    been submitted and requires...      │
│    2 hours ago                    View │
├────────────────────────────────────────┤
│ ⚠️  Payment Due Reminder                │
│    Payment of ₱5,000 is due on         │
│    Dec 15, 2024                    Dismiss
├────────────────────────────────────────┤
│              View All Notifications    │
└────────────────────────────────────────┘
```

## 📋 Full Notifications Page (notifications.php)

```
┌─────────────────────────────────────────────────────────────┐
│ 🔔 Notifications (5 New)                                    │
│                                  Mark All as Read | Go Back  │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ [✅] Loan Calculation Completed              | 5 min ago   │
│      You calculated an Individual loan for ₱50,000         │
│      with 12-month term (Monthly repayment)               │
│      Category: Loan     [→ View]  [Dismiss]               │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ [🔔] New Application Received              | 2 hours ago   │
│      A new Individual loan application for ₱50,000 has     │
│      been submitted and requires review.                    │
│      Category: Loan     [→ View]  [Dismiss]               │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ [⚠️] Payment Due Reminder                   | 1 day ago    │
│      Payment of ₱5,000 is due on Dec 15, 2024            │
│      Category: Payment  [→ View]  [Mark Read]             │
│                                                             │
└─────────────────────────────────────────────────────────────┘

< Previous    Page 1    Next >
```

## 🎨 Notification Colors

| Type        | Color  | Icon | Example                                |
| ----------- | ------ | ---- | -------------------------------------- |
| ✅ Success  | Green  | ✓    | Loan calculated, Application submitted |
| ⚠️ Warning  | Yellow | !    | Payment due, Document needed           |
| ❌ Error    | Red    | ✕    | Application rejected, Payment failed   |
| ℹ️ Info     | Blue   | i    | Status updated, New message            |
| 🕐 Reminder | Gray   | ⏰   | Upcoming payment, Follow-up            |

## 🔄 Notification Triggers

### When Do Notifications Get Created?

```
┌─────────────────────┐
│ User Action         │ → Notification Created → 🔔 Shows in Bell
├─────────────────────┤
│ Loan Calculator     │ → "Loan Calculation Completed"
│ Submit Application  │ → "Application Submitted" (User + Admins)
│ Loan Approved       │ → "Loan Status Updated" (User + Admins)
│ Payment Due         │ → "Payment Due Reminder" (User + Admins)
│ Document Upload     │ → "Document Uploaded" (User + Admins)
│ Credit Check Done   │ → "Credit Investigation Updated" (User)
└─────────────────────┘
```

## 📊 Notification Flow

```
                    User/Admin Dashboard
                           |
                           ↓
            Performs action (e.g., calculates loan)
                           |
                           ↓
              Application calls notification API
                           |
                           ↓
        notification_manager.php receives request
                           |
         ┌─────────────────┴─────────────────┐
         ↓                                   ↓
    Database stores                 Admins also get
    notification                    notified (if applicable)
         |                                   |
         └─────────────────┬─────────────────┘
                           ↓
              Notification appears in:
         ┌──────────────────────────┐
         ├─ 🔔 Bell Icon (dropdown) │
         ├─ notifications.php       │
         ├─ Browser notification   │
         └─ Email (future)         │
```

## 🧬 Notification Lifecycle

```
Created
   ↓
├─ User sees in 🔔 Bell dropdown (unread)
│  └─ 30-second auto-refresh
│  └─ User can click "Dismiss" → marked as read
│  └─ User can click "View" → goes to related page
├─ User opens notifications.php
│  └─ Sees full list
│  └─ Can mark individual as read
│  └─ Can "Mark All as Read"
│  └─ Can delete notifications
└─ (If expiration set) Automatically hidden after expiry

Read Notification
   ↓
├─ Still visible in notifications.php (but grayed out)
├─ No longer shows in 🔔 Bell
├─ Stays in database (for history)
└─ Can be manually deleted
```

## 🎯 Priority Display Order

```
🔔 Bell Dropdown (Top to Bottom)
┌─────────────────────────┐
│ [URGENT]  Payment Due   │ ← Shown first
├─────────────────────────┤
│ [HIGH]    App Submitted │
├─────────────────────────┤
│ [NORMAL]  Loan Calc     │
├─────────────────────────┤
│ [LOW]     System Update │ ← Shown last
└─────────────────────────┘
```

## 📱 Mobile View

```
┌──────────────────┐
│ Dashboard  🔔[3] │
├──────────────────┤
│                  │
│ Notification     │
│ dropdown below   │
│ bell when clicked│
│                  │
└──────────────────┘
```

## 🔌 Integration Points

```
┌──────────────────────────────────────────────────────────┐
│                   CYCLOAN PAGES                          │
├──────────────────────────────────────────────────────────┤
│                                                          │
│  user_dashboard.php          [✓ Integrated]            │
│  ├─ Notification Center      [✓ Added]                 │
│  ├─ Loan Calculator          [✓ Triggers notification] │
│  └─ Auto-notify on calc      [✓ Working]               │
│                                                          │
│  user_active_record.php      [+ Can be added]          │
│  user_pending_records.php    [+ Can be added]          │
│  user_closed_records.php     [+ Can be added]          │
│  user_history_activity.php   [+ Can be added]          │
│                                                          │
│  admin1_dashboard.php        [+ Can be added]          │
│  admin2_dashboard.php        [+ Can be added]          │
│  Superadmin_dashboard.php    [+ Can be added]          │
│                                                          │
│  notifications.php           [✓ New page]              │
│  ├─ Full notification list   [✓ Working]               │
│  ├─ Pagination               [✓ Working]               │
│  ├─ Mark as read             [✓ Working]               │
│  └─ Delete                   [✓ Working]               │
│                                                          │
└──────────────────────────────────────────────────────────┘
```

## 🔐 Who Gets Notified?

```
User Actions
├─ User performs action (e.g., submits loan application)
│
├─ User receives notification?
│  └─ YES (Always notified about their own actions)
│
└─ Admins receive notification?
   ├─ If "Admin-relevant" action? YES
   │  (Application submitted, status change, etc.)
   │
   └─ All admin IDs are fetched and notified
      (admin1, admin2, superadmin)
```

## 📊 Database Structure

```
notifications table
├─ id (Primary Key)
├─ user_id (FK to users1) ← Who gets the notification
├─ title ← "Loan Calculation Completed"
├─ message ← Full details
├─ type ← success/warning/error/info/reminder
├─ category ← loan/payment/document/account/system
├─ is_read ← 0/1 (read status)
├─ read_at ← When marked as read
├─ action_url ← Link to related page (optional)
├─ priority ← low/normal/high/urgent
├─ created_at ← Timestamp
└─ expires_at ← When to hide (optional)
```

## 🚀 Performance Metrics

```
Auto-Refresh Interval:    30 seconds
Dropdown Load Limit:      10 notifications
Full Page Load Limit:     20 per page
Badge Update:             Real-time
Database Indexes:         user_id, is_read, created_at
Typical Query Time:       < 100ms
```

## ✅ Status Checks

### How to Verify Everything Is Working

```
1. Bell Icon Visible?
   └─ Check top-right header
   └─ Should say "CYCLOAN" logo area

2. Badge Shows Unread Count?
   └─ Red circle with number
   └─ Appears when unread > 0

3. Dropdown Opens?
   └─ Click bell icon
   └─ Shows list of notifications

4. Auto-Refresh Working?
   └─ Make a calculation
   └─ Wait 30 seconds
   └─ Count should update

5. Notifications Page Works?
   └─ Go to notifications.php
   └─ Should show all notifications
   └─ Can mark as read
   └─ Can delete

6. Dismiss Works?
   └─ Click dismiss in dropdown
   └─ Notification disappears from bell
   └─ Still shows in notifications.php (grayed out)
```

## 📚 Related Files

- `notification_manager.php` - Backend logic
- `notification_center.php` - Frontend UI
- `notifications.php` - Full page
- `user_dashboard.php` - Integration point
- `NOTIFICATION_SYSTEM_README.md` - Full documentation
- `NOTIFICATION_QUICK_START.md` - Quick start guide

---

**Visual Guide Version:** 1.0  
**Last Updated:** November 2025
