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
s.spawn_time,

COALESCE(k.respawn_delay, rb.respawn_delay, gb.respawn) AS respawn_delay,
COALESCE(k.respawn_random, rb.respawn_random, gb.respawn_random) AS respawn_random
FROM boss_list b

/* poslední kill */
LEFT JOIN (
    SELECT boss_id, kill_time, respawn_delay, respawn_random
    FROM boss_kill_log
    ORDER BY kill_time DESC
) k
ON k.boss_id = b.boss_id

/* poslední spawn */
LEFT JOIN boss_spawn_log s
ON s.boss_id = b.boss_id

LEFT JOIN raidboss_spawnlist rb
ON rb.boss_id = b.boss_id

LEFT JOIN boss_respawn gb
ON gb.boss_id = b.boss_id

WHERE
b.type='raid'
OR b.boss_id IN (29001,29006,29014,29019,29020,29028,29045)

ORDER BY b.level ASC
";

$stmt = $pdoGame->query($sql);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($data as &$b){

$b['kill_time'] = intval($b['kill_time'] ?? 0);
$b['spawn_time'] = intval($b['spawn_time'] ?? 0);
$b['respawn_delay'] = intval($b['respawn_delay'] ?? 0);
$b['respawn_random'] = intval($b['respawn_random'] ?? 0);

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