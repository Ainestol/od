<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../api/admin/_bootstrap.php';

try {

    assert_admin();

    $limit = min((int)($_GET['limit'] ?? 50), 200);

    $sql = "
        SELECT 
            created_at,
            action,
            user_id,
            target_id,
            status,
            meta
        FROM system_logs
        ORDER BY created_at DESC
        LIMIT $limit
    ";

    $stmt = $pdo->query($sql);

    echo json_encode([
        "ok" => true,
        "logs" => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        "ok" => false,
        "error" => $e->getMessage()
    ]);
}