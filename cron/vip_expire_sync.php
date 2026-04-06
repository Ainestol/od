<?php
file_put_contents('/tmp/cron_debug.log', date('c') . " CRON START\n", FILE_APPEND);
require_once __DIR__ . '/../config/db.php';
$pdoGame = require __DIR__ . '/../config/db_game.php';

// ================================================================
// FILOZOFIE:
// WEB VIP (lvl 3) > GAME VIP (lvl 2) > CHAR VIP (lvl 1)
// Cron jen ODEBÍRÁ expirované VIP — nikdy nepřepisuje aktivní vyšší.
// ================================================================

// ================================================================
// 1) CHAR VIP — odeber VIP_CHAR z character_variables
//    pouze pokud postava nemá aktivní GAME ani WEB VIP
// ================================================================
$st = $pdo->query("
    SELECT vg.target_id AS charId
    FROM vip_grants vg
    JOIN l2game.characters c ON c.charId = vg.target_id
    JOIN game_accounts ga ON ga.login = c.account_name
    WHERE vg.scope = 'CHAR'
      AND vg.end_at <= NOW()
      AND NOT EXISTS (
          SELECT 1 FROM vip_grants vg2
          WHERE vg2.scope = 'GAME'
            AND vg2.target_id = ga.id
            AND vg2.end_at > NOW()
      )
      AND NOT EXISTS (
          SELECT 1 FROM vip_grants vg3
          WHERE vg3.scope = 'WEB'
            AND vg3.target_id = ga.web_user_id
            AND vg3.end_at > NOW()
      )
");
$chars = $st->fetchAll(PDO::FETCH_COLUMN);

if (!empty($chars)) {
    $in = implode(',', array_map('intval', $chars));
    $pdoGame->exec("
        DELETE FROM character_variables
        WHERE var IN ('VIP_CHAR', 'VIP_CHAR_END')
          AND charId IN ($in)
    ");
}
file_put_contents('/tmp/cron_debug.log', date('c') . " CHAR VIP deleted: " . json_encode($chars) . "\n", FILE_APPEND);
// ================================================================
// 2) GAME VIP — odeber account_premium
//    pouze pokud nemá aktivní WEB VIP ani jiný aktivní GAME VIP
// ================================================================
$st = $pdo->query("
    SELECT ga.login
    FROM game_accounts ga
    WHERE EXISTS (
        SELECT 1 FROM vip_grants vg
        WHERE vg.scope = 'GAME'
          AND vg.target_id = ga.id
          AND vg.end_at <= NOW()
    )
    AND NOT EXISTS (
        SELECT 1 FROM vip_grants vg2
        WHERE vg2.scope = 'GAME'
          AND vg2.target_id = ga.id
          AND vg2.end_at > NOW()
    )
    AND NOT EXISTS (
        SELECT 1 FROM vip_grants vg3
        WHERE vg3.scope = 'WEB'
          AND vg3.target_id = ga.web_user_id
          AND vg3.end_at > NOW()
    )
");
$rows = $st->fetchAll(PDO::FETCH_COLUMN);

if (!empty($rows)) {
    $in = implode(',', array_map(fn($a) => $pdo->quote($a), $rows));
    $pdoGame->exec("UPDATE account_premium SET enddate = 0 WHERE account_name IN ($in)");
}

// ================================================================
// 3) WEB VIP — odeber account_premium
//    pouze pokud nemá aktivní WEB VIP ani GAME VIP
// ================================================================
$st = $pdo->query("
    SELECT ga.login
    FROM game_accounts ga
    WHERE EXISTS (
        SELECT 1 FROM vip_grants vg
        WHERE vg.scope = 'WEB'
          AND vg.target_id = ga.web_user_id
          AND vg.end_at <= NOW()
    )
    AND NOT EXISTS (
        SELECT 1 FROM vip_grants vg2
        WHERE vg2.scope = 'WEB'
          AND vg2.target_id = ga.web_user_id
          AND vg2.end_at > NOW()
    )
    AND NOT EXISTS (
        SELECT 1 FROM vip_grants vg3
        WHERE vg3.scope = 'GAME'
          AND vg3.target_id = ga.id
          AND vg3.end_at > NOW()
    )
");
$rows = $st->fetchAll(PDO::FETCH_COLUMN);

if (!empty($rows)) {
    $in = implode(',', array_map(fn($a) => $pdo->quote($a), $rows));
    $pdoGame->exec("UPDATE account_premium SET enddate = 0 WHERE account_name IN ($in)");
}

// ================================================================
// 4) SYNC — nastav account_premium pro aktivní VIP (GREATEST priorita)
// ================================================================

// WEB VIP
$st = $pdo->query("
    SELECT ga.login, UNIX_TIMESTAMP(vg.end_at) * 1000 AS endMs
    FROM game_accounts ga
    JOIN vip_grants vg ON vg.target_id = ga.web_user_id
    WHERE vg.scope = 'WEB'
      AND vg.end_at > NOW()
    ORDER BY vg.end_at DESC
");
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $pdoGame->prepare("
        INSERT INTO account_premium (account_name, enddate)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE enddate = GREATEST(enddate, VALUES(enddate))
    ")->execute([$row['login'], $row['endMs']]);
}

// GAME VIP (jen pokud nemá vyšší WEB VIP)
$st = $pdo->query("
    SELECT ga.login, UNIX_TIMESTAMP(vg.end_at) * 1000 AS endMs
    FROM game_accounts ga
    JOIN vip_grants vg ON vg.target_id = ga.id
    WHERE vg.scope = 'GAME'
      AND vg.end_at > NOW()
    ORDER BY vg.end_at DESC
");
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $pdoGame->prepare("
        INSERT INTO account_premium (account_name, enddate)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE enddate = GREATEST(enddate, VALUES(enddate))
    ")->execute([$row['login'], $row['endMs']]);
}

