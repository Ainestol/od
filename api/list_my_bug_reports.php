<?php
ini_set('display_errors',1);
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'].'/config/db.php';

header('Content-Type: application/json');

try {

    $st = $pdo->query("
        SELECT id, web_user_id, game_account, category, title, status, created_at
        FROM bug_reports
        ORDER BY created_at DESC
    ");

    echo json_encode([
        "ok"=>true,
        "rows"=>$st->fetchAll(PDO::FETCH_ASSOC)
    ]);

} catch(Throwable $e) {

    echo json_encode([
        "ok"=>false,
        "error"=>$e->getMessage()
    ]);

}