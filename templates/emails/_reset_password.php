<?php
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * @var string $fullName
 * @var string $resetLink
 */

$title = 'Password Reset Request';
ob_start();


?>

<h2 style="margin-top:0;color:#111827;">
    Password Reset Request
</h2>


<p style="font-size:16px;color:#374151;">
    Hello <strong><?= htmlspecialchars($fullName) ?></strong>,
</p>


<p style="font-size:15px;color:#4b5563;line-height:1.7;">
    We received a request to reset your password.
</p>


<p style="font-size:15px;color:#4b5563;line-height:1.7;">
    Click the button below to create a new password.
    This link expires in <strong>1 hour</strong>.
</p>


<table cellpadding="0" cellspacing="0" style="margin:30px auto;">
    <tr>
        <td bgcolor="#2563eb" style="border-radius:6px;">

            <a href="<?= htmlspecialchars($resetLink) ?>"
                style="
                display:inline-block;
                padding:14px 28px;
                font-size:16px;
                font-weight:bold;
                color:#ffffff;
                text-decoration:none;
                ">
                Reset Password
            </a>

        </td>
    </tr>
</table>


<p style="font-size:14px;color:#6b7280;">
    If you did not request this password reset, you can safely ignore this email.
</p>


<?php

$content = ob_get_clean();
