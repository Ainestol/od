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

$image = imagecreatetruecolor($width,$height);

$pos = 0;

for($y=$height-1;$y>=0;$y--){

    for($x=0;$x<$width;$x++){

        $b = ord($data[$pos++]);
        $g = ord($data[$pos++]);
        $r = ord($data[$pos++]);

        $color = imagecolorallocate($image,$r,$g,$b);

        imagesetpixel($image,$x,$y,$color);
    }
}

header("Content-Type: image/png");

imagepng($image);
imagedestroy($image);