# 🎓 MANAGE CREDIT POINTS - USAGE GUIDE & TROUBLESHOOTING

## Quick Start Guide

### Accessing the Feature

1. **Login** as superadmin or admin1
2. **Go to Dashboard** → Click "CREDIT POINTS" in sidebar
3. **View all users** with their current credit points
4. **Click "Manage"** on any user to adjust points

---

## Usage Scenarios

### Scenario 1: Award Points for Achievement

**Task**: Give a user 50 points for completing training

**Steps**:

1. Find user in table
2. Click "Manage"
3. In "Or Add/Deduct Points" section:
   - Enter **50** in points field
   - Click **"+ Add"** button
4. Enter reason: **"Completed loan officer training"**
5. Modal shows history below
6. Click confirmation
7. ✅ User gains 50 points

**Result**:

- User's points increase by 50
- History shows: "+50 points - Completed loan officer training"
- Admin name recorded
- Timestamp recorded

---

### Scenario 2: Penalize Late Payment

**Task**: Deduct 25 points for late payment

**Steps**:

1. Find user in table
2. Click "Manage"
3. In "Or Add/Deduct Points" section:
   - Enter **25** in points field
   - Click **"- Deduct"** button
4. Enter reason: **"Late payment - Loan #2025-001"**
5. Click confirmation
6. ✅ User loses 25 points

**Result**:

- User's points decrease by 25
- History shows: "-25 points - Late payment - Loan #2025-001"
- Minimum points respected (won't go below limit)

---

### Scenario 3: Set Exact Score

**Task**: Reset a user's points to exactly 500 (investigation/correction)

**Steps**:

1. Find user in table
2. Click "Manage"
3. **Clear "Set Exact Points"** field (shows current value)
4. Enter **500** as new exact value
5. Enter reason: **"Score correction - Audit request"**
6. Click **"Set Exact Points"** button
7. ✅ User's points set to 500

**Result**:

- User's points become exactly 500
- System calculates difference (e.g., was 650, now 500 = -150)
- History shows: "-150 points - Score correction - Audit request"

---

## Common Use Cases

### ✅ Use Case 1: Automatic Rewards

```
When: User completes a loan on time
What: auto-award points
Who: System (no admin needed)
How: creditManager->awardLoanCompletionPoints()
Result: Points added, history logged
```

### ✅ Use Case 2: Manual Adjustment

```
When: Admin needs to correct points
What: Set exact value
Who: Admin clicks button
How: Modal → Set Exact Points
Result: New value set, history logged
```

### ✅ Use Case 3: Promotional Bonus

```
When: Special promotion or event
What: Add points to group
Who: Admin manually
How: Individual user management
Result: Each user gets bonus, history logs it
```

### ✅ Use Case 4: Penalty

```
When: Late payment, default, or violation
What: Deduct points
Who: Manual or automatic
How: Modal deduct OR system deduct
Result: Points removed, minimum respected
```

---

## Understanding the Interface

### Main Table

```
┌─────────────────────────────────────────────────────────────┐
│ User         │ Email              │ Points   │ Last Updated  │
├─────────────────────────────────────────────────────────────┤
│ [IMG] Jane   │ jane@example.com   │ 850 pts  │ Nov 02, 2025  │
│ [IMG] John   │ john@example.com   │ 720 pts  │ Nov 01, 2025  │
│ [IMG] Sarah  │ sarah@example.com  │ 600 pts  │ Oct 30, 2025  │
└─────────────────────────────────────────────────────────────┘

Features:
✓ Shows user avatar
✓ Full name displayed
✓ Email shown
✓ Current points in badge
✓ Last update date
✓ Action button on each row
✓ Sorted by points (highest first)
```

### Modal Dialog

```
┌──────────────────────────────────────────────┐
│ Manage Credit Points                     [X] │
├──────────────────────────────────────────────┤
│                                              │
│ Jane Doe                                     │
│ Current Points: 850 pts                      │
│                                              │
│ Set Exact Points                             │
│ [____________850______________]              │
│                                              │
│ Or Add/Deduct Points                         │
│ [________]  [+ Add]  [- Deduct]              │
│                                              │
│ Reason (Required)                            │
│ [          Completed training        ]       │
│                                              │
│ [Cancel]  [Set Exact Points]                 │
│                                              │
│ ────────────────────────────────────────────  │
│                                              │
│ Point History                                │
│                                              │
│ [+50 pts] Nov 02, 2025                       │
│ Completed loan officer training              │
│ By: John Admin                               │
│                                              │
│ [-25 pts] Nov 01, 2025                       │
│ Late payment - Loan #2025-001                │
│ By: Admin System                             │
│                                              │
└──────────────────────────────────────────────┘
```

---

## Settings You Can Manage

The system has these configurable settings (in `credit_points_settings`):

```
Setting Name                      | Default | Description
─────────────────────────────────────────────────────────────────
points_per_completed_loan         | 100     | Points for finishing loan
bonus_points_first_loan          | 50      | Extra bonus on first loan
points_per_ontime_payment        | 25      | Points for payment before due date
points_deduction_late_payment    | -20     | Penalty for late payment
points_deduction_default         | -100    | Penalty for loan default
minimum_credit_points            | 0       | Lowest allowed score
```

**To modify**: Contact database or use settings admin panel (if available)

---

## Error Handling

### Error: "Reason is required"

```
Problem: User didn't enter a reason
Solution: Type a reason in "Reason" field before clicking
Example: "First loan bonus", "Late payment penalty"
```

### Error: "Points must be greater than 0"

```
Problem: Entered 0 or negative when using Add/Deduct
Solution: Enter positive number (system handles +/-)
Example: Enter 50, not -50 or 0
```

### Error: "Failed to update credit points"

```
Problem: Database connection or permission issue
Solution:
  1. Verify you're logged in as admin1 or superadmin
  2. Check database connection
  3. Refresh page and try again
  4. Check server logs
```

### Error: "No history found"

```
Problem: User has no prior point changes
Solution: This is normal for new users
Action: First operation will create history
```

---

## Validation Rules

✅ **Input Validation**

```
Points value:
  ✓ Must be an integer (no decimals)
  ✓ Must be positive when adding/deducting
  ✓ Can be any value for "Set Exact"
  ✓ System respects minimum points

Reason field:
  ✓ Required (cannot be empty)
  ✓ Can be any text
  ✓ Recommended: Be specific
  ✓ Examples: "Training complete", "Late payment #2025-001"
```

---

## Database Queries

### Check a User's Points

```sql
SELECT credit_points, credit_points_updated_at
FROM users1
WHERE id = 123;
```

### View User's History

```sql
SELECT * FROM credit_points_history
WHERE user_id = 123
ORDER BY created_at DESC;
```

### Get Statistics

```sql
SELECT
  COUNT(*) as total_users,
  SUM(credit_points) as total_points,
  AVG(credit_points) as avg_score,
  MAX(credit_points) as highest_score,
  MIN(credit_points) as lowest_score
FROM users1
WHERE status = 'active';
```

---

## Troubleshooting

### Issue: Modal won't open

```
Symptom: Click "Manage" but nothing happens
Solution:
  1. Clear browser cache (Ctrl+Shift+Del)
  2. Refresh page (F5)
  3. Check browser console (F12 → Console tab)
  4. Try different user
```

### Issue: Changes not saving

```
Symptom: Click button but points don't update
Solution:
  1. Check reason field (must have text)
  2. Check network tab (F12 → Network)
  3. Wait 2 seconds for AJAX to complete
  4. Check server logs for errors
  5. Verify admin1 or superadmin role
```

### Issue: History not showing

```
Symptom: Modal opens but history is blank
Solution:
  1. Wait for history to load (shows "Loading...")
  2. Close and reopen modal
  3. This is normal for first-time users
```

### Issue: Points go negative

```
Symptom: Points become less than minimum
Solution:
  This shouldn't happen - system enforces minimum
  If it does:
    1. Use "Set Exact Points" to fix
    2. Set to minimum (usually 0)
    3. Report bug to developer
```

---

## Performance Tips

✅ **For Superadmin**

```
✓ Keep browser refreshed (clears cache)
✓ Don't have multiple modals open
✓ Wait for refresh before another action
✓ Use pagination if 1000+ users
```

---

## Security Reminders

🔐 **Important**

```
✓ Only share admin credentials with trusted people
✓ All changes are logged with admin name
✓ Cannot undo changes (designed this way)
✓ Always enter accurate reason
✓ Invalid operations are prevented by validation
✓ Session expires after inactivity
```

---

## FAQ

### Q: Can I undo a points change?

**A**: No, by design. All changes are permanent. Use "Set Exact Points" to correct if needed.

### Q: Who can see the history?

**A**: History is internal - shows admin name who made change. Users see their points in dashboard.

### Q: What if I deduct too many points?

**A**: Use "Set Exact Points" to correct. System logs the correction.

### Q: Can users earn points automatically?

**A**: Yes! System auto-awards for: loan completion, on-time payment. Auto-deducts for: late payment, default.

### Q: What's the maximum points?

**A**: No hard limit. Can set to any value superadmin chooses.

### Q: Can I bulk-update users?

**A**: Currently no. Must do individually. Feature could be added if needed.

### Q: Is there a leaderboard users can see?

**A**: Function exists. May be shown on user dashboard (depends on design).

---

## Tips & Tricks

💡 **Efficiency Tips**

```
1. Sort table by points to find high/low scorers
2. Use Ctrl+F to search user in table
3. Keep modal open to adjust multiple users
4. Copy-paste common reasons to save time
5. Note down user IDs for batch operations
```

---

## Contact & Support

**Issues?** Check:

1. This guide
2. Server logs (`/var/log/...`)
3. Browser console (F12)
4. Database directly (SQL queries)

---

**Last Updated**: November 2, 2025
**Version**: 1.0
**Status**: ✅ FULLY FUNCTIONAL
