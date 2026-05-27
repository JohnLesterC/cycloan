# Migration Guide: Formatted Application IDs

## Overview

This guide will help you change the `application_id` from an auto-increment number (1, 2, 3...) to a formatted ID like `APP-20251026-0001`.

## ⚠️ IMPORTANT WARNINGS

1. **Backup your database first!** This migration modifies primary keys and foreign keys.
2. **This will affect existing data** - all existing application_id references will need to be updated.
3. **Test on a development database** before running on production.

## Step 1: Backup Your Database

```bash
# Using phpMyAdmin: Export the database
# OR using command line:
mysqldump -u root -p cycloan_db > backup_before_migration.sql
```

## Step 2: Run the Migration Script

You have two options:

### Option A: Using phpMyAdmin

1. Open phpMyAdmin
2. Select the `cycloan_db` database
3. Click on the "SQL" tab
4. Copy and paste the contents of `migrate_application_id_to_varchar.sql`
5. Click "Go" to execute

### Option B: Using MySQL Command Line

```bash
mysql -u root -p cycloan_db < database/migrate_application_id_to_varchar.sql
```

## Step 3: Verify the Changes

Run this query to check the new schema:

```sql
DESCRIBE loan_applications;
```

You should see `application_id` as `VARCHAR(20)` instead of `INT(11)`.

## Step 4: Update Existing Records (if you have existing data)

If you already have loan applications in the database, run this to convert them:

```sql
SET @counter = 0;
UPDATE loan_applications
SET application_id = CONCAT('APP-', DATE_FORMAT(created_at, '%Y%m%d'), '-', LPAD((@counter := @counter + 1), 4, '0'))
ORDER BY created_at;
```

## Step 5: Test the New System

1. Submit a new loan application
2. Check that the application_id is formatted like `APP-20251026-0001`
3. Verify it displays correctly on the dashboard
4. Check the `debug.log` file for confirmation messages

## Changes Made to Code

The following files were updated:

1. **loan_register_process.php**

   - Changed to generate formatted `application_id` (APP-YYYYMMDD-XXXX)
   - Updated INSERT query to include `application_id` in the values
   - Modified verification query to use VARCHAR comparison
   - Updated documents INSERT to use VARCHAR for application_id

2. **Database Schema**
   - `loan_applications.application_id`: INT → VARCHAR(20)
   - `documents.application_id`: INT → VARCHAR(20)
   - `loans.application_id`: INT → VARCHAR(20)

## Expected Result

After migration, new loan applications will have:

- **Application ID**: `APP-20251026-0001`, `APP-20251026-0002`, etc.
- **Loan ID**: `LOAN-20251026-0001`, `LOAN-20251026-0002`, etc.

Both will increment daily and reset the sequence number each day.

## Rollback (If Needed)

If something goes wrong, restore from backup:

```bash
mysql -u root -p cycloan_db < backup_before_migration.sql
```

Then revert the code changes using Git or manually.

## Support

If you encounter any issues:

1. Check the `debug.log` file for error messages
2. Verify the database schema matches the expected structure
3. Ensure all foreign key constraints are properly recreated
