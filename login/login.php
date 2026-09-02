<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/_auth.php';


if (!empty($_SESSION['user_id'])) {
    $pdo = get_pdo();
    $currentUser = fetch_user_by_id($pdo, (int) $_SESSION['user_id']);

    if ($currentUser !== null && user_requires_password_reset($currentUser)) {
        redirect('set_password.php');
    }

    redirect(get_dashboard_url());
}

if (is_post()) {
    require_once __DIR__ . '/process_login.php';
}

include_head("Login - Online Shopping System");
?>

<div class="container">
    <h2>Welcome Back</h2>
    <p class="subtitle">Use your credentials to sign in to your account.</p>

    <?php if ($msg = temp('flash_success')): ?>
        <div class="alert-box alert-success"><?php echo encode($msg); ?></div>
    <?php endif; ?>

    <?php if ($msg = temp('flash_notice')): ?>
        <div class="alert-box alert-notice"><?php echo encode($msg); ?></div>
    <?php endif; ?>

    <div id="alertBox" class="alert-box" style="display:none;" role="status" aria-live="polite"></div>

    <!-- Google OAuth Box -->
    <div class="oauth-box">
        <?php html_google($googleClientId, 'handleGoogleSignIn', false); ?>
    </div>

    <div class="divider"><span>OR</span></div>

    <form id="loginForm" method="POST" action="login.php">
        <?php echo csrf_field(); ?>

        <div class="form-group">
            <label for="email">Email Address *</label>
            <?php html_email('email', 'placeholder="Enter your email" autofocus required inputmode="email"'); ?>
        </div>

        <div class="form-group">
            <label for="password">Password *</label>
            <div class="password-wrapper">
                <?php html_password('password', 'placeholder="Enter your password" required'); ?>
                <span class="toggle-password" role="button" tabindex="0" title="Toggle password visibility">
                    <i class="fas fa-eye"></i>
                </span>
            </div>
        </div>

        <div class="form-options">
            <label class="checkbox-label">
                <input type="checkbox" name="remember" value="1">
                <span>Stay signed in</span>

                <button type="button" class="remember-info" aria-label="Remember me information">
                    ?
                    <span class="remember-tooltip">
                        If selected, you will stay logged into your account after closing the browser tab.
                        Not recommended for shared or public devices.
                    </span>
                </button>
            </label>

            <a href="forgot_password.php" class="forgot-password-link">
                Forgot password?
            </a>
        </div>

        <?php
        html_cloudflare($cloudflareToken);
        html_button('submit', 'Sign In', 'class="btn btn-primary"');
        ?>
    </form>

    <p class="subtitle" style="margin-top: 15px;">
        Don't have an account? <a href="register.php">Register here</a>
    </p>
</div>

<?php include_foot(); ?>
