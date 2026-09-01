<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/_auth.php';
require_once __DIR__ . '/../app/_validation_lib.php';

require_login();

try {
    $pdo = get_pdo();
    $userId = (int) $_SESSION['user_id'];
    $user = fetch_user_by_id($pdo, $userId);

    if (!$user) {
        json_response(['success' => false, 'message' => 'User account not found.'], 404);
    }

    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
    if (!verify_csrf($csrfToken)) {
        json_response(['success' => false, 'message' => 'Invalid or expired session. Please refresh and try again.'], 403);
    }

    $turnstileToken = trim((string) ($_POST['cf-turnstile-response'] ?? ''));
    if (!verify_turnstile($turnstileToken)) {
        json_response(['success' => false, 'message' => 'Security check failed. Please try again.'], 400);
    }

    $firstName  = trim((string) ($_POST['first_name'] ?? ''));
    $lastName   = trim((string) ($_POST['last_name'] ?? ''));
    $gender     = strtolower(trim((string) ($_POST['gender'] ?? 'prefer_not_to_say')));
    $dob        = trim((string) ($_POST['date_of_birth'] ?? ''));
    $phone      = trim((string) ($_POST['phone_number'] ?? ''));
    $address1   = trim((string) ($_POST['address_line1'] ?? ''));
    $state      = trim((string) ($_POST['state'] ?? ''));
    $city       = trim((string) ($_POST['city'] ?? ''));
    $postalCode = trim((string) ($_POST['postcode'] ?? $_POST['postal_code'] ?? ''));
    $country    = trim((string) ($_POST['country'] ?? 'Malaysia'));

    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword     = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    $_err = [];

    if ($firstName === '') {
        $_err['first_name'] = 'First name is required.';
    } elseif (mb_strlen($firstName) > MAX_NAME_LENGTH || !preg_match("/^[\p{L}\s'-]+$/u", $firstName)) {
        $_err['first_name'] = 'First name contains invalid characters or is too long.';
    }

    if ($lastName === '') {
        $_err['last_name'] = 'Last name is required.';
    } elseif (mb_strlen($lastName) > MAX_NAME_LENGTH || !preg_match("/^[\p{L}\s'-]+$/u", $lastName)) {
        $_err['last_name'] = 'Last name contains invalid characters or is too long.';
    }

    if (!in_array($gender, ['male', 'female', 'other', 'prefer_not_to_say'], true)) {
        $_err['gender'] = 'Invalid gender selection.';
    }

    if ($dob !== '') {
        $dobError = validate_dob($dob);
        if ($dobError !== null) {
            $_err['date_of_birth'] = $dobError;
        }
    }

    if ($phone !== '' && !preg_match('/^\+?\d{7,15}$/', $phone)) {
        $_err['phone_number'] = 'Enter a valid phone number.';
    }

    if (mb_strlen($address1) > MAX_ADDRESS_LENGTH) {
        $_err['address_line1'] = 'Address line 1 cannot exceed ' . MAX_ADDRESS_LENGTH . ' characters.';
    }

    if ($state !== '' || $city !== '' || $postalCode !== '') {
        validate_location($state, $city, $postalCode, $_err);
    }

    if ($country === '' || mb_strlen($country) > 100) {
        $_err['country'] = 'Please enter a valid country.';
    }

    // A Google-only account (no password_hash yet) has nothing to verify a
    // "current password" against — allow it to set its first local password
    // directly. An account that already has a password must still prove
    // knowledge of it before changing it.
    $hasExistingPassword = !empty($user['password_hash']);

    if ($currentPassword !== '' || $newPassword !== '' || $confirmPassword !== '') {
        if ($hasExistingPassword && !password_verify($currentPassword, (string) $user['password_hash'])) {
            $_err['current_password'] = 'Incorrect current password.';
        }

        if ($newPassword === '') {
            $_err['new_password'] = 'Please enter a new password.';
        } else {
            $issues = get_password_policy_issues($newPassword);
            if (!empty($issues)) {
                $_err['new_password'] = implode(' ', $issues);
            }
        }

        if ($confirmPassword === '' || $newPassword !== $confirmPassword) {
            $_err['confirm_password'] = 'New passwords do not match.';
        }
    }

    $avatarPath = null;
    $f = get_file('avatar');

    if ($f !== null) {
        if (!is_uploaded_file($f->tmp_name)) {
            $_err['avatar'] = 'Invalid upload.';
        } elseif ($f->size <= 0 || $f->size > 2 * 1024 * 1024) {
            $_err['avatar'] = 'Profile image must be between 1 byte and 2MB.';
        } elseif (!in_array($f->type, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $_err['avatar'] = 'Invalid image format. Allowed: JPG, PNG, WEBP.';
        } elseif (@getimagesize($f->tmp_name) === false) {
            $_err['avatar'] = 'The uploaded file is not a valid image.';
        } else {
            $uploadDir = BASE_PATH . '/app/uploads/avatars/';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                $_err['avatar'] = 'Unable to prepare the avatar upload directory.';
            } else {
                $newFileName = 'avatar_' . $userId . '_' . bin2hex(random_bytes(12)) . '.jpg';
                $targetPath = $uploadDir . $newFileName;

                require_once __DIR__ . '/../app/lib/SimpleImage.php';
                try {
                    $img = new SimpleImage();
                    $img->fromFile($f->tmp_name)
                        ->thumbnail(300, 300)
                        ->toFile($targetPath, 'image/jpeg');

                    $avatarPath = 'uploads/avatars/' . $newFileName;
                } catch (Throwable $e) {
                    error_log('Avatar processing error: ' . $e->getMessage());
                    $_err['avatar'] = 'Could not process the uploaded image. Please try a different file.';
                }
            }
        }
    } elseif (isset($_FILES['avatar']) && ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $_err['avatar'] = 'An error occurred while uploading the file. Please try again.';
    }

    if (!empty($_err)) {
        json_response(['success' => false, 'errors' => $_err, 'message' => reset($_err)], 422);
    }

    $pdo->beginTransaction();

    if ($newPassword !== '') {
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $pwStmt = $pdo->prepare('UPDATE users SET password_hash = :hash, password_changed_at = NOW(), updated_at = NOW() WHERE user_id = :id');
        $pwStmt->execute(['hash' => $newHash, 'id' => $userId]);
    }

    $profileData = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'gender' => $gender,
        'date_of_birth' => $dob !== '' ? dob_to_iso($dob) : null,
        'phone_number' => $phone,
        'address_line1' => $address1,
        'city' => $city,
        'state' => $state,
        'postal_code' => $postalCode,
        'country' => $country,
    ];

    if ($avatarPath !== null) {
        $profileData['avatar_url'] = $avatarPath;
    }

    update_user_profile($pdo, $userId, $profileData);
    $pdo->commit();

    // Clean up the previous avatar file now that the new one is saved and
    // committed — otherwise every re-upload leaves the old file behind
    // permanently, since each upload gets a fresh random filename.
    if ($avatarPath !== null) {
        $oldAvatarUrl = $user['avatar_url'] ?? null;
        if (!empty($oldAvatarUrl) && !str_ends_with($oldAvatarUrl, 'default.svg')) {
            $oldAvatarFile = BASE_PATH . '/' . ltrim($oldAvatarUrl, '/');
            if (is_file($oldAvatarFile)) {
                @unlink($oldAvatarFile);
            }
        }
    }

    $freshUser = fetch_user_by_id($pdo, $userId);
    set_auth_session(array_merge($freshUser ?: $user, $profileData));

    json_response([
        'success' => true,
        'message' => 'Profile updated successfully.',
        'avatar_url' => $avatarPath !== null ? url($avatarPath) : get_avatar_url($user['avatar_url'] ?? null),
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Profile Update Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    json_response(['success' => false, 'message' => 'An internal error occurred. Please try again later.'], 500);
}
