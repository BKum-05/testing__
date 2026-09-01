<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../app/_auth.php';
require_once __DIR__ . '/../app/_validation_lib.php';

if (!is_post()) {
    redirect('register.php');
}

// -------------------------------------------------------------------------
// MARK: 1. Extract & Normalize Inputs
// -------------------------------------------------------------------------
$firstName      = trim(post('first_name', ''));
$lastName       = trim(post('last_name', ''));
$email          = strtolower(trim(post('email', '')));
$gender         = strtolower(trim(post('gender', 'prefer_not_to_say')));
$dob            = trim(post('date_of_birth', ''));
$phoneNumber    = trim(post('phone_number') ?? post('phone', ''));
$address1       = trim(post('address_line1', ''));
$state          = trim(post('state', ''));
$city           = trim(post('city', ''));
$postalCode     = trim(post('postcode', ''));
$csrfToken      = post('csrf_token', '');
$turnstileToken = post('cf-turnstile-response', '');

$GLOBALS['email']         = $email;
$GLOBALS['first_name']    = $firstName;
$GLOBALS['last_name']     = $lastName;
$GLOBALS['gender']        = $gender;
$GLOBALS['date_of_birth'] = $dob;
$GLOBALS['phone_number']  = $phoneNumber;
$GLOBALS['address_line1'] = $address1;
$GLOBALS['state']         = $state;
$GLOBALS['city']          = $city;
$GLOBALS['postcode']      = $postalCode;

$_err = [];

// -------------------------------------------------------------------------
// MARK: 2. Security Verification
// -------------------------------------------------------------------------
if (!verify_csrf($csrfToken)) {
    $_err['csrf'] = 'Invalid or expired session token. Please refresh and try again.';
}

if (!verify_turnstile($turnstileToken)) {
    $_err['turnstile'] = 'Security verification failed. Please check the captcha box again.';
}

// -------------------------------------------------------------------------
// MARK: 3. Input Field Validations
// -------------------------------------------------------------------------
$validationErrors = validate_person_fields($_POST);
$_err = array_merge($_err, $validationErrors);

if (!empty($_err)) {
    if (req('ajax')) {
        json_response([
            'success' => false,
            'errors'  => $_err,
            'message' => reset($_err)
        ], 422);
    }

    temp('danger', reset($_err));
    include 'register.php';
    exit;
}

$pdo = get_pdo();

// -------------------------------------------------------------------------
// MARK: 5. Pre-Check Existing Email & Store Pending Data
// -------------------------------------------------------------------------
try {
    $statement = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $statement->execute(['email' => $email]);
    if ($statement->fetch()) {
        $_err['email'] = 'Email address is already registered.';
        if (req('ajax')) {
            json_response([
                'success' => false,
                'errors'  => $_err,
                'message' => 'Email address is already registered.'
            ], 409);
        }

        temp('danger', $_err['email']);
        include 'register.php';
        exit;
    }

    $_SESSION['pending_user'] = [
        'first_name'    => $firstName,
        'last_name'     => $lastName,
        'email'         => $email,
        'gender'        => $gender,
        'date_of_birth' => !empty($dob) ? dob_to_iso($dob) : null,
        'phone_number'  => $phoneNumber,
        'address_line1' => $address1,
        'city'          => $city,
        'state'         => $state,
        'postal_code'   => $postalCode,
    ];

    $otp       = sprintf('%06d', random_int(0, 999999));
    $otpHash   = password_hash($otp, PASSWORD_DEFAULT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));

    $statement = $pdo->prepare('DELETE FROM email_otps WHERE email = :email');
    $statement->execute(['email' => $email]);

    $statement = $pdo->prepare(
        'INSERT INTO email_otps (email, otp_hash, expires_at) 
         VALUES (:email, :otp_hash, :expires_at)'
    );
    $statement->execute([
        'email'      => $email,
        'otp_hash'   => $otpHash,
        'expires_at' => $expiresAt,
    ]);

} catch (\PDOException $e) {
    error_log('Registration OTP Database Error: ' . $e->getMessage());

    if (req('ajax')) {
        json_response(['success' => false, 'message' => 'A database error occurred during verification setup.'], 500);
    }

    $_err['general'] = 'An unexpected system error occurred. Please try again later.';
    temp('danger', $_err['general']);
    include 'register.php';
    exit;
}

// -------------------------------------------------------------------------
// MARK: 6. Send OTP Email
// -------------------------------------------------------------------------
$fullName  = trim($firstName . ' ' . $lastName);
$emailSent = send_email(
    toEmail: $email,
    subject: 'Your Email Verification Code - Online Shopping System',
    templateName: '_otp',
    templateData: [
        'otp' => $otp,
    ],
    recipientName: $fullName
);

if (!$emailSent) {
    $errorMsg = 'Failed to send verification email. Please check your email address and try again.';
    if (req('ajax')) {
        json_response(['success' => false, 'message' => $errorMsg], 500);
    }

    $_err['general'] = $errorMsg;
    temp('danger', $errorMsg);
    include 'register.php';
    exit;
}

unset($_SESSION['otp_verified']);

// -------------------------------------------------------------------------
// MARK: 7. Response Output
// -------------------------------------------------------------------------
temp('success', 'A 6-digit verification code has been sent to your email.');

if (req('ajax')) {
    json_response([
        'success'  => true,
        'redirect' => 'verify_otp.php',
        'message'  => 'Verification code sent successfully!'
    ]);
}

redirect('verify_otp.php');