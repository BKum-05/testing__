<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/_auth.php';
require_once __DIR__ . '/../app/_audit_lib.php';

require_role('admin');
require_post();

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (!verify_csrf($csrfToken)) {
    json_response(['success' => false, 'message' => 'Invalid or expired session.'], 403);
}

$memberId = (int) ($_POST['member_id'] ?? 0);
if ($memberId <= 0) {
    json_response(['success' => false, 'message' => 'Invalid member.'], 422);
}

$pdo = get_pdo();
$target = fetch_user_by_id($pdo, $memberId);
if ($target === null) {
    json_response(['success' => false, 'message' => 'Member not found.'], 404);
}

if (empty($target['password_hash'])) {
    json_response(['success' => false, 'message' => 'This account uses Google sign-in and has no password to reset.'], 422);
}

$fullName = trim(($target['first_name'] ?? '') . ' ' . ($target['last_name'] ?? '')) ?: $target['email'];

try {
    // Only the newest reset request should remain usable.
    $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL')
        ->execute(['user_id' => $memberId]);

    $token = issue_password_reset_token($pdo, $memberId);
    $resetLink = build_absolute_url('set_password.php?token=' . rawurlencode($token));

    $emailSent = send_email(
        toEmail: $target['email'],
        subject: 'Password Reset Requested - Online Shopping System',
        templateName: '_reset_password',
        templateData: [
            'title' => 'Password Reset Request',
            'resetLink' => $resetLink,
            'fullName' => $fullName,
        ],
        recipientName: $fullName,
        altBody: "Hello {$fullName},\n\nAn administrator has requested a password reset for your account.\n\nClick or copy the link below (valid for " . PASSWORD_RESET_EXPIRY_HOURS . " hour):\n{$resetLink}"
    );

    if (!$emailSent) {
        json_response(['success' => false, 'message' => 'The reset email could not be sent. No successful reset notification was recorded.'], 502);
    }

    log_activity($pdo, 'password_reset_sent', $memberId, 'Reset link sent by administrator.');
    json_response(['success' => true, 'message' => 'Password reset email sent.']);
} catch (Throwable $e) {
    error_log('Admin Reset Password Error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Failed to send reset email.'], 500);
}
