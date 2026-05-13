<?php
/**
 * Admin game accounts list — enriched per-account metadata.
 *
 * GET params:
 *   webUserId (required) — web user id
 *
 * Response (additive to previous shape):
 *   {
 *     ok: true,
 *     data: [{
 *       id, login, web_user_id, is_primary, created_at,
 *       character_count, premium_end_ms,
 *       any_online, last_access_ms
 *     }, ...]
 *   }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../config/db_game_write.php'; // $pdoPremium (game DB read)

try {
    assert_admin();

    $webUserId = (int)($_GET['webUserId'] ?? 0);
    if ($webUserId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Missing webUserId']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id, login, web_user_id, is_primary, created_at
        FROM game_accounts
        WHERE web_user_id = ?
        ORDER BY is_primary DESC, id DESC
    ");
    $stmt->execute([$webUserId]);
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($accounts)) {
        $logins     = array_column($accounts, 'login');
        $loginPlace = implode(',', array_fill(0, count($logins), '?'));

        // Char aggregates per account_name
        $chStmt = $pdoPremium->prepare("
            SELECT account_name,
                   COUNT(*)        AS char_count,
                   MAX(online)     AS any_online,
                   MAX(lastAccess) AS last_access
            FROM characters
            WHERE account_name IN ($loginPlace)
            GROUP BY account_name
        ");
        $chStmt->execute($logins);
        $chMap = [];
        foreach ($chStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $chMap[strtolower($r['account_name'])] = $r;
        }

        // Premium end per account_name (enddate is ms decimal)
        $pStmt = $pdoPremium->prepare("
            SELECT account_name, enddate
            FROM account_premium
            WHERE account_name IN ($loginPlace)
        ");
        $pStmt->execute($logins);
        $pMap = [];
        foreach ($pStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $pMap[strtolower($r['account_name'])] = (int)$r['enddate'];
        }

        foreach ($accounts as &$a) {
            $loginKey            = strtolower($a['login']);
            $ch                  = $chMap[$loginKey] ?? null;

            $a['id']             = (int)$a['id'];
            $a['web_user_id']    = (int)$a['web_user_id'];
            $a['is_primary']     = (int)$a['is_primary'] === 1;
            $a['character_count']= $ch ? (int)$ch['char_count'] : 0;
            $a['any_online']     = $ch ? ((int)$ch['any_online'] === 1) : false;
            $a['last_access_ms'] = $ch ? (int)$ch['last_access']   : 0;
            $a['premium_end_ms'] = isset($pMap[$loginKey]) ? $pMap[$loginKey] : 0;
        }
        unset($a);
    }

    echo json_encode([
        'ok'   => true,
        'data' => $accounts,
    ]);

} catch (Throwable $e) {
    error_log('[admin/game_accounts_list] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'SERVER_ERROR']);
}
