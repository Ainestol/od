<?php
require_once __DIR__ . '/../config/db_game.php';
header('Content-Type: application/json');

try {

    // server je online, protože DB odpověděla
    $serverOnline = true;

    $stmt = $pdoGameStatus->query("SELECT COUNT(*) FROM characters WHERE online = 1");
    $players = (int)$stmt->fetchColumn();

    echo json_encode([
        'online' => $serverOnline,
        'players' => $players
    ]);
} catch (Exception $e) {
    echo json_encode([
        'online' => false,
        'players' => 0
    ]);
}
