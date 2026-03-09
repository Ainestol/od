<?php

require_once __DIR__ . '/../config/db_game.php';

header("Content-Type: image/png");

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    http_response_code(404);
    exit;
}

try {

    $stmt = $pdoGame->prepare("
        SELECT data
        FROM crest
        WHERE crest_id = ?
        LIMIT 1
    ");

    $stmt->execute([$id]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['data'])) {
        http_response_code(404);
        exit;
    }

    echo $row['data'];

} catch (Exception $e) {

    http_response_code(500);

}