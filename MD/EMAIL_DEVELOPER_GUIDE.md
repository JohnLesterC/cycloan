# Email System Quick Reference

## For Developers: How to Send Professional Emails

### 1. Basic Email Sending

```php
// Step 1: Generate email content
$content = "
    <p>Your custom message here.</p>
    <div class='highlight-box'>
        <p><strong>Important Info:</strong> Details here</p>
    </div>
    <a href='http://cycloan-cldd.com/page.php' class='button'>Click Here</a>
    <p>Best regards,<br><strong>The CLDD Team</strong></p>
";

// Step 2: Generate email template
$emailBody = generateEmailTemplate($userName, $content);

// Step 3: Send email
$subject = "Your Subject Line";
$result = sendEmail($userEmail, $userName, $subject, $emailBody, "Context for logging");
```

### 2. Available CSS Classes

```php
// Highlight Box (green border, light green background)
<div class='highlight-box'>Your content</div>

// Button (green, rounded, hover effect)
<a href='url' class='button'>Button Text</a>

// Custom styling examples:
<p style='color: #2e7d32;'>Green text</p>
<p style='font-size: 18px; font-weight: bold;'>Big bold text</p>
```

### 3. Status Icons & Colors

```php
// Approved/Completed
$statusIcon = '✅';
$statusColor = '#2e7d32'; // Green

// Rejected/Cancelled/Failed
$statusIcon = '❌';
$statusColor = '#d32f2f'; // Red

// Pending/New
$statusIcon = '⏳';
$statusColor = '#fbc02d'; // Yellow

// Active
$statusIcon = '🔵';
$statusColor = '#1976d2'; // Blue

// Closed
$statusIcon = '⚫';
$statusColor = '#757575'; // Gray

// Usage:
<span style='color: $statusColor;'>$statusIcon $status</span>
```

### 4. Common Email Patterns

#### Pattern A: Status Update

```php
$content = "
    <p>We have an update regarding your application.</p>
    <div class='highlight-box'>
        <p style='font-size: 18px;'>$statusIcon <strong>Status:</strong>
           <span style='color: $statusColor;'>$status</span></p>
        <p><strong>Application ID:</strong> $applicationId</p>
    </div>
    <p>Additional information here.</p>
    <a href='http://cycloan-cldd.com/user_dashboard.php' class='button'>View Details</a>
    <p>Best regards,<br><strong>The CLDD Loan Support Team</strong></p>
";
```

#### Pattern B: Information Display

```php
$content = "
    <p>Here is the information you requested.</p>
    <div class='highlight-box'>
        <p><strong>Field 1:</strong> Value 1</p>
        <p><strong>Field 2:</strong> Value 2</p>
        <p><strong>Field 3:</strong> Value 3</p>
    </div>
    <a href='url' class='button'>Take Action</a>
    <p>Best regards,<br><strong>The CLDD Loan Support Team</strong></p>
";
```

#### Pattern C: Multi-Section

```php
$content = "
    <p>Your introduction message.</p>

    <div class='highlight-box'>
        <p><strong>Section 1 Title</strong></p>
        <p>Section 1 content</p>
    </div>

    <div class='highlight-box' style='background-color: #e3f2fd; border-left-color: #1976d2;'>
        <p><strong>Section 2 Title (Blue theme)</strong></p>
        <p>Section 2 content</p>
    </div>

    <a href='url' class='button'>Primary Action</a>
    <p>Best regards,<br><strong>The CLDD Loan Support Team</strong></p>
";
```

### 5. Function Signatures

```php
// Send email with professional template
function sendEmail($to, $toName, $subject, $body, $context = '')
// Returns: bool (true on success)

// Generate email template
function generateEmailTemplate($name, $content, $footer = true)
// Returns: string (HTML email)

// Admin 1 specific
function sendCreditStatusEmail($conn, $applicationId, $newStatus, $finalLoanAmount = null)
function sendRemarkEmail($conn, $applicationId, $remarks)

// Superadmin specific
function sendLoanStatusEmail($conn, $applicationId, $newStatus)
```

### 6. Error Handling

```php
try {
    $result = sendEmail($to, $name, $subject, $emailBody, "Context");

    if ($result) {
        error_log("Email sent successfully: $context");
    } else {
        error_log("Email sending failed: $context");
    }

    return $result;
} catch (Exception $e) {
    error_log("Email error: " . $e->getMessage());
    return false;
}
```

### 7. Amount Formatting

```php
// Format currency
$formattedAmount = number_format($amount, 2);

// Display in email
<p style='font-size: 20px; color: #1976d2; font-weight: bold;'>
    PHP $formattedAmount
</p>
```

### 8. Custom Highlight Box Colors

```php
// Green (default)
<div class='highlight-box'>Content</div>

// Blue
<div class='highlight-box' style='background-color: #e3f2fd; border-left-color: #1976d2;'>
    Content
</div>

// Yellow
<div class='highlight-box' style='background-color: #fffbeb; border-left-color: #fbc02d;'>
    Content
</div>

// Red
<div class='highlight-box' style='background-color: #ffebee; border-left-color: #d32f2f;'>
    Content
</div>
```

### 9. Adding Icons

```php
// Common icons
📝 - Remarks/Notes
💰 - Money/Amount
✅ - Success/Approved
❌ - Failed/Rejected
⏳ - Pending/Waiting
🔵 - Active/In Progress
⚫ - Closed/Completed
📧 - Email
📱 - Contact
🏦 - Bank/Finance
📄 - Document
⚠️ - Warning/Alert

// Usage
<p>💰 <strong>Loan Amount:</strong> PHP 50,000.00</p>
```

### 10. Complete Example

```php
function sendCustomEmail($conn, $applicationId, $message)
{
    // Get user info
    $stmt = $conn->prepare("
        SELECT u.email, u.first_name, u.last_name
        FROM loan_applications la
        JOIN users1 u ON la.user_id = u.id
        WHERE la.application_id = ?
    ");
    $stmt->bind_param("i", $applicationId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        return false;
    }

    $to = $user['email'];
    $name = trim($user['first_name'] . ' ' . $user['last_name']);
    $safeMessage = htmlspecialchars($message);

    // Create content
    $content = "
        <p>You have received a new message regarding your loan application.</p>
        <div class='highlight-box'>
            <p><strong>Application ID:</strong> $applicationId</p>
            <p><strong>Message:</strong></p>
            <p style='background-color: #fff; padding: 10px; border-radius: 4px; border: 1px solid #e0e0e0;'>
                $safeMessage
            </p>
        </div>
        <a href='http://cycloan-cldd.com/user_dashboard.php' class='button'>View Application</a>
        <p>Best regards,<br><strong>The CLDD Loan Support Team</strong></p>
    ";

    // Generate and send
    $emailBody = generateEmailTemplate($name, $content);
    $subject = "New Message - Application #$applicationId";

    return sendEmail($to, $name, $subject, $emailBody, "Custom message for app $applicationId");
}
```

## Best Practices

1. ✅ Always use `htmlspecialchars()` for user input
2. ✅ Include application ID in emails
3. ✅ Add call-to-action buttons
4. ✅ Use consistent colors and icons
5. ✅ Log email sending with context
6. ✅ Handle errors gracefully
7. ✅ Test on multiple email clients
8. ✅ Keep subject lines concise
9. ✅ Make button links absolute URLs
10. ✅ Provide context in error logs

## Don'ts

1. ❌ Don't send raw user input in emails
2. ❌ Don't use relative URLs in buttons
3. ❌ Don't skip error handling
4. ❌ Don't hardcode user names
5. ❌ Don't forget to log email sends
6. ❌ Don't use inline styles for layout (use classes)
7. ❌ Don't send emails without testing first

---

**Quick Copy-Paste Template:**

```php
$content = "
    <p>Your message here.</p>
    <div class='highlight-box'>
        <p><strong>Label:</strong> Value</p>
    </div>
    <a href='http://cycloan-cldd.com/page.php' class='button'>Action Button</a>
    <p>Best regards,<br><strong>The CLDD Loan Support Team</strong></p>
";

$emailBody = generateEmailTemplate($userName, $content);
$result = sendEmail($userEmail, $userName, "Subject", $emailBody, "Context");
```
