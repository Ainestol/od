<?php

require_once __DIR__.'/../config/db_game.php';

$id = intval($_GET['id'] ?? 0);

if(!$id){
    http_response_code(404);
    exit;
}

$stmt = $pdoGame->prepare("
SELECT data
FROM crests
WHERE crest_id = ?
");

$stmt->execute([$id]);

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$row){
    http_response_code(404);
    exit;
}

$tmp = "/tmp/crest_$id.dds";
$tmp2 = "/tmp/crest_$id.png";

file_put_contents($tmp,$row['data']);

shell_exec("nvdecompress $tmp $tmp2");

header("Content-Type: image/png");
readfile($tmp2);

unlink($tmp);
unlink($tmp2);