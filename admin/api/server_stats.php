<?php

require_once __DIR__.'/../../api/admin/_bootstrap.php';
assert_admin();

header('Content-Type: application/json; charset=utf-8');

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

$out['characters'] = $pdo_game->query("
SELECT COUNT(*) FROM characters
")->fetchColumn();

/* VIP 24H */

$out['vip_24h'] = $pdo->query("
SELECT COUNT(*)
FROM user_vip
WHERE vip_type='VIP_24H'
AND vip_expires_at > NOW()
")->fetchColumn();

/* OTHER VIP */

$out['vip_other'] = $pdo->query("
SELECT COUNT(*)
FROM user_vip
WHERE vip_type!='VIP_24H'
AND vip_expires_at > NOW()
")->fetchColumn();


echo json_encode([
 'ok'=>true,
 'data'=>$out
]);