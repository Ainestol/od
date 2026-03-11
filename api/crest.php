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

$ddsHeader = hex2bin(
"444453207C00000007100A00000000000C00000010000000000000000000000000000000000000000000000020000000040000004458543100000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000"
);

file_put_contents($tmpDDS, $ddsHeader . $row['data']);

/* převod DDS → PNG */

$cmd = "/usr/bin/nvdecompress $tmpDDS > $cacheFile";

$output = shell_exec($cmd . " 2>&1");

file_put_contents("/tmp/crest_debug.txt",$output);

unlink($tmpDDS);

/* zobraz PNG */

if(file_exists($cacheFile)){
    header("Content-Type: image/png");
    readfile($cacheFile);
}else{
    http_response_code(500);
}