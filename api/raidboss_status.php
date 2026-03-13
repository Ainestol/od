<?php

header('Content-Type: application/json');

require_once __DIR__.'/../config/db_game.php';

try{

$xmlFile = "/opt/l2/ClassicLude/game/data/spawns/RBspawn.xml";

$respawns = [];

/* -------------------------
   LOAD RESPAWN DATA FROM XML
------------------------- */

if(file_exists($xmlFile)){

    $xml = simplexml_load_file($xmlFile);

    foreach($xml->xpath('//npc') as $npc){

        $id = intval($npc['id']);

        $respawnTime = (string)$npc['respawnTime'];
        $respawnRandom = (string)$npc['respawnRandom'];

        $respawn = 0;
        $random = 0;

        /* parse respawnTime */

        if(preg_match('/(\d+)hour/',$respawnTime,$m)){
            $respawn = intval($m[1]) * 3600;
        }
        elseif(preg_match('/(\d+)min/',$respawnTime,$m)){
            $respawn = intval($m[1]) * 60;
        }

        /* parse respawnRandom */

        if(preg_match('/(\d+)hour/',$respawnRandom,$m)){
            $random = intval($m[1]) * 3600;
        }
        elseif(preg_match('/(\d+)min/',$respawnRandom,$m)){
            $random = intval($m[1]) * 60;
        }

        $respawns[$id] = [
            "respawn" => $respawn,
            "random" => $random
        ];

    }
}


/* -------------------------
   LOAD RAID BOSSES FROM DB
------------------------- */

$sql = "
SELECT
r.id AS boss_id,
b.name,
b.level,
FLOOR(r.respawnTime/1000) AS kill_time,
s.respawn_time,
s.respawn_random
FROM npc_respawns r
LEFT JOIN boss_list b ON b.boss_id = r.id
LEFT JOIN raidboss_spawnlist s ON s.boss_id = r.id
WHERE b.type='raid'
ORDER BY b.level ASC;

$stmt = $pdoGame->query($sql);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* -------------------------
   CALCULATE RESPAWN WINDOW
------------------------- */

foreach($data as &$b){

    $id = $b['boss_id'];
    $kill = intval($b['kill_time']);

    if($kill > time()){
        $kill = 0;
    }

    if(isset($respawns[$id])){

        $respawn = $respawns[$id]['respawn'];
        $random  = $respawns[$id]['random'];

    }else{

        /* fallback */

        $respawn = 36*3600;
        $random  = 24*3600;

    }

    if($kill > 0){

        $windowStart = $kill + $respawn;

        $b['respawn_time'] = $windowStart;
        $b['respawn_random'] = $random;

    }else{

        $b['respawn_time'] = 0;
        $b['respawn_random'] = 0;

    }

    unset($b['kill_time']);

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