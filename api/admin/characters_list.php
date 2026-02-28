<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_bootstrap.php';
assert_admin();

$pdoGame = new PDO(
    'mysql:host=localhost;dbname=l2game;charset=utf8mb4',
    'premium_user',
    '@Heslojeheslo55',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);

$gameAccountId = $_GET['gameAccountId'] ?? null;

if ($gameAccountId) {

    // 1️⃣ nejdřív zjistíme LOGIN z WEB DB
    $stmtLogin = $pdoWeb->prepare("
        SELECT login
        FROM game_accounts
        WHERE id = ?
    ");
    $stmtLogin->execute([(int)$gameAccountId]);
    $login = $stmtLogin->fetchColumn();

    if (!$login) {
        echo json_encode(['ok' => true, 'data' => []]);
        exit;
    }

    // 2️⃣ pak taháme postavy z L2 DB
    $stmt = $pdoGame->prepare("
        SELECT charId, char_name
        FROM characters
        WHERE account_name = ?
        ORDER BY charId DESC
    ");
    $stmt->execute([$login]);

} else {

    // fallback – všechny postavy
    $stmt = $pdoGame->query("
        SELECT charId, char_name, account_name
        FROM characters
        ORDER BY charId DESC
        LIMIT 500
    ");
}

echo json_encode([
    'ok' => true,
    'data' => $stmt->fetchAll()
]);
