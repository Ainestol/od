<?php

header('Content-Type: application/json');

require_once __DIR__.'/../config/db_game.php';

try{

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


/* -----------------------------
   DEFAULT RAID BOSS RESPAWN
   ----------------------------- */

$respawn = 36 * 3600;   // 36h
$random  = 24 * 3600;   // 24h


foreach($data as &$b){

    $kill = intval($b['kill_time']);

    /* ochrana proti špatnému času */
    if($kill > time()){
        $kill = 0;
    }

    if($kill > 0){

        $window_start = $kill + $respawn;
        $window_end   = $window_start + $random;

        $b['respawn_time']   = $window_start;
        $b['respawn_random'] = $random;

    }else{

        /* boss alive */

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