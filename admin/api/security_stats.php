<?php

require_once __DIR__.'/../../api/admin/_bootstrap.php';
require_once __DIR__.'/../../config/db.php';
$days = isset($_GET['days']) ? (int)$_GET['days'] : 7;

if ($days < 1) $days = 1;
if ($days > 360) $days = 360;
$out = [];

/* TOP brute force IP */

$sql = "
SELECT 
JSON_UNQUOTE(JSON_EXTRACT(meta,'$.ip')) AS ip,
COUNT(*) AS fails
FROM system_logs
WHERE action='LOGIN_FAIL'
AND created_at >= NOW() - INTERVAL $days DAY
GROUP BY ip
ORDER BY fails DESC
LIMIT 10
";

$out['brute_ips'] = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);


/* LOGIN FAIL posledních 10 minut */

$sql = "
SELECT 
JSON_UNQUOTE(JSON_EXTRACT(meta,'$.ip')) AS ip,
COUNT(*) AS fails
FROM system_logs
WHERE action='LOGIN_FAIL'
AND created_at > NOW() - INTERVAL 10 MINUTE
GROUP BY ip
ORDER BY fails DESC
";

$out['recent_fails'] = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);


/* RATE LIMIT */

$sql = "
SELECT 
JSON_UNQUOTE(JSON_EXTRACT(meta,'$.ip')) AS ip,
COUNT(*) AS blocks
FROM system_logs
WHERE action='LOGIN_RATE_LIMIT'
AND created_at >= NOW() - INTERVAL $days DAY
GROUP BY ip
ORDER BY blocks DESC
";

$out['rate_limits'] = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);


/* ATTACKED ACCOUNTS */

$sql = "
SELECT 
JSON_UNQUOTE(JSON_EXTRACT(meta,'$.email')) AS email,
COUNT(*) AS fails
FROM system_logs
WHERE action='LOGIN_FAIL'
AND created_at >= NOW() - INTERVAL $days DAY
GROUP BY email
ORDER BY fails DESC
LIMIT 10
";

$out['accounts'] = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);


/* ECONOMY SPAM */

$sql = "
SELECT 
user_id,
action,
COUNT(*) AS count
FROM system_logs
WHERE action IN (
'SHOP_PURCHASE',
'VIP_24H_ACTIVATE',
'VIP_24H_EXTEND',
'VC_TO_DC_CONVERSION'
)
AND created_at >= NOW() - INTERVAL $days DAY
GROUP BY user_id, action
ORDER BY count DESC
LIMIT 10
";

$out['economy'] = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);


echo json_encode([
 'ok'=>true,
 'data'=>$out
]);