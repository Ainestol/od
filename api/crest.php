<?php

require_once __DIR__.'/../config/db_game.php';

$id = intval($_GET['id'] ?? 0);

if(!$id){
    http_response_code(404);
    exit;
}

$cacheDir = __DIR__."/../cache/crests/";
$cacheFile = $cacheDir.$id.".png";

/* pokud už crest existuje v cache */

if(file_exists($cacheFile)){
    header("Content-Type: image/png");
    readfile($cacheFile);
    exit;
}

/* načti z databáze */

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

/* uložit DDS */

$tmpDDS = "/tmp/crest_$id.dds";

file_put_contents($tmpDDS,$row['data']);

/* převod DDS → PNG */

$cmd = "nvdecompress $tmpDDS $cacheFile";
shell_exec($cmd);

unlink($tmpDDS);

/* zobraz PNG */

if(file_exists($cacheFile)){
    header("Content-Type: image/png");
    readfile($cacheFile);
}else{
    http_response_code(500);
}