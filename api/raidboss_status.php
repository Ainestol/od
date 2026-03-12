<?php

require_once __DIR__ . '/../config/db_game.php';

header('Content-Type: application/json; charset=utf-8');

try {

$sql = "
SELECT
boss_id,
respawn_time,
respawn_random
FROM raidboss_spawnlist
ORDER BY boss_id ASC
";

$stmt = $pdoGame->query($sql);

echo json_encode([
    "ok" => true,
    "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
]);

} catch(Exception $e){

echo json_encode([
    "ok" => false,
    "error" => $e->getMessage()
]);

}