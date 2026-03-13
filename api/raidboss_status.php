<?php

header('Content-Type: application/json');

require_once __DIR__.'/../config/db_game.php';

try{

$sql = "
SELECT
r.id AS boss_id,
b.name,
b.level,
FLOOR(r.respawnTime/1000) AS respawn_time,
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

    $respawn = intval($b['respawn_time']);
    $delay   = intval($b['respawn_delay']);
    $random  = intval($b['respawn_random']);

    if($respawn > 0){

        // odhad kill time
        $kill = $respawn - $delay - $random;

        $b['kill_time'] = $kill;

    }else{

        $b['kill_time'] = 0;

    }

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