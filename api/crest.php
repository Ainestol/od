<?php

require_once __DIR__.'/../config/db_game.php';

$id = intval($_GET['id'] ?? 0);

if(!$id){
    http_response_code(404);
    exit;
}

$cacheFile = __DIR__."/../cache/crests/$id.png";

if(file_exists($cacheFile)){
    header("Content-Type: image/png");
    readfile($cacheFile);
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

file_put_contents($tmp,$row['data']);

$cmd = "convert $tmp -define dds:compression=none -filter point -resize 48x36 $cacheFile";
shell_exec($cmd);

unlink($tmp);

header("Content-Type: image/png");
readfile($cacheFile);