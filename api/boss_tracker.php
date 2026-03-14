<?php

header('Content-Type: application/json');
require_once __DIR__.'/../config/db_game.php';

try{

$sql = "
SELECT
b.boss_id,
b.name AS boss_name,
b.type AS boss_type,
b.level,

FLOOR(r.respawnTime/1000) - s.respawn_delay - s.respawn_random AS kill_time,

s.respawn_delay,
s.respawn_random

FROM boss_list b

LEFT JOIN npc_respawns r ON r.id = b.boss_id
LEFT JOIN raidboss_spawnlist s ON s.boss_id = b.boss_id

WHERE b.type IN ('raid','grand')

ORDER BY b.level ASC
";

$stmt = $pdoGame->query($sql);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

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