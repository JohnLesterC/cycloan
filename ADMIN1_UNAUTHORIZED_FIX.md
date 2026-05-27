# Admin1 Dashboard - "Unauthorized" Error Fix

## Problem

When Admin1 tries to submit credit investigation, they receive an "Unauthorized access" error even though they are logged in.

## Root Cause

The authorization check at the beginning of `admin1_dashboard.php` was too strict:

1. It tried to match the email from session EXACTLY with the email in the `admin1` table
2. If there was any case sensitivity mismatch or extra whitespace, it would fail
3. The error message wasn't descriptive enough to help diagnose the problem

## Solution Implemented

### 1. **Improved Authorization Check** (Lines ~150-180)

- Now uses `LOWER(TRIM(email))` to normalize email comparison
- Tries to match by email first, then falls back to user_id
- Handles both string and numeric comparisons properly
- More robust error handling for edge cases

### 2. **Better Error Messages**

- Returns specific error message for AJAX requests (includes debug info)
- Logs which email/user_id failed authorization
- Tells user to verify they're logged in with correct Admin1 credentials

### 3. **Session Debugging** (Line ~42)

- Logs session info when dashboard is accessed
- Helps identify session mismatch issues
- Shows email, user_id, and role being used

## How to Verify the Fix

### If you get "Unauthorized" error:

1. **Check the debug_log.txt file** and look for:

   ```
   Admin1 Dashboard Access - Email: ken@example.com, User ID: 13, Role: admin1
   ```

2. **Verify the email in the admin1 table**:

   ```sql
   SELECT id, email, first_name, last_name FROM admin1;
   ```

3. **Make sure the session has the correct email**:

   - Login again
   - Check if email matches exactly

4. **If still unauthorized**:
   - Check debug_log.txt for "ADMIN1 AUTHORIZATION FAILED" message
   - It will show what email/user_id was attempted
   - Compare with what's in the admin1 table

## Admin1 Account Details

From your database, Admin1 is registered as:

- **ID**: 13
- **Email**: ken@example.com
- **Name**: John Lloyd Pepino Dataro

Make sure you're logged in with this account or an admin1 account with a matching email.

## What Changed in Code

### Before:

```php
$stmt = $conn->prepare("SELECT id, first_name, last_name FROM admin1 WHERE email = ?");
$stmt->bind_param("s", $_SESSION['email']);
// If no match → "Unauthorized" error
```

### After:

```php
$sessionEmail = isset($_SESSION['email']) ? strtolower(trim($_SESSION['email'])) : '';
$sessionUserId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Try by email (with normalization)
$stmt = $conn->prepare("SELECT id, first_name, last_name, email FROM admin1 WHERE LOWER(TRIM(email)) = ?");
$stmt->bind_param("s", $sessionEmail);

// If no match by email, try by user_id
// If both fail → detailed error message with debug info
```

## Testing Checklist

- [ ] Log in as Admin1 (email: ken@example.com)
- [ ] Navigate to admin1_dashboard.php
- [ ] Go to Credit Investigation section
- [ ] Fill in all fields:
  - Application ID
  - Credit Status (Completed/Failed/Pending)
  - Final Loan Amount
  - Term Length
  - Remarks (optional)
- [ ] Click "Submit Investigation"
- [ ] Should see "Credit investigation submitted successfully"
- [ ] Check debug_log.txt to verify no authorization errors

## If Error Persists

1. **Check debug_log.txt** for the exact error message
2. **Run this SQL** to verify admin data:
   ```sql
   SELECT * FROM admin1 WHERE id = 13;
   ```
3. **Clear browser cookies** and login again
4. **Check if you're logged in as Admin1** - the role should be "admin1" not "admin2" or "superadmin"

## Database Query to Verify All Admins

```sql
-- Check all Admin1 accounts
SELECT id, email, first_name, last_name, created_at FROM admin1;

-- Check all Admin2 accounts
SELECT id, email, first_name, last_name, created_at FROM admin2;

-- Check all SuperAdmins
SELECT id, email, first_name, last_name, created_at FROM superadmins;
```

## Related Files Modified

- `admin1_dashboard.php` - Authorization check improvement (Lines 40-180)
- Adds detailed logging for debugging

## Next Steps

1. Try submitting the credit investigation again
2. If you get an error, check `debug_log.txt` for the exact message
3. Share the debug message for further assistance
