<?php

function system_log(PDO $pdo, string $type, string $action, ?int $userId, ?int $targetId, string $status, array $meta = []) {

    $stmt = $pdo->prepare("
        INSERT INTO system_logs
        (log_type, action, user_id, target_id, status, ip_address, meta)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $type,
        $action,
        $userId,
        $targetId,
        $status,
        $_SERVER['REMOTE_ADDR'] ?? null,
        json_encode($meta, JSON_UNESCAPED_UNICODE)
    ]);
}