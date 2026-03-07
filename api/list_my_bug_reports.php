<?php

ini_set('display_errors',1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'].'/config/db.php';

try {

    $sql = "
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
    ";

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "ok" => true,
        "tickets" => $rows
    ]);

} catch (Throwable $e) {

    echo json_encode([
        "ok" => false,
        "error" => $e->getMessage()
    ]);

}