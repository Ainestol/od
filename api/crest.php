<?php

require_once __DIR__.'/../config/db_game.php';

$id = intval($_GET['id'] ?? 0);

if(!$id){
    http_response_code(404);
    exit;
}

$cacheDir = __DIR__.'/../cache/crests/';
$cacheFile = $cacheDir.$id.'.png';

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

$tmp = sys_get_temp_dir()."/crest_".$id.".dds";
file_put_contents($tmp,$row['data']);

exec("convert $tmp -filter point -resize 16x12! $cacheFile");

unlink($tmp);

header("Content-Type: image/png");
readfile($cacheFile);