<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/_auth.php';
require_once __DIR__ . '/../app/_audit_lib.php';
require_once __DIR__ . '/../app/lib/SimplePager.php';

require_role('admin');

global $_db;
$_db = get_pdo();

$page = req('page', 1);

$p = new SimplePager(fetch_all_audit_logs_query(), [], 20, $page);
$logs = $p->result;

include_head("Activity Log - Online Shopping System");
?>

<div class="admin-page">
    <a href="staff_profile.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    <h2>System Activity Log</h2>
    <p class="subtitle">
        <?= $p->count ?> of <?= $p->item_count ?> record(s) |
        Page <?= $p->page ?> of <?= $p->page_count ?>
    </p>

    <div class="table-responsive">
        <table class="data-table">
            <tr>
                <th scope="col">Action</th>
                <th scope="col">Target</th>
                <th scope="col">Details</th>
                <th scope="col">By</th>
                <th scope="col">When</th>
            </tr>

            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="5" style="text-align:center;">No activity recorded yet.</td>
                </tr>
            <?php endif; ?>

            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo encode(ucwords(str_replace('_', ' ', $log['action']))); ?></td>
                    <td>
                        <?php if ($log['target_email']): ?>
                            <?php echo encode($log['target_email']); ?>
                        <?php else: ?>
                            <span class="field-hint">(account no longer exists)</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo encode($log['details'] ?? ''); ?></td>
                    <td><?php echo encode($log['actor_email'] ?? 'System'); ?></td>
                    <td><?php echo encode(date('d M Y H:i', strtotime($log['created_at']))); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <?php $p->html('', "role='navigation' aria-label='Pagination Navigation'"); ?>
</div>

<?php include_foot(); ?>