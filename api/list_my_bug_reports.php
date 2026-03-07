<?php
ini_set('display_errors',1);
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'].'/api/admin/_bootstrap.php';

try {
    assert_admin();
} catch (Throwable $e) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'FORBIDDEN']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {

    $st = $pdo->query("
        SELECT
            br.id,
            u.email,
            br.game_account,
            br.category,
            br.title,
            br.status,
            br.created_at
        FROM bug_reports br
        LEFT JOIN users u ON br.web_user_id = u.id
        ORDER BY br.created_at DESC
    ");

    echo json_encode([
        'ok' => true,
        'tickets' => $st->fetchAll(PDO::FETCH_ASSOC)
    ]);

} catch (Throwable $e) {

    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);

}