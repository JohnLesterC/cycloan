# Document Notification System - Quick Reference

## What Was Implemented

A complete document notification system that automatically sends notifications to users when their submitted documents are updated by administrators.

## Files Created

- **DocumentNotificationHandler.php** - Main notification handler class (438 lines)

## Files Modified

1. **notifications_enhanced.php** - Added API endpoints
2. **admin2_dashboard.php** - Integrated notification creation
3. **user_pending_records.php** - Added document status endpoint
4. **user_pending_records.js** - Added document loading functions

## How It Works

### When Admin Updates Document:

1. Admin2 changes document status in admin2_dashboard.php
2. System automatically creates notification
3. Notification stored in database with document details
4. User sees notification in notification center

### What User Sees:

```
Document Update: 2x2 Picture
📄 Document '2x2 Picture' status changed from Pending to Approved
Updated by: Admin Name
2 hours ago
```

If rejected:

```
Document Update: Voter's Certificate  ⚠️ HIGH PRIORITY
📄 Document 'Voter's Certificate' status changed from Pending to Rejected
Reason: Image quality is too low
Updated by: Admin Name
1 hour ago
```

## Key Features

✅ Automatic notification creation
✅ Rejection reason tracking
✅ Full audit trail
✅ Priority levels (High for rejected, Normal for approved)
✅ Unread notification counting
✅ Application-specific history

## Testing

### Quick Test:

1. Login as Admin2
2. Go to applicant profile
3. Update any document status
4. Add rejection reason if rejecting
5. Login as applicant
6. Go to notifications center
7. See notification with document details

### Database Check:

```sql
SELECT * FROM notifications
WHERE notification_type = 'document_status'
LIMIT 10;
```

## API Endpoints

### Get Document Notifications:

```
notifications_enhanced.php?action=get_document_updates&user_id=123&limit=10
```

### Get Application Document History:

```
notifications_enhanced.php?action=get_application_document_updates&application_id=APP-12345
```

## Priority Levels

- **High (Red)**: Document Rejected - Requires action
- **Normal (Blue)**: Document Approved - Informational
- **Low (Gray)**: System updates

## Database Tables Used

- `documents` - Document info and status
- `document_audit` - Change tracking
- `notifications` - Notification records
- `activity_logs` - Activity tracking

## Common Use Cases

### Scenario 1: Admin Approves Document

```
Flow: Admin approves → Notification created → User sees "Approved"
Message: "Document 'X' status changed from Pending to Approved"
Priority: Normal
```

### Scenario 2: Admin Rejects with Reason

```
Flow: Admin rejects + adds reason → Notification created → User sees reason
Message: "Document 'X' status changed from Pending to Rejected\nReason: [Admin's note]"
Priority: High (requires resubmission)
```

### Scenario 3: Application-Wide Changes

```
User can see all document changes for their application:
- Which documents changed
- What the changes were
- Who made the changes
- When they were made
```

## Error Handling

- All database queries use prepared statements
- Input validation and sanitization
- Error logging for debugging
- Graceful failure handling

## Performance

- Indexed queries for fast retrieval
- Pagination support (default 20 per page)
- Lightweight response format
- Efficient database structure

## Security

✅ SQL injection prevention (prepared statements)
✅ XSS prevention (HTML escaping)
✅ Session validation
✅ Admin authentication checks
✅ User permission validation

## Troubleshooting

### Notification Not Appearing

1. Check DocumentNotificationHandler.php exists in root
2. Verify admin2_dashboard.php has: `require_once 'DocumentNotificationHandler.php';`
3. Check MySQL user has INSERT permission
4. Review error_log file

### Wrong Data Displayed

1. Verify application ID extraction from banner
2. Check database queries in notifications_enhanced.php
3. Ensure document_name field is populated

### Performance Issues

1. Add index to notifications table on recipient_id and created_at
2. Archive old notifications (older than 90 days)
3. Limit query results with pagination

## Next Steps (Optional Enhancements)

1. Add email notification when document rejected
2. Add real-time push notifications
3. Add SMS alerts for urgent rejections
4. Let users customize notification preferences
5. Add notification templates

## Support Resources

- See: DOCUMENT_NOTIFICATION_GUIDE.md (detailed guide)
- See: DOCUMENT_NOTIFICATION_COMPLETION.md (full change log)
- Check: DocumentNotificationHandler.php (source code comments)
- Review: admin2_dashboard.php (integration example)

## Version Info

- Implementation Date: November 19, 2025
- Version: 1.0
- Status: READY FOR TESTING

---

**Quick Links:**

- Main Class: `DocumentNotificationHandler.php`
- Integration Point: `admin2_dashboard.php` line 2196
- API Endpoints: `notifications_enhanced.php` action handlers
- Display Logic: `user_pending_records.js` functions

**Remember:** All changes maintain backward compatibility with existing code and follow CYCLOAN coding standards.
