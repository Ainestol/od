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

$data = $row['data'];

/* crest velikost v L2 */
$width = 16;
$height = 12;

/* vytvoření BMP hlavičky */

$filesize = 54 + strlen($data);

$bmp  = "BM";
$bmp .= pack("V", $filesize);
$bmp .= pack("V", 0);
$bmp .= pack("V", 54);
$bmp .= pack("V", 40);
$bmp .= pack("V", $width);
$bmp .= pack("V", $height);
$bmp .= pack("v", 1);
$bmp .= pack("v", 8);
$bmp .= pack("V", 0);
$bmp .= pack("V", strlen($data));
$bmp .= pack("V", 0);
$bmp .= pack("V", 0);
$bmp .= pack("V", 256);
$bmp .= pack("V", 0);

/* grayscale paleta */

for ($i = 0; $i < 256; $i++) {
    $bmp .= chr($i) . chr($i) . chr($i) . chr(0);
}

$bmp .= $data;

header("Content-Type: image/bmp");

echo $bmp;