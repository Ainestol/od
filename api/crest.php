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

     $blockX = intdiv($x,4);
$blockY = intdiv($y,4);

$blockIndex = ($blockY * 4 + $blockX) * 8;

$c0 = unpack("v", substr($data,$blockIndex,2))[1];
$c1 = unpack("v", substr($data,$blockIndex+2,2))[1];

$r0 = (($c0 >> 11) & 31) << 3;
$g0 = (($c0 >> 5) & 63) << 2;
$b0 = ($c0 & 31) << 3;

$r1 = (($c1 >> 11) & 31) << 3;
$g1 = (($c1 >> 5) & 63) << 2;
$b1 = ($c1 & 31) << 3;

$colors = [
    [$r0,$g0,$b0],
    [$r1,$g1,$b1],
    [($r0+$r1)/2,($g0+$g1)/2,($b0+$b1)/2],
    [0,0,0]
];

$lookup = unpack("V", substr($data,$blockIndex+4,4))[1];

$px = $x % 4;
$py = $y % 4;

$shift = ($py*4 + $px) * 2;

$index = ($lookup >> $shift) & 3;

$c = $colors[$index];

$color = imagecolorallocate($img,$c[0],$c[1],$c[2]);

imagesetpixel($img,$x,$y,$color);
    }
}

/* uložit PNG */

imagepng($img, $cacheFile);
imagedestroy($img);

/* zobraz PNG */

header("Content-Type: image/png");
readfile($cacheFile);