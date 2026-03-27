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


// === GAME VIP (správná logika) ===
$st = $pdo->query("
    SELECT ga.login, ga.id
    FROM game_accounts ga
    WHERE EXISTS (
        SELECT 1
        FROM vip_grants vg
        WHERE vg.scope = 'GAME'
          AND vg.target_id = ga.id
          AND vg.end_at <= NOW()
    )
    AND NOT EXISTS (
        SELECT 1
        FROM vip_grants vg2
        WHERE vg2.scope = 'GAME'
          AND vg2.target_id = ga.id
          AND vg2.end_at > NOW()
    )
    AND NOT EXISTS (
        SELECT 1
        FROM vip_grants vg3
        WHERE vg3.scope = 'WEB'
          AND vg3.target_id = ga.web_user_id
          AND vg3.end_at > NOW()
    )
");

$rows = $st->fetchAll(PDO::FETCH_ASSOC);

if (!empty($rows)) {
    $accounts = array_column($rows, 'login');
    $ids = array_column($rows, 'id');

    $in = implode(',', array_map(fn($a) => $pdo->quote($a), $accounts));
    $idsIn = implode(',', array_map('intval', $ids));

    // GAME DB
    $pdoGame->exec("
        UPDATE account_premium
        SET enddate = 0
        WHERE account_name IN ($in)
    ");

    // WEB DB sync
    $pdo->exec("
        UPDATE vip_grants
        SET end_at = NOW()
        WHERE scope = 'GAME'
          AND target_id IN ($idsIn)
          AND end_at > NOW()
    ");
}


// === WEB VIP ===
$st = $pdo->query("
    SELECT ga.login, ga.web_user_id
    FROM game_accounts ga
    WHERE NOT EXISTS (
        SELECT 1
        FROM vip_grants vg
        WHERE vg.scope = 'WEB'
          AND vg.target_id = ga.web_user_id
          AND vg.end_at > NOW()
    )
");

$rows = $st->fetchAll(PDO::FETCH_ASSOC);

if (!empty($rows)) {
    $accounts = array_unique(array_column($rows, 'login'));
    $userIds = array_unique(array_column($rows, 'web_user_id'));

    $in = implode(',', array_map(fn($a) => $pdo->quote($a), $accounts));
    $userIn = implode(',', array_map('intval', $userIds));

    // GAME DB
    $pdoGame->exec("
        UPDATE account_premium
        SET enddate = 0
        WHERE account_name IN ($in)
    ");

    // WEB DB sync
    $pdo->exec("
        UPDATE vip_grants
        SET end_at = NOW()
        WHERE scope = 'WEB'
          AND target_id IN ($userIn)
          AND end_at > NOW()
    ");
}