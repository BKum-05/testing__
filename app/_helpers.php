<?php
declare(strict_types=1);

// ============================================================================
// MARK: PHP Setups
// ============================================================================

date_default_timezone_set('Asia/Kuala_Lumpur');

if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (ob_get_level() === 0) {
    ob_start();
}


// Global error container
$_err = [];

// Global lookup arrays
$_genders = [
    'male'              => 'Male',
    'female'            => 'Female',
    'other'             => 'Other',
    'prefer_not_to_say' => 'Prefer not to say',
];

const OTP_EXPIRY_MINUTES          = 10;
const OTP_MAX_ATTEMPTS            = 3;
const OTP_RESEND_COOLDOWN_SECONDS = 60;
const PASSWORD_RESET_EXPIRY_HOURS = 1;
const LOGIN_MAX_ATTEMPTS          = 3;
const LOGIN_LOCKOUT_MINUTES       = 5;
const MAX_NAME_LENGTH    = 50;
const MAX_EMAIL_LENGTH   = 255;
const MAX_ADDRESS_LENGTH = 255;

const VALID_ROLES = ['admin', 'member'];
const VALID_STATUSES = ['pending', 'active', 'suspended'];
const CREATABLE_STATUSES = ['pending', 'active'];

$cloudflareToken = '0x4AAAAAAEDHocErlpkEsV43';
$googleClientId = '329185449175-k97avg809hnc61r2p6sgt9cc1tec9m2v.apps.googleusercontent.com';


// ============================================================================
// MARK: General
// ============================================================================

function is_get(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'GET';
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function get(string $key, mixed $default = null): mixed
{
    $value = $_GET[$key] ?? $default;
    return is_array($value) ? array_map('trim', $value) : (is_string($value) ? trim($value) : $value);
}

function post(string $key, mixed $default = null): mixed
{
    $value = $_POST[$key] ?? $default;
    return is_array($value) ? array_map('trim', $value) : (is_string($value) ? trim($value) : $value);
}

function req(string $key, mixed $default = null): mixed
{
    $value = $_REQUEST[$key] ?? $default;
    return is_array($value) ? array_map('trim', $value) : (is_string($value) ? trim($value) : $value);
}


// Is date?
function is_date($value, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $value);
    return $d && $d->format($format) == $value;
}


/**
 * Filesystem path to the project root (one level up from /includes),
 * and the web-root-relative URL prefix the app is served under (e.g.
 * '/Degree-webbased-assignment--main', or '' if served from the domain root). Computed once
 * here — inside a file whose own location never changes — so it stays
 * correct no matter which folder depth the requesting page lives at.
 */
define('BASE_PATH', dirname(__DIR__));

/**
 * Derived from the CURRENT REQUEST's SCRIPT_NAME rather than comparing
 * DOCUMENT_ROOT against a filesystem path — the previous approach
 * string-subtracted DOCUMENT_ROOT from BASE_PATH, which is case-sensitive
 * and breaks silently on Windows/XAMPP if Apache's configured DocumentRoot
 * casing doesn't exactly match how PHP resolves the folder (a real risk:
 * Windows filesystems are case-insensitive, but str_replace() isn't).
 * SCRIPT_NAME is always a clean, forward-slash, web-relative path supplied
 * directly by the web server for this request, so no filesystem comparison
 * is needed at all. Every page in this project lives exactly one folder
 * level below the project root (login/, member/, admin/ are siblings of
 * app/).
 */
function get_base_url(): string
{
    $cleanScriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php/x');
    if (preg_match('#^(.*)/(?:admin|member|login|app|page|templates)(?:/.*)?$#i', $cleanScriptName, $m)) {
        return rtrim($m[1], '/');
    }
    $dir = str_replace('\\', '/', dirname($cleanScriptName));
    $trimmed = rtrim($dir, '/');
    if ($trimmed === '.' || $trimmed === '/' || $trimmed === '\\') {
        return '';
    }
    return $trimmed;
}

if (!defined('BASE_URL')) {
    define('BASE_URL', get_base_url());
}

/**
 * Builds a root-relative URL for a path inside this app, e.g. url('login.php')
 * or url('admin/member_list.php'). Always resolves correctly regardless of
 * which folder the current page is served from — use this (or redirect(),
 * which already applies it) instead of bare relative filenames in <a href>,
 * <form action>, and asset links whenever the target may live in a
 * different folder than the current page.
 */
function url(string $path): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(?string $url = null): void
{
    $url ??= $_SERVER['REQUEST_URI'];
    // Bare relative filenames (no scheme, not already root-relative) are
    // resolved against BASE_URL so redirect('login.php') works correctly
    // from any folder depth — absolute/external URLs pass through untouched.
    if ($url !== '' && $url[0] !== '/' && !preg_match('#^https?://#i', $url)) {
        $url = url($url);
    }
    header("Location: $url");
    exit;
}

function temp(string $key, mixed $value = null): mixed
{
    if ($value !== null) {
        $_SESSION["temp_$key"] = $value;
        return null;
    }

    $val = $_SESSION["temp_$key"] ?? null;
    unset($_SESSION["temp_$key"]);
    return $val;
}

function json_response(array $data, int $statusCode = 200): void
{
    if (ob_get_length()) {
        ob_clean(); 
    }
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function include_head(string $title = 'Online Shopping System'): void
{
    $_title = $title;
    include __DIR__ . '/_head.php';
}

function include_foot(): void
{
    include __DIR__ . '/_foot.php';
}

function csv_safe(string $value): string
{
    return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
}

// ============================================================================
// MARK: HTML Input
// ============================================================================

function encode(mixed $value): string
{
    return htmlentities((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function html_input(string $type, string $key, string $attr = '', mixed $value = null): void
{
    $value = encode($value ?? ($GLOBALS[$key] ?? ''));
    echo "<input type='$type' id='$key' name='$key' value='$value' $attr>";
}

function html_text(string $key, string $attr = '', mixed $value = null): void
{
    html_input('text', $key, $attr, $value);
}

function html_email(string $key, string $attr = '', mixed $value = null): void
{
    html_input('email', $key, $attr, $value);
}

function html_password(string $key, string $attr = '', mixed $value = null): void
{
    html_input('password', $key, $attr, $value);
}

function html_phone(string $key, string $attr = '', mixed $value = null): void
{
    html_input('tel', $key, $attr, $value);
}

function html_date(string $key, string $attr = '', mixed $value = null): void
{
    html_input('date', $key, $attr, $value);
}

$dob = $_POST['date_of_birth'] ?? '';

if (empty($dob)) {
    $errors['date_of_birth'] = 'Date of birth is required.';
} else {
    $d = DateTime::createFromFormat('Y-m-d', $dob);
    
    // Check if it's a real calendar date matching YYYY-MM-DD
    if (!$d || $d->format('Y-m-d') !== $dob) {
        $errors['date_of_birth'] = 'Invalid date format.';
    } else {
        $today = new DateTime();
        $minAllowedDate = (clone $today)->modify('-120 years');
        $maxAllowedDate = (clone $today)->modify('-13 years'); // Must be at least 13 years old

        if ($d > $maxAllowedDate) {
            $errors['date_of_birth'] = 'You must be at least 13 years old to register.';
        } elseif ($d < $minAllowedDate) {
            $errors['date_of_birth'] = 'Please enter a valid date of birth.';
        } else {
            // Validated successfully
            $validDob = $d->format('Y-m-d');
        }
    }
}

function table_headers(array $fields, string $sort, string $dir, string $extraQuery = ''): string
{
    $html = '';
    foreach ($fields as $key => $label) {
        $newDir = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
        $query = ($extraQuery !== '' ? $extraQuery . '&' : '') . 'sort=' . urlencode($key) . '&dir=' . urlencode($newDir);

        $ariaSort = 'none';
        $arrow = '';
        if ($sort === $key) {
            $ariaSort = $dir === 'asc' ? 'ascending' : 'descending';
            $arrow = $dir === 'asc' ? ' &uarr;' : ' &darr;';
        }

        $html .= "<th scope='col' aria-sort='{$ariaSort}'><a href='?{$query}'>" . encode($label) . "{$arrow}</a></th>";
    }
    return $html;
}

function html_radios(string $key, array $items, bool $br = false): void
{
    $value = encode($GLOBALS[$key] ?? '');
    echo '<div class="radio-group">';
    foreach ($items as $id => $text) {
        $state = (string)$id === (string)$value ? 'checked' : '';
        echo "<label class='radio-label'><input type='radio' id='{$key}_$id' name='$key' value='$id' $state> $text</label>";
        if ($br) {
            echo '<br>';
        }
    }
    echo '</div>';
}

function html_select(string $key, array $items, ?string $default = '- Select One -', string $attr = ''): void
{
    $value = encode($GLOBALS[$key] ?? '');
    echo "<select id='$key' name='$key' $attr>";
    if ($default !== null) {
        echo "<option value=''>$default</option>";
    }
    foreach ($items as $id => $text) {
        $state = (string)$id === (string)$value ? 'selected' : '';
        echo "<option value='$id' $state>$text</option>";
    }
    echo '</select>';
}


function html_select_data(string $key, string $placeholder = 'Select One', string $dataAttrKey = '', string $attr = ''): void
{
    $value = encode($GLOBALS[$key] ?? '');
    $dataAttr = $dataAttrKey !== '' ? "data-{$dataAttrKey}='{$value}'" : '';
    echo "<select id='$key' name='$key' {$dataAttr} {$attr}>";
    echo "<option value=''>{$placeholder}</option>";
    if ($value !== '') {
        echo "<option value='{$value}' selected>{$value}</option>";
    }
    echo '</select>';
}


function html_datalist(string $key, array $items, string $placeholder = '', string $attr = ''): void
{
    $value = encode($GLOBALS[$key] ?? '');
    $listId = "{$key}_list";

    echo "<input type='text' id='$key' name='$key' value='$value' list='$listId' placeholder='$placeholder' $attr>";
    echo "<datalist id='$listId'>";
    foreach ($items as $id => $text) {
        $optValue = is_int($id) ? $text : $id;
        echo "<option value='$optValue'>$text</option>";
    }
    echo '</datalist>';
}


function html_datalist_data(string $key, string $placeholder = '', string $dataAttrKey = '', string $attr = ''): void
{
    $value = encode($GLOBALS[$key] ?? '');
    $listId = "{$key}_list";
    $dataAttr = $dataAttrKey !== '' ? "data-{$dataAttrKey}='{$value}'" : '';

    echo "<input type='text' id='$key' name='$key' value='$value' list='$listId' placeholder='$placeholder' $dataAttr $attr>";
    echo "<datalist id='$listId'></datalist>";
}

function html_button(string $type, string $text, string $attr = ''): void
{
    echo "<button type='$type' $attr>$text</button>";
}

function get_file(string $key): ?object
{
    if (empty($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$key];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    return (object) [
        'name'     => $file['name'],
        'type'     => $realType ?: $file['type'],
        'tmp_name' => $file['tmp_name'],
        'size'     => (int) $file['size'],
    ];
}

function html_file(string $key, string $accept = '', string $attr = ''): void
{
    $key = encode($key);
    $accept = encode($accept);
    echo "<input type='file' id='$key' name='$key' accept='$accept' $attr>";
}


function html_google(string $clientId, string $callback, bool $autoPrompt = false, string $text = 'signin_with'): void
{
    $autoPromptAttr = $autoPrompt ? 'true' : 'false';
    echo "<div id='g_id_onload' data-client_id='$clientId' data-callback='$callback' data-auto_prompt='$autoPromptAttr'></div>";
    echo "<div class='g_id_signin' data-type='standard' data-shape='rectangular' data-theme='outline' data-text='$text'></div>";
}

function html_cloudflare(string $sitekey): void
{
    echo "<div class='captcha-box'><div class='cf-turnstile' data-sitekey='$sitekey'></div></div>";
}

function turnstile_set_error(?string $error): void
{
    $GLOBALS['_turnstile_error'] = $error;
}

function turnstile_get_error(): ?string
{
    return $GLOBALS['_turnstile_error'] ?? null;
}



function perform_logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => 'Lax',
            ]
        );
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}


function build_absolute_url(string $path): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Delegate to url()/BASE_URL rather than recomputing the base path from
    // dirname($_SERVER['SCRIPT_NAME']) — that approach breaks the moment the
    // calling script lives in a subfolder (e.g. staff/process_member_create.php),
    // since it returns that script's OWN folder, not the project root.
    return $protocol . '://' . $host . url($path);
}


// ============================================================================
// MARK: Error Display
// ============================================================================

function err(string $key): void
{
    global $_err;
    if (!empty($_err[$key])) {
        echo "<span class='err-msg'>" . encode($_err[$key]) . "</span>";
    } else {
        echo "<span class='err-msg'></span>";
    }
}
