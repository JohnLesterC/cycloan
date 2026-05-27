# FINDING & FIXING: Line-by-Line Reference

## 🎯 Exact Locations of Email Password in Code

### Email Block 1: "Completed" Status Email

**File:** `admin1_dashboard.php`  
**Search For:** `elseif ($creditStatus === 'Completed')`  
**Line Range:** 2230-2366

**The SMTP Configuration Block:**

```php
Line 2337:    $mailer = new PHPMailer(true);
Line 2338:    try {
Line 2339:        // Verify email address exists
Line 2340:        if (empty($currentApp['email'])) {
Line 2341:            error_log("Critical: No email address found for applicant ID: " . $currentApp['application_id']);
Line 2342:            throw new Exception("Applicant email address is missing");
Line 2343:        }
Line 2344:
Line 2345:        $mailer->isSMTP();
Line 2346:        $mailer->Host = 'smtp.gmail.com';
Line 2347:        $mailer->SMTPAuth = true;
Line 2348:        $mailer->Username = 'cycloancldd@gmail.com';
Line 2349:        $mailer->Password = 'hbfh ukgh tmzw nqbq';   ← ❌ INVALID PASSWORD #1
Line 2350:        $mailer->SMTPSecure = 'tls';
Line 2351:        $mailer->Port = 587;
Line 2352:        $mailer->setFrom('cycloancldd@gmail.com', 'CYCLOAN Loan Support');
Line 2353:        $mailer->addAddress($currentApp['email'], $currentApp['first_name'] . ' ' . $currentApp['last_name']);
Line 2354:        $mailer->isHTML(true);
Line 2355:        $mailer->Subject = $emailSubject;
Line 2356:        $mailer->Body = $emailBody;
Line 2357:        $mailer->AltBody = "Congratulations! Your loan application has been approved. Please visit our office to confirm and finalize your loan.";
Line 2358:        $mailer->send();
Line 2359:        error_log("✅ Credit investigation completion email successfully sent to: " . $currentApp['email'] . " (Application: " . $currentApp['application_id'] . ")");
Line 2360:    } catch (Exception $e) {
Line 2361:        error_log("❌ FAILED to send credit investigation email to: " . (isset($currentApp['email']) ? $currentApp['email'] : 'UNKNOWN') . " | Error: " . $e->getMessage() . " | PHPMailer: " . $mailer->ErrorInfo);
Line 2362:    }
```

---

### Email Block 2: "Failed" Status Email

**File:** `admin1_dashboard.php`  
**Search For:** `} elseif ($creditStatus === 'Failed')`  
**Line Range:** 2368-2432

**The SMTP Configuration Block:**

```php
Line 2396:    $mailer = new PHPMailer(true);
Line 2397:    try {
Line 2398:        // Verify email address exists
Line 2399:        if (empty($currentApp['email'])) {
Line 2400:            error_log("Critical: No email address found for applicant ID: " . $currentApp['application_id']);
Line 2401:            throw new Exception("Applicant email address is missing");
Line 2402:        }
Line 2403:
Line 2404:        $mailer->isSMTP();
Line 2405:        $mailer->Host = 'smtp.gmail.com';
Line 2406:        $mailer->SMTPAuth = true;
Line 2407:        $mailer->Username = 'cycloancldd@gmail.com';
Line 2408:        $mailer->Password = 'hbfh ukgh tmzw nqbq';   ← ❌ INVALID PASSWORD #2
Line 2409:        $mailer->SMTPSecure = 'tls';
Line 2410:        $mailer->Port = 587;
Line 2411:        $mailer->setFrom('cycloancldd@gmail.com', 'CYCLOAN Loan Support');
Line 2412:        $mailer->addAddress($currentApp['email'], $currentApp['first_name'] . ' ' . $currentApp['last_name']);
Line 2413:        $mailer->isHTML(true);
Line 2414:        $mailer->Subject = $emailSubject;
Line 2415:        $mailer->Body = $emailBody;
Line 2416:        $mailer->AltBody = "Your loan application requires further review. Please await communication from our team with next steps.";
Line 2417:        $mailer->send();
Line 2418:        error_log("✅ Credit investigation rejection email successfully sent to: " . $currentApp['email'] . " (Application: " . $currentApp['application_id'] . ")");
Line 2419:    } catch (Exception $e) {
Line 2420:        error_log("❌ FAILED to send credit investigation rejection email to: " . (isset($currentApp['email']) ? $currentApp['email'] : 'UNKNOWN') . " | Error: " . $e->getMessage() . " | PHPMailer: " . $mailer->ErrorInfo);
Line 2421:    }
```

---

## 🔍 HOW TO FIND THESE LINES IN VS CODE

### Method 1: Use Find & Replace

1. Press `Ctrl+H` to open Find & Replace
2. Find: `$mailer->Password = 'hbfh ukgh tmzw nqbq'`
3. Replace with: `$mailer->Password = 'YOUR_NEW_APP_PASSWORD'`
4. Click "Replace All" (will replace both instances)

### Method 2: Go to Line Number

1. Press `Ctrl+G` to open "Go to Line"
2. Type `2349` and press Enter (goes to first password)
3. Scroll down to find second one around line 2408

### Method 3: Search for Context

1. Press `Ctrl+F` to find
2. Search: `elseif ($creditStatus === 'Completed')`
3. This takes you to line 2230
4. Scroll down to find `$mailer->Password` line

---

## 📝 EXACT REPLACEMENTS

### Replacement 1: Line 2349 (Completed Email)

**FIND:**

```php
        $mailer->Password = 'hbfh ukgh tmzw nqbq';
```

**REPLACE WITH:**

```php
        $mailer->Password = 'PASTE_YOUR_NEW_APP_PASSWORD_HERE';
```

**Example After Fix:**

```php
        $mailer->Password = 'abcd efgh ijkl mnop';
```

---

### Replacement 2: Line 2408 (Failed Email)

**FIND:**

```php
        $mailer->Password = 'hbfh ukgh tmzw nqbq';
```

**REPLACE WITH:**

```php
        $mailer->Password = 'PASTE_YOUR_NEW_APP_PASSWORD_HERE';
```

**Example After Fix:**

```php
        $mailer->Password = 'abcd efgh ijkl mnop';
```

---

## 🎯 Context Before Each Password Line

### Context for First Password (Line 2349):

Look for this pattern:

```php
        $mailer->isSMTP();
        $mailer->Host = 'smtp.gmail.com';
        $mailer->SMTPAuth = true;
        $mailer->Username = 'cycloancldd@gmail.com';
        $mailer->Password = 'hbfh ukgh tmzw nqbq';   ← FIRST ONE
        $mailer->SMTPSecure = 'tls';
```

### Context for Second Password (Line 2408):

Look for this pattern:

```php
        $mailer->isSMTP();
        $mailer->Host = 'smtp.gmail.com';
        $mailer->SMTPAuth = true;
        $mailer->Username = 'cycloancldd@gmail.com';
        $mailer->Password = 'hbfh ukgh tmzw nqbq';   ← SECOND ONE
        $mailer->SMTPSecure = 'tls';
```

Both look identical - they ARE both the same!

---

## ✅ HOW TO GET THE NEW PASSWORD

### Step-by-Step with Screenshots in Mind:

1. **Open Browser:**

   - Navigate to: https://myaccount.google.com

2. **Login:**

   - Email: cycloancldd@gmail.com
   - Password: (your Gmail password)

3. **Go to Security:**

   - Left menu → "Security"
   - OR direct: https://myaccount.google.com/security

4. **Find App Passwords:**

   - Scroll down to "How you sign in to Google"
   - Look for "App passwords" (only visible if 2FA is enabled)
   - If not visible: Enable 2-Factor Authentication first

5. **Generate New Password:**

   - Click "App passwords"
   - Select: "Mail" (from dropdown)
   - Select: "Windows Computer" (from dropdown)
   - Click "Generate"

6. **Copy the Password:**
   - Google shows: `xxxx xxxx xxxx xxxx` (16 characters, 4 groups)
   - Copy: `xxxxxxxxxxxxxx` (without spaces for code)
   - Example: If shown as `abcd efgh ijkl mnop`, copy as `abcdefghijklmnop`

---

## ⚙️ VERIFICATION AFTER FIX

### Visual Verification:

Before fix, lines should look like:

```php
$mailer->Password = 'hbfh ukgh tmzw nqbq';
```

After fix, lines should look like:

```php
$mailer->Password = 'abcdefghijklmnop';
```

(Exact password depends on what Gmail generated)

### How to Verify Both Changed:

1. Use Find: `Ctrl+F`
2. Search: `hbfh ukgh tmzw nqbq`
3. Should find: 0 matches (if both replaced correctly)
4. Should find: 2 matches (if you only replaced one)

---

## 🧪 TEST AFTER FIXING

### Quick Test Procedure:

1. **Save file** (Ctrl+S)
2. **Refresh browser** (Ctrl+R or F5)
3. **Go to Admin Dashboard**
4. **Submit credit investigation:**

   - Application: Select any applicant
   - Credit Status: "Completed"
   - Final Loan Amount: 50000
   - Term Length: 24
   - Click: "Submit"

5. **Check Results:**
   - Admin dashboard should show success ✅
   - Applicant email should receive email within 5 minutes
   - debug_log.txt should show "✅ successfully sent"

---

## 📊 LINE NUMBER REFERENCE TABLE

| Item                  | Line #   | Content                                      |
| --------------------- | -------- | -------------------------------------------- |
| File Start            | 1        | `<?php`                                      |
| PHPMailer Include     | 12       | `require 'phpmailer/src/Exception.php';`     |
| Use Statement         | 16       | `use PHPMailer\PHPMailer\PHPMailer;`         |
| Completed Email Start | 2230     | `if ($creditStatus === 'Completed') {`       |
| Completed Password    | **2349** | `$mailer->Password = 'hbfh ukgh tmzw nqbq';` |
| Completed Send        | 2358     | `$mailer->send();`                           |
| Failed Email Start    | 2368     | `} elseif ($creditStatus === 'Failed') {`    |
| Failed Password       | **2408** | `$mailer->Password = 'hbfh ukgh tmzw nqbq';` |
| Failed Send           | 2417     | `$mailer->send();`                           |
| Remarks Insert        | 2475     | `if (!empty($remarks)) {`                    |
| Notification Create   | 2500     | `createStatusNotification(...)`              |
| Success Response      | 2514     | `echo json_encode(['success' => true, ...])` |
| Outer Catch           | 2520     | `} catch (Exception $e) {`                   |

---

## 🚨 COMMON MISTAKES TO AVOID

❌ **DON'T:**

- Replace without getting new password first
- Copy password WITH spaces into code: `'xxxx xxxx xxxx xxxx'` ← WRONG
- Replace only one location (there are 2!)
- Hardcode password differently in each location
- Use old/expired password

✅ **DO:**

- Get new app password from Gmail account settings
- Copy without spaces: `'xxxxxxxxxxxxxx'`
- Replace both locations identically
- Verify replacement worked with Find
- Test email immediately after fix

---

## 📞 TROUBLESHOOTING REFERENCE

| Problem                   | Location          | Check                    |
| ------------------------- | ----------------- | ------------------------ |
| Email not sent            | debug_log.txt     | Look for "❌ FAILED"     |
| Still see old password    | Line 2349         | Did you save file?       |
| Only one email type sends | Lines 2349 & 2408 | Did you update both?     |
| SMTP error 535            | Gmail account     | App password invalid     |
| Email format error        | Line 2353-2357    | Email variables correct? |
| Silent failure            | Line 2360         | Check debug_log.txt      |

---

## 📋 FINAL CHECKLIST

- [ ] Opened Gmail account settings
- [ ] Generated new app password (16 chars format)
- [ ] Copied new password exactly
- [ ] Opened admin1_dashboard.php
- [ ] Found line 2349 (first password)
- [ ] Replaced with new password
- [ ] Found line 2408 (second password)
- [ ] Replaced with new password (same as first)
- [ ] Saved file (Ctrl+S)
- [ ] Verified no old password remains (Find = 0 results)
- [ ] Tested with credit investigation
- [ ] Email received by applicant ✅
