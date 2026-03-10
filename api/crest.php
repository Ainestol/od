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

/* crest rozměry */
$width = 16;
$height = 12;

$image = imagecreatetruecolor($width,$height);

/* jednoduchá grayscale paleta */
for($y=0;$y<$height;$y++){
    for($x=0;$x<$width;$x++){

        $i = ($y*$width)+$x;

        if(!isset($data[$i])) continue;

        $val = ord($data[$i]);

        $color = imagecolorallocate($image,$val,$val,$val);

        imagesetpixel($image,$x,$y,$color);
    }
}

header("Content-Type: image/png");

imagepng($image);
imagedestroy($image);