<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/_auth.php';

require_post();

try {
    $pdo = get_pdo();

    $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $turnstileToken = trim((string) ($_POST['cf-turnstile-response'] ?? ''));
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');

    if (!verify_csrf($csrfToken)) {
        json_response(['success' => false, 'message' => 'Invalid or expired session. Please refresh and try again.'], 403);
    }
    if (!verify_turnstile($turnstileToken)) {
        json_response(['success' => false, 'message' => 'Security check failed.'], 400);
    }
    if ($email === false) {
        json_response(['success' => false, 'message' => 'Please enter a valid email address.'], 400);
    }

    $user = fetch_user_by_email($pdo, (string) $email);
    $genericSuccessMsg = 'We have sent instructions to your email to reset your password.';

    if ($user !== null && !empty($user['password_hash'])) {
        $stmt = $pdo->prepare('SELECT created_at FROM password_resets WHERE user_id = :id ORDER BY id DESC LIMIT 1');
        $stmt->execute(['id' => (int) $user['id']]);
        $lastRequest = $stmt->fetchColumn();

        if ($lastRequest !== false && (time() - strtotime((string) $lastRequest)) < 60) {
            json_response(['success' => true, 'message' => $genericSuccessMsg]);
        }

        $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL')
            ->execute(['user_id' => (int) $user['id']]);

        $token = issue_password_reset_token($pdo, (int) $user['id']);
        $resetLink = build_absolute_url('set_password.php?token=' . rawurlencode($token));
        $fullName = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? '')) ?: 'Valued Customer';

        $emailSent = send_email(
            toEmail: (string) $email,
            subject: 'Reset Your Password - Online Shopping System',
            templateName: '_reset_password',
            templateData: [
                'title' => 'Password Reset Request',
                'resetLink' => $resetLink,
                'fullName' => $fullName,
            ],
            recipientName: $fullName,
            altBody: "Hello {$fullName},\n\nWe received a request to reset your password.\n\nClick or copy the link below to reset your password (valid for " . PASSWORD_RESET_EXPIRY_HOURS . " hour):\n{$resetLink}\n\nIf you did not request this, please ignore this email."
        );

        if (!$emailSent) {
            $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE token_hash = :hash')
                ->execute(['hash' => hash('sha256', $token)]);
        }
    }

    json_response(['success' => true, 'message' => $genericSuccessMsg]);
} catch (Throwable $exception) {
    error_log('Forgot Password Error: ' . $exception->getMessage());
    json_response(['success' => false, 'message' => 'An error occurred while processing your request.'], 500);
}
