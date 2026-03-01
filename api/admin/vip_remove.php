<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../config/db.php';              // $pdo (WEB DB)
require_once __DIR__ . '/../../config/db_game_write.php';   // $pdoPremium
require_once __DIR__ . '/../../lib/vip.php';

assert_admin();

$input = $_POST ?: json_decode(file_get_contents('php://input'), true);

if (empty($input['vipGrantId'])) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'MISSING_vipGrantId']);
    exit;
}

$vipGrantId = (int)$input['vipGrantId'];

// 1️⃣ zjisti scope + target_id
$stmt = $pdo->prepare("
    SELECT scope, target_id
    FROM vip_grants
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$vipGrantId]);
$grant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$grant) {
    http_response_code(404);
    echo json_encode(['ok'=>false,'error'=>'VIP_NOT_FOUND']);
    exit;
}

$scope    = $grant['scope'];
$targetId = (int)$grant['target_id'];

// 2️⃣ ukonči VIP
$stmt = $pdo->prepare("
    UPDATE vip_grants
    SET end_at = NOW()
    WHERE id = ?
");
$stmt->execute([$vipGrantId]);

// 3️⃣ pokud to byl CHAR, smaž VIP flag z postavy
if ($scope === 'CHAR') {
    $stmt = $pdoGame->prepare("
        DELETE FROM character_variables
        WHERE charId = :charId
          AND var = 'VIP_CHAR'
    ");
    $stmt->execute([
        ':charId' => $targetId
    ]);
}

echo json_encode(['ok'=>true]);
