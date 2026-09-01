<?php
require_once __DIR__ . '/../app/_auth.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: profile.php');
    exit;
}

include_head("Forgot Password - Online Shopping System");
?>

<div class="container">
    <h2>Reset Password</h2>
    <p class="subtitle">Enter your registered email address. <br>We'll send you instructions to reset your password.</p>

    <div id="alertBox" class="alert-box" style="display:none;" role="status" aria-live="polite"></div>

    <form id="forgotPasswordForm">
        <?php echo csrf_field(); ?>
        <div class="form-group">
            <label for="email">Email Address*</label>
            <?php html_email('email', 'placeholder="Enter your registered email" required autofocus'); ?>
        </div>

        
        <?php 
        // Cloudflare Turnstile
        html_cloudflare($cloudflareToken);        
        html_button('submit', 'Send Reset Link', 'class="btn-primary"');
        ?>
    </form>

    <p class="subtitle" style="margin-top: 20px;">
        Remembered your password? <a href="login.php">Back to Login</a>
    </p>
</div>

<?php include_foot(); ?> 