<?php
require "CYCLOAN_db.php";

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PhpMailer.php';
require 'phpmailer/src/SMTP.php';

// Get form data
$firstName = $_POST['firstName'];
$middleName = $_POST['middleName'];
$lastName = $_POST['lastName'];
$email = $_POST['email'];
$phone = $_POST['phone'];
$address = $_POST['address'];
$password = $_POST['password'];
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Prepare and bind
$stmt = $conn->prepare("INSERT INTO users (first_name, middle_name, last_name, email, phone, address, password) VALUES (?, ?, ?, ?, ?, ?, ? )");
$stmt->bind_param("ssssiss", $firstName, $middleName, $lastName, $email, $phone, $address, $hashedPassword);

// Execute the statement
if ($stmt->execute()) {
    // Send confirmation email
    sendEmail($email);
    // Redirect with success parameter
    header("Location: registration.php?success=true");
    exit();
} else {
    // Redirect with error parameter (optional)
    header("Location: registration.php?error=" . urlencode($stmt->error));
}

// Close connections
$stmt->close();
$conn->close();

function sendEmail($to)
{
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'cycloancldd@gmail.com';
        $mail->Password = 'hbfh ukgh tmzw nqbq'; // Use an app password or environment variable for security
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('cycloancldd@gmail.com', 'CYCLOAN Support');
        $mail->addAddress($to);

        // Content
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Thank You for Registering!';
        $mail->Body = "Dear <strong>$to</strong>,<br><br>"
            . "Thank you for registering on our platform! We are excited to have you on board.<br>"
            . "You can now log in using your email address. If you have any questions, feel free to reach out to our support team.<br><br>"
            . "Best regards,<br>"
            . "The CLDD Loan Team";

        $mail->send();
    } catch (Exception $e) {
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}
?>