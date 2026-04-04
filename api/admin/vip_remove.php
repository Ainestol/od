<?php
ini_set('display_errors', 0);
error_reporting(0);

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

    // smaž VIP flag ve hře
    $stmt = $pdoPremium->prepare("
        DELETE FROM character_variables
        WHERE charId = :charId
          AND var = 'VIP_CHAR'
    ");
    $stmt->execute([
        ':charId' => $targetId
    ]);

    // 🔥 navíc: ukonči všechny CHAR VIP granty (aby zmizelo z adminu)
    $stmt = $pdo->prepare("
        UPDATE vip_grants
        SET end_at = NOW()
        WHERE scope = 'CHAR'
          AND target_id = ?
          AND end_at > NOW()
    ");
    $stmt->execute([$targetId]);
}
// 4️⃣ pokud GAME → zruš account_premium
if ($scope === 'GAME') {
    $stmt = $pdo->prepare("
        SELECT login FROM game_accounts WHERE id = ?
    ");
    $stmt->execute([$targetId]);
    $login = $stmt->fetchColumn();

    if ($login) {
        $stmt = $pdoPremium->prepare("
            UPDATE account_premium
            SET enddate = 0
            WHERE account_name = ?
        ");
        $stmt->execute([$login]);
    }
}

// 5️⃣ pokud WEB → zruš všem účtům usera
if ($scope === 'WEB') {
    $stmt = $pdo->prepare("
        SELECT login FROM game_accounts WHERE web_user_id = ?
    ");
    $stmt->execute([$targetId]);
    $accounts = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($accounts)) {
        $in = implode(',', array_map(fn($a) => $pdo->quote($a), $accounts));

        $pdoPremium->exec("
            UPDATE account_premium
            SET enddate = 0
            WHERE account_name IN ($in)
        ");
    }
}
echo json_encode(['ok'=>true]);
