<?php

require_once __DIR__.'/../config/db_game.php';

try{

$sql = "
SELECT
g.boss_id,
b.name,
b.level,
g.respawn_time
FROM grandboss_data g
LEFT JOIN boss_list b ON b.boss_id = g.boss_id
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