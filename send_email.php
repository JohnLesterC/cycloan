<?php
session_start();
require "CYCLOAN_db.php";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PhpMailer.php';
require 'phpmailer/src/SMTP.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $loan_id = $_POST["loan_id"];
    $applicant_email = $_POST["applicant_email"];
    $email_subject = $_POST["email_subject"];
    $email_body = $_POST["email_body"];

    // Send the email
    if (sendLoanEmail($applicant_email, $email_subject, $email_body)) {
        echo "Email sent successfully.";
    } else {
        echo "Failed to send email.";
    }

    exit();
}

function sendLoanEmail($to, $subject, $body)
{
    $mail = new PHPMailer(true);

    try {
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

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false; // Return false if sending fails
    }
}
?>