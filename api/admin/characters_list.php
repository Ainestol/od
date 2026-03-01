<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/_bootstrap.php'; // $pdo
require_once __DIR__ . '/../../config/db_game_write.php'; // $pdoPremium

assert_admin();

$gameAccountId = $_GET['gameAccountId'] ?? null;

if ($gameAccountId) {

    // 1️⃣ nejdřív zjistíme LOGIN z WEB DB
    $stmtLogin = $pdo->prepare("
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
    $stmt = $pdoPremium->prepare("
        SELECT charId, char_name
        FROM characters
        WHERE account_name = ?
        ORDER BY charId DESC
    ");
    $stmt->execute([$login]);

} else {

    // fallback – všechny postavy
    $stmt = $pdoPremium->query("
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
