<?php
/**
 * Admin characters list — enriched per-character metadata.
 *
 * GET params:
 *   gameAccountId (optional) — if given, lists chars of that web game account;
 *                              otherwise returns first 500 chars globally
 *
 * Response (additive — backward compatible):
 *   {
 *     ok: true,
 *     data: [{
 *       charId, char_name, level, classid,
 *       online, lastAccess, onlinetime
 *       (+ account_name in global mode)
 *     }, ...]
 *   }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/_bootstrap.php';                       // $pdo
require_once __DIR__ . '/../../config/db_game_write.php';       // $pdoPremium

try {
    assert_admin();

    $gameAccountId = isset($_GET['gameAccountId']) ? (int)$_GET['gameAccountId'] : 0;

    if ($gameAccountId > 0) {
        // 1) Resolve login from web DB
        $loginStmt = $pdo->prepare("SELECT login FROM game_accounts WHERE id = ?");
        $loginStmt->execute([$gameAccountId]);
        $login = $loginStmt->fetchColumn();

        if (!$login) {
            echo json_encode(['ok' => true, 'data' => []]);
            exit;
        }

        // 2) Fetch chars from game DB
        $stmt = $pdoPremium->prepare("
            SELECT charId, char_name, level, classid, online, lastAccess, onlinetime
            FROM characters
            WHERE account_name = ?
            ORDER BY level DESC, charId DESC
        ");
        $stmt->execute([$login]);

    } else {
        // Fallback — global list (legacy behaviour preserved)
        $stmt = $pdoPremium->query("
            SELECT charId, char_name, level, classid, online, lastAccess, onlinetime, account_name
            FROM characters
            ORDER BY charId DESC
            LIMIT 500
        ");
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['charId']     = (int)$r['charId'];
        $r['level']      = $r['level'] === null ? 0 : (int)$r['level'];
        $r['classid']    = $r['classid'] === null ? 0 : (int)$r['classid'];
        $r['online']     = (int)$r['online'] === 1;
        $r['lastAccess'] = (int)$r['lastAccess'];
        $r['onlinetime'] = (int)$r['onlinetime'];
    }
    unset($r);

    echo json_encode(['ok' => true, 'data' => $rows]);

} catch (Throwable $e) {
    error_log('[admin/characters_list] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'SERVER_ERROR']);
}
