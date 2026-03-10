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

/* vytvoříme DDS header */

$dds_header =
"DDS ".
pack("V",124).
pack("V",0x00021007).
pack("V",12).
pack("V",16).
pack("V",256).
pack("V",0).
pack("V",0).
str_repeat(pack("V",0),11).
pack("V",32).
pack("V",0x00000004).
"DXT1".
pack("V",0).
pack("V",0).
pack("V",0).
pack("V",0).
pack("V",0).
pack("V",0).
pack("V",0);

$dds = $dds_header.$data;

$tmp = sys_get_temp_dir()."/crest_".$id.".dds";

file_put_contents($tmp,$dds);

header("Content-Type: image/png");

passthru("convert $tmp -filter point -resize 16x12! png:-");

unlink($tmp);