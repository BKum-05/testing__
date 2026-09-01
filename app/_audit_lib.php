<?php
declare(strict_types=1);

if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

function log_activity(PDO $pdo, string $action, ?int $targetUserId = null, ?string $details = null): void
{
    $actorId = $_SESSION['user_id'] ?? null;

    $stmt = $pdo->prepare(
        'INSERT INTO audit_logs (actor_id, action, target_user_id, details)
         VALUES (:actor_id, :action, :target_user_id, :details)'
    );
    $stmt->execute([
        'actor_id'       => $actorId,
        'action'         => $action,
        'target_user_id' => $targetUserId,
        'details'        => $details,
    ]);
}

function fetch_audit_logs_for_user(PDO $pdo, int $userId, int $limit = 20): array
{
    $stmt = $pdo->prepare(
        'SELECT al.*, u.email AS actor_email
         FROM audit_logs al
         LEFT JOIN users u ON u.user_id = al.actor_id
         WHERE al.target_user_id = :user_id
         ORDER BY al.created_at DESC
         LIMIT :limit'
    );
    $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function fetch_all_audit_logs_query(): string
{
    return "SELECT al.id, al.action, al.details, al.created_at,
                   actor.email AS actor_email,
                   target.email AS target_email
            FROM audit_logs al
            LEFT JOIN users actor ON actor.user_id = al.actor_id
            LEFT JOIN users target ON target.user_id = al.target_user_id
            ORDER BY al.created_at DESC";
}