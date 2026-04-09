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

if(!$boss_id || !$kill_time) continue;

/* kontrola duplicity */

$check = $pdoGame->prepare("
SELECT id
FROM boss_kill_log
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
'raid',
$kill_time,
intval($b['respawn_delay']),
intval($b['respawn_random'])
]);

/* RESET SPAWN LOG */

$reset = $pdoGame->prepare("
UPDATE boss_spawn_log
SET spawn_time = 0
WHERE boss_id = ?
");

$reset->execute([$boss_id]);

}

}


/*
=====================================
GRAND BOSSES
=====================================
*/

$sql = "
SELECT
g.boss_id,
b.name,
UNIX_TIMESTAMP() AS kill_time,
b.respawn_delay,
b.respawn_random
FROM grandboss_data g
LEFT JOIN boss_list b ON b.boss_id = g.boss_id
WHERE g.respawn_time > 0
";

$stmt = $pdoGame->query($sql);
$epics = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($epics as $b){

$boss_id = intval($b['boss_id']);
$kill_time = intval($b['kill_time']);

if(!$boss_id || !$kill_time) continue;

$check = $pdoGame->prepare("
SELECT id
FROM boss_kill_log
WHERE boss_id = ?
AND kill_time = ?
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
'grand',
$kill_time,
intval($b['respawn'] ?? 0),
intval($b['respawn_random'] ?? 0)
]);

/* RESET SPAWN LOG */

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