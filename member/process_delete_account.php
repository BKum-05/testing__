<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/_auth.php';
require_once __DIR__ . '/../app/_audit_lib.php';

require_login();
require_post();

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (!verify_csrf($csrfToken)) {
    json_response(['success' => false, 'message' => 'Invalid or expired session.'], 403);
}

$userId = (int) $_SESSION['user_id'];
$pdo = get_pdo();
$user = fetch_user_by_id($pdo, $userId);

if ($user === null) {
    json_response(['success' => false, 'message' => 'Account not found.'], 404);
}

if (!empty($user['password_hash'])) {
    $password = (string) ($_POST['delete_password'] ?? '');
    if (!password_verify($password, (string) $user['password_hash'])) {
        json_response(['success' => false, 'message' => 'Incorrect password.'], 401);
    }
} else {
    $confirmEmail = strtolower(trim((string) ($_POST['delete_password'] ?? '')));
    if ($confirmEmail !== strtolower($user['email'])) {
        json_response(['success' => false, 'message' => 'Please type your email address to confirm.'], 401);
    }
}

if ($user['role'] === 'admin') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $stmt->execute();
    if ((int) $stmt->fetchColumn() <= 1) {
        json_response(['success' => false, 'message' => 'You are the last admin account and cannot delete yourself. Assign another admin first.'], 422);
    }
}

try {
    log_activity($pdo, 'member_deleted', $userId, "self-deleted account: {$user['email']}");
    $pdo->prepare('DELETE FROM users WHERE user_id = :id')->execute(['id' => $userId]);

    $avatarUrl = $user['avatar_url'] ?? null;
    if (!empty($avatarUrl) && !str_ends_with($avatarUrl, 'default.svg')) {
        $avatarFile = BASE_PATH . '/' . ltrim($avatarUrl, '/');
        if (is_file($avatarFile)) {
            @unlink($avatarFile);
        }
    }

    perform_logout();

    json_response(['success' => true, 'message' => 'Your account has been deleted.', 'redirect' => url('login/login.php')]);
} catch (Throwable $e) {
    error_log('Self Delete Error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'An error occurred while deleting your account.'], 500);
}
