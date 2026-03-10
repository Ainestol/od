<?php

require_once __DIR__ . '/../config/db_game.php';

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(404);
    exit;
}

try {

    $stmt = $pdoGame->prepare("
        SELECT data, type
        FROM crests
        WHERE crest_id = ?
        LIMIT 1
    ");

    $stmt->execute([$id]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['data'])) {
        http_response_code(404);
        exit;
    }

    $data = $row['data'];

    /*
     L2J někdy ukládá crest bez BMP hlavičky.
     Pokud hlavička chybí, vytvoříme ji.
    */

    if (substr($data, 0, 2) !== "BM") {

        $size = strlen($data) + 54;

        $header =
            "BM" .
            pack("V", $size) .
            pack("v", 0) .
            pack("v", 0) .
            pack("V", 54) .
            pack("V", 40) .
            pack("V", 16) .
            pack("V", 12) .
            pack("v", 1) .
            pack("v", 24) .
            pack("V", 0) .
            pack("V", strlen($data)) .
            pack("V", 0) .
            pack("V", 0) .
            pack("V", 0) .
            pack("V", 0);

        $data = $header . $data;
    }

    header("Content-Type: image/bmp");
    echo $data;

} catch (Exception $e) {

    http_response_code(500);

}