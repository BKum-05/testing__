<?php

declare(strict_types=1);

if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

const MEMBER_SORT_COLUMNS = [
    'name'   => "CONCAT(u.first_name, ' ', u.last_name)",
    'email'  => 'u.email',
    'joined' => 'u.created_at',
    'login'  => 'u.last_login_at',
];

function get_member_filters(): array
{
    return [
        'search' => trim((string) req('search', '')),
        'role'   => trim((string) req('role', '')),
        'status' => trim((string) req('status', '')),
    ];
}

function build_member_where(array $filters, array &$params): string
{
    $where = ['1=1'];

    if ($filters['search'] !== '') {
        $where[] = '(u.email LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)';
        $params['search'] = '%' . $filters['search'] . '%';
    }

    if ($filters['role'] !== '' && in_array($filters['role'], VALID_ROLES, true)) {
        $where[] = 'u.role = :role';
        $params['role'] = $filters['role'];
    }

    if ($filters['status'] !== '' && in_array($filters['status'], VALID_STATUSES, true)) {
        $where[] = 'u.status = :status';
        $params['status'] = $filters['status'];
    }

    return implode(' AND ', $where);
}

function fetch_all_members_for_export(PDO $pdo, array $filters, string $sort, string $dir): array
{
    $params = [];
    $whereClause = build_member_where($filters, $params);
    $sortColumn = MEMBER_SORT_COLUMNS[$sort] ?? MEMBER_SORT_COLUMNS['joined'];

    $sql = "SELECT u.user_id AS id, u.email, u.role, u.status, u.created_at, u.last_login_at,
                   u.first_name, u.last_name, u.phone_number
            FROM users u
            WHERE {$whereClause}
            ORDER BY {$sortColumn} {$dir}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}
