<?php

require_once __DIR__.'/../config/db_game.php';

$id = intval($_GET['id'] ?? 0);
if(!$id) exit;

$cacheDir = __DIR__."/../cache/crests/";
$pngFile = $cacheDir.$id.".png";

/* cache */

if(file_exists($pngFile)){
header("Content-Type: image/png");
readfile($pngFile);
exit;
}

/* načíst crest */

$stmt = $pdoGame->prepare("SELECT data FROM crests WHERE crest_id=?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$row) exit;

/* uložit DDS */

$tmpDDS = "/tmp/crest_".$id.".dds";
$tmpPNG = "/tmp/crest_".$id.".png";

file_put_contents($tmpDDS,$row['data']);

/* převod DDS -> PNG */

shell_exec("convert $tmpDDS $tmpPNG");

/* cache */

if(file_exists($tmpPNG)){
rename($tmpPNG,$pngFile);
header("Content-Type: image/png");
readfile($pngFile);
}

/* cleanup */

@unlink($tmpDDS);