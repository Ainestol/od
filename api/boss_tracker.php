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

s.spawn_time,

COALESCE(rb.respawn_delay, gb.respawn) AS respawn_delay,
COALESCE(rb.respawn_random, gb.respawn_random) AS respawn_random

FROM boss_list b

LEFT JOIN boss_spawn_log s
ON s.boss_id = b.boss_id

LEFT JOIN raidboss_spawnlist rb
ON rb.boss_id = b.boss_id

LEFT JOIN boss_respawn gb
ON gb.boss_id = b.boss_id

WHERE
b.type='raid'
OR b.boss_id IN (29001,29006,29014,29020,29019,29045)

ORDER BY b.level ASC
";

$stmt = $pdoGame->query($sql);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($data as &$b){

$spawn = intval($b['spawn_time'] ?? 0);
$delay = intval($b['respawn_delay'] ?? 0);
$random = intval($b['respawn_random'] ?? 0);

if($spawn > 0){

$kill_time = $spawn - $delay;
$b['kill_time'] = $kill_time;

}else{

$b['kill_time'] = 0;

}

$b['spawn_time'] = $spawn;

}

echo json_encode([
"ok"=>true,
"data"=>$data
]);

}catch(Exception $e){

echo json_encode([
"ok"=>false,
"error"=>$e->getMessage()
]);

}