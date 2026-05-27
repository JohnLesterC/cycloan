# Credit Points System - Implementation Guide

## Overview

The Credit Points System rewards users for good loan payment behavior and helps assess creditworthiness.

## Installation Steps

### 1. Database Setup

Run the SQL file to add credit points tables and columns:

```sql
-- Execute this file in your MySQL database
source database/add_credit_points.sql;
```

Or manually run it in phpMyAdmin by:

1. Open phpMyAdmin
2. Select your `cycloan_db` database
3. Click "SQL" tab
4. Copy and paste the contents of `database/add_credit_points.sql`
5. Click "Go"

### 2. Verify Installation

Check that these were created:

- `users1` table now has `credit_points` and `credit_points_updated_at` columns
- `credit_points_history` table exists
- `credit_points_settings` table exists with default values

## Files Created

### Core Files:

1. **credit_points_manager.php** - Core credit points management class
2. **auto_credit_points.php** - Helper functions for automatic point awards
3. **manage_credit_points.php** - Admin interface to manage user points
4. **database/add_credit_points.sql** - Database schema

### Modified Files:

1. **user_dashboard.php** - Displays credit points card
2. **profile.php** - Shows credit score badge
3. **CSS/user_dashboard.css** - Styling for credit points display
4. **CSS/profile.css** - Styling for credit badge

## Features

### For Users:

1. **View Credit Score** - Displayed on dashboard and profile
2. **View History** - See recent credit point transactions
3. **Earn Points** - Automatic awards for:

   - Completing loans: 100 points
   - On-time payments: 10 points per payment
   - First loan completion: 50 bonus points

4. **Point Deductions** for:
   - Late payments: -5 points
   - Loan defaults: -50 points

### For Admins:

1. **Manage All Users** - View all users and their credit scores
2. **Add/Deduct Points** - Manually adjust points with reasons
3. **Set Exact Points** - Override user's credit score
4. **View History** - See complete point transaction history
5. **Statistics** - View system-wide credit point stats

## How to Use

### For Admin Users:

#### Access Credit Points Management:

1. Login as Superadmin or Admin1
2. Navigate to sidebar menu
3. Click "CREDIT POINTS" (star icon)

#### Manage User Points:

1. Find the user in the table
2. Click "Manage" button
3. Choose action:
   - **Set Exact Points**: Enter specific point value
   - **Add Points**: Enter points to add
   - **Deduct Points**: Enter points to remove
4. **Always provide a reason** (required)
5. Click the appropriate button

#### View User History:

- History automatically loads when managing a user
- Shows all point changes with dates and reasons
- Color-coded: Green for additions, Red for deductions

### Automatic Point Awards:

Points are automatically awarded when:

#### 1. Loan Completion:

When a loan's `remaining_balance` reaches 0, call:

```php
require_once "auto_credit_points.php";
awardLoanCompletionPoints($userId, $loanId);
```

#### 2. Payment Made:

When recording a payment, call:

```php
require_once "auto_credit_points.php";
checkAndAwardPaymentPoints($userId, $loanId, $dueDate, $paymentDate);
```

This will:

- Award points if payment is on-time
- Deduct points if payment is late

## Integration Points

### In `create_loan_payment.php` (or similar):

Add after recording payment:

```php
require_once "auto_credit_points.php";

// After successful payment
$paymentResult = checkAndAwardPaymentPoints(
    $userId,
    $loanId,
    $dueDate,
    $paymentDate
);

if ($paymentResult === 'on-time') {
    $_SESSION['success'] .= ' You earned 10 credit points for on-time payment!';
}
```

### In loan closure logic:

Add when marking loan as closed:

```php
require_once "auto_credit_points.php";

// When remaining_balance becomes 0
if ($remainingBalance == 0) {
    awardLoanCompletionPoints($userId, $loanId);
    $_SESSION['success'] = 'Loan completed! You earned credit points!';
}
```

## Credit Tiers

Users are categorized into tiers based on points:

| Tier      | Points Required | Color  |
| --------- | --------------- | ------ |
| Excellent | 500+            | Green  |
| Good      | 300-499         | Blue   |
| Fair      | 100-299         | Orange |
| Building  | 0-99            | Gray   |

Use these helper functions:

```php
require_once "auto_credit_points.php";

$tier = getUserCreditTier($creditPoints);
$color = getCreditTierColor($creditPoints);
```

## Configurable Settings

Adjust point values in database table `credit_points_settings`:

| Setting                       | Default | Description                      |
| ----------------------------- | ------- | -------------------------------- |
| points_per_completed_loan     | 100     | Points for completing a loan     |
| points_per_ontime_payment     | 10      | Points per on-time payment       |
| points_deduction_late_payment | -5      | Points deducted for late payment |
| points_deduction_default      | -50     | Points deducted for defaulting   |
| minimum_credit_points         | 0       | Minimum allowed points           |
| bonus_points_first_loan       | 50      | Bonus for first completed loan   |

To modify:

```sql
UPDATE credit_points_settings
SET setting_value = 150
WHERE setting_name = 'points_per_completed_loan';
```

## Display Credit Points

### On User Dashboard:

Already integrated! Shows:

- Large credit score display
- Recent activity (last 3 transactions)
- Animated star icon
- Color-coded transactions

### On User Profile:

Badge display showing current points

### Custom Display:

```php
require_once "credit_points_manager.php";

$creditManager = getCreditPointsManager();
$points = $creditManager->getUserPoints($userId);

echo "Credit Score: " . number_format($points) . " points";
```

## Troubleshooting

### Points not showing?

1. Check database tables exist
2. Verify `credit_points` column in `users1` table
3. Check error logs for PHP errors

### Points not auto-awarding?

1. Ensure `auto_credit_points.php` is included
2. Check function is called after payment/completion
3. Verify loan status is being updated correctly

### Admin can't access management page?

1. Ensure user role is 'superadmin' or 'admin1'
2. Check session variables are set correctly

## Security Notes

1. **Admin Authorization**: Only superadmin and admin1 can manage points
2. **Audit Trail**: All changes logged in `credit_points_history`
3. **Minimum Points**: System enforces minimum (default 0)
4. **Validation**: All inputs validated and sanitized

## Future Enhancements

Consider adding:

1. Email notifications when points are earned/deducted
2. Badges/achievements for milestones
3. Credit score impact on loan approval process
4. Leaderboard for top credit scores
5. Points expiration after inactivity
6. Redemption system (points for benefits)

## Support

For issues or questions:

1. Check error logs: `debug.log` and PHP error log
2. Verify database schema matches expected structure
3. Test with sample data first

## Version

- **Version**: 1.0
- **Created**: October 28, 2025
- **Database**: MySQL/MariaDB
- **PHP Version**: 7.4+
