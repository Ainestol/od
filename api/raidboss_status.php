<?php

header('Content-Type: application/json');

require_once __DIR__.'/../config/db_game.php';

try{

/* --------------------------------
   LOAD RESPAWN DATA FROM XML
-------------------------------- */

$xmlFile = "/opt/l2/ClassicLude/game/data/spawns/RBspawn.xml";

$respawns = [];

if(file_exists($xmlFile)){

    $xml = simplexml_load_file($xmlFile);

    foreach($xml->spawn as $spawn){

        $npc = $spawn->npc;

        $id = intval($npc['id']);

        $respawnTime = (string)$npc['respawnTime'];
        $respawnRandom = (string)$npc['respawnRandom'];

        /* convert respawnTime */

        if(str_contains($respawnTime,'hour')){
            $respawn = intval($respawnTime) * 3600;
        }elseif(str_contains($respawnTime,'min')){
            $respawn = intval($respawnTime) * 60;
        }else{
            $respawn = intval($respawnTime);
        }

        /* convert random */

        if(str_contains($respawnRandom,'hour')){
            $random = intval($respawnRandom) * 3600;
        }elseif(str_contains($respawnRandom,'min')){
            $random = intval($respawnRandom) * 60;
        }else{
            $random = intval($respawnRandom);
        }

        $respawns[$id] = [
            "respawn" => $respawn,
            "random" => $random
        ];
    }

}


/* --------------------------------
   LOAD RAID BOSS DATA
-------------------------------- */

$sql = "
SELECT
r.id AS boss_id,
b.name,
b.level,
FLOOR(r.respawnTime / 1000) AS kill_time
FROM npc_respawns r
LEFT JOIN boss_list b ON b.boss_id = r.id
WHERE b.type = 'raid'
ORDER BY b.level ASC
";

$stmt = $pdoGame->query($sql);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* --------------------------------
   CALCULATE RESPAWN WINDOW
-------------------------------- */

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

        $b['respawn_time']   = $windowStart;
        $b['respawn_random'] = $random;

    }else{

        $b['respawn_time']   = 0;
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