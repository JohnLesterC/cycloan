# Real-Time Email Validation - TESTING GUIDE

## Quick Test Checklist

### Basic Functionality Tests

#### Test 1: Valid New Email ✓

**Steps:**

1. Open registration.php?step=1
2. Enter a new email that doesn't exist in the database
3. Wait for 500ms after typing stops
4. Observe result

**Expected:**

- ✓ Green checkmark appears
- ✓ Message: "Email is available"
- ✓ Input border turns green
- ✓ Email field has `is-valid` class

**Actual Result:** ******\_\_\_******

**Pass/Fail:** \_\_\_

---

#### Test 2: Already Registered Email ✓

**Steps:**

1. Open registration.php?step=1
2. Enter an email that exists in the database (e.g., from a previous registration)
3. Wait for 500ms after typing stops
4. Observe result

**Expected:**

- ✗ Red X appears
- ✗ Message: "This email is already registered..."
- ✗ Input border turns red
- ✗ Email field has `is-invalid` class

**Actual Result:** ******\_\_\_******

**Pass/Fail:** \_\_\_

---

#### Test 3: Invalid Email Format ✓

**Steps:**

1. Open registration.php?step=1
2. Type: `invalidemail` (no @ symbol)
3. Leave the field or type more
4. Observe result

**Expected:**

- ✗ Red X appears
- ✗ Message: "Invalid email format"
- ✗ Input border turns red

**Actual Result:** ******\_\_\_******

**Pass/Fail:** \_\_\_

---

#### Test 4: Empty Email Field ✓

**Steps:**

1. Open registration.php?step=1
2. Leave email field empty
3. Tab away from the field
4. Observe result

**Expected:**

- No feedback message appears
- No validation indicator

**Actual Result:** ******\_\_\_******

**Pass/Fail:** \_\_\_

---

### User Experience Tests

#### Test 5: Loading State Display ✓

**Steps:**

1. Open registration.php?step=1
2. Type a new email address
3. Immediately look for loading indicator
4. Observe before result appears

**Expected:**

- Loading spinner appears briefly
- Message: "Checking availability..."
- Spinner disappears when validation completes

**Actual Result:** ******\_\_\_******

**Pass/Fail:** \_\_\_

---

#### Test 6: Debouncing (Only 1 API Call) ✓

**Steps:**

1. Open browser DevTools (F12)
2. Go to Network tab
3. Open registration.php?step=1
4. Type an email quickly: `testuser@example.com`
5. Count POST requests to validate_email.php

**Expected:**

- Only 1 POST request to validate_email.php
- Not multiple requests for each keystroke

**Actual Result:** ******\_\_\_******

**Pass/Fail:** \_\_\_

---

#### Test 7: Form Submission Blocked ✓

**Steps:**

1. Open registration.php?step=1
2. Enter an already-registered email (Test 2 email)
3. Fill out other required fields on this step
4. Click "Next" button

**Expected:**

- Form does NOT submit
- Red error message remains
- Page stays on same step
- Email field scrolls into view and receives focus

**Actual Result:** ******\_\_\_******

**Pass/Fail:** \_\_\_

---

#### Test 8: Form Submission Allowed ✓

**Steps:**

1. Open registration.php?step=1
2. Enter a new valid email
3. Wait for green checkmark
4. Fill out other required fields
5. Click "Next" button

**Expected:**

- Form submits successfully
- Page advances to step 2
- Green validation remains visible until form submits

**Actual Result:** ******\_\_\_******

**Pass/Fail:** \_\_\_

---

### Edge Case Tests

#### Test 9: Email with Plus Sign ✓

**Steps:**

1. Open registration.php?step=1
2. Type: `testuser+tag@example.com`

**Expected:**

- Validates correctly (+ is valid in email)
- Shows appropriate result based on database

**Actual Result:** ******\_\_\_******

**Pass/Fail:** \_\_\_

---

#### Test 10: Email with Dots ✓

**Steps:**

1. Open registration.php?step=1
2. Type: `test.user@example.com`

**Expected:**

- Validates correctly (dots are valid)
- Shows appropriate result based on database

**Actual Result:** ******\_\_\_******

**Pass/Fail:** \_\_\_

---

#### Test 11: Mixed Case Email ✓

**Steps:**

1. Open registration.php?step=1
2. Type: `TestUser@Example.COM`

**Expected:**

- Validates correctly
- Treats as same email if stored in lowercase

**Actual Result:** ******\_\_\_******

**Pass/Fail:** \_\_\_

---

#### Test 12: Email with Extra Spaces ✓

**Steps:**

1. Open registration.php?step=1
2. Type: ` testuser@example.com ` (with leading/trailing spaces)

**Expected:**

- Spaces are trimmed during validation
- Shows correct validation result

**Actual Result:** ******\_\_\_******

**Pass/Fail:** \_\_\_

---

### Mobile/Responsive Tests

#### Test 13: Mobile View (Small Screen) ✓

**Steps:**

1. Open registration.php?step=1 on mobile or use F12 device emulation
2. Set viewport to 375px (iPhone SE)
3. Type an email
4. Observe validation feedback

**Expected:**

- Feedback message displays correctly
- No text overflow
- Readable on small screen
- Checkmark/X icon visible

**Actual Result:** ******\_\_\_******

**Pass/Fail:** \_\_\_

---

#### Test 14: Tablet View (Medium Screen) ✓

**Steps:**

1. Open registration.php?step=1 on tablet or use F12 device emulation
2. Set viewport to 768px (iPad)
3. Type an email
4. Observe validation feedback

**Expected:**

- Feedback message displays correctly
- Proper spacing and alignment
- All visual elements visible

**Actual Result:** ******\_\_\_******

**Pass/Fail:** \_\_\_

---

### Browser Compatibility Tests

#### Test 15: Chrome/Chromium ✓

**Steps:**

1. Open Chrome browser
2. Navigate to registration.php?step=1
3. Run Test 1 (Valid New Email)

**Expected:**

- All features work correctly
- No console errors

**Browser Version:** ******\_\_\_******
**Pass/Fail:** \_\_\_

---

#### Test 16: Firefox ✓

**Steps:**

1. Open Firefox browser
2. Navigate to registration.php?step=1
3. Run Test 1 (Valid New Email)

**Expected:**

- All features work correctly
- No console errors

**Browser Version:** ******\_\_\_******
**Pass/Fail:** \_\_\_

---

#### Test 17: Safari ✓

**Steps:**

1. Open Safari browser
2. Navigate to registration.php?step=1
3. Run Test 1 (Valid New Email)

**Expected:**

- All features work correctly
- No console errors

**Browser Version:** ******\_\_\_******
**Pass/Fail:** \_\_\_

---

### Security Tests

#### Test 18: XSS Prevention ✓

**Steps:**

1. Open registration.php?step=1
2. Type: `<script>alert('XSS')</script>@example.com`

**Expected:**

- No JavaScript alert appears
- Message: "Invalid email format"
- Safe error message displayed

**Actual Result:** ******\_\_\_******

**Pass/Fail:** \_\_\_

---

#### Test 19: SQL Injection Prevention ✓

**Steps:**

1. Open registration.php?step=1
2. Type: `test' OR '1'='1@example.com`

**Expected:**

- No SQL error displayed
- Treated as invalid email format
- Safe error message displayed

**Actual Result:** ******\_\_\_******

**Pass/Fail:** \_\_\_

---

### Error Handling Tests

#### Test 20: Database Connection Error ✓

**Steps:**

1. Temporarily disconnect database or stop MySQL service
2. Open registration.php?step=1
3. Type a new email

**Expected:**

- No JavaScript error in console
- Graceful handling of error
- Generic message: "An error occurred while validating the email"
- No sensitive database information exposed

**Actual Result:** ******\_\_\_******

**Pass/Fail:** \_\_\_

---

#### Test 21: Network Error (Offline Mode) ✓

**Steps:**

1. Open DevTools (F12) > Network tab > Offline
2. Open registration.php?step=1 (or keep it open)
3. Type a new email

**Expected:**

- Validation gracefully fails
- Form can still be submitted with manual validation
- No broken UI elements

**Actual Result:** ******\_\_\_******

**Pass/Fail:** \_\_\_

---

### Performance Tests

#### Test 22: API Response Time ✓

**Steps:**

1. Open DevTools Network tab
2. Open registration.php?step=1
3. Type an email
4. Observe Network tab for validate_email.php request
5. Note response time

**Expected:**

- Response time: < 300ms
- Ideally: 100-200ms

**Actual Response Time:** **_ ms
**Pass/Fail:** _**

---

#### Test 23: Rapid Email Changes ✓

**Steps:**

1. Open registration.php?step=1
2. Quickly type multiple different emails: `test1@test1.com`, then `test2@test2.com`, then `test3@test3.com`
3. Watch Network tab for API calls

**Expected:**

- Only validates the final email
- No API calls for intermediate emails
- Efficient debouncing

**Actual Result:** ******\_\_\_******

**Pass/Fail:** \_\_\_

---

### Integration Tests

#### Test 24: Multi-Step Form Flow ✓

**Steps:**

1. Start registration with valid new email (Test 8)
2. Complete step 1 with valid email
3. Click "Next"
4. Verify step 2 loads correctly
5. Go back to step 1
6. Verify validation still works

**Expected:**

- Form progresses correctly
- Validation persists after navigation
- No data loss

**Actual Result:** ******\_\_\_******

**Pass/Fail:** \_\_\_

---

#### Test 25: Form Data Persistence ✓

**Steps:**

1. Enter email: `test@test.com`
2. Wait for validation (should be registered or new)
3. Refresh page
4. Check if email field is populated

**Expected:**

- Email value persists from session
- Validation runs again on page load
- Correct feedback displays

**Actual Result:** ******\_\_\_******

**Pass/Fail:** \_\_\_

---

## Test Results Summary

| Test # | Test Name                | Status | Notes |
| ------ | ------------------------ | ------ | ----- |
| 1      | Valid New Email          | ✓/✗    |       |
| 2      | Already Registered Email | ✓/✗    |       |
| 3      | Invalid Email Format     | ✓/✗    |       |
| 4      | Empty Email Field        | ✓/✗    |       |
| 5      | Loading State Display    | ✓/✗    |       |
| 6      | Debouncing               | ✓/✗    |       |
| 7      | Form Submission Blocked  | ✓/✗    |       |
| 8      | Form Submission Allowed  | ✓/✗    |       |
| 9      | Plus Sign in Email       | ✓/✗    |       |
| 10     | Dots in Email            | ✓/✗    |       |
| 11     | Mixed Case Email         | ✓/✗    |       |
| 12     | Email with Spaces        | ✓/✗    |       |
| 13     | Mobile View              | ✓/✗    |       |
| 14     | Tablet View              | ✓/✗    |       |
| 15     | Chrome Browser           | ✓/✗    |       |
| 16     | Firefox Browser          | ✓/✗    |       |
| 17     | Safari Browser           | ✓/✗    |       |
| 18     | XSS Prevention           | ✓/✗    |       |
| 19     | SQL Injection Prevention | ✓/✗    |       |
| 20     | Database Error Handling  | ✓/✗    |       |
| 21     | Network Error Handling   | ✓/✗    |       |
| 22     | API Response Time        | ✓/✗    |       |
| 23     | Rapid Email Changes      | ✓/✗    |       |
| 24     | Multi-Step Form Flow     | ✓/✗    |       |
| 25     | Form Data Persistence    | ✓/✗    |       |

**Total Tests: 25**  
**Passed: \_\_**  
**Failed: \_\_**  
**Pass Rate: \_%**

---

## Browser & Device Testing Matrix

| Browser | Version | Desktop | Mobile | Tablet | Status |
| ------- | ------- | ------- | ------ | ------ | ------ |
| Chrome  | Latest  | ✓/✗     | ✓/✗    | ✓/✗    |        |
| Firefox | Latest  | ✓/✗     | ✓/✗    | ✓/✗    |        |
| Safari  | Latest  | ✓/✗     | ✓/✗    | ✓/✗    |        |
| Edge    | Latest  | ✓/✗     | ✓/✗    | ✓/✗    |        |

---

## Known Issues / Bugs Found

### Issue #1

- **Description**: ******\_\_\_******
- **Steps to Reproduce**: ******\_\_\_******
- **Expected Behavior**: ******\_\_\_******
- **Actual Behavior**: ******\_\_\_******
- **Severity**: [Critical/High/Medium/Low]
- **Status**: [New/In Progress/Fixed]

### Issue #2

- **Description**: ******\_\_\_******
- **Steps to Reproduce**: ******\_\_\_******
- **Expected Behavior**: ******\_\_\_******
- **Actual Behavior**: ******\_\_\_******
- **Severity**: [Critical/High/Medium/Low]
- **Status**: [New/In Progress/Fixed]

---

## Test Environment Details

**Server:**

- PHP Version: ******\_\_\_******
- MySQL Version: ******\_\_\_******
- Database: ******\_\_\_******

**Client:**

- OS: ******\_\_\_******
- Browser: ******\_\_\_******
- Screen Resolution: ******\_\_\_******

**Network:**

- Connection Type: ******\_\_\_******
- Latency: \_\_\_ ms
- Bandwidth: \_\_\_ Mbps

---

## Tester Information

**Name**: ******\_\_\_******  
**Date**: ******\_\_\_******  
**Time Spent**: \_\_\_ hours  
**Overall Status**: ✓ PASS / ✗ FAIL

**Comments/Feedback**:

---

---

---

---

## Sign-Off

**Tested By**: ********\_\_\_******** (Signature/Initials)

**Date**: ******\_\_\_******

**Approved By**: ********\_\_\_******** (Supervisor)

**Date**: ******\_\_\_******

---

## Next Steps

- [ ] All tests passed
- [ ] Issues logged and assigned
- [ ] Ready for production deployment
- [ ] Need additional testing
- [ ] Need bug fixes

**Recommended Actions**:

---

---

---
