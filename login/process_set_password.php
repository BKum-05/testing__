<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/_auth.php';

require_post();

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (!verify_csrf($csrfToken)) {
    json_response(['success' => false, 'message' => 'Invalid or expired session. Please refresh and try again.'], 403);
}

$pendingUser   = $_SESSION['pending_user'] ?? null;
$isOtpVerified = !empty($_SESSION['otp_verified']);
$userId        = (int) ($_SESSION['user_id'] ?? 0);
$resetToken    = trim((string) ($_POST['token'] ?? ''));
$resetUserId   = (int) ($_SESSION['reset_user_id'] ?? 0);

if (!$isOtpVerified && $userId === 0 && $resetUserId === 0) {
    json_response(['success' => false, 'message' => 'Unauthorized access. Please complete verification or log in.'], 401);
}

$newPassword     = (string) ($_POST['new_password'] ?? '');
$confirmPassword = (string) ($_POST['confirm_password'] ?? '');

$_err = [];

if ($newPassword === '') {
    $_err['new_password'] = 'Please enter a new password.';
}
if ($confirmPassword === '') {
    $_err['confirm_password'] = 'Please confirm your password.';
} elseif ($newPassword !== $confirmPassword) {
    $_err['confirm_password'] = 'Passwords do not match.';
}
if ($newPassword !== '') {
    $passwordIssues = get_password_policy_issues($newPassword);
    if ($passwordIssues !== []) {
        $_err['new_password'] = 'Password requirements not met: ' . implode(', ', $passwordIssues);
    }
}

if ($_err !== []) {
    json_response(['success' => false, 'errors' => $_err, 'message' => reset($_err)], 400);
}

$pdo = get_pdo();

try {
    $pdo->beginTransaction();
    $pwdHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $finalUserId = null;

    if ($pendingUser !== null) {
        if (!$isOtpVerified) {
            throw new RuntimeException('Email verification is required before creating the account.');
        }

        $email = strtolower(trim((string) ($pendingUser['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Registration session is invalid.');
        }

        $finalUserId = create_user_with_profile($pdo, [
            'email'         => $email,
            'password_hash' => $pwdHash,
            'role'          => 'member',
            'status'        => 'active',
            'first_name'    => $pendingUser['first_name'] ?? '',
            'last_name'     => $pendingUser['last_name'] ?? '',
            'gender'        => $pendingUser['gender'] ?? 'prefer_not_to_say',
            'date_of_birth' => $pendingUser['date_of_birth'] ?? null,
            'phone_number'  => $pendingUser['phone_number'] ?? null,
            'address_line1' => $pendingUser['address_line1'] ?? null,
            'city'          => $pendingUser['city'] ?? null,
            'state'         => $pendingUser['state'] ?? null,
            'postal_code'   => $pendingUser['postal_code'] ?? null,
            'country'       => $pendingUser['country'] ?? 'Malaysia',
        ]);
    } elseif ($resetUserId !== 0) {
        $resetRequestId = (int) ($_SESSION['reset_request_id'] ?? 0);
        if ($resetRequestId === 0 || $resetToken === '') {
            throw new RuntimeException('Reset token is no longer valid.');
        }

        $hashedToken = hash('sha256', $resetToken);
        $stmt = $pdo->prepare(
            'SELECT id FROM password_resets
             WHERE id = :reset_id AND user_id = :user_id AND token_hash = :hash
               AND expires_at > NOW() AND used_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'reset_id' => $resetRequestId,
            'user_id' => $resetUserId,
            'hash' => $hashedToken,
        ]);

        if (!$stmt->fetch()) {
            throw new RuntimeException('Reset token is no longer valid.');
        }

        $stmt = $pdo->prepare(
            'UPDATE users SET
                password_hash = :password_hash,
                password_changed_at = NOW(),
                failed_attempts = 0,
                lockout_until = NULL,
                status = CASE WHEN status = "pending" THEN "active" ELSE status END,
                updated_at = NOW()
            WHERE user_id = :id'
        );
        $stmt->execute(['password_hash' => $pwdHash, 'id' => $resetUserId]);

        $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id')
            ->execute(['id' => $resetRequestId]);

        unset($_SESSION['reset_user_id'], $_SESSION['reset_request_id']);
        $finalUserId = $resetUserId;
    } else {
        $stmt = $pdo->prepare(
            'UPDATE users SET
                password_hash = :password_hash,
                password_changed_at = NOW(),
                failed_attempts = 0,
                lockout_until = NULL,
                status = CASE WHEN status = "pending" THEN "active" ELSE status END,
                updated_at = NOW()
            WHERE user_id = :id'
        );
        $stmt->execute(['password_hash' => $pwdHash, 'id' => $userId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('User account could not be updated.');
        }
        $finalUserId = $userId;
    }

    $freshUser = fetch_user_by_id($pdo, $finalUserId);
    if ($freshUser === null) {
        throw new RuntimeException('User account could not be loaded after password update.');
    }

    $pdo->commit();

    $_SESSION['user_id'] = $finalUserId;
    $_SESSION['role'] = strtolower((string) ($freshUser['role'] ?? 'member'));
    $_SESSION['email'] = (string) $freshUser['email'];

    temp('success', 'Account created successfully!');

    json_response([
        'success' => true,
        'message' => 'Password updated successfully.',
        'redirect' => get_dashboard_url(),
    ]);
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
        json_response(['success' => false, 'message' => 'This email address is already registered. Please log in instead.'], 409);
    }

    error_log('Set Password Database Error: ' . $exception->getMessage());
    json_response(['success' => false, 'message' => 'An error occurred while setting your password. Please try again.'], 500);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Set Password Error: ' . $exception->getMessage());
    json_response(['success' => false, 'message' => 'An error occurred while setting your password. Please try again.'], 400);
}
