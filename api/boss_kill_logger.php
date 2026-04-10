<?php

require_once __DIR__.'/../config/db_game.php';

try{

/*
=====================================
RAID BOSSES
=====================================
*/

$sql = "
SELECT
r.id AS boss_id,
b.name,
UNIX_TIMESTAMP() AS kill_time,
b.respawn_delay,
b.respawn_random
FROM npc_respawns r
LEFT JOIN boss_list b ON b.boss_id = r.id
WHERE b.type='raid'
AND r.respawnTime > (UNIX_TIMESTAMP() * 1000)
";

$stmt = $pdoGame->query($sql);
$raids = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($raids as $b){

$boss_id = intval($b['boss_id']);
$kill_time = intval($b['kill_time']);

if(!$boss_id || !$kill_time) continue;

$check = $pdoGame->prepare("
SELECT id
FROM boss_kill_log
WHERE boss_id = ?
AND kill_time > (UNIX_TIMESTAMP() - 259200)
LIMIT 1
");
$check->execute([$boss_id]);

if(!$check->fetch()){

$insert = $pdoGame->prepare("
INSERT INTO boss_kill_log
(boss_id,boss_name,boss_type,kill_time,respawn_delay,respawn_random)
VALUES (?,?,?,?,?,?)
");

$insert->execute([
$boss_id,
$b['name'],
'RAID',
$kill_time,
intval($b['respawn_delay']),
intval($b['respawn_random'])
]);

$reset = $pdoGame->prepare("
UPDATE boss_spawn_log
SET spawn_time = 0
WHERE boss_id = ?
");
$reset->execute([$boss_id]);

}
}




echo "OK";

}catch(Exception $e){
echo $e->getMessage();
}