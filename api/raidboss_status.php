<?php

header('Content-Type: application/json');

require_once __DIR__.'/../config/db_game.php';

try{

$sql = "
SELECT
r.id AS boss_id,
b.name,
b.level,
r.respawnTime AS respawn_time,
r.currentHp
FROM npc_respawns r
LEFT JOIN boss_list b ON b.boss_id = r.id
WHERE b.type = 'raid'
ORDER BY b.level ASC
";

$stmt = $pdoGame->query($sql);

$data = $stmt->fetchAll();

echo json_encode([
    "ok" => true,
    "data" => $data
]);

}catch(Exception $e){

echo json_encode([
    "ok" => false,
    "error" => $e->getMessage()
]);

}