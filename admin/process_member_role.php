<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/_auth.php';
require_once __DIR__ . '/../app/_audit_lib.php';

require_role('admin');
require_post();

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (!verify_csrf($csrfToken)) {
    json_response(['success' => false, 'message' => 'Invalid or expired session.'], 403);
}

$memberId = (int) ($_POST['member_id'] ?? 0);
$newRole = trim((string) ($_POST['role'] ?? ''));

if (!in_array($newRole, VALID_ROLES, true)) {
    json_response(['success' => false, 'message' => 'Invalid role.'], 422);
}

if ($memberId === (int) $_SESSION['user_id']) {
    json_response(['success' => false, 'message' => 'You cannot change your own role.'], 403);
}

$pdo = get_pdo();
$target = fetch_user_by_id($pdo, $memberId);
if ($target === null) {
    json_response(['success' => false, 'message' => 'Member not found.'], 404);
}

$oldRole = $target['role'];
if ($oldRole === $newRole) {
    json_response(['success' => true, 'message' => 'No changes made.']);
}

if ($oldRole === 'admin' && $newRole === 'member') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status != 'suspended'");
    $stmt->execute();
    if ((int) $stmt->fetchColumn() <= 1) {
        json_response(['success' => false, 'message' => 'Cannot remove the last active admin account.'], 422);
    }
}

$stmt = $pdo->prepare('UPDATE users SET role = :role, updated_at = NOW() WHERE user_id = :id');
$stmt->execute(['role' => $newRole, 'id' => $memberId]);

log_activity($pdo, 'role_changed', $memberId, "role: {$oldRole} -> {$newRole}");

json_response(['success' => true, 'message' => 'Role updated successfully.']);