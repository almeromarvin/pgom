<?php
require_once 'config/database.php';
require_once 'mailer.php';

// var_dump($mail);
// require_once 'config/encryption_config.php';
// require_once 'mailer.php';

header('Content-Type: application/json');


if (isset($_POST['send_code'])) {
    $email = $_POST['email'];

    if (empty($email) || is_null($email)) {
        echo json_encode(["status" => "error", "message" => "invalid Email"]);
        exit;
    }

    // validate if the user is existing
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        echo json_encode(["status" => "error", "message" => "Invalid Email"]);
        exit;
    }

    $randomString = bin2hex(random_bytes(16));

    // store the data first

    $stmt = $pdo->prepare("INSERT INTO password_reset(email,token) VALUES(:email,:token)");
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->bindParam(':token', $randomString, PDO::PARAM_STR);

    if ($stmt->execute()) {
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Reset Password';
        $mail->Body = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px; background-color: #f9f9f9;">
                <h2 style="color: #333;">Password Reset Request</h2>
                <p>Hello,</p>
                <p>We received a request to reset your password. If you made this request, you can reset your password using the button below.</p>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="http://localhost/pgom/resetPassword.php?token=' . $randomString . '" 
                    style="background-color: #007bff; color: #ffffff; padding: 12px 20px; text-decoration: none; border-radius: 5px; display: inline-block; font-size: 16px;">
                        🔐 Reset Password
                    </a>
                </div>

                <p>If you did not request a password reset, please ignore this email. Your account is still secure.</p>
                <p>Thanks,<br><strong>PGOM Administrator</strong></p>
            </div>';
        $mail->AltBody = 'This is a test email sent using PHPMailer.';
        if ($mail->send()) {
            echo json_encode(["status" => "success", "message" => "Email Sent"]);
            exit;
        }
    }

    echo json_encode(["status" => "errpr", "message" => "An Error Occured"]);
    exit;
}

if (isset($_POST['resetPassword'])) {
    $token = $_GET['token'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM password_reset WHERE token = :token");
    $stmt->bindParam(':token', $token, PDO::PARAM_STR);
    $stmt->execute();

    // ✅ Check if the token exists
    if ($stmt->rowCount() === 0) {
        echo json_encode(["status" => "error", "message" => "Invalid or expired token"]);
        exit;
    }

    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $email = $result['email'];
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $updatePassword = $pdo->prepare("UPDATE users SET password = :password WHERE email = :email");
    $updatePassword->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
    $updatePassword->bindParam(':email', $email, PDO::PARAM_STR);

    if ($updatePassword->execute()) {
        // ✅ Clean up token after success
        $stmt = $pdo->prepare("DELETE FROM password_reset WHERE email = :email");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        echo json_encode(["status" => "success", "message" => "Password reset successfully"]);
        exit;
    }

    echo json_encode(["status" => "error", "message" => "Failed to reset password"]);
    exit;
}



// echo $randomString = bin2hex(random_bytes(16));
