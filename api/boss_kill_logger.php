<?php

require_once __DIR__.'/../config/db_game.php';

try{

/* RAID BOSSES */

$sql = "
SELECT
r.id AS boss_id,
b.name,
FLOOR(r.respawnTime/1000) AS kill_time,
s.respawn_delay,
s.respawn_random
FROM npc_respawns r
LEFT JOIN boss_list b ON b.boss_id = r.id
LEFT JOIN raidboss_spawnlist s ON s.boss_id = r.id
WHERE b.type='raid'
AND r.respawnTime > 0
";

$stmt = $pdoGame->query($sql);
$raids = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($raids as $b){

$boss_id = intval($b['boss_id']);
$kill_time = intval($b['kill_time']);

/* kontrola jestli už kill existuje */

$check = $pdoGame->prepare("
SELECT id FROM boss_kill_log
WHERE boss_id = ?
AND kill_time = ?
LIMIT 1
");

$check->execute([$boss_id,$kill_time]);

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
$b['respawn_delay'],
$b['respawn_random']
]);

}

}

/* GRAND BOSSES */

$sql = "
SELECT
g.boss_id,
b.name,
FLOOR(g.respawn_time/1000) AS kill_time,
r.respawn,
r.respawn_random
FROM grandboss_data g
LEFT JOIN boss_list b ON b.boss_id = g.boss_id
LEFT JOIN boss_respawn r ON r.boss_id = g.boss_id
WHERE g.respawn_time > 0
";

$stmt = $pdoGame->query($sql);
$epics = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($epics as $b){

$boss_id = intval($b['boss_id']);
$kill_time = intval($b['kill_time']);

$check = $pdoGame->prepare("
SELECT id FROM boss_kill_log
WHERE boss_id = ?
AND kill_time = ?
LIMIT 1
");

$check->execute([$boss_id,$kill_time]);

if(!$check->fetch()){

$insert = $pdoGame->prepare("
INSERT INTO boss_kill_log
(boss_id,boss_name,boss_type,kill_time,respawn_delay,respawn_random)
VALUES (?,?,?,?,?,?)
");

$insert->execute([
$boss_id,
$b['name'],
'GRAND',
$kill_time,
$b['respawn'] ?? 0,
$b['respawn_random'] ?? 0
]);

}

}

echo "OK";

}catch(Exception $e){

echo $e->getMessage();

}