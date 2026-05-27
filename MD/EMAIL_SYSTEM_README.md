# CYCLOAN Email System Documentation

## 📧 Overview

The CYCLOAN email system provides a centralized, professional email solution for sending notifications to users. It includes beautiful HTML email templates that match your brand design and a simple API for sending emails.

---

## 🎨 Features

✅ **Professional Email Templates**

- Welcome emails for new users
- Loan status updates (Approved, Rejected, Pending)
- Payment confirmations with invoice details
- Payment reminders with urgency indicators
- OTP verification codes

✅ **Responsive Design**

- Mobile-optimized layouts
- Works on all email clients (Gmail, Outlook, Apple Mail, etc.)
- Beautiful gradients matching CYCLOAN brand colors

✅ **Easy to Use**

- Simple function calls
- Centralized configuration
- Automatic error logging
- Fallback plain text versions

---

## 📁 File Structure

```
email_config.php        # Centralized email configuration
email_templates.php     # HTML email templates
email_sender.php        # Email sending functions
```

---

## 🚀 Quick Start

### Step 1: Include the Email Sender

```php
require_once 'email_sender.php';
```

### Step 2: Send an Email

```php
// Send welcome email
EmailSender::sendWelcomeEmail(
    'user@example.com',  // Recipient email
    'John Doe'           // Recipient name
);

// Send loan status update
EmailSender::sendLoanStatusEmail(
    'user@example.com',
    'John Doe',
    '12345',                                    // Loan ID
    'approved',                                 // Status
    'Your loan has been approved! Please...',  // Message
    '50000.00'                                  // Loan amount (optional)
);

// Send payment confirmation
EmailSender::sendPaymentConfirmation(
    'user@example.com',
    'John Doe',
    '12345',        // Loan ID
    5000.00,        // Amount paid
    '2025-01-15',   // Payment date
    45000.00,       // Remaining balance
    'INV-2025-001'  // Invoice number (optional)
);

// Send payment reminder
EmailSender::sendPaymentReminder(
    'user@example.com',
    'John Doe',
    '12345',       // Loan ID
    5000.00,       // Amount due
    '2025-02-15',  // Due date
    7              // Days until due
);

// Send OTP
EmailSender::sendOTPEmail(
    'user@example.com',
    'John Doe',
    '123456',  // OTP code
    10         // Expiry minutes
);
```

---

## 📝 Usage Examples

### Example 1: Send Welcome Email After Registration

```php
// In verify_otp.php or after user registration
require_once 'email_sender.php';

$userEmail = $_SESSION['form_data']['email'];
$userName = $_SESSION['form_data']['first_name'] . ' ' . $_SESSION['form_data']['last_name'];

EmailSender::sendWelcomeEmail($userEmail, $userName);
```

### Example 2: Send Loan Status Update

```php
// In admin dashboard when approving/rejecting loans
require_once 'email_sender.php';

// Get user email and name from database
$sql = "SELECT email, CONCAT(first_name, ' ', last_name) as full_name, loan_amount
        FROM users1 WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($newStatus == 'approved') {
    EmailSender::sendLoanStatusEmail(
        $user['email'],
        $user['full_name'],
        $application_id,
        'approved',
        'Congratulations! Your loan application has been approved. We will contact you shortly to finalize the details.',
        number_format($user['loan_amount'], 2)
    );
} elseif ($newStatus == 'rejected') {
    EmailSender::sendLoanStatusEmail(
        $user['email'],
        $user['full_name'],
        $application_id,
        'rejected',
        'Unfortunately, we are unable to approve your loan application at this time. Please contact our office for more details.'
    );
}
```

### Example 3: Send Payment Confirmation

```php
// In pay_balance.php after successful payment
require_once 'email_sender.php';

// Get user info from database
$sql = "SELECT u.email, CONCAT(u.first_name, ' ', u.last_name) as full_name
        FROM users1 u
        JOIN loan_applications la ON u.user_id = la.user_id
        WHERE la.application_loan_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $loan_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

EmailSender::sendPaymentConfirmation(
    $user['email'],
    $user['full_name'],
    $loan_id,
    $amount_paid,
    date('Y-m-d'),
    $remaining_balance,
    $invoice_number
);
```

### Example 4: Automated Payment Reminders (Cron Job)

```php
// Create payment_reminder_cron.php
<?php
require_once 'CYCLOAN_db.php';
require_once 'email_sender.php';

// Get all payments due in next 7 days
$sql = "SELECT ps.*, la.application_loan_id, u.email,
        CONCAT(u.first_name, ' ', u.last_name) as full_name,
        DATEDIFF(ps.due_date, CURDATE()) as days_until_due
        FROM payment_schedule ps
        JOIN loan_applications la ON ps.application_loan_id = la.application_loan_id
        JOIN users1 u ON la.user_id = u.user_id
        WHERE ps.status != 'Paid'
        AND ps.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";

$result = $conn->query($sql);

while ($payment = $result->fetch_assoc()) {
    EmailSender::sendPaymentReminder(
        $payment['email'],
        $payment['full_name'],
        $payment['application_loan_id'],
        $payment['amount'],
        $payment['due_date'],
        $payment['days_until_due']
    );
}
?>
```

---

## ⚙️ Configuration

### Email Server Settings

Edit `email_config.php` to update your email credentials:

```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_FROM_EMAIL', 'your-email@gmail.com');
define('SMTP_FROM_NAME', 'CYCLOAN Cooperative');
```

### Brand Colors

Update brand colors in `email_config.php`:

```php
define('PRIMARY_COLOR', '#1b5e20');    // Dark green
define('SECONDARY_COLOR', '#2e7d32');  // Medium green
define('ACCENT_COLOR', '#34c759');     // Bright green
```

---

## 🎨 Email Template Customization

### Adding a New Email Template

1. Open `email_templates.php`
2. Add a new static method to the `EmailTemplate` class:

```php
public static function yourNewTemplate($userName, $param1, $param2) {
    $content = <<<HTML
<h2 style="color: #1b5e20; margin-bottom: 16px;">Your Title</h2>
<p>Dear <strong>{$userName}</strong>,</p>
<p>Your custom content here...</p>

<div class="info-card">
    <h3>Information</h3>
    <div class="info-row">
        <span class="info-label">Label:</span>
        <span class="info-value">{$param1}</span>
    </div>
</div>

<center>
    <a href="https://cycloan-cldd.com" class="button">Action Button</a>
</center>
HTML;

    return self::getBaseTemplate($content, "Preheader text");
}
```

3. Add a corresponding method in `email_sender.php`:

```php
public static function sendYourNewEmail($toEmail, $userName, $param1, $param2) {
    try {
        $mail = self::configurePHPMailer();
        if (!$mail) return false;

        $mail->addAddress($toEmail, $userName);
        $mail->Subject = 'Your Subject Line';
        $mail->Body = EmailTemplate::yourNewTemplate($userName, $param1, $param2);
        $mail->AltBody = 'Plain text version';

        return $mail->send();
    } catch (Exception $e) {
        error_log("Email error: " . $e->getMessage());
        return false;
    }
}
```

---

## 🎯 Available CSS Classes in Templates

Use these classes in your email templates:

```css
.button              /* Primary action button */
/* Primary action button */
.info-card           /* Information container with green border */
.info-row            /* Row in info card */
.info-label          /* Label in info row */
.info-value          /* Value in info row (green color) */
.alert               /* Alert box */
.alert-success       /* Green success alert */
.alert-info          /* Blue info alert */
.alert-warning       /* Orange warning alert */
.divider; /* Horizontal divider line */
```

---

## 📱 Mobile Responsiveness

All email templates are mobile-responsive and will automatically adjust:

- Smaller fonts on mobile
- Stacked layouts for small screens
- Touch-friendly button sizes
- Optimized spacing

---

## ✅ Best Practices

1. **Always check return value**

   ```php
   if (EmailSender::sendWelcomeEmail($email, $name)) {
       // Email sent successfully
   } else {
       // Handle error
       error_log("Failed to send email to {$email}");
   }
   ```

2. **Use formatted numbers**

   ```php
   $formattedAmount = number_format($amount, 2);
   EmailSender::sendPaymentConfirmation(..., $formattedAmount, ...);
   ```

3. **Log all email activities**

   - The system automatically logs to PHP error log
   - Check logs for debugging: `error_log`

4. **Test emails before production**
   ```php
   // Test email
   EmailSender::sendCustomEmail(
       'your-test@email.com',
       'Test User',
       'Test Email',
       '<h1>This is a test</h1>',
       'This is a test'
   );
   ```

---

## 🐛 Troubleshooting

### Emails not sending?

1. **Check SMTP credentials**

   - Verify in `email_config.php`
   - For Gmail, use App Password (not regular password)

2. **Enable debugging**

   ```php
   // In email_sender.php, uncomment:
   $mail->SMTPDebug = 2;
   ```

3. **Check error logs**

   ```php
   // View PHP error log
   tail -f /path/to/php-error.log
   ```

4. **Test SMTP connection**
   ```php
   telnet smtp.gmail.com 587
   ```

### Gmail App Password Setup

1. Go to Google Account → Security
2. Enable 2-Step Verification
3. Go to App Passwords
4. Generate new app password for "Mail"
5. Use that password in `email_config.php`

---

## 📊 Email Analytics (Optional)

Track email opens and clicks by adding:

```php
// In email template
<img src="https://your-domain.com/track.php?email_id=<?php echo $emailId; ?>" width="1" height="1">

// In track.php
<?php
require_once 'CYCLOAN_db.php';
$email_id = $_GET['email_id'];
$conn->query("UPDATE email_logs SET opened_at = NOW() WHERE id = {$email_id}");
header('Content-Type: image/gif');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
?>
```

---

## 🔒 Security Notes

1. **Never commit credentials**

   - Add `email_config.php` to `.gitignore`
   - Use environment variables in production

2. **Validate email addresses**

   ```php
   if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
       EmailSender::sendWelcomeEmail($email, $name);
   }
   ```

3. **Sanitize user input**
   ```php
   $userName = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
   ```

---

## 📈 Future Enhancements

- [ ] Email queue system for bulk sending
- [ ] Email templates admin panel
- [ ] Email open/click tracking
- [ ] Scheduled email sending
- [ ] Email template A/B testing
- [ ] Attachment support
- [ ] CC/BCC functionality

---

## 📞 Support

For issues or questions:

- Email: cycloancorp@gmail.com
- Check error logs
- Review PHPMailer documentation

---

**Last Updated**: January 2025  
**Version**: 1.0.0  
**Status**: ✅ Production Ready
