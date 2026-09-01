<?php
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

$title = $title ?? 'Untitled';
$content = $content ?? '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= $title ?></title>
</head>

<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;">

    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:40px 0;">
        <tr>
            <td align="center">

                <table width="600" cellpadding="0" cellspacing="0"
                    style="background:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #e5e7eb;">

                    <tr>
                        <td style="background:#2563eb;padding:30px;text-align:center;">
                            <h1 style="margin:0;color:#ffffff;font-size:26px;">
                                Online Shopping System
                            </h1>
                        </td>
                    </tr>


                    <tr>
                        <td style="padding:40px;">
                            <?= $content ?>
                        </td>
                    </tr>


                    <!-- Footer -->
                    <tr>
                        <td style="background:#f9fafb;padding:20px;text-align:center;">

                            <p style="margin:0;color:#6b7280;font-size:13px;">
                                © <?= date('Y') ?> Online Shopping System
                            </p>

                            <p style="margin-top:6px;color:#9ca3af;font-size:12px;">
                                This is an automated email. Please do not reply.
                            </p>

                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>

</html>