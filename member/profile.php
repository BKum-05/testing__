<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/_auth.php';

require_role('member');

$pdo = get_pdo();
$userId = (int) ($_SESSION['user_id'] ?? 0);

$user = fetch_user_by_id($pdo, $userId) ?? [];

if (!empty($user) && user_requires_password_reset($user)) {
    redirect('login/set_password.php');
}

$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
if ($fullName === '') {
    $fullName = $user['email'] ?? 'User';
}

$avatarUrl = get_avatar_url($user['avatar_url'] ?? null);

include_head("My Account - Online Shopping System");
?>

<div class="container account-dashboard">
    <h2>My Account</h2>
    <p class="subtitle">Manage your personal information, view orders, and update security settings.</p>

    <?php if ($msg = temp('flash_success')): ?>
        <div class="alert-box alert-success"><?php echo encode($msg); ?></div>
    <?php endif; ?>

    <?php if ($msg = temp('flash_notice')): ?>
        <div class="alert-box alert-notice"><?php echo encode($msg); ?></div>
    <?php endif; ?>

    <div class="account-layout">
        <div class="account-info-col">
            <div class="profile-card">
                <div class="profile-banner"></div>
                <div class="profile-body">
                    <div class="avatar-wrapper">
                        <img src="<?php echo encode($avatarUrl); ?>" alt="Profile Photo" class="profile-avatar">
                    </div>

                    <div class="profile-main-info">
                        <div class="profile-header-group">
                            <h3 class="profile-name"><?php echo encode($fullName); ?></h3>
                            <span class="badge badge-active">Active Member</span>
                        </div>
                        <p class="profile-email">
                            <i class="fas fa-envelope"></i> <?php echo encode($user['email'] ?? ''); ?>
                        </p>
                        <?php if (!empty($user['phone_number'])): ?>
                            <p class="profile-phone">
                                <i class="fas fa-phone-alt"></i> <?php echo encode($user['phone_number']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="account-stats-grid">
                <div class="stat-card">
                    <span class="stat-label">Member Since</span>
                    <span class="stat-value"><?php echo encode(date('M Y', strtotime($user['created_at'] ?? 'now'))); ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Account Status</span>
                    <span class="stat-value text-success">Verified</span>
                </div>
            </div>
        </div>

        <div class="account-menu-col">
            <div class="account-menu">
                <a href="edit_profile.php" class="account-menu-item">
                    <div class="menu-icon"><i class="fas fa-user-cog"></i></div>
                    <div class="menu-text">
                        <strong>Edit Profile & Security</strong>
                        <span>Update personal details, photo, and change password</span>
                    </div>
                    <i class="fas fa-chevron-right menu-arrow"></i>
                </a>

                <a href="orders.php" class="account-menu-item">
                    <div class="menu-icon"><i class="fas fa-box-open"></i></div>
                    <div class="menu-text">
                        <strong>Order History</strong>
                        <span>Track current orders and view past purchases</span>
                    </div>
                    <i class="fas fa-chevron-right menu-arrow"></i>
                </a>

                <a href="<?php echo url('login/logout.php'); ?>" class="account-menu-item menu-logout">
                    <div class="menu-icon"><i class="fas fa-sign-out-alt"></i></div>
                    <div class="menu-text">
                        <strong>Log Out</strong>
                        <span>Sign out of your account on this device</span>
                    </div>
                    <i class="fas fa-chevron-right menu-arrow"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<?php include_foot(); ?>