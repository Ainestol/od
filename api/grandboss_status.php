<?php

require_once __DIR__ . '/../config/db_game.php';

header('Content-Type: application/json; charset=utf-8');

try {

$sql = "
SELECT
g.boss_id,
n.name,
n.level,
g.respawn_time,
g.status
FROM grandboss_data g
LEFT JOIN npc n ON n.id = g.boss_id
ORDER BY n.level ASC
";

$stmt = $pdoGame->query($sql);

echo json_encode([
    "ok" => true,
    "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
]);

} catch(Exception $e){

echo json_encode([
    "ok" => false,
    "error" => $e->getMessage()
]);

}