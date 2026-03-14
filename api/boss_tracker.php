<?php

header('Content-Type: application/json');
require_once __DIR__.'/../config/db_game.php';

try{

$sql = "
SELECT
l.boss_id,
l.boss_name,
l.boss_type,
l.kill_time,
l.respawn_delay,
l.respawn_random,
b.level
FROM boss_list b
LEFT JOIN boss_kill_log l ON l.boss_id = b.boss_id
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