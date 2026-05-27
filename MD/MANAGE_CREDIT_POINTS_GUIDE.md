# 📊 MANAGE CREDIT POINTS - COMPLETE FUNCTIONALITY GUIDE

## Overview

The Credit Points Management system is a superadmin/admin1 tool that allows administrators to manage user credit scores in the CYCLOAN loan system.

---

## Current Functionality

### 1. **Credit Points Manager Class** (`credit_points_manager.php`)

#### Core Functions:

| Function                      | Purpose                               | Parameters                                         |
| ----------------------------- | ------------------------------------- | -------------------------------------------------- |
| `getUserPoints($userId)`      | Get current points of a user          | User ID                                            |
| `addPoints()`                 | Add points to user                    | userId, points, reason, loanId, adminId, adminRole |
| `deductPoints()`              | Remove points from user               | userId, points, reason, loanId, adminId, adminRole |
| `setPoints()`                 | Set exact points (admin override)     | userId, points, reason, adminId, adminRole         |
| `awardLoanCompletionPoints()` | Auto-award points when loan completes | userId, loanId                                     |
| `awardOnTimePayment()`        | Auto-award points for on-time payment | userId, loanId                                     |
| `deductLatePayment()`         | Auto-deduct points for late payment   | userId, loanId                                     |
| `deductLoanDefault()`         | Auto-deduct points for loan default   | userId, loanId                                     |
| `getUserHistory()`            | Get user's points change history      | userId, limit                                      |
| `getLeaderboard()`            | Get top users by points               | limit                                              |
| `getAllSettings()`            | Get all credit point settings         | -                                                  |
| `updateSetting()`             | Update credit point settings          | settingName, value                                 |

---

### 2. **Manage Credit Points Page** (`manage_credit_points.php`)

#### Features:

✅ **Dashboard Overview**

- Total users count
- Total points distributed
- Average credit score
- Highest credit score

✅ **User Table**

- Display all active users
- Current credit points for each user
- Last updated date
- Last updated date
- Action buttons to manage points

✅ **Modal Dialogs**

- **Manage User Points Modal** - Allows:
  - Set exact points value
  - Add points with reason
  - Deduct points with reason
  - View user's points history

✅ **Points History**

- Shows all changes to user's points
- Displays reason for change
- Shows date of change
- Shows which admin made the change

✅ **Access Control**

- Only superadmin and admin1 can access
- Navigates to appropriate dashboard
- Profile management per role

---

## Database Tables Used

### 1. **users1**

```sql
- id (PK)
- first_name, last_name
- email
- credit_points (current points)
- profile_image
- credit_points_updated_at
- status
```

### 2. **credit_points_history**

```sql
- id (PK)
- user_id (FK)
- points_change (positive/negative)
- previous_points
- new_points
- reason (text)
- loan_id (FK, nullable)
- admin_id (nullable)
- admin_role (admin1, admin2, superadmin)
- created_at (timestamp)
```

### 3. **credit_points_settings**

```sql
- id (PK)
- setting_name (text)
- setting_value (int)
```

---

## AJAX Actions

The page handles these AJAX POST requests:

### 1. **update_points**

```javascript
action=update_points&user_id=X&points=Y&reason=TEXT
// Sets exact points value
```

### 2. **add_points**

```javascript
action=add_points&user_id=X&points=Y&reason=TEXT
// Adds points to current value
```

### 3. **deduct_points**

```javascript
action=deduct_points&user_id=X&points=Y&reason=TEXT
// Removes points from current value
```

### 4. **get_user_history**

```javascript
action=get_user_history&user_id=X
// Returns last 20 history entries
```

---

## Workflow

### Adding/Deducting Points:

1. Admin clicks "Manage" button for a user
2. Modal opens showing current points
3. Admin enters points amount and reason
4. Admin clicks "Add" or "Deduct"
5. AJAX request sent
6. System updates database
7. History is logged
8. Page refreshes
9. Admin sees success message

### Setting Exact Points:

1. Admin opens user manage modal
2. Enters exact points value
3. Enters reason
4. Clicks "Set Exact Points"
5. System calculates change and logs it
6. History is updated

---

## Credit Points Settings

Settings stored in `credit_points_settings` table:

- `points_per_completed_loan` - Points awarded for completing a loan
- `bonus_points_first_loan` - Extra points for first loan
- `points_per_ontime_payment` - Points for paying on time
- `points_deduction_late_payment` - Points lost for late payment
- `points_deduction_default` - Points lost for defaulting
- `minimum_credit_points` - Lowest points allowed

---

## UI Components

### Navigation

- Sidebar menu with all admin options
- "CREDIT POINTS" link highlighted when active
- Only shows for admin1 and superadmin

### Stats Cards

- Displays 4 key metrics
- Color gradient background
- Auto-calculated from database

### User Table

- Sortable columns
- User avatars
- Points displayed as badges
- Action buttons for each row

### Modal Dialog

- Semi-transparent backdrop
- Close button
- User info display
- Input forms
- History section at bottom

---

## Security Features

✅ **Access Control**

- Checks $\_SESSION['role']
- Only admin1 and superadmin allowed
- Redirects unauthorized users

✅ **Input Validation**

- Points must be positive integers
- Reason is required
- Empty fields prevented

✅ **Database Security**

- Prepared statements used
- Parameter binding for all queries
- No SQL injection possible

✅ **Data Integrity**

- All changes logged in history
- Admin info recorded
- Previous points tracked

---

## Styling Features

- **Color Scheme**: Purple/Blue gradient (#667eea, #764ba2)
- **Green Accent**: #1b5e20 (primary button)
- **Icons**: Font Awesome 6.0
- **Font**: Poppins (Google Fonts)
- **Responsive Design**: Works on mobile and desktop

---

## JavaScript Functions

| Function            | Purpose                            |
| ------------------- | ---------------------------------- |
| `openManageModal()` | Open manage points modal for user  |
| `closeModal()`      | Close the modal                    |
| `setExactPoints()`  | Send exact points update AJAX      |
| `addPoints()`       | Send add points AJAX               |
| `deductPoints()`    | Send deduct points AJAX            |
| `loadHistory()`     | Fetch and display user history     |
| `showMessage()`     | Display success/error notification |
| `toggleDropdown()`  | Toggle admin profile menu          |

---

## Status & Quality

✅ **Fully Functional**

- All AJAX calls work correctly
- Database operations complete
- UI responsive and intuitive
- Error handling in place
- History tracking works

✅ **Features Complete**

- User management
- Points adjustment
- History tracking
- Leaderboard ready
- Settings management ready

---

## Example Workflow

1. **Superadmin logs in** → Sees "Credit Points" menu
2. **Clicks CREDIT POINTS** → Sees all users with points
3. **Clicks Manage on a user** → Modal opens
4. **Changes points and enters reason** → "User completed training course"
5. **Clicks Set Exact Points** → AJAX sends request
6. **Database updates** → History logged
7. **Page refreshes** → Shows success message
8. **User's points updated** → Visible in table

---

## Notes

- Points can be set to any positive value
- Minimum points enforced by system settings
- All changes are permanently logged
- No undo function (by design)
- Admin role is always recorded

---

**Status**: ✅ FULLY FUNCTIONAL AND COMPLETE
