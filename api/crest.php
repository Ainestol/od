<?php

require_once __DIR__.'/../config/db_game.php';

$id = intval($_GET['id'] ?? 0);
if(!$id){
    http_response_code(404);
    exit;
}

$cacheDir = __DIR__."/../cache/crests/";
if(!is_dir($cacheDir)){
    mkdir($cacheDir, 0775, true);
}

$pngFile = $cacheDir.$id.".png";

/* pokud je v cache */
if(file_exists($pngFile)){
    header("Content-Type: image/png");
    readfile($pngFile);
    exit;
}

/* načíst crest z DB */
$stmt = $pdoGame->prepare("SELECT data FROM crests WHERE crest_id=?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$row){
    http_response_code(404);
    exit;
}

/* vytvořit dočasné soubory */
$tmpDDS = "/tmp/crest_".$id.".dds";
$tmpPNG = "/tmp/crest_".$id.".png";

/* DDS header pro DXT1 16x12 */
$header = hex2bin(
"444453207C00000007100A00000000000C00000010000000000000000000000000000000000000000000000020000000040000004458543100000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000"
);

file_put_contents($tmpDDS, $header.$row['data']);

/* převod DDS -> PNG pomocí NVTT */
shell_exec("/usr/bin/nvdecompress $tmpDDS $tmpPNG");

/* uložit cache */
if(file_exists($tmpPNG)){
    rename($tmpPNG, $pngFile);
}

/* úklid */
@unlink($tmpDDS);

/* vrátit obrázek */
if(file_exists($pngFile)){
    header("Content-Type: image/png");
    readfile($pngFile);
}else{
    http_response_code(500);
}