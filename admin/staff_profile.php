<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/_auth.php';

require_role('admin');

$pdo = get_pdo();
$userId = (int) $_SESSION['user_id'];

$user = fetch_user_by_id($pdo, $userId);

if ($user !== null && user_requires_password_reset($user)) {
    redirect('login/set_password.php');
}

$role = strtolower((string) ($user['role'] ?? 'admin'));

$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
if ($fullName === '') {
    $fullName = $user['email'] ?? 'Staff Member';
}

$avatarUrl = get_avatar_url($user['avatar_url'] ?? null);
$roleTitle = ucfirst($role);

include_head("Staff Dashboard - Online Shopping System");
?>

<div class="container account-dashboard">
    <h2>Staff Portal</h2>
    <p class="subtitle">Manage store operations, orders, inventory, and account security.</p>

    <?php if ($msg = temp('flash_success')): ?>
        <div class="alert-box alert-success"><?php echo encode($msg); ?></div>
    <?php endif; ?>

    <?php if ($msg = temp('flash_notice')): ?>
        <div class="alert-box alert-notice"><?php echo encode($msg); ?></div>
    <?php endif; ?>

    <div class="account-layout">
        <div class="account-info-col">
            <div class="profile-card staff-profile-card">
                <div class="profile-banner staff-banner"></div>
                <div class="profile-body">
                    <div class="avatar-wrapper">
                        <img src="<?php echo encode($avatarUrl); ?>" alt="Profile Photo" class="profile-avatar">
                    </div>

                    <div class="profile-main-info">
                        <div class="profile-header-group">
                            <h3 class="profile-name"><?php echo encode($fullName); ?></h3>
                            <span class="badge badge-staff"><i class="fas fa-user-shield"></i> <?php echo encode($roleTitle); ?></span>
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
                    <span class="stat-label">Staff Since</span>
                    <span class="stat-value"><?php echo encode(date('M Y', strtotime($user['created_at'] ?? 'now'))); ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">System Status</span>
                    <span class="stat-value text-success">Active</span>
                </div>
            </div>
        </div>

        <div class="account-menu-col">
            <div class="account-menu">
                <a href="member_list.php" class="account-menu-item">
                    <div class="menu-icon"><i class="fas fa-users-cog"></i></div>
                    <div class="menu-text">
                        <strong>Member Management</strong>
                        <span>Manage member and staff accounts, roles, and status</span>
                    </div>
                    <i class="fas fa-chevron-right menu-arrow"></i>
                </a>

                <a href="audit_log.php" class="account-menu-item">
                    <div class="menu-icon"><i class="fas fa-clipboard-list"></i></div>
                    <div class="menu-text">
                        <strong>Activity Log</strong>
                        <span>View all account creation, role, and status changes across the system</span>
                    </div>
                    <i class="fas fa-chevron-right menu-arrow"></i>
                </a>

                <a href="admin/orders.php" class="account-menu-item">
                    <div class="menu-icon"><i class="fas fa-tasks"></i></div>
                    <div class="menu-text">
                        <strong>Manage Orders</strong>
                        <span>Process incoming customer orders and update shipping statuses</span>
                    </div>
                    <i class="fas fa-chevron-right menu-arrow"></i>
                </a>

                <a href="admin/products.php" class="account-menu-item">
                    <div class="menu-icon"><i class="fas fa-boxes"></i></div>
                    <div class="menu-text">
                        <strong>Inventory & Products</strong>
                        <span>Add, update, or adjust stock levels and product details</span>
                    </div>
                    <i class="fas fa-chevron-right menu-arrow"></i>
                </a>

                <a href="<?php echo url('member/edit_profile.php'); ?>" class="account-menu-item">
                    <div class="menu-icon"><i class="fas fa-user-cog"></i></div>
                    <div class="menu-text">
                        <strong>Edit Profile & Security</strong>
                        <span>Update staff profile information, photo, and change password</span>
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