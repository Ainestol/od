<?php

require_once __DIR__.'/../config/db_game.php';

$id = intval($_GET['id'] ?? 0);

if(!$id){
    http_response_code(404);
    exit;
}

$cacheDir = __DIR__."/../cache/crests/";
$cacheFile = $cacheDir.$id.".png";

/* pokud crest už existuje v cache */

if(file_exists($cacheFile)){
    header("Content-Type: image/png");
    readfile($cacheFile);
    exit;
}

/* načíst crest z databáze */

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

/* vytvořit dočasný DDS */

$tmpDDS = "/tmp/crest_$id.dds";

/* správný DDS header pro L2 crest (DXT1 16x12) */

$header = pack(
    "a4V7a4V5",
    "DDS ",
    124,
    0x00021007,
    12,
    16,
    0,
    0,
    0,
    "DXT1",
    0,
    0,
    0,
    0,
    0
);

/* zapsat DDS */

file_put_contents($tmpDDS, $header . $row['data']);

/* převod DDS → PNG */

$cmd = "/usr/bin/nvdecompress $tmpDDS > $cacheFile";
shell_exec($cmd);

/* smazat tmp */

unlink($tmpDDS);

/* zobraz PNG */

if(file_exists($cacheFile) && filesize($cacheFile) > 0){
    header("Content-Type: image/png");
    readfile($cacheFile);
}else{
    http_response_code(500);
}