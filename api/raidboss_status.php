<?php

header('Content-Type: application/json');

require_once __DIR__.'/../config/db_game.php';

try{

$sql = "
SELECT
r.id AS boss_id,
b.name,
b.level,
FLOOR(r.respawnTime/1000) AS kill_time,
s.respawn_delay,
s.respawn_random
FROM npc_respawns r
LEFT JOIN boss_list b ON b.boss_id = r.id
LEFT JOIN raidboss_spawnlist s ON s.boss_id = r.id
WHERE b.type='raid'
ORDER BY b.level ASC
";

$stmt = $pdoGame->query($sql);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($data as &$b){

    $respawnTime = intval($b['kill_time']);
    $random = intval($b['respawn_random']);

    if($respawnTime > 0){

        $b['respawn_time'] = $respawnTime;
        $b['respawn_random'] = $random;

    }else{

        $b['respawn_time'] = 0;
        $b['respawn_random'] = 0;

    }

    unset($b['kill_time']);
    unset($b['respawn_delay']);

}

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