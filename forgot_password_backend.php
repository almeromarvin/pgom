<?php
require_once 'config/database.php';
require_once 'config/encryption_config.php';
require_once 'mailer.php';

header('Content-Type: application/json');

function encryptToken($token) {
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(ENCRYPTION_METHOD));
    $encrypted = openssl_encrypt($token, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
    return base64_encode($iv . $encrypted);
}

function decryptToken($encryptedToken) {
    $data = base64_decode($encryptedToken);
    $ivLength = openssl_cipher_iv_length(ENCRYPTION_METHOD);
    $iv = substr($data, 0, $ivLength);
    $encrypted = substr($data, $ivLength);
    return openssl_decrypt($encrypted, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
}

function randomCode($length = 6) {
    return str_pad(random_int(0, 999999), $length, '0', STR_PAD_LEFT);
}

$action = $_POST['action'] ?? '';
$email = $_POST['email'] ?? '';

if ($action === 'send_code' || $action === 'resend_code') {
    // Check if email exists
    $stmt = $pdo->prepare('SELECT id, username FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Email not found.']);
        exit();
    }
    // Mark all previous tokens for this email as used
    $pdo->prepare('UPDATE password_reset_tokens SET used = 1 WHERE email = ? AND used = 0')->execute([$email]);
    // Generate code and encrypt it before storing
    $code = randomCode();
    $encryptedCode = encryptToken($code);
    $expires_at = date('Y-m-d H:i:s', time() + 600); // 10 minutes from now
    $pdo->prepare('INSERT INTO password_reset_tokens (user_id, email, token, expires_at, used) VALUES (?, ?, ?, ?, 0)')
        ->execute([$user['id'], $email, $encryptedCode, $expires_at]);
    // Send email with the original (unencrypted) code
    $mail = getMailer();
    $mail->clearAllRecipients();
    $mail->addAddress($email, $user['username']);
    $mail->Subject = $action === 'send_code' ? 'Your Password Reset Code' : 'Your Password Reset Code (Resent)';
    $mail->Body = '<h2>Password Reset Request</h2><p>Your verification code is: <b>' . $code . '</b></p><p>This code will expire in 10 minutes.</p>';
    $mail->AltBody = 'Your verification code is: ' . $code;
    if ($mail->send()) {
        echo json_encode(['success' => true, 'message' => 'Verification code sent to your email.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send email. Try again.']);
    }
    exit();
}

if ($action === 'verify_code') {
    $code = $_POST['code'] ?? '';
    // Get all unused, unexpired tokens for this email
    $stmt = $pdo->prepare('SELECT * FROM password_reset_tokens WHERE email = ? AND used = 0 AND expires_at > NOW() ORDER BY id DESC');
    $stmt->execute([$email]);
    $tokens = $stmt->fetchAll();
    
    $validToken = null;
    foreach ($tokens as $token) {
        $decryptedToken = decryptToken($token['token']);
        if ($decryptedToken === $code) {
            $validToken = $token;
            break;
        }
    }
    
    if (!$validToken) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code.']);
        exit();
    }
    
    // Mark this token as used
    $pdo->prepare('UPDATE password_reset_tokens SET used = 1 WHERE id = ?')->execute([$validToken['id']]);
    // Store token id in session for password reset
    session_start();
    $_SESSION['reset_token_id'] = $validToken['id'];
    $_SESSION['reset_email'] = $email;
    echo json_encode(['success' => true, 'message' => 'Code verified.']);
    exit();
}

if ($action === 'reset_password') {
    session_start();
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $token_id = $_SESSION['reset_token_id'] ?? null;
    $reset_email = $_SESSION['reset_email'] ?? null;
    if (!$token_id || !$reset_email || $reset_email !== $email) {
        echo json_encode(['success' => false, 'message' => 'No verified reset request found. Please verify your code first.']);
        exit();
    }
    // Check that the token is still valid and used
    $stmt = $pdo->prepare('SELECT * FROM password_reset_tokens WHERE id = ? AND email = ? AND used = 1 AND expires_at > NOW()');
    $stmt->execute([$token_id, $email]);
    $token = $stmt->fetch();
    if (!$token) {
        echo json_encode(['success' => false, 'message' => 'Verification code expired or invalid.']);
        exit();
    }
    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
        exit();
    }
    if ($password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit();
    }
    // Update password
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE email = ?');
    $stmt->execute([$hashed, $email]);
    // Clean up session and tokens
    unset($_SESSION['reset_token_id'], $_SESSION['reset_email']);
    $pdo->prepare('UPDATE password_reset_tokens SET used = 1 WHERE id = ?')->execute([$token_id]);
    echo json_encode(['success' => true, 'message' => 'Password reset successful. You can now log in.']);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid request.']); 