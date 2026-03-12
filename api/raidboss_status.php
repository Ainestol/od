<?php

require_once __DIR__ . '/../config/db_game.php';

header('Content-Type: application/json; charset=utf-8');

try {

$sql = "
SELECT
r.boss_id,
n.name,
n.level,
r.respawn_time

FROM raidboss_spawnlist r

LEFT JOIN npc n
ON n.id = r.boss_id

ORDER BY n.level ASC, n.name ASC
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