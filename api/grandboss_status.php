<?php

header('Content-Type: application/json');

require_once __DIR__.'/../config/db_game.php';

try{

$sql = "
SELECT
g.boss_id,
b.name,
b.level,
MAX(g.respawn_time) AS respawn_time
FROM grandboss_data g
LEFT JOIN boss_list b ON b.boss_id = g.boss_id
WHERE g.boss_id IN (
29001, -- Queen Ant
29006, -- Core
29014, -- Orfen
29022, -- Zaken
29020, -- Baium
29019, -- Antharas
29028, -- Valakas
29045  -- Frintezza
)
GROUP BY g.boss_id
ORDER BY b.level ASC
";

$stmt = $pdoGame->query($sql);

$data = $stmt->fetchAll();

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