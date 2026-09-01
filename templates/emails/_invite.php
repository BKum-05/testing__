<?php
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

/** @var string $fullName @var string $inviteLink @var string $role */
$title = 'Account Created';
ob_start();
?>
<h2 style="margin-top:0;color:#111827;">Welcome to Online Shopping System</h2>
<p style="font-size:16px;color:#374151;">Hello <strong><?= htmlspecialchars($fullName) ?></strong>,</p>
<p style="font-size:15px;color:#4b5563;line-height:1.7;">
    An account has been created for you with <strong><?= htmlspecialchars($role) ?></strong> access.
    Click the button below to set your password for your account. This link expires in <strong>1 hour</strong>.
</p>
<table cellpadding="0" cellspacing="0" style="margin:30px auto;">
    <tr>
        <td bgcolor="#2563eb" style="border-radius:6px;">
            <a href="<?= htmlspecialchars($inviteLink) ?>" style="display:inline-block;padding:14px 28px;font-size:16px;font-weight:bold;color:#ffffff;text-decoration:none;">
                Set Your Password
            </a>
        </td>
    </tr>
</table>
<p style="font-size:14px;color:#6b7280;">If you were not expecting this email, please contact your administrator.</p>
<?php
$content = ob_get_clean();