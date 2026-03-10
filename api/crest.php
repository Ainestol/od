<?php
require_once __DIR__.'/../config/db_game.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) { http_response_code(404); exit; }

$stmt = $pdoGame->prepare("SELECT data FROM crests WHERE crest_id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row || empty($row['data'])) { http_response_code(404); exit; }

$data = $row['data'];

// cílové rozměry crestů
$W = 16;
$H = 12;

// DXT1 bloky jsou 4x4 pixely, 8 bajtů na blok
$blocksX = intdiv($W, 4);
$blocksY = intdiv($H, 4);

// vezmeme jen tolik dat, kolik potřebujeme pro 16x12
$needed = $blocksX * $blocksY * 8;
$buf = substr($data, 0, $needed);

// pomocná funkce: převod RGB565 -> RGB888
function rgb565_to_rgb($c) {
    $r = (($c >> 11) & 0x1F) * 255 / 31;
    $g = (($c >> 5)  & 0x3F) * 255 / 63;
    $b = ( $c        & 0x1F) * 255 / 31;
    return [intval($r), intval($g), intval($b)];
}

$img = imagecreatetruecolor($W, $H);

$offset = 0;
for ($by = 0; $by < $blocksY; $by++) {
    for ($bx = 0; $bx < $blocksX; $bx++) {

        // čtení bloku
        $c0 = unpack('v', substr($buf, $offset, 2))[1];
        $c1 = unpack('v', substr($buf, $offset+2, 2))[1];
        $bits = unpack('V', substr($buf, $offset+4, 4))[1];
        $offset += 8;

        [$r0,$g0,$b0] = rgb565_to_rgb($c0);
        [$r1,$g1,$b1] = rgb565_to_rgb($c1);

        $palette = [];
        $palette[0] = [$r0,$g0,$b0];
        $palette[1] = [$r1,$g1,$b1];

        if ($c0 > $c1) {
            $palette[2] = [intval((2*$r0+$r1)/3), intval((2*$g0+$g1)/3), intval((2*$b0+$b1)/3)];
            $palette[3] = [intval(($r0+2*$r1)/3), intval(($g0+2*$g1)/3), intval(($b0+2*$b1)/3)];
        } else {
            $palette[2] = [intval(($r0+$r1)/2), intval(($g0+$g1)/2), intval(($b0+$b1)/2)];
            $palette[3] = [0,0,0]; // transparent/black
        }

        // 4x4 pixely v bloku
        for ($py = 0; $py < 4; $py++) {
            for ($px = 0; $px < 4; $px++) {
                $idx = ($bits >> (2*(4*$py+$px))) & 0x3;
                [$r,$g,$b] = $palette[$idx];

                $x = $bx*4 + $px;
                $y = $by*4 + $py;

                if ($x < $W && $y < $H) {
                    $col = imagecolorallocate($img, $r, $g, $b);
                    imagesetpixel($img, $x, $y, $col);
                }
            }
        }
    }
}

header("Content-Type: image/png");
imagepng($img);
imagedestroy($img);