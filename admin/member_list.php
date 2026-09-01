<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/_auth.php';
require_once __DIR__ . '/../app/_member_query.php';
require_once __DIR__ . '/../app/lib/SimplePager.php';

require_role('admin');

global $_db;
$_db = get_pdo();

// -----------------------------------------------------------------
// MARK: Sorting
// -----------------------------------------------------------------
$fields = [
    'name'   => 'Name',
    'email'  => 'Email',
    'joined' => 'Joined',
    'login'  => 'Last Login',
];

$sort = req('sort');
key_exists($sort, $fields) || $sort = 'joined';

$dir = req('dir');
in_array($dir, ['asc', 'desc']) || $dir = 'desc';

// -----------------------------------------------------------------
// MARK: Filters
// -----------------------------------------------------------------
$filters = get_member_filters();
$params = [];
$whereClause = build_member_where($filters, $params);
$sortColumn = MEMBER_SORT_COLUMNS[$sort];

// -----------------------------------------------------------------
// MARK: Paging
// -----------------------------------------------------------------
$page = req('page', 1);

$query = "SELECT u.user_id AS id, u.email, u.role, u.status,
                 DATE_FORMAT(u.created_at, '%d %b %Y') AS joined_display,
                 DATE_FORMAT(u.last_login_at, '%d %b %Y %H:%i') AS login_display,
                 u.first_name, u.last_name
          FROM users u
          WHERE {$whereClause}
          ORDER BY {$sortColumn} {$dir}";

$p = new SimplePager($query, $params, 15, $page);
$members = $p->result;

$extraQuery = http_build_query(array_filter([
    'search' => $filters['search'],
    'role'   => $filters['role'],
    'status' => $filters['status'],
]));


$pagerHref = http_build_query(array_filter([
    'sort'   => $sort,
    'dir'    => $dir,
    'search' => $filters['search'],
    'role'   => $filters['role'],
    'status' => $filters['status'],
]));

include_head("Member Maintenance - Online Shopping System");
?>

<div class="admin-page">
    <a href="staff_profile.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    <h2>Member Maintenance</h2>
    <p class="subtitle">
        <?= $p->count ?> of <?= $p->item_count ?> record(s) |
        Page <?= $p->page ?> of <?= $p->page_count ?>
    </p>

    <?php if ($msg = temp('flash_success')): ?>
        <div class="alert-box alert-success" role="status" aria-live="polite"><?php echo encode($msg); ?></div>
    <?php endif; ?>

    <div id="alertBox" class="alert-box" style="display:none;" role="status" aria-live="polite"></div>

    <div class="member-toolbar">
        <form method="GET" action="member_list.php" class="member-filter-form" id="memberFilterForm">
            <?php
            $GLOBALS['sort']   = $sort;
            $GLOBALS['dir']    = $dir;
            $GLOBALS['search'] = $filters['search'];
            $GLOBALS['role']   = $filters['role'];
            $GLOBALS['status'] = $filters['status'];

            html_input('hidden', 'sort');
            html_input('hidden', 'dir');
            ?>

            <div class="form-group">
                <?php html_text('search', 'placeholder="Search name or email" aria-label="Search name or email"'); ?>
            </div>

            <div class="form-group">
                <?php html_select('role', [
                    ''       => 'All Roles',
                    'member' => 'Member',
                    'admin'  => 'Admin',
                ], null, 'aria-label="Filter by role"'); ?>
            </div>

            <div class="form-group">
                <?php html_select('status', [
                    ''          => 'All Statuses',
                    'active'    => 'Active',
                    'pending'   => 'Pending',
                    'suspended' => 'Suspended',
                ], null, 'aria-label="Filter by status"'); ?>
            </div>

            <?php html_button('submit', 'Filter', 'class="btn btn-primary"'); ?>
            <a href="member_list.php" class="btn btn-outline">Reset</a>
        </form>

        <div class="member-actions">
            <a href="member_create.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> Add Member</a>
            <a href="member_export.php?<?php echo http_build_query(array_merge($filters, ['sort' => $sort, 'dir' => $dir])); ?>" class="btn btn-outline"><i class="fas fa-file-csv"></i> Export CSV</a>
        </div>
    </div>

    <div class="bulk-actions-bar" id="bulkActionsBar" style="display:none;">
        <?php
        echo csrf_field();
        html_radios('bulk_action', [
            'activate' => 'Set Active',
            'suspend'  => 'Suspend',
            'delete'   => 'Delete',
        ]);
        html_button('button', 'Apply', 'id="applyBulkAction" class="btn btn-outline"');
        ?>
        <span id="bulkSelectedCount" class="field-hint">0 selected</span>
    </div>

    <div class="table-sort">
        <table class="data-table">
            <tr>
                <th scope="col"><input type="checkbox" id="selectAllMembers" aria-label="Select all members"></th>
                <?= table_headers($fields, $sort, $dir, $extraQuery) ?>
                <th scope="col">Role</th>
                <th scope="col">Status</th>
                <th scope="col">Actions</th>
            </tr>

            <?php if (empty($members)): ?>
                <tr>
                    <td colspan="7" style="text-align:center;">No members found.</td>
                </tr>
            <?php endif; ?>

            <?php foreach ($members as $member): ?>
                <?php
                $fullName = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')) ?: '(No Profile)';
                $isSelf = (int) $member['id'] === (int) $_SESSION['user_id'];
                ?>
                <tr>
                    <td>
                        <input type="checkbox" class="member-checkbox" value="<?php echo (int) $member['id']; ?>" aria-label="Select <?php echo encode($fullName); ?>" <?php echo $isSelf ? 'disabled title="You cannot bulk-act on your own account"' : ''; ?>>
                    </td>
                    <td><a href="member_detail.php?id=<?php echo (int) $member['id']; ?>"><?php echo encode($fullName); ?></a></td>
                    <td><?php echo encode($member['email']); ?></td>
                    <td><?php echo encode($member['joined_display']); ?></td>
                    <td><?php echo $member['login_display'] ? encode($member['login_display']) : '—'; ?></td>
                    <td><span class="badge badge-<?php echo encode($member['role']); ?>"><?php echo encode(ucfirst($member['role'])); ?></span></td>
                    <td><span class="badge badge-<?php echo encode($member['status']); ?>"><?php echo encode(ucfirst($member['status'])); ?></span></td>
                    <td><a href="member_detail.php?id=<?php echo (int) $member['id']; ?>" class="btn btn-sm btn-outline">Manage</a></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <?php $p->html($pagerHref, "role='navigation' aria-label='Pagination Navigation'"); ?>
</div>

<?php include_foot(); ?>