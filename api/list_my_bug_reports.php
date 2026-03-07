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

if (empty($_SESSION['web_user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'NOT_LOGGED']);
    exit;
}

try {

    $st = $pdo->query("
        SELECT
            id,
            web_user_id,
            game_account,
            category,
            title,
            status,
            created_at
        FROM bug_reports
        ORDER BY created_at DESC
    ");

    echo json_encode([
        'ok' => true,
        'tickets' => $st->fetchAll(PDO::FETCH_ASSOC)
    ]);

} catch (Throwable $e) {

    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);

}