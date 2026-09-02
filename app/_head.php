<?php
declare(strict_types=1);
$currentUser = null;
if (function_exists('get_pdo') && !empty($_SESSION['user_id'])) {
    try {
        $pdo = get_pdo();
        $currentUser = fetch_user_by_id($pdo, (int) $_SESSION['user_id']);
    } catch (Throwable $e) {
        $currentUser = null;
    }
}
$isStaff = ($currentUser['role'] ?? '') === 'admin';
$isMember = ($currentUser['role'] ?? '') === 'member';
$isLoggedIn = !empty($currentUser);
$userDisplayName = trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')) ?: ($currentUser['email'] ?? 'User');
$userAvatar = function_exists('get_avatar_url') ? get_avatar_url($currentUser['avatar_url'] ?? null) : url('uploads/avatar/default.svg');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($_title ?? $page_title ?? 'Fashion Shop - Online Shopping System', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="shortcut icon" href="<?= url('uploads/avatar/default.svg') ?>">
    <link rel="stylesheet" href="<?= url('app/css/app.css') ?>">
    <link rel="stylesheet" href="<?= url('app/css/account.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script>
        window.BASE_URL = <?= json_encode(defined('BASE_URL') ? BASE_URL : '') ?>;
        window.addEventListener('error', function(event) {
            if (event.message === 'Script error.' || (!event.filename && !event.lineno)) {
                if (typeof event.stopImmediatePropagation === 'function') {
                    event.stopImmediatePropagation();
                }
                return true;
            }
        }, true);
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="<?= url('app/js/app.js') ?>"></script>
    <script src="<?= url('app/js/account.js') ?>"></script>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body>
    <div id="app-content"> <!-- (for accessibility) -->
        <header class="main-header">
            <div class="header-inner">
                <div class="logo-area">
                    <a href="<?= url('index.php') ?>" class="site-logo">
                        <i class="fas fa-gem" aria-hidden="true"></i>
                        <span>Fashion Shop</span>
                    </a>
                </div>
                <nav class="main-nav" aria-label="Main Navigation">
                    <?php if ($isLoggedIn): ?>
                        <?php if ($isStaff): ?>
                            <a href="<?= url('admin/staff_profile.php') ?>"><i class="fas fa-user-shield"></i> Staff Portal</a>
                            <a href="<?= url('admin/member_list.php') ?>"><i class="fas fa-users-gear"></i> Member Directory</a>
                            <a href="<?= url('admin/member_create.php') ?>"><i class="fas fa-user-plus"></i> Add Member</a>
                            <a href="<?= url('admin/audit_log.php') ?>"><i class="fas fa-shield-halved"></i> Activity Logs</a>
                        <?php else: ?>
                            <a href="<?= url('member/profile.php') ?>"><i class="fas fa-user"></i> My Profile</a>
                            <a href="<?= url('member/edit_profile.php') ?>"><i class="fas fa-user-pen"></i> Edit Profile</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="<?= url('login/login.php') ?>"><i class="fas fa-sign-in-alt"></i> Sign In</a>
                        <a href="<?= url('login/register.php') ?>"><i class="fas fa-user-plus"></i> Register</a>
                    <?php endif; ?>
                </nav>
                <div class="user-quick-bar">
                    <?php if ($isLoggedIn): ?>
                        <div class="user-greeting">
                            <img src="<?= htmlspecialchars($userAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="User Avatar" class="user-nav-avatar">
                            <span class="user-nav-name"><?= htmlspecialchars($userDisplayName, ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="user-nav-badge <?= $isStaff ? 'badge-admin' : 'badge-member' ?>"><?= $isStaff ? 'Staff' : 'Member' ?></span>
                        </div>
                        <a href="<?= url('login/logout.php') ?>" class="btn-nav-logout" title="Sign Out"><i class="fas fa-right-from-bracket"></i> Logout</a>
                    <?php else: ?>
                        <a href="<?= url('login/login.php') ?>" class="btn-nav-action"><i class="fas fa-user"></i> Sign In</a>
                    <?php endif; ?>
                </div>
            </div>
        </header>
        <main class="page-body">
