<?php

require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
$mail = new PHPMailer(true);
try {
    // SMTP Server Configuration
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'nabilishane@gmail.com';       // Your Gmail address
    $mail->Password   = 'dbpd ugew hufw enrl';    // App password from Google
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    // Sender and Recipient
    $mail->setFrom('nabilishane@gmail.com', 'Ace the Coder');
    $mail->addAddress('almeromarvin482@gmail.com', 'Cool Friend');
    $mail->isHTML(true);
    $mail->Subject = 'Hello from PHPMailer!';
    $mail->Body    = '<h1>This is a test email sent using PHPMailer 🎉</h1>';
    $mail->AltBody = 'This is a test email sent using PHPMailer.';

    $mail->send();
    echo '✅ Email sent successfully, Ace!';
} catch (Exception $e) {
    echo "❌ Email could not be sent. Error: {$mail->ErrorInfo}";
}

?>