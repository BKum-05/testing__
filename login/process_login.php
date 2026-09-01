<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/_auth.php';

require_post();

try {
    $pdo = get_pdo();

    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
    if (!verify_csrf($csrfToken)) {
        json_response(['success' => false, 'message' => 'Invalid or expired session. Please refresh and try again.'], 403);
    }

    $rawEmail       = trim((string) ($_POST['email'] ?? ''));
    $email          = filter_var($rawEmail, FILTER_VALIDATE_EMAIL);
    $password       = (string) ($_POST['password'] ?? '');
    $remember       = !empty($_POST['remember']);
    $turnstileToken = trim((string) ($_POST['cf-turnstile-response'] ?? ''));
    $ipAddress      = $_SERVER['REMOTE_ADDR'] ?? null;

    if (!verify_turnstile($turnstileToken)) {
        json_response(['success' => false, 'message' => 'Security check failed. Please try again.'], 400);
    }
    if ($rawEmail === '') {
        json_response(['success' => false, 'message' => 'Email address is required.'], 400);
    }
    if ($email === false) {
        json_response(['success' => false, 'message' => 'Please enter a valid email address.'], 400);
    }
    if ($password === '') {
        json_response(['success' => false, 'message' => 'Password is required.'], 400);
    }

    $user = fetch_user_by_email($pdo, (string) $email);
    if ($user === null) {
        password_verify($password, '$2y$10$abcdefghijklmnopqrstuvABCDEFGHIJKLMNOPQRSTUV0123456789');
        json_response(['success' => false, 'message' => 'Invalid email or password.'], 401);
    }

    if (empty($user['password_hash'])) {
        json_response(['success' => false, 'message' => 'This account uses Google sign-in.'], 403);
    }
    if (($user['status'] ?? 'pending') === 'suspended') {
        json_response(['success' => false, 'message' => 'This account is suspended.'], 403);
    }

    $isLockoutActive = !empty($user['lockout_until']) && strtotime((string) $user['lockout_until']) > time();
    if (!empty($user['lockout_until']) && !$isLockoutActive) {
        $pdo->prepare('UPDATE users SET failed_attempts = 0, lockout_until = NULL WHERE user_id = :id')
            ->execute(['id' => (int) $user['id']]);
        $user['failed_attempts'] = 0;
        $user['lockout_until'] = null;
    }

    if ($isLockoutActive) {
        $secondsRemaining = max(1, strtotime((string) $user['lockout_until']) - time());
        json_response([
            'success' => false,
            'message' => 'This account is temporarily locked due to failed attempts.',
            'locked' => true,
            'seconds_remaining' => $secondsRemaining,
        ], 429);
    }

    if (!password_verify($password, (string) $user['password_hash'])) {
        $newFailedAttempts = (int) $user['failed_attempts'] + 1;
        $justLocked = $newFailedAttempts >= LOGIN_MAX_ATTEMPTS;

        $failureStatement = $pdo->prepare(
            'UPDATE users SET
                failed_attempts = failed_attempts + 1,
                lockout_until = CASE
                    WHEN failed_attempts + 1 >= :max_attempts THEN DATE_ADD(NOW(), INTERVAL :lockout_minutes MINUTE)
                    ELSE lockout_until
                END,
                updated_at = NOW()
            WHERE user_id = :id'
        );
        $failureStatement->execute([
            'id' => (int) $user['id'],
            'max_attempts' => LOGIN_MAX_ATTEMPTS,
            'lockout_minutes' => LOGIN_LOCKOUT_MINUTES,
        ]);

        if ($justLocked) {
            json_response([
                'success' => false,
                'message' => 'Too many failed attempts. This account is now locked.',
                'locked' => true,
                'seconds_remaining' => LOGIN_LOCKOUT_MINUTES * 60,
            ], 429);
        }
        json_response(['success' => false, 'message' => 'Invalid email or password.'], 401);
    }

    $successStatement = $pdo->prepare(
        'UPDATE users SET
            status = CASE WHEN status = "pending" THEN "active" ELSE status END,
            failed_attempts = 0,
            lockout_until = NULL,
            last_login_at = NOW(),
            last_login_ip = :last_login_ip,
            updated_at = NOW()
        WHERE user_id = :id'
    );
    $successStatement->execute(['id' => (int) $user['id'], 'last_login_ip' => $ipAddress]);

    $authData = $user;

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    set_auth_session($authData);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['role'] = strtolower((string) ($user['role'] ?? 'member'));

    if ($remember) {
        $cookieLifetime = time() + (30 * 24 * 60 * 60);
        setcookie(session_name(), session_id(), [
            'expires' => $cookieLifetime,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    if (user_requires_password_reset($user)) {
        json_response([
            'success' => true,
            'password_reset_required' => true,
            'redirect_url' => 'set_password.php',
            'message' => 'Password reset required before continuing.',
        ]);
    }

    json_response(['success' => true, 'redirect_url' => get_dashboard_url(), 'message' => 'Login successful.']);
} catch (Throwable $exception) {
    error_log('Login Error: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
    json_response(['success' => false, 'message' => 'An internal server error occurred during login. Please try again later.'], 500);
}
