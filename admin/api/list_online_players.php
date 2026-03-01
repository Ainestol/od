<?php
require_once __DIR__ . '/../../config/db_game.php';
header('Content-Type: application/json; charset=utf-8');
session_start();

/* ===== ADMIN CHECK ===== */
if (empty($_SESSION['web_user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

try {
    /* ===== STEJNÉ PŘIPOJENÍ JAKO status.php ===== */
 
    /* ===== DOTAZ NA ONLINE HRÁČE ===== */
    $stmt = $pdoGameStatus->query("
        SELECT
            c.char_name,
            c.level,
            c.account_name,
            c.onlineTime,
            IF(cv.val = '100', 1, 0) AS is_gm
        FROM characters c
        LEFT JOIN character_variables cv
            ON cv.charId = c.charId
           AND cv.var = 'accesslevel'
        WHERE c.online = 1
        ORDER BY c.level DESC
    ");

    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok' => true,
        'players' => $players
    ]);

} catch (Exception $e) {
    echo json_encode([
        'ok' => false,
        'players' => [],
        'error' => $e->getMessage()
    ]);
}
