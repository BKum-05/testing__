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

$newStatus = trim((string) ($_POST['status'] ?? ''));
if (!in_array($newStatus, VALID_STATUSES, true)) {
    json_response(['success' => false, 'message' => 'Invalid status.'], 422);
}

$memberIds = [];
if (!empty($_POST['member_ids']) && is_array($_POST['member_ids'])) {
    $memberIds = array_map('intval', $_POST['member_ids']);
} elseif (!empty($_POST['member_id'])) {
    $memberIds = [(int) $_POST['member_id']];
}

$currentUserId = (int) $_SESSION['user_id'];
$memberIds = array_values(array_unique(array_filter($memberIds, fn($id) => $id > 0 && $id !== $currentUserId)));

if (empty($memberIds)) {
    json_response(['success' => false, 'message' => 'No valid members selected.'], 422);
}

$pdo = get_pdo();
$updated = 0;

try {
    $pdo->beginTransaction();

    foreach ($memberIds as $id) {
        $target = fetch_user_by_id($pdo, $id);
        if ($target === null || $target['status'] === $newStatus) {
            continue;
        }

        $stmt = $pdo->prepare('UPDATE users SET status = :status, updated_at = NOW() WHERE user_id = :id');
        $stmt->execute(['status' => $newStatus, 'id' => $id]);

        log_activity($pdo, 'status_changed', $id, "status: {$target['status']} -> {$newStatus}");
        $updated++;
    }

    $pdo->commit();

    json_response(['success' => true, 'message' => "{$updated} member(s) updated to '{$newStatus}'."]);
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('Member Status Update Error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'An error occurred while updating status.'], 500);
}