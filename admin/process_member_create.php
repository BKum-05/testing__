<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/_auth.php';
require_once __DIR__ . '/../app/_audit_lib.php';
require_once __DIR__ . '/../app/_validation_lib.php';

require_role('admin');
require_post();

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (!verify_csrf($csrfToken)) {
    json_response(['success' => false, 'message' => 'Invalid or expired session.'], 403);
}

$firstName   = trim((string) post('first_name', ''));
$lastName    = trim((string) post('last_name', ''));
$email       = strtolower(trim((string) post('email', '')));
$gender      = strtolower(trim((string) post('gender', 'prefer_not_to_say')));
$dobIso      = trim((string) post('date_of_birth', ''));
$phoneNumber = trim((string) post('phone_number', ''));
$address1    = trim((string) post('address_line1', ''));
$state       = trim((string) post('state', ''));
$city        = trim((string) post('city', ''));
$postalCode  = trim((string) post('postcode', ''));
$role        = trim((string) post('role', 'member'));
$status      = trim((string) post('status', 'pending'));

$_err = validate_person_fields($_POST);

if (!in_array($role, VALID_ROLES, true)) {
    $_err['role'] = 'Invalid role selected.';
}

if (!in_array($status, CREATABLE_STATUSES, true)) {
    $_err['status'] = 'Invalid status selected.';
}

if (!empty($_err)) {
    json_response(['success' => false, 'errors' => $_err, 'message' => reset($_err)], 422);
}

$pdo = get_pdo();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        json_response([
            'success' => false,
            'errors' => ['email' => 'Email already registered.'],
            'message' => 'Email address is already registered.'
        ], 409);
    }

    $newUserId = create_user_with_profile($pdo, [
        'email'         => $email,
        'role'          => $role,
        'status'        => $status,
        'first_name'    => $firstName,
        'last_name'     => $lastName,
        'gender'        => $gender,
        'date_of_birth' => $dobIso,
        'phone_number'  => $phoneNumber,
        'address_line1' => $address1,
        'city'          => $city,
        'state'         => $state,
        'postal_code'   => $postalCode,
        'country'       => 'Malaysia',
    ]);

    $token = issue_password_reset_token($pdo, $newUserId);

    log_activity($pdo, 'member_created', $newUserId, "role: {$role}, status: {$status}");

    $pdo->commit();

    $inviteLink = build_absolute_url("set_password.php?token=" . rawurlencode($token));
    $fullName = trim($firstName . ' ' . $lastName);

    $emailSent = send_email(
        toEmail: $email,
        subject: "You've Been Invited - Online Shopping System",
        templateName: '_invite',
        templateData: [
            'title'      => 'Account Created',
            'inviteLink' => $inviteLink,
            'fullName'   => $fullName,
            'role'       => ucfirst($role),
        ],
        recipientName: $fullName,
        altBody: "Hello {$fullName},\n\nAn account has been created for you as a {$role}.\n\nSet your password using this link (valid for " . PASSWORD_RESET_EXPIRY_HOURS . " hour):\n{$inviteLink}"
    );

    if (!$emailSent) {
        json_response([
            'success'  => true,
            'message'  => 'Account created, but the invite email could not be sent. Please use "Send Password Reset Email" on the member detail page to resend it.',
            'redirect' => 'member_detail.php?id=' . $newUserId,
        ]);
    }

    json_response([
        'success'  => true,
        'message'  => 'Account created and invite email sent.',
        'redirect' => 'member_detail.php?id=' . $newUserId,
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ((int) $e->errorInfo[1] === 1062) {
        json_response(['success' => false, 'message' => 'Email address is already registered.'], 409);
    }

    error_log('Member Create Database Error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'An error occurred while creating the account.'], 500);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Member Create Error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'An error occurred while creating the account.'], 500);
}
