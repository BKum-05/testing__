<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/_auth.php';
require_once __DIR__ . '/../app/_audit_lib.php';

require_role('admin');

$pdo = get_pdo();
$memberId = (int) get('id', 0);

if ($memberId <= 0) {
    redirect('member_list.php');
}

$member = fetch_user_by_id($pdo, $memberId);
if ($member === null) {
    temp('flash_notice', 'Member not found.');
    redirect('member_list.php');
}

$logs = fetch_audit_logs_for_user($pdo, $memberId);

$fullName = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')) ?: $member['email'];
$avatarUrl = get_avatar_url($member['avatar_url'] ?? null);
$isSelf = $memberId === (int) $_SESSION['user_id'];

$GLOBALS['role'] = $member['role'];
$GLOBALS['status'] = $member['status'];
$GLOBALS['member_id'] = (string) $memberId;

include_head("Member Detail - Online Shopping System");
?>

<div class="container account-dashboard">
    <a href="member_list.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Member List</a>
    <h2>Member Detail</h2>

    <div id="alertBox" class="alert-box" style="display:none;" role="status" aria-live="polite"></div>

    <div class="profile-card">
        <div class="profile-body">
            <div class="avatar-wrapper">
                <img src="<?php echo encode($avatarUrl); ?>" alt="Profile Photo" class="profile-avatar">
            </div>
            <div class="profile-main-info">
                <h3 class="profile-name"><?php echo encode($fullName); ?></h3>
                <p class="profile-email"><i class="fas fa-envelope"></i> <?php echo encode($member['email']); ?></p>
                <?php if (!empty($member['phone_number'])): ?>
                    <p class="profile-phone"><i class="fas fa-phone-alt"></i> <?php echo encode($member['phone_number']); ?></p>
                <?php endif; ?>
                <p class="field-hint">
                    Joined <?php echo encode(date('d M Y', strtotime($member['created_at']))); ?>
                    &middot; Last login <?php echo $member['last_login_at'] ? encode(date('d M Y H:i', strtotime($member['last_login_at']))) : 'never'; ?>
                </p>
            </div>
        </div>
    </div>

    <div class="member-controls">
        <form id="roleForm" class="inline-form" method="POST" action="process_member_role.php">
            <?php
            echo csrf_field();
            html_input('hidden', 'member_id');
            ?>
            <label for="role">Role</label>
            <?php
            html_select('role', [
                'member' => 'Member',
                'admin'  => 'Admin',
            ], null, $isSelf ? 'disabled title="You cannot change your own role"' : '');

            if (!$isSelf) {
                html_button('submit', 'Update Role', 'class="btn btn-outline"');
            }
            ?>
        </form>

        <form id="statusForm" class="inline-form" method="POST" action="process_member_status.php">
            <?php
            echo csrf_field();
            html_input('hidden', 'member_id');
            ?>
            <label for="status">Status</label>
            <?php
            html_select('status', [
                'pending'   => 'Pending',
                'active'    => 'Active',
                'suspended' => 'Suspended',
            ], null, $isSelf ? 'disabled title="You cannot change your own status"' : '');

            if (!$isSelf) {
                html_button('submit', 'Update Status', 'class="btn btn-outline"');
            }
            ?>
        </form>

        <?php if (!empty($member['password_hash'])): ?>
            <form id="adminResetPasswordForm" class="inline-form" method="POST" action="process_member_reset_password.php">
                <?php
                echo csrf_field();
                html_input('hidden', 'member_id');
                html_button('submit', 'Send Password Reset Email', 'class="btn btn-outline"');
                ?>
            </form>
        <?php else: ?>
            <span class="field-hint">This account signs in with Google only and has no password to reset.</span>
        <?php endif; ?>

        <?php if (!$isSelf): ?>
            <form id="deleteForm" class="inline-form" method="POST" action="process_member_delete.php">
                <?php
                echo csrf_field();
                html_input('hidden', 'member_id');
                html_button('submit', 'Delete Account', 'class="btn btn-danger"');
                ?>
            </form>
        <?php endif; ?>
    </div>

    <h3>Activity Log</h3>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">Action</th>
                    <th scope="col">Details</th>
                    <th scope="col">By</th>
                    <th scope="col">When</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="4" style="text-align:center;">No activity recorded yet.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo encode(ucwords(str_replace('_', ' ', $log['action']))); ?></td>
                        <td><?php echo encode($log['details'] ?? ''); ?></td>
                        <td><?php echo encode($log['actor_email'] ?? 'System'); ?></td>
                        <td><?php echo encode(date('d M Y H:i', strtotime($log['created_at']))); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_foot(); ?>