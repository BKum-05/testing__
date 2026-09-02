<?php

declare(strict_types=1);



require_once __DIR__ . '/_helpers.php';


if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}


function get_pdo(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $db   = getenv('DB_NAME') ?: 'fashion_shop';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $charset = 'utf8mb4';

        $_db = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        ];

        try {
            $pdo = new PDO($_db, $user, $pass, $options);
        } catch (PDOException $e) {
            die('Database Connection Failed.');
        }
    }

    return $pdo;
}


$dob = req('date_of_birth');


$d = new DateTime('today');
$max = $d->modify('-13 years')->format('Y-m-d');
$min = $d->modify('-120 years')->format('Y-m-d');

// Validate: date
if ($dob == '') {
    $_err['date'] = 'Required';
} else if (!is_date($dob)) {
    $_err['date'] = 'Invalid date';
} else if ($dob < $min || $dob > $max) {
    $_err['date'] = "Must between $min to $max";
}

// ============================================================================
// MARK: Security
// ============================================================================

function require_post(): void
{
    if (!is_post()) {
        json_response(['success' => false, 'message' => 'Method Not Allowed'], 405);
    }
}

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        redirect('login/login.php');
    }

    enforce_live_account_state();
}


function enforce_live_account_state(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT role, status FROM users WHERE user_id = :id LIMIT 1');
    $stmt->execute(['id' => (int) $_SESSION['user_id']]);
    $current = $stmt->fetch();

    if ($current === false || $current['status'] === 'suspended') {
        perform_logout();
        temp('flash_notice', 'Your session has ended. Please contact an administrator if you believe this is a mistake.');
        redirect('login/login.php');
    }

    $liveRole = strtolower((string) $current['role']);
    if (($_SESSION['role'] ?? '') !== $liveRole) {
        $_SESSION['role'] = $liveRole;
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . encode(csrf_token()) . '">';
}

function verify_csrf(?string $token): bool
{
    return !empty($_SESSION['csrf_token']) && !empty($token) && hash_equals($_SESSION['csrf_token'], $token);
}

function is_admin(): bool
{
    return strtolower(trim((string) ($_SESSION['role'] ?? 'member'))) === 'admin';
}


function get_dashboard_url(): string
{
    return is_admin() ? url('admin/staff_profile.php') : url('member/profile.php');
}

function require_role(string $requiredRole): void
{
    require_login();

    $currentRole = strtolower(trim((string) ($_SESSION['role'] ?? 'member')));
    if ($currentRole !== strtolower($requiredRole)) {
        temp('flash_notice', 'Access restricted to authorized personnel.');
        redirect(get_dashboard_url());
    }
}


/**
 * PHPMailer.
 *
 * @param string $toEmail       Recipient email address
 * @param string $subject       Email subject line
 * @param string $templateName  Name of template file for render_email()
 * @param array  $templateData  Key-value array passed into template renderer
 * @param string $recipientName Recipient full name (optional)
 * @param string $altBody       Plain text fallback body (optional)
 * @return bool True on success, false on failure
 */
function send_email(
    string $toEmail,
    string $subject,
    string $templateName,
    array $templateData = [],
    string $recipientName = '',
    string $altBody = ''
): bool {
    if (empty($recipientName)) {
        if (!empty($templateData['recipientName'])) {
            $recipientName = $templateData['recipientName'];
        } elseif (!empty($_SESSION['pending_user'])) {
            $recipientName = trim(($_SESSION['pending_user']['first_name'] ?? '') . ' ' . ($_SESSION['pending_user']['last_name'] ?? ''));
        } elseif (!empty($_SESSION['user'])) {
            $recipientName = trim(($_SESSION['user']['first_name'] ?? '') . ' ' . ($_SESSION['user']['last_name'] ?? ''));
        }
    }

    if (empty($recipientName)) {
        $recipientName = 'Valued Customer';
    }

    $templateData['recipientName'] = $recipientName;
    if (!isset($templateData['title'])) {
        $templateData['title'] = $subject;
    }

    require_once __DIR__ . '/_email_template.php';

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('SMTP_USERNAME') ?: 'bmit.2013.ecommerce@gmail.com';
        $mail->Password   = getenv('SMTP_PASSWORD') ?: 'ouvm ovrm jrhc eovg';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom($mail->Username, 'Online Shopping System');
        $mail->addAddress($toEmail, $recipientName);
        $mail->isHTML(true);
        $mail->Subject = $subject;

        $htmlBody   = render_email($templateName, $templateData);
        $mail->Body = $htmlBody;

        if (!empty($altBody)) {
            $mail->AltBody = $altBody;
        } else {
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $htmlBody));
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error [{$subject}]: " . $mail->ErrorInfo);
        return false;
    }
}


function fetch_user_by_email(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare('SELECT *, user_id AS id FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}

function fetch_user_by_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT *, user_id AS id FROM users WHERE user_id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}

function fetch_user_by_google_sub(PDO $pdo, string $googleSub): ?array
{
    $stmt = $pdo->prepare('SELECT *, user_id AS id FROM users WHERE google_id = :google_id LIMIT 1');
    $stmt->execute(['google_id' => $googleSub]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}

function set_auth_session(array $userData): void
{
    $_SESSION['user_id']   = (int)($userData['user_id'] ?? $userData['id']);
    $_SESSION['email']     = $userData['email'] ?? '';
    $_SESSION['user_role'] = $userData['role'] ?? 'member';
    $_SESSION['name']      = trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? '')) ?: ($userData['name'] ?? 'User');
}


function user_requires_password_reset(array $user): bool
{
    return ($user['status'] ?? '') === 'pending';
}

/** 
 * @return string The raw (unhashed) token to embed in the emailed link.
 */
function issue_password_reset_token(PDO $pdo, int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $hashedToken = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . PASSWORD_RESET_EXPIRY_HOURS . ' hour'));

    $stmt = $pdo->prepare(
        'INSERT INTO password_resets (user_id, token_hash, requested_ip, expires_at)
         VALUES (:user_id, :token_hash, :requested_ip, :expires_at)'
    );
    $stmt->execute([
        'user_id'      => $userId,
        'token_hash'   => $hashedToken,
        'requested_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'expires_at'   => $expiresAt,
    ]);

    return $token;
}

function get_password_policy_issues(string $password): array
{
    $issues = [];
    if (strlen($password) < 8) {
        $issues[] = 'Must be at least 8 characters long.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $issues[] = 'Must include at least one uppercase letter.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $issues[] = 'Must include at least one lowercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $issues[] = 'Must include at least one number.';
    }
    if (!preg_match('/[\W_]/', $password)) {
        $issues[] = 'Must include at least one symbol.';
    }
    return $issues;
}

function get_avatar_url(?string $dbAvatarUrl): string
{
    $relativePath = ltrim((string) $dbAvatarUrl, '/');

    if ($relativePath === '' || $relativePath === 'uploads/avatars' || $relativePath === 'uploads/avatars/' || $relativePath === 'uploads/avatars/default.svg' || $relativePath === 'app/uploads/avatars/default.svg') {
        $relativePath = 'app/uploads/avatars/default.svg';
    } elseif (!str_starts_with($relativePath, 'app/')) {
        $relativePath = 'app/' . $relativePath;
    }

    return url($relativePath);
}



function create_user_with_profile(PDO $pdo, array $data): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO users (
            email, password_hash, auth_provider, google_id, role, status, password_changed_at,
            first_name, last_name, gender, date_of_birth, phone_number,
            address_line1, address_line2, city, state, postal_code, country, avatar_url,
            last_login_at, last_login_ip, created_at, updated_at
        ) VALUES (
            :email, :password_hash, :auth_provider, :google_id, :role, :status, :password_changed_at,
            :first_name, :last_name, :gender, :date_of_birth, :phone_number,
            :address_line1, :address_line2, :city, :state, :postal_code, :country, :avatar_url,
            :last_login_at, :last_login_ip, NOW(), NOW()
        )'
    );

    $hasPassword = !empty($data['password_hash']);

    $stmt->execute([
        'email'               => $data['email'],
        'password_hash'       => $data['password_hash'] ?? null,
        'auth_provider'       => $data['auth_provider'] ?? 'local',
        'google_id'           => $data['google_id'] ?? null,
        'role'                => $data['role'] ?? 'member',
        'status'              => $data['status'] ?? 'pending',
        'password_changed_at' => $hasPassword ? date('Y-m-d H:i:s') : null,
        'first_name'          => $data['first_name'] ?? '',
        'last_name'           => $data['last_name'] ?? '',
        'gender'              => $data['gender'] ?? 'prefer_not_to_say',
        'date_of_birth'       => !empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
        'phone_number'        => !empty($data['phone_number']) ? $data['phone_number'] : null,
        'address_line1'       => !empty($data['address_line1']) ? $data['address_line1'] : null,
        'address_line2'       => !empty($data['address_line2']) ? $data['address_line2'] : null,
        'city'                => !empty($data['city']) ? $data['city'] : null,
        'state'               => !empty($data['state']) ? $data['state'] : null,
        'postal_code'         => !empty($data['postal_code']) ? $data['postal_code'] : null,
        'country'             => $data['country'] ?? 'Malaysia',
        'avatar_url'          => $data['avatar_url'] ?? 'uploads/avatars/default.svg',
        'last_login_at'       => !empty($data['touch_login']) ? date('Y-m-d H:i:s') : null,
        'last_login_ip'       => $data['last_login_ip'] ?? null,
    ]);

    return (int) $pdo->lastInsertId();
}


function update_user_profile(PDO $pdo, int $userId, array $data): bool
{
    $statement = $pdo->prepare(
        'UPDATE users SET
            first_name    = :first_name,
            last_name     = :last_name,
            gender        = :gender,
            date_of_birth = :date_of_birth,
            phone_number  = :phone_number,
            address_line1 = :address_line1,
            address_line2 = :address_line2,
            city          = :city,
            state         = :state,
            postal_code   = :postal_code,
            country       = :country,
            avatar_url    = COALESCE(:avatar_url, avatar_url),
            updated_at    = NOW()
        WHERE user_id = :user_id'
    );

    return $statement->execute([
        'user_id'       => $userId,
        'first_name'    => $data['first_name'] ?? '',
        'last_name'     => $data['last_name'] ?? '',
        'gender'        => $data['gender'] ?? 'prefer_not_to_say',
        'date_of_birth' => !empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
        'phone_number'  => !empty($data['phone_number']) ? $data['phone_number'] : null,
        'address_line1' => !empty($data['address_line1']) ? $data['address_line1'] : null,
        'address_line2' => !empty($data['address_line2']) ? $data['address_line2'] : null,
        'city'          => !empty($data['city']) ? $data['city'] : null,
        'state'         => !empty($data['state']) ? $data['state'] : null,
        'postal_code'   => !empty($data['postal_code']) ? $data['postal_code'] : null,
        'country'       => $data['country'] ?? 'Malaysia',
        'avatar_url'    => $data['avatar_url'] ?? null,
    ]);
}

function verify_turnstile(?string $token, ?string $remoteIp = null): bool
{
    turnstile_set_error(null);

    if (empty($token)) {
        turnstile_set_error('missing-input-response');
        return false;
    }

    $secretKey = getenv('TURNSTILE_SECRET_KEY') ?: '0x4AAAAAAEDHod6yVIwST6nv0gRu6TLo9cQ';

    $postData = http_build_query([
        'secret'   => $secretKey,
        'response' => $token,
        'remoteip' => $remoteIp ?? $_SERVER['REMOTE_ADDR'] ?? ''
    ]);

    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $curlError = curl_error($ch);
        curl_close($ch);
        turnstile_set_error('curl-error-' . $curlError);
        return false;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        turnstile_set_error('siteverify-request-failed-http-' . $httpCode);
        return false;
    }

    $result = json_decode($response, true);
    if (!is_array($result)) {
        turnstile_set_error('invalid-json-response');
        return false;
    }

    if (empty($result['success'])) {
        $errors = $result['error-codes'] ?? [];
        turnstile_set_error(is_array($errors) && !empty($errors) ? implode(',', $errors) : 'unknown');
        return false;
    }

    return true;
}

function verify_google_id_token(string $idToken): array|false
{
    if (empty($idToken)) {
        return false;
    }

    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);

    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'ignore_errors' => true,
        ]
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return false;
    }

    $data = json_decode($response, true);
    if (!is_array($data) || isset($data['error_description']) || isset($data['error'])) {
        return false;
    }

    return $data;
}



