<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/_auth.php';
require_once __DIR__ . '/../app/_member_query.php';

require_role('admin');

$pdo = get_pdo();
$filters = get_member_filters();

$sort = req('sort');
key_exists($sort, MEMBER_SORT_COLUMNS) || $sort = 'joined';
$dir = req('dir');
in_array($dir, ['asc', 'desc']) || $dir = 'desc';

$members = fetch_all_members_for_export($pdo, $filters, $sort, $dir);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="members_export_' . date('Y-m-d_His') . '.csv"');

$output = fopen('php://output', 'w');

// UTF-8 BOM: without this, Excel misreads a CSV whose first cell is
// literally "ID" as a legacy SYLK file (hence the "SYLK file" / "format
// and extension don't match" dialogs) — the BOM bytes precede "ID" in the
// file, which defeats that false-positive sniff. It also guarantees any
// non-ASCII characters in names render correctly in Excel.
fwrite($output, "\xEF\xBB\xBF");

fputcsv($output, ['ID', 'First Name', 'Last Name', 'Email', 'Role', 'Status', 'Phone', 'Joined', 'Last Login']);

foreach ($members as $member) {
    fputcsv($output, [
        csv_safe((string) $member['id']),
        csv_safe((string) ($member['first_name'] ?? '')),
        csv_safe((string) ($member['last_name'] ?? '')),
        csv_safe((string) $member['email']),
        csv_safe((string) $member['role']),
        csv_safe((string) $member['status']),
        csv_safe((string) ($member['phone_number'] ?? '')),
        csv_safe((string) $member['created_at']),
        csv_safe((string) ($member['last_login_at'] ?? '')),
    ]);
}

fclose($output);
exit;