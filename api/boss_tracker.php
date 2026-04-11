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

COALESCE(k.kill_time,0) AS kill_time,
COALESCE(s.spawn_time,0) AS spawn_time,

b.respawn_delay,
b.respawn_random,

g.status AS grand_status,
g.respawn_time AS grand_respawn_time

FROM boss_list b

/* poslední kill */
LEFT JOIN (
    SELECT boss_id, MAX(kill_time) AS kill_time
    FROM boss_kill_log
    GROUP BY boss_id
) k
ON k.boss_id = b.boss_id

/* poslední spawn */
LEFT JOIN (
    SELECT boss_id, MAX(spawn_time) AS spawn_time
    FROM boss_spawn_log
    GROUP BY boss_id
) s
ON s.boss_id = b.boss_id

LEFT JOIN grandboss_data g
ON g.boss_id = b.boss_id

WHERE
b.type='raid'
OR b.boss_id IN (29001,29006,29014,29022,29068,29020,29028,29045)


ORDER BY b.level ASC
";

$stmt = $pdoGame->query($sql);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($data as &$b){

$b['kill_time'] = intval($b['kill_time'] ?? 0);
$b['spawn_time'] = intval($b['spawn_time'] ?? 0);
$b['respawn_delay'] = intval($b['respawn_delay'] ?? 0);
$b['respawn_random'] = intval($b['respawn_random'] ?? 0);
$b['grand_status'] = intval($b['grand_status'] ?? 0);
$b['grand_respawn_time'] = intval(($b['grand_respawn_time'] ?? 0) / 1000);
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