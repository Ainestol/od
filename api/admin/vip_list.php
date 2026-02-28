<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_bootstrap.php';

try {
    assert_admin();

    $showExpired = !empty($_GET['showExpired']);

    if ($showExpired) {
        // všechno (aktivní + expirované)
        $stmt = $pdoWeb->query("
            SELECT id, scope, target_id, level_id, end_at
            FROM vip_grants
            ORDER BY end_at DESC
            LIMIT 200
        ");
    } else {
        // jen aktivní
        $stmt = $pdoWeb->query("
            SELECT id, scope, target_id, level_id, end_at
            FROM vip_grants
            WHERE end_at > NOW()
            ORDER BY end_at DESC
            LIMIT 200
        ");
    }

    echo json_encode([
        'ok' => true,
        'data' => $stmt->fetchAll()
    ]);

} catch (Throwable $e) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
