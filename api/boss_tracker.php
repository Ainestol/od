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
l.kill_time,
l.respawn_delay,
l.respawn_random
FROM boss_list b
LEFT JOIN (
    SELECT boss_id, MAX(kill_time) AS kill_time,
           respawn_delay, respawn_random
    FROM boss_kill_log
    GROUP BY boss_id
) l ON l.boss_id = b.boss_id
WHERE b.type IN ('raid','grand')
ORDER BY b.level ASC
";

$stmt = $pdoGame->query($sql);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

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