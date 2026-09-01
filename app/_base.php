<?php

// Google Map and Stripe Payment API 
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// ============================================================================
// PHP Setups
// ============================================================================

//timezone
date_default_timezone_set("Asia/Kuala_Lumpur");

session_start();

$_db = new PDO(
    'mysql:host=localhost;dbname=fashion_shop;charset=utf8mb4',
    'root',
    '',
    [
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]
);


$_err = [];

// user details
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



function is_get()
{
    return $_SERVER['REQUEST_METHOD'] == 'GET';
}


function is_post()
{
    return $_SERVER['REQUEST_METHOD'] == 'POST';
}

function req($key)
{
    return $_REQUEST[$key] ?? '';
}


function temp($key, $msg = null)
{
    if ($msg) {
        $_SESSION[$key] = $msg;
    } else {
        $val = $_SESSION[$key] ?? '';
        unset($_SESSION[$key]);
        return $val;
    }
}

function redirect($url)
{
    header("Location: $url");
    exit;
}


function old($key)
{
    return $_POST[$key] ?? '';
}


function err($key)
{
    global $_err;
    if (isset($_err[$key])) {
        echo "<div style='color:red'>{$_err[$key]}</div>";
    }
}

function html_input(string $type, string $key, string $attr = '', mixed $value = null): void
{
    $value = encode($value ?? ($GLOBALS[$key] ?? ''));
    echo "<input type='$type' id='$key' name='$key' value='$value' $attr>";
}


function html_text($name, $attr = '')
{
    $val = old($name);
    echo "<input type='text' name='$name' value='$val' $attr>";
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



function encode($value)
{
    return htmlentities($value, ENT_QUOTES);
}



// Generate <select> dropdown list
function html_select($key, $items, $default = '- Select One -', $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    echo "<select id='$key' name='$key' $attr>";
    if ($default !== null) {
        echo "<option value=''>$default</option>";
    }
    foreach ($items as $id => $text) {
        $state = $id == $value ? 'selected' : '';
        echo "<option value='$id' $state>$text</option>";
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
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}



// Obtain GET parameter
function get($key, $value = null) {
    $value = $_GET[$key] ?? $value;
    return is_array($value) ? array_map('trim', $value) : trim($value);
}

// Obtain POST parameter 
function post($key, $value = null) {
    $value = $_POST[$key] ?? $value;
    return is_array($value) ? array_map('trim', $value) : trim($value);
}

// Obtain uploaded file --> cast to object
//function get_file($key) {
//    $f = $_FILES[$key] ?? null;
//    
//    if ($f && $f['error'] == 0) {
//        return (object)$f;
//    }

//    return null;
//}

// Crop, resize and save photo
function save_photo($f, $folder, $width = 200, $height = 200) {

    if (!is_dir($folder)) {
        mkdir($folder, 0777, true);
    }

    $photo = uniqid() . '.jpg';

    require_once 'lib/SimpleImage.php';

    $img = new SimpleImage();

    $img->fromFile($f->tmp_name)
        ->thumbnail($width, $height)
        ->toFile("$folder/$photo", 'image/jpeg');

    return $photo;
}
// Is money?
function is_money($value) {
    return preg_match('/^\-?\d+(\.\d{1,2})?$/', $value);
}


function is_unique($value, $table, $field) {
    global $_db;
    $stm = $_db->prepare("SELECT COUNT(*) FROM $table WHERE $field = ?");
    $stm->execute([$value]);
    return $stm->fetchColumn() == 0;
}


function is_exists($value, $table, $field) {
    global $_db;
    $stm = $_db->prepare("SELECT COUNT(*) FROM $table WHERE $field = ?");
    $stm->execute([$value]);
    return $stm->fetchColumn() > 0;
}

// Generate <input type='number'>
function html_number($key, $min = '', $max = '', $step = '', $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    echo "<input type='number' id='$key' name='$key' value='$value'
                 min='$min' max='$max' step='$step' $attr>";
}

// Generate <input type='search'>
function html_search($key, $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    echo "<input type='search' id='$key' name='$key' value='$value' $attr>";
}

// Generate <input type='radio'> list
function html_radios($key, $items, $br = false) {
    $value = encode($GLOBALS[$key] ?? '');
    echo '<div>';
    foreach ($items as $id => $text) {
        $state = $id == $value ? 'checked' : '';
        echo "<label><input type='radio' id='{$key}_$id' name='$key' value='$id' $state>$text</label>";
        if ($br) {
            echo '<br>';
        }
    }
    echo '</div>';
}

// Generate <input type='file'>
function html_file($key, $accept = '', $attr = '') {
    echo "<input type='file' id='$key' name='$key' accept='$accept' $attr>";
}

// Generate table headers <th>
function table_headers($fields, $sort, $dir, $href = '') {
    foreach ($fields as $k => $v) {
        $d = 'asc'; // Default direction
        $c = '';    // Default class
        
        if ($k == $sort) {
            $d = $dir == 'asc' ? 'desc' : 'asc';
            $c = $dir;
        }

        echo "<th><a href='?sort=$k&dir=$d&$href' class='$c'>$v</a></th>";
    }
}

// Placeholder for TODO
/*function TODO() {
    echo '<span>TODO</span>';
}*/



?>
