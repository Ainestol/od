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

k.kill_time,

s.respawn_delay,
s.respawn_random

FROM boss_list b

LEFT JOIN (
    SELECT boss_id, MAX(kill_time) AS kill_time
    FROM boss_kill_log
    GROUP BY boss_id
) k ON k.boss_id = b.boss_id

LEFT JOIN raidboss_spawnlist s ON s.boss_id = b.boss_id

WHERE b.type IN ('raid','grand')
AND (
    b.type='raid'
    OR b.boss_id IN (29001,29006,29014,29020,29022,29028,29045)
)

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