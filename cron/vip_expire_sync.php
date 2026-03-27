<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/db_game.php';

// === CHAR VIP ===
$st = $pdo->query("
    SELECT target_id
    FROM vip_grants
    WHERE scope = 'CHAR'
      AND end_at <= NOW()
");

$chars = $st->fetchAll(PDO::FETCH_COLUMN);

if (!empty($chars)) {
    $pdoGame->exec("
        DELETE FROM character_variables
        WHERE var = 'VIP_CHAR'
          AND charId IN (" . implode(',', array_map('intval', $chars)) . ")
    ");
}


// === GAME VIP ===
$st = $pdo->query("
    SELECT ga.login
    FROM vip_grants vg
    JOIN game_accounts ga ON vg.target_id = ga.id
    WHERE vg.scope = 'GAME'
      AND vg.end_at <= NOW()
");

$accounts = $st->fetchAll(PDO::FETCH_COLUMN);

if (!empty($accounts)) {
    $in = implode(',', array_map(fn($a) => $pdo->quote($a), $accounts));

    $pdoGame->exec("
        UPDATE account_premium
        SET enddate = 0
        WHERE account_name IN ($in)
    ");
}


// === WEB VIP (jen pokud user nemá žádný aktivní WEB VIP) ===
$st = $pdo->query("
    SELECT ga.login
    FROM game_accounts ga
    WHERE NOT EXISTS (
        SELECT 1
        FROM vip_grants vg
        WHERE vg.scope = 'WEB'
          AND vg.target_id = ga.web_user_id
          AND vg.end_at > NOW()
    )
");

$accounts = $st->fetchAll(PDO::FETCH_COLUMN);

if (!empty($accounts)) {
    $in = implode(',', array_map(fn($a) => $pdo->quote($a), $accounts));

    $pdoGame->exec("
        UPDATE account_premium
        SET enddate = 0
        WHERE account_name IN ($in)
    ");
}