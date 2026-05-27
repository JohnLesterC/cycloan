# Update Summary: Smart ID Display Based on Loan Status

## Overview

The system now intelligently displays different IDs based on the loan application status:

- **During application process** (Pending, Approved, Rejected): Shows `application_id` (e.g., APP-20251026-0001)
- **When loan is active or closed** (Active, Closed): Shows `loan_id` (e.g., LOAN-20251026-0001)

## Files Updated

### 1. **loan_register_process.php**

**Changes:**

- Now generates `application_id` as a formatted ID (APP-YYYYMMDD-XXXX)
- Includes `application_id` in the INSERT query (not auto-increment anymore)
- Both `application_id` and `loan_id` are generated with the same daily sequence

**Key Code:**

```php
// Generate unique Application ID in the format APP-YYYYMMDD-XXXX
$applicationId = 'APP-' . $today . '-' . str_pad($dailyCount, 4, '0', STR_PAD_LEFT);

// Generate unique Loan ID in the format LOAN-YYYYMMDD-XXXX
$loanId = 'LOAN-' . $today . '-' . str_pad($dailyCount, 4, '0', STR_PAD_LEFT);
```

### 2. **user_dashboard.php**

**Changes:**

- Active loan banner now shows appropriate ID based on status
- Loan application cards display correct ID based on status

**Display Logic:**

```php
<?php if ($activeLoan['status'] === 'Active' || $activeLoan['status'] === 'Closed'): ?>
    <p>Loan ID: <?= htmlspecialchars($activeLoan['loans_loan_id'] ?: $activeLoan['application_loan_id']) ?></p>
<?php else: ?>
    <p>Application ID: <?= htmlspecialchars($activeLoan['application_loan_id'] ?: '#' . $activeLoan['application_id']) ?></p>
<?php endif; ?>
```

### 3. **user_active_record.php**

**Changes:**

- Banner header displays correct ID label based on loan status

### 4. **user_pending_records.php**

**Changes:**

- Query updated to fetch `loan_id` field
- Ready for future display enhancements if needed

### 5. **user_closed_records.php**

**Changes:**

- Query updated to fetch `loan_id` field
- Closed loans will show loan_id (formatted)

### 6. **user_history_activity.php**

**Changes:**

- Application history table shows correct ID based on status
- Query updated to distinguish between `application_loan_id` and `loans_loan_id`

**Table Display:**

```php
<?php if ($app['status'] === 'Active' || $app['status'] === 'Closed'): ?>
    <strong>Loan ID: <?php echo htmlspecialchars($app['loans_loan_id'] ?: $app['application_loan_id']); ?></strong>
<?php else: ?>
    <strong>App ID: <?php echo htmlspecialchars($app['application_loan_id'] ?: '#' . $app['application_id']); ?></strong>
<?php endif; ?>
```

## Database Migration Required

⚠️ **IMPORTANT**: Before this will work fully, you MUST run the database migration!

### Migration Steps:

1. **Backup your database**

   ```bash
   mysqldump -u root -p cycloan_db > backup_before_migration.sql
   ```

2. **Run the migration script**

   - Open phpMyAdmin
   - Select `cycloan_db` database
   - Go to SQL tab
   - Copy and paste the content from `database/migrate_application_id_to_varchar.sql`
   - Execute

3. **Update existing records** (if you have data)
   ```sql
   SET @counter = 0;
   UPDATE loan_applications
   SET application_id = CONCAT('APP-', DATE_FORMAT(created_at, '%Y%m%d'), '-', LPAD((@counter := @counter + 1), 4, '0'))
   ORDER BY created_at;
   ```

## Expected Behavior After Migration

### Scenario 1: New Loan Application (Pending)

- User submits application
- System generates:
  - `application_id`: APP-20251026-0001
  - `loan_id`: LOAN-20251026-0001 (stored but not displayed yet)
- User sees: **"Application ID: APP-20251026-0001"**

### Scenario 2: Loan Approved and Activated

- Admin approves and activates the loan
- Status changes to "Active"
- User now sees: **"Loan ID: LOAN-20251026-0001"**

### Scenario 3: Loan Completed

- Loan is fully paid and closed
- Status changes to "Closed"
- User still sees: **"Loan ID: LOAN-20251026-0001"**

## Testing Checklist

After running the migration:

- [ ] Submit a new loan application
- [ ] Verify `application_id` shows as APP-YYYYMMDD-XXXX in database
- [ ] Verify `loan_id` shows as LOAN-YYYYMMDD-XXXX in database
- [ ] Check dashboard displays "Application ID: APP-..." for pending loans
- [ ] Activate a loan (admin panel)
- [ ] Verify dashboard now displays "Loan ID: LOAN-..." for active loans
- [ ] Check history activity page shows correct IDs
- [ ] Check pending records page
- [ ] Check active records page
- [ ] Check closed records page

## Rollback Plan

If something goes wrong:

1. **Restore database from backup**

   ```bash
   mysql -u root -p cycloan_db < backup_before_migration.sql
   ```

2. **Revert code changes using Git**
   ```bash
   git checkout HEAD -- loan_register_process.php user_dashboard.php user_active_record.php user_pending_records.php user_closed_records.php user_history_activity.php
   ```

## Notes

- Both IDs (`application_id` and `loan_id`) use the same daily sequence number
- Sequence resets every day
- Format: TYPE-YYYYMMDD-XXXX where XXXX is 0001, 0002, 0003, etc.
- The smart display logic checks the `status` field to determine which ID to show
- This provides better user experience by showing relevant ID at each stage

## Support

If you encounter any issues:

1. Check `debug.log` for error messages
2. Verify database schema matches expected structure
3. Ensure all foreign keys are properly recreated after migration
4. Test on a development database first before applying to production
