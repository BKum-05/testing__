<?php
declare(strict_types=1);

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$filePath = __DIR__ . $uri;

// 1. Serve static assets directly (css, js, images, fonts, svgs, etc.)
if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    if ($ext !== 'php') {
        return false; // Let PHP built-in server handle static asset
    }
    require $filePath;
    exit;
}

// 2. Map root '/' to index.php
if ($uri === '/' || $uri === '') {
    require __DIR__ . '/index.php';
    exit;
}

// 3. Direct route to PHP files inside folders (e.g. /login/login.php, /member/profile.php, /admin/member_list.php)
if (file_exists($filePath) && !is_dir($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) === 'php') {
    require $filePath;
    exit;
}

// 4. Clean URLs without extension (e.g. /login/login -> /login/login.php, /member/profile -> /member/profile.php)
if (file_exists($filePath . '.php')) {
    require $filePath . '.php';
    exit;
}

// 5. Short clean aliases
$aliasMap = [
    '/login' => '/login/login.php',
    '/register' => '/login/register.php',
    '/forgot-password' => '/login/forgot_password.php',
    '/verify-otp' => '/login/verify_otp.php',
    '/set-password' => '/login/set_password.php',
    '/logout' => '/login/logout.php',
    '/profile' => '/member/profile.php',
    '/edit-profile' => '/member/edit_profile.php',
    '/admin' => '/admin/staff_profile.php',
    '/members' => '/admin/member_list.php',
    '/audit-log' => '/admin/audit_log.php',
];

if (isset($aliasMap[$uri]) && file_exists(__DIR__ . $aliasMap[$uri])) {
    require __DIR__ . $aliasMap[$uri];
    exit;
}

// 6. Backward-compatibility routing: redirect/map root-level files to their new folder locations
$rootToFolderMap = [
    '/login.php' => '/login/login.php',
    '/register.php' => '/login/register.php',
    '/verify_otp.php' => '/login/verify_otp.php',
    '/set_password.php' => '/login/set_password.php',
    '/forgot_password.php' => '/login/forgot_password.php',
    '/logout.php' => '/login/logout.php',
    '/resend_otp.php' => '/login/resend_otp.php',
    '/process_login.php' => '/login/process_login.php',
    '/process_register.php' => '/login/process_register.php',
    '/process_verify_otp.php' => '/login/process_verify_otp.php',
    '/process_set_password.php' => '/login/process_set_password.php',
    '/process_forgot_password.php' => '/login/process_forgot_password.php',
    '/process_google_auth.php' => '/login/process_google_auth.php',
    '/process_logout.php' => '/login/process_logout.php',

    '/profile.php' => '/member/profile.php',
    '/edit_profile.php' => '/member/edit_profile.php',
    '/process_profile.php' => '/member/process_profile.php',
    '/process_delete_account.php' => '/member/process_delete_account.php',

    '/staff_profile.php' => '/admin/staff_profile.php',
    '/member_list.php' => '/admin/member_list.php',
    '/member_detail.php' => '/admin/member_detail.php',
    '/member_create.php' => '/admin/member_create.php',
    '/member_export.php' => '/admin/member_export.php',
    '/audit_log.php' => '/admin/audit_log.php',
    '/process_member_create.php' => '/admin/process_member_create.php',
    '/process_member_delete.php' => '/admin/process_member_delete.php',
    '/process_member_reset_password.php' => '/admin/process_member_reset_password.php',
    '/process_member_role.php' => '/admin/process_member_role.php',
    '/process_member_status.php' => '/admin/process_member_status.php',
];

if (isset($rootToFolderMap[$uri]) && file_exists(__DIR__ . $rootToFolderMap[$uri])) {
    require __DIR__ . $rootToFolderMap[$uri];
    exit;
}

// 7. 404 Fallback
http_response_code(404);
echo "404 Not Found";
