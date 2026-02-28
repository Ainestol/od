<?php

require_once __DIR__ . '/../config/db.php';

// najdeme všechny postavy kde VIP vypršel
$st = $pdo->query("
    SELECT target_id
    FROM vip_grants
    WHERE scope = 'CHAR'
      AND end_at <= NOW()
");

$chars = $st->fetchAll(PDO::FETCH_COLUMN);

if ($chars) {

    $pdo->exec("
        DELETE FROM l2game.character_variables
        WHERE var = 'VIP_CHAR'
          AND charId IN (" . implode(',', array_map('intval',$chars)) . ")
    ");
}
