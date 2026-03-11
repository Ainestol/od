<?php

require_once __DIR__.'/../config/db_game.php';

$id = intval($_GET['id'] ?? 0);

if(!$id){
    http_response_code(404);
    exit;
}

$cacheDir = __DIR__."/../cache/crests/";
$cacheFile = $cacheDir.$id.".png";

/* pokud crest existuje v cache */

if(file_exists($cacheFile)){
    header("Content-Type: image/png");
    readfile($cacheFile);
    exit;
}

/* načíst crest z DB */

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

/* vytvořit obrázek 16x12 */

$width = 16;
$height = 12;

$img = imagecreatetruecolor($width, $height);

$offset = 0;

for ($y = 0; $y < $height; $y++) {
    for ($x = 0; $x < $width; $x++) {

        if ($offset >= strlen($data)) {
            break;
        }

        $val = ord($data[$offset]);

        $color = imagecolorallocate($img, $val, $val, $val);

        imagesetpixel($img, $x, $y, $color);

        $offset++;
    }
}

/* uložit PNG */

imagepng($img, $cacheFile);
imagedestroy($img);

/* zobraz PNG */

header("Content-Type: image/png");
readfile($cacheFile);