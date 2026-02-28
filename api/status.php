<?php
header('Content-Type: application/json');

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=l2game;charset=utf8",
        "webuser",
        "@Heslojeheslo20",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // server je online, protože DB odpověděla
    $serverOnline = true;

    $stmt = $pdo->query("SELECT COUNT(*) FROM characters WHERE online = 1");
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
