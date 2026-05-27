# Login Troubleshooting Guide

## Debug Steps to Find the Issue

### 1. **Check Error Logs**
Navigate to your server's error log:
- **Apache**: `/var/log/apache2/error.log` or `C:\xampp\apache\logs\error.log`
- **PHP**: Check `php.ini` for `error_log` location
- **Look for lines with**: "Login attempt for email:"

Run:
```bash
tail -f /var/log/apache2/error.log | grep "Login attempt"
```

### 2. **Test Database Connection**
Create a test file `test_db.php`:
```php
<?php
require 'CYCLOAN_db.php';

// Test if connection works
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "Connection successful!<br>";

// Test if users1 table exists
$result = $conn->query("SHOW TABLES LIKE 'users1'");
echo "users1 table exists: " . ($result->num_rows > 0 ? "YES" : "NO") . "<br>";

// Check if user exists
$email = "test@example.com"; // Replace with your test email
$stmt = $conn->prepare("SELECT id, email, is_active FROM users1 WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
echo "Test user found: " . ($result->num_rows > 0 ? "YES" : "NO") . "<br>";

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo "User ID: " . $user['id'] . "<br>";
    echo "Email: " . $user['email'] . "<br>";
    echo "Active: " . ($user['is_active'] ? "YES" : "NO") . "<br>";
}
?>
```

### 3. **Check Password Hash**
Create a test file `test_password.php`:
```php
<?php
require 'CYCLOAN_db.php';

$test_email = "test@example.com"; // Replace with your email
$test_password = "yourpassword"; // Replace with your password

// Get stored hash
$stmt = $conn->prepare("SELECT password FROM users1 WHERE email = ?");
$stmt->bind_param("s", $test_email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user) {
    $stored_hash = $user['password'];
    echo "Stored Hash: " . $stored_hash . "<br>";
    echo "Hash verification result: " . (password_verify($test_password, $stored_hash) ? "MATCH ✓" : "NO MATCH ✗") . "<br>";
} else {
    echo "User not found!";
}
?>
```

### 4. **Check Browser Console**
- Press `F12` in browser
- Go to **Network** tab
- Try to login
- Check if request is being sent
- Check response status (should be 302 for redirect or 200)

### 5. **Check Session Files**
Session files are usually in `/var/lib/php/sessions/` or `C:\xampp\tmp\`
- Look for files modified recently
- Check if session contains user_id, email, role

### 6. **Common Issues**

#### Issue: "Account not verified. Please check your email for the OTP."
- **Solution**: Set `is_active = 1` in database for your user

```sql
UPDATE users1 SET is_active = 1 WHERE email = 'your_email@example.com';
```

#### Issue: "Invalid email or password."
- **Check 1**: Email matches exactly (case-sensitive check)
- **Check 2**: Password hash is valid (see test_password.php)
- **Check 3**: Password was hashed with `password_hash($password, PASSWORD_DEFAULT)`

#### Issue: Loading spinner never disappears
- **Solution**: Check browser console for JavaScript errors
- **Solution**: Check Network tab to see if redirect happened

#### Issue: Redirects but not logged in
- **Solution**: Check if redirect URL is correct
- **Solution**: Check if session is being created properly

### 7. **View Debug Logs**
Add this to `index.php` temporarily to see debug output:
```php
// At the top after session_start()
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

Then check the page for error messages.

### 8. **Database Query Check**
Run these SQL queries to debug:

```sql
-- Check if user exists
SELECT * FROM users1 WHERE email = 'your_email@example.com';

-- Check if password hash is valid format (should start with $2y$ or $2a$)
SELECT email, password FROM users1 LIMIT 1;

-- Check login attempts table
SELECT * FROM logattempts WHERE email = 'your_email@example.com' ORDER BY attempt DESC LIMIT 10;

-- Check if there are any active users
SELECT COUNT(*) as active_users FROM users1 WHERE is_active = 1;
```

### 9. **Most Common Solutions**

1. **User account is not active (is_active = 0)**
   ```sql
   UPDATE users1 SET is_active = 1 WHERE email = 'your_email@example.com';
   ```

2. **Password hash is corrupted/incorrect**
   - Reset password with `password_hash()`:
   ```php
   $new_password = password_hash('newpassword', PASSWORD_DEFAULT);
   echo $new_password; // Copy this hash
   ```
   - Update database:
   ```sql
   UPDATE users1 SET password = '$2y$10$...' WHERE email = 'your_email@example.com';
   ```

3. **Database connection failing silently**
   - Check `CYCLOAN_db.php` for correct credentials
   - Verify MySQL server is running
   - Check firewall/network issues

4. **Session not being saved**
   - Check `/var/lib/php/sessions/` folder has write permissions
   - Verify `session.save_path` in `php.ini`

### 10. **Next Steps**
1. Run `test_db.php` and `test_password.php`
2. Check error logs for "Login attempt for email:" messages
3. Verify user `is_active = 1`
4. Verify password hash is correct
5. Try logging in and check Network tab for redirect

**Still not working?** Share the output from:
- `test_db.php`
- Error log lines with "Login attempt"
- SQL query results from step 8
