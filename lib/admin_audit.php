<?php

function admin_audit(PDO $pdo, string $action, ?int $targetUserId = null, array $meta = []): void
{
    if (empty($_SESSION['web_user_id'])) {
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO audit_log
        (actor_user_id, action, target_user_id, meta, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $_SESSION['web_user_id'],
        $action,
        $targetUserId,
        json_encode($meta, JSON_UNESCAPED_UNICODE)
    ]);
}