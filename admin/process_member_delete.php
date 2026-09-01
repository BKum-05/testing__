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
$deleted = 0;
$skipped = [];
$avatarsToRemove = [];

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $stmt->execute();
    $totalAdmins = (int) $stmt->fetchColumn();

    foreach ($memberIds as $id) {
        $target = fetch_user_by_id($pdo, $id);
        if ($target === null) {
            continue;
        }

        if ($target['role'] === 'admin') {
            if ($totalAdmins <= 1) {
                $skipped[] = $target['email'];
                continue;
            }
            $totalAdmins--;
        }

        // Logged before delete; target_user_id FK is ON DELETE SET NULL, and the
        // email is preserved in `details` so the log entry stays meaningful.
        log_activity($pdo, 'member_deleted', $id, "deleted account: {$target['email']}");

        $pdo->prepare('DELETE FROM users WHERE user_id = :id')->execute(['id' => $id]);
        $deleted++;

        if (!empty($target['avatar_url']) && !str_ends_with($target['avatar_url'], 'default.svg')) {
            $avatarsToRemove[] = $target['avatar_url'];
        }
    }

    $pdo->commit();

    // Remove avatar files only after the delete has actually committed —
    // otherwise a rolled-back transaction would leave the DB row intact
    // but its photo already gone from disk.
    foreach ($avatarsToRemove as $avatarUrl) {
        $avatarFile = BASE_PATH . '/' . ltrim($avatarUrl, '/');
        if (is_file($avatarFile)) {
            @unlink($avatarFile);
        }
    }

    $message = "{$deleted} member(s) deleted.";
    if (!empty($skipped)) {
        $message .= ' Skipped (last admin): ' . implode(', ', $skipped) . '.';
    }

    json_response(['success' => true, 'message' => $message]);
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('Member Delete Error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'An error occurred while deleting members.'], 500);
}