<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/_auth.php';

require_post();

if (empty($_SESSION['pending_user'])) {
    json_response(['success' => false, 'message' => 'Session expired. Please start registration again.'], 401);
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (!verify_csrf($csrfToken)) {
    json_response(['success' => false, 'message' => 'Invalid or expired session. Please refresh and try again.'], 403);
}

$pendingUser = $_SESSION['pending_user'];
$email       = strtolower(trim((string) ($pendingUser['email'] ?? '')));
$fullName    = trim(($pendingUser['first_name'] ?? '') . ' ' . ($pendingUser['last_name'] ?? '')) ?: 'Valued Customer';

if ($email === '') {
    json_response(['success' => false, 'message' => 'Session expired. Please start registration again.'], 401);
}

$pdo = get_pdo();

try {
    $stmt = $pdo->prepare('SELECT expires_at FROM email_otps WHERE email = :email ORDER BY id DESC LIMIT 1');
    $stmt->execute(['email' => $email]);
    $lastOtp = $stmt->fetch();

    if ($lastOtp) {
        $totalWindowSeconds = OTP_EXPIRY_MINUTES * 60;
        $secondsRemaining = strtotime((string) $lastOtp['expires_at']) - time();
        $secondsPassed    = $totalWindowSeconds - $secondsRemaining;

        if ($secondsPassed < OTP_RESEND_COOLDOWN_SECONDS && $secondsRemaining > 0) {
            $waitTime = OTP_RESEND_COOLDOWN_SECONDS - $secondsPassed;
            json_response([
                'success'          => false,
                'message'          => "Please wait {$waitTime} seconds before requesting a new code.",
                'seconds_remaining' => $waitTime,
            ], 429);
        }
    }

    $rawOtp    = sprintf('%06d', random_int(0, 999999));
    $otpHash   = password_hash($rawOtp, PASSWORD_DEFAULT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));

    $pdo->prepare('DELETE FROM email_otps WHERE email = :email')->execute(['email' => $email]);
    $pdo->prepare('INSERT INTO email_otps (email, otp_hash, expires_at) VALUES (:email, :otp_hash, :expires_at)')
        ->execute(['email' => $email, 'otp_hash' => $otpHash, 'expires_at' => $expiresAt]);

    $emailSent = send_email(
        toEmail: $email,
        subject: 'Your Email Verification Code - Online Shopping System',
        templateName: '_otp',
        templateData: ['otp' => $rawOtp],
        recipientName: $fullName
    );

    if (!$emailSent) {
        throw new RuntimeException('Email delivery failed.');
    }

    json_response([
        'success'           => true,
        'message'           => 'A new verification code has been sent to your email.',
        'seconds_remaining' => OTP_RESEND_COOLDOWN_SECONDS,
    ]);
} catch (\Throwable $exception) {
    error_log('Resend OTP Error: ' . $exception->getMessage());
    json_response(['success' => false, 'message' => 'Failed to resend verification code. Please try again later.'], 500);
}