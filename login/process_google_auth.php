<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/_auth.php';

require_post();

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (!verify_csrf($csrfToken)) {
    json_response(['success' => false, 'message' => 'Invalid or expired session. Please refresh and try again.'], 403);
}

try {
    $pdo = get_pdo();
    $credential = trim((string) ($_POST['token'] ?? ''));

    if ($credential === '') {
        json_response(['success' => false, 'message' => 'Missing Google credential.'], 400);
    }

    $googleData = verify_google_id_token($credential);
    if ($googleData === false) {
        json_response(['success' => false, 'message' => 'Google token verification failed.'], 401);
    }

    global $googleClientId;
    $issuer = (string) ($googleData['iss'] ?? '');
    $audience = (string) ($googleData['aud'] ?? '');
    $emailVerified = filter_var($googleData['email_verified'] ?? false, FILTER_VALIDATE_BOOL);

    if ($googleClientId === '' || $audience !== $googleClientId || !in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true) || !$emailVerified) {
        json_response(['success' => false, 'message' => 'Google account verification failed.'], 401);
    }

    $email = filter_var((string) ($googleData['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $googleSub = trim((string) ($googleData['sub'] ?? ''));
    $firstName = trim((string) ($googleData['given_name'] ?? ''));
    $lastName = trim((string) ($googleData['family_name'] ?? ''));
    $fullName = trim((string) ($googleData['name'] ?? ($firstName . ' ' . $lastName)));

    if ($email === false || $googleSub === '') {
        json_response(['success' => false, 'message' => 'Google account data is incomplete.'], 400);
    }

    $user = fetch_user_by_google_sub($pdo, $googleSub);
    if ($user === null) {
        $user = fetch_user_by_email($pdo, $email);
    }

    if ($user === null) {
        $nameParts = $fullName !== '' ? preg_split('/\s+/', $fullName, 2) : [];
        if (!is_array($nameParts) || $nameParts === []) {
            $nameParts = [$firstName !== '' ? $firstName : 'Google', $lastName];
        }

        $firstName = $firstName !== '' ? $firstName : (string) ($nameParts[0] ?? 'Google');
        $lastName = $lastName !== '' ? $lastName : (string) ($nameParts[1] ?? 'User');

        $userId = create_user_with_profile($pdo, [
            'email'         => $email,
            'auth_provider' => 'google',
            'google_id'     => $googleSub,
            'role'          => 'member',
            'status'        => 'active',
            'first_name'    => $firstName !== '' ? $firstName : 'Google',
            'last_name'     => $lastName !== '' ? $lastName : 'User',
            'gender'        => 'prefer_not_to_say',
            'country'       => 'Malaysia',
            'avatar_url'    => 'uploads/avatars/default.svg',
            'touch_login'   => true,
            'last_login_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
        $user = fetch_user_by_id($pdo, $userId);
    } else {
        if (($user['status'] ?? '') === 'suspended') {
            json_response(['success' => false, 'message' => 'This account is suspended.'], 403);
        }

        if (!empty($user['google_id']) && !hash_equals((string) $user['google_id'], $googleSub)) {
            json_response(['success' => false, 'message' => 'This email is already linked to a different Google account.'], 409);
        }

        $statement = $pdo->prepare(
            'UPDATE users SET
                google_id = :google_id,
                auth_provider = "google",
                status = CASE WHEN status = "pending" THEN "active" ELSE status END,
                last_login_at = NOW(),
                last_login_ip = :last_login_ip,
                updated_at = NOW()
            WHERE user_id = :id'
        );
        $statement->execute([
            'google_id' => $googleSub,
            'last_login_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'id' => (int) $user['id'],
        ]);
        $user = fetch_user_by_id($pdo, (int) $user['id']);
    }

    if ($user === null) {
        json_response(['success' => false, 'message' => 'Unable to create the Google account session.'], 500);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    set_auth_session($user);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['role'] = strtolower((string) ($user['role'] ?? 'member'));

    json_response([
        'success' => true,
        'redirect_url' => get_dashboard_url(),
        'message' => 'Google sign-in successful.',
    ]);
} catch (PDOException $exception) {
    if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
        json_response(['success' => false, 'message' => 'This Google account is already linked to another account.'], 409);
    }
    error_log('Google Auth Database Error: ' . $exception->getMessage());
    json_response(['success' => false, 'message' => 'Google sign-in failed.'], 500);
} catch (Throwable $exception) {
    error_log('Google Auth Error: ' . $exception->getMessage());
    json_response(['success' => false, 'message' => 'Google sign-in failed.'], 500);
}
