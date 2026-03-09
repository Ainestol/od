<?php

require_once __DIR__.'/../../api/admin/_bootstrap.php';
assert_admin();

require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../config/db_game.php';

$out = [];

/* WEB USERS */

$out['web_users'] = $pdo->query("
SELECT COUNT(*) FROM users
")->fetchColumn();

/* GAME ACCOUNTS */

$out['game_accounts'] = $pdo->query("
SELECT COUNT(*) FROM game_accounts
")->fetchColumn();

/* CHARACTERS */

$out['characters'] = $pdoGame->query("
SELECT COUNT(*) FROM characters
")->fetchColumn();

/* VIP I */

$out['vip_1'] = $pdo->query("
SELECT COUNT(*)
FROM vip_grants
WHERE level_id = 1
AND end_at > NOW()
")->fetchColumn();

/* VIP II */

$out['vip_2'] = $pdo->query("
SELECT COUNT(*)
FROM vip_grants
WHERE level_id = 2
AND end_at > NOW()
")->fetchColumn();

/* VIP III */

$out['vip_3'] = $pdo->query("
SELECT COUNT(*)
FROM vip_grants
WHERE level_id = 3
AND end_at > NOW()
")->fetchColumn();

$out['online'] = $pdoGame->query("
SELECT COUNT(*) FROM characters WHERE online = 1
")->fetchColumn();

/* ===== TOTAL VOTE COINS ===== */

$out['vote_total'] = $pdo->query("
SELECT COALESCE(SUM(amount),0)
FROM wallet_ledger
WHERE currency = 'VOTE_COIN'
AND amount > 0
")->fetchColumn();


/* ===== TOTAL DRAGON COINS ===== */

$out['dc_total'] = $pdo->query("
SELECT COALESCE(SUM(amount),0)
FROM wallet_ledger
WHERE currency = 'DC'
AND amount > 0
")->fetchColumn();

echo json_encode([
 'ok'=>true,
 'data'=>$out
]);