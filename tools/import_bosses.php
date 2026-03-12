<?php

$pdo = new PDO(
    "mysql:host=localhost;dbname=l2game;charset=utf8mb4",
    "l2_reader",
    trim(file_get_contents('/var/www/.env')) // pokud máš jinak, dej heslo
);

$dir = "/opt/l2/ClassicLude/game/data/stats/npcs/";

$files = glob($dir . "*.xml");

foreach ($files as $file){

    $xml = simplexml_load_file($file);

    foreach ($xml->npc as $npc){

        $id = (int)$npc['id'];

        $stmt = $pdo->prepare("SELECT boss_id FROM boss_list WHERE boss_id=?");
        $stmt->execute([$id]);

        if(!$stmt->fetch()) continue;

        $name = (string)$npc['name'];
        $level = (int)$npc->level;

        $update = $pdo->prepare("
            UPDATE boss_list
            SET name=?, level=?
            WHERE boss_id=?
        ");

        $update->execute([$name,$level,$id]);

        echo "Imported: $id $name (lvl $level)\n";

    }

}

echo "DONE\n";