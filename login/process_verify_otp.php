<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/_auth.php';

if (!is_post()) {
    redirect('verify_otp.php');
}

if (empty($_SESSION['pending_user'])) {
    if (req('ajax')) {
        json_response(['success' => false, 'message' => 'Session expired. Please start registration again.'], 401);
    }
    temp('danger', 'Please complete the registration form first.');
    redirect('register.php');
}

$pendingUser = $_SESSION['pending_user'];
$email       = strtolower(trim((string) ($pendingUser['email'] ?? '')));
$otp         = trim((string) post('otp', ''));
$csrfToken   = (string) post('csrf_token', '');

$GLOBALS['otp'] = $otp;
$_err = [];

if (!verify_csrf($csrfToken)) {
    $_err['csrf'] = 'Invalid or expired session. Please try again.';
}

if (empty($otp) || !preg_match('/^\d{6}$/', $otp)) {
    $_err['otp'] = 'Please enter a valid 6-digit verification code.';
}

$pdo = get_pdo();

if (empty($_err)) {
    try {
        $stmt = $pdo->prepare('SELECT id, otp_hash, expires_at, attempts FROM email_otps WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $record = $stmt->fetch();

        if (!$record) {
            $_err['otp'] = 'No OTP request found. Please click "Resend OTP".';
        } elseif (strtotime((string) $record['expires_at']) < time()) {
            $_err['otp'] = 'Verification code has expired. Please click "Resend OTP".';
        } elseif ((int) $record['attempts'] >= OTP_MAX_ATTEMPTS) {
            $_err['otp'] = 'Too many incorrect attempts. Please click "Resend OTP" to get a new code.';
        } elseif (!password_verify($otp, (string) $record['otp_hash'])) {
            $pdo->prepare('UPDATE email_otps SET attempts = attempts + 1 WHERE id = :id')
                ->execute(['id' => $record['id']]);
            $_err['otp'] = 'Incorrect verification code. Please check and try again.';
        }
    } catch (\PDOException $e) {
        error_log('OTP Verification Error: ' . $e->getMessage());
        $_err['general'] = 'A database error occurred. Please try again.';
    }
}

if (!empty($_err)) {
    if (req('ajax')) {
        json_response(['success' => false, 'errors' => $_err, 'message' => reset($_err)], 422);
    }
    temp('danger', reset($_err));
    include 'verify_otp.php';
    exit;
}

$_SESSION['otp_verified'] = true;

if (req('ajax')) {
    json_response([
        'success'  => true,
        'redirect' => 'set_password.php',
        'message'  => 'Email verified successfully!'
    ]);
}

redirect('set_password.php');
