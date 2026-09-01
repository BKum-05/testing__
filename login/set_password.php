<?php
require_once __DIR__ . '/../app/_auth.php';

$resetToken = trim((string) ($_GET['token'] ?? ''));
$isRegistrationFlow = !empty($_SESSION['pending_user']) && !empty($_SESSION['otp_verified']);
$isResetFlow = false;

if ($resetToken !== '') {
    $pdo = get_pdo();
    $hashedToken = hash('sha256', $resetToken);

    $stmt = $pdo->prepare(
        'SELECT id, user_id FROM password_resets
         WHERE token_hash = :hash AND expires_at > NOW() AND used_at IS NULL
         LIMIT 1'
    );
    $stmt->execute(['hash' => $hashedToken]);
    $matchedReset = $stmt->fetch();

    if ($matchedReset) {
        $isResetFlow = true;
        $_SESSION['reset_user_id']    = (int) $matchedReset['user_id'];
        $_SESSION['reset_request_id'] = (int) $matchedReset['id'];
    }
}

if (!$isRegistrationFlow && !$isResetFlow) {
    temp('danger', 'This link is invalid or has expired. Please request a new one.');
    redirect('forgot_password.php');
}

include_head("Create Password - Online Shopping System");
?>

<div class="container">
    <h2>Create Your Password</h2>
    <p class="subtitle">Set a secure password for your new account</p>

    <div id="alertBox" class="alert-box" style="display:none;" role="status" aria-live="polite"></div>
    <form method="POST" action="process_set_password.php" id="set-password-form">
        <?php echo csrf_field(); ?>
        <?php if ($resetToken !== ''): ?>
            <input type="hidden" name="token" value="<?php echo encode($resetToken); ?>">
        <?php endif; ?>

        <div class="form-group">
            <label for="new_password">New Password *</label>
            <div class="password-wrapper">
                <?php html_password('new_password', 'placeholder="Enter new password" required autofocus'); ?>
                <span class="toggle-password" role="button" tabindex="0" title="Toggle password visibility">
                    <i class="fas fa-eye"></i>
                </span>
            </div>
            <?php err('new_password'); ?>
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm New Password *</label>
            <div class="password-wrapper">
                <?php html_password('confirm_password', 'placeholder="Confirm new password" required'); ?>
                <span class="toggle-password" role="button" tabindex="0" title="Toggle password visibility">
                    <i class="fas fa-eye"></i>
                </span>
            </div>
            <?php err('confirm_password'); ?>
        </div>

        <ul class="password-requirements" id="password-requirements">
            <li id="req-length">At least 8 characters long</li>
            <li id="req-casing">Includes uppercase and lowercase letters</li>
            <li id="req-number">Includes at least one number</li>
            <li id="req-symbol">Includes at least one symbol</li>
            <li id="req-match">Passwords match</li>
        </ul>

        <?php html_button('submit', 'Complete Setup & Log In', 'class="btn btn-primary"') ?>
    </form>
</div>


<?php include_foot(); ?>