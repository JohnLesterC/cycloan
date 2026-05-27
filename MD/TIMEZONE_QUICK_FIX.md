# ⏰ Philippines Timezone Fix - Quick Reference

## What Was Fixed

**Problem**: Timestamps in system were 8 hours behind Philippine time (UTC instead of PHT)

**Solution**: Set both PHP and MySQL to use Philippine Time Zone (UTC+8)

## Files Changed

### 1. ✅ CYCLOAN_db.php
**Updated with:**
- PHP timezone: `date_default_timezone_set('Asia/Manila')`
- MySQL timezone: `SET SESSION time_zone = '+08:00'`

### 2. ✅ NEW: timezone_config.php
Helper functions for proper time handling throughout the app

## How to Use

### In Your PHP Files

#### Display database timestamp correctly:
```php
// BEFORE (Wrong timezone)
echo date('Y-m-d H:i:s', strtotime($row['created_at']));

// AFTER (Correct timezone)
require_once 'timezone_config.php';
echo formatPHTimestamp($row['created_at']); // 2025-11-02 14:30:45
```

#### Get current time:
```php
require_once 'timezone_config.php';
echo getCurrentPHTime(); // Returns correct Philippines time
```

#### Check if OTP expired:
```php
require_once 'timezone_config.php';
$remaining = getTimeRemaining($otp['expires_at']);
if ($remaining['expired']) {
    echo "OTP has expired!";
}
```

#### Display time ago:
```php
require_once 'timezone_config.php';
echo getTimeDifference($row['created_at']); // "2 hours ago"
```

## Key Functions

| Function | Purpose | Example |
|----------|---------|---------|
| `getCurrentPHTime()` | Get current time as string | `"2025-11-02 14:30:45"` |
| `formatPHTimestamp($ts)` | Display any database timestamp | `formatPHTimestamp($row['created_at'])` |
| `getTimeRemaining($date)` | Time until deadline | Check OTP expiry, payment due |
| `getTimeDifference($ts)` | Time since timestamp | `"2 hours ago"` |
| `getStartOfDayPH()` | Start of today (00:00:00) | Database queries |
| `getEndOfDayPH()` | End of today (23:59:59) | Database queries |

## What Now Uses Correct Time

✅ All database timestamps (NEW records)
✅ All PHP date() functions
✅ All MySQL NOW() functions
✅ Activity logs
✅ OTP expiry checks
✅ Payment due dates
✅ User registration times
✅ All created_at/updated_at fields

## Existing Data (Before Fix)

**Important:** Old records were recorded in UTC (8 hours behind).

**Option 1 - Fix Old Data (Recommended):**
Run this SQL to add 8 hours to all old timestamps:
```sql
UPDATE activity_logs SET created_at = DATE_ADD(created_at, INTERVAL 8 HOUR);
UPDATE loan_applications SET created_at = DATE_ADD(created_at, INTERVAL 8 HOUR);
UPDATE payment_schedules SET due_date = DATE_ADD(due_date, INTERVAL 8 HOUR);
-- And so on for other tables
```

**Option 2 - Leave As Is:**
- Old data stays in UTC (historically accurate)
- New data in PHT (correct going forward)
- Just display dates with timezone awareness

## Quick Test

### Test 1: PHP Timezone
```php
require_once 'timezone_config.php';
echo getCurrentPHTime();
// Should match your current Philippine time
```

### Test 2: MySQL Timezone
```sql
SELECT NOW();
-- Should show current Philippine time
```

### Test 3: In Admin Dashboard
Look at any timestamp - should match your local time exactly

## Verify It's Working

1. Go to any admin page showing timestamps
2. Compare with your local clock
3. Should match exactly (not 8 hours behind)

## Most Important Changes

1. **CYCLOAN_db.php** - Now sets correct timezone on connection (automatic)
2. **timezone_config.php** - Use functions from here for all date operations
3. **formatPHTimestamp()** - Use this when displaying dates from database

## Common Mistakes to Avoid

❌ Don't use: `date('Y-m-d H:i:s', strtotime($row['created_at']))`
✅ Use instead: `formatPHTimestamp($row['created_at'])`

❌ Don't use: `date('Y-m-d H:i:s')` to store in database
✅ Use instead: `NOW()` in SQL (automatically uses +08:00)

❌ Don't use: `new DateTime()` without timezone
✅ Use instead: `getPhilippineTime()` or specify timezone

## Emergency Rollback

If something breaks:
```php
// Temporarily disable timezone fix by commenting out in CYCLOAN_db.php:
// $conn->query("SET SESSION time_zone = '+08:00'");
```

## Need Help?

1. Check current timezone: See function `getTimezoneInfo()` in timezone_config.php
2. Read full guide: See PHILIPPINES_TIMEZONE_FIX.md
3. View all functions: See timezone_config.php file
4. Test query: `SELECT NOW(), @@session.time_zone;`

---

**✅ All timestamps now use Philippine Time (UTC+8)**
**Status: READY TO USE**
