<?php

require_once __DIR__.'/../config/db_game.php';

try{

$sql = "
SELECT
g.boss_id,
b.name,
b.level,
FLOOR(g.respawn_time/1000) AS respawn_time,
r.respawn,
r.respawn_random
FROM grandboss_data g
LEFT JOIN boss_list b ON b.boss_id = g.boss_id
LEFT JOIN boss_respawn r ON r.boss_id = g.boss_id
WHERE g.boss_id IN (29001,29006,29014,29020,29022,29028,29045,29068)
ORDER BY b.level ASC
";

$stmt = $pdoGame->query($sql);

echo json_encode([
    "ok"=>true,
    "data"=>$stmt->fetchAll(PDO::FETCH_ASSOC)
]);

}catch(Exception $e){

echo json_encode([
    "ok"=>false,
    "error"=>$e->getMessage()
]);

}