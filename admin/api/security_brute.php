<?php

require_once __DIR__.'/../../api/admin/_bootstrap.php';
require_once __DIR__.'/../../config/db.php';

$sql = "
SELECT 
JSON_UNQUOTE(JSON_EXTRACT(meta,'$.ip')) AS ip,
COUNT(*) AS fails
FROM system_logs
WHERE action='LOGIN_FAIL'
GROUP BY ip
ORDER BY fails DESC
LIMIT 10
";

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
 'ok'=>true,
 'rows'=>$rows
]);