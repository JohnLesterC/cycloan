# ✅ Timestamp Fix - Deployment Checklist

## Files to Upload (5 files)

- [ ] `loan_register_process.php` 
  - Line 4: `require_once 'timezone_config.php';`
  
- [ ] `pay_balance.php`
  - Line 4: `require_once 'timezone_config.php';`
  
- [ ] `admin1_dashboard.php`
  - Line 11: `require_once 'timezone_config.php';`
  
- [ ] `Superadmin_dashboard.php`
  - Line 4: `require_once 'timezone_config.php';`
  
- [ ] `user_dashboard.php`
  - Line 4: `require_once 'timezone_config.php';`

## Required File (Must Exist)

- [ ] `timezone_config.php` - **MUST be in the same folder**
  - This file contains the timezone configuration
  - It should already exist in your project
  - Size: ~5KB
  - If missing, ask admin to provide it

---

## Testing Steps After Upload

### Test 1: Loan Application Timestamp
1. Open registration page
2. Submit a new loan application
3. Go to database and check `loan_applications` table
4. Look at the `created_at` column for the latest entry
5. ✅ **PASS**: Shows afternoon time (14:xx:xx)
6. ❌ **FAIL**: Shows morning time (06:xx:xx)

**SQL Command to Check:**
```sql
SELECT created_at FROM loan_applications ORDER BY id DESC LIMIT 1;
```

Expected: `2025-11-02 14:16:40` (or whatever current time in Philippines)
Wrong: `2025-11-02 06:16:40`

### Test 2: Payment Timestamp
1. Go to user dashboard
2. Make a payment
3. Go to database and check `payment_schedules` table
4. Look at `updated_at` for latest entry
5. ✅ **PASS**: Shows afternoon time (14:xx:xx)
6. ❌ **FAIL**: Shows morning time (06:xx:xx)

**SQL Command to Check:**
```sql
SELECT updated_at FROM payment_schedules ORDER BY payment_id DESC LIMIT 1;
```

### Test 3: Admin Update Timestamp
1. Log in as admin
2. Update a loan application status
3. Check `loan_applications.updated_at`
4. ✅ **PASS**: Shows current Philippines time
5. ❌ **FAIL**: Shows UTC time

**SQL Command to Check:**
```sql
SELECT updated_at FROM loan_applications ORDER BY id DESC LIMIT 1;
```

---

## Troubleshooting

### Issue: Still Showing UTC Time (06:xx:xx)

**Solution 1:** Verify `timezone_config.php` exists
```bash
# Check if file exists
ls -la timezone_config.php
```

**Solution 2:** Check if require statement is correct
- Open each PHP file
- Look at line 4 (or near database require)
- Should see: `require_once 'timezone_config.php';`
- If not, the file didn't upload correctly

**Solution 3:** Clear PHP cache
- Restart web server
- Or wait 5 minutes for cache to clear

### Issue: "File not found" Error

**Solution:** 
- Make sure `timezone_config.php` is in **same folder** as other PHP files
- Don't rename the file
- Upload it along with other files

### Issue: PHP Error / Blank Page

**Solution:**
- Check error_log.txt
- Look for timezone-related errors
- Verify timezone_config.php has no syntax errors

---

## ✅ Final Verification

After uploading and testing:

- [x] All 5 PHP files uploaded
- [x] timezone_config.php exists in same folder
- [x] New loan application shows correct time
- [x] Payment shows correct time
- [x] Admin updates show correct time
- [x] No error messages in logs

---

## Success Criteria

✅ **Timestamp shows afternoon (14:xx)** → FIX IS WORKING

❌ **Timestamp shows morning (06:xx)** → FIX NOT WORKING

❌ **Error message** → CHECK ERROR LOG

---

## Contact

If timestamps are still incorrect after following these steps:
1. Check error_log.txt for error messages
2. Verify timezone_config.php exists and is readable
3. Contact admin with error message

---

**Date:** November 2, 2025  
**Status:** Ready for Deployment ✅

