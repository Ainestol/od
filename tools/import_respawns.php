<?php

require_once __DIR__.'/../config/db_game.php';

$path = '/opt/l2/ClassicLude/game/data/spawns/RBspawn.xml';

if(!file_exists($path)){
    die("XML NOT FOUND: ".$path."\n");
}

$xml = simplexml_load_file($path);

foreach ($xml->spawn as $spawn)
{
    $npc = $spawn->npc;

    $boss_id = (int)$npc['id'];

    $respawnTime = (string)$npc['respawnTime'];
    $respawnRandom = (string)$npc['respawnRandom'];

    preg_match('/(\d+)/', $respawnTime, $time);
    preg_match('/(\d+)/', $respawnRandom, $rand);

    if(str_contains($respawnTime,'hour'))
        $respawn = $time[1] * 3600;
    else
        $respawn = $time[1] * 60;

    $respawn_random = $rand[1] * 60;

    $stmt = $pdoGame->prepare("
        REPLACE INTO boss_respawn
        (boss_id,respawn,respawn_random)
        VALUES (?,?,?)
    ");

    $stmt->execute([
        $boss_id,
        $respawn,
        $respawn_random
    ]);
}

echo "Respawns imported\n";