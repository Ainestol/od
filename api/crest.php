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

$width = 16;
$height = 12;

$image = imagecreate($width,$height);

/* vytvoření 256 barevné palety */
$palette = [];

for($i=0;$i<256;$i++){
    $palette[$i] = imagecolorallocate($image,$i,$i,$i);
}

/* kreslení pixelů */

$pos = 0;

for($y=0;$y<$height;$y++){

    for($x=0;$x<$width;$x++){

        if(!isset($data[$pos])) continue;

        $colorIndex = ord($data[$pos]);

        imagesetpixel($image,$x,$y,$palette[$colorIndex]);

        $pos++;
    }
}

header("Content-Type: image/png");

imagepng($image);

imagedestroy($image);