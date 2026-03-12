<?php

require_once __DIR__.'/../config/db_game.php';

try{

$sql = "
SELECT
r.boss_id,
b.name,
b.level,
r.respawn_time,
r.respawn_random
FROM raidboss_spawnlist r
LEFT JOIN boss_list b ON b.boss_id = r.boss_id
ORDER BY b.level ASC
";

$stmt = $pdoGame->query($sql);

echo json_encode([
    "ok"=>true,
    "data"=>$stmt->fetchAll()
]);

}catch(Exception $e){

echo json_encode([
    "ok"=>false,
    "error"=>$e->getMessage()
]);

}