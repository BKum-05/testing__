<?php
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * @var string $recipientName
 * @var string $otp
 */

$title = 'Your Verification Code';
ob_start();
?>

<h2 style="margin-top:0;color:#111827;">Verification Code</h2>

<p style="font-size:16px;color:#374151;">
    Hello <strong><?= htmlspecialchars($recipientName) ?></strong>,
</p>

<p style="font-size:15px;color:#4b5563;line-height:1.7;">
    Use the verification code below to complete your registration. This code expires in <strong>10 minutes</strong>:
</p>

<div style="margin:24px 0;padding:16px;background-color:#f3f4f6;border-radius:6px;text-align:center;border:1px solid #e5e7eb;">
    <span style="font-size:32px;font-weight:bold;color:#2563eb;letter-spacing:8px;font-family:monospace;">
        <?= htmlspecialchars($otp) ?>
    </span>
</div>

<p style="font-size:14px;color:#6b7280;">
    If you did not request this verification code, please ignore this email.
</p>

<?php
$content = ob_get_clean();