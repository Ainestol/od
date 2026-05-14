<?php
/**
 * Admin: online players + offline-trade shops + stats.
 *
 * GET — no params.
 *
 * Response:
 *   {
 *     ok: true,
 *     online: [{ charId, char_name, level, classid, account_name, onlineTime,
 *                is_gm, web_email, web_user_id }, ...],
 *     offline_trade: [{ charId, char_name, level, classid, account_name,
 *                       title, type, since_ms, web_email, web_user_id }, ...],
 *     stats: {
 *       online_total, offline_trade_total, online_gm,
 *       online_unique_web_users,
 *       online_by_level: { "1-39":n, "40-59":n, "60-79":n, "80+":n }
 *     }
 *   }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../api/admin/_bootstrap.php';
require_once __DIR__ . '/../../config/db_game.php';      // $pdoGameStatus (online read)

try {
    assert_admin();

    // ─── ONLINE chars ────────────────────────────────────────
    $stmt = $pdoGameStatus->query("
        SELECT
            c.charId,
            c.char_name,
            c.level,
            c.classid,
            c.account_name,
            c.onlineTime,
            IF(cv.val = '100', 1, 0) AS is_gm
        FROM characters c
        LEFT JOIN character_variables cv
               ON cv.charId = c.charId
              AND cv.var = 'accesslevel'
        WHERE c.online = 1
        ORDER BY c.level DESC, c.char_name ASC
    ");
    $online = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ─── OFFLINE TRADE chars (joined to characters for level/class info) ──
    $stmt = $pdoGameStatus->query("
        SELECT
            cot.charId,
            cot.title,
            cot.type,
            cot.time AS since_ms,
            c.char_name,
            c.level,
            c.classid,
            c.account_name
        FROM character_offline_trade cot
        LEFT JOIN characters c ON c.charId = cot.charId
        ORDER BY cot.time DESC
    ");
    $offlineTrade = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ─── Resolve web user email per account_name (one query for both sets) ─
    $accountNames = array_unique(array_merge(
        array_column($online, 'account_name'),
        array_column($offlineTrade, 'account_name')
    ));
    $accountNames = array_filter($accountNames, fn($v) => $v !== null && $v !== '');

    $accMap = []; // login => ['web_user_id' => int, 'email' => string]
    if (!empty($accountNames)) {
        $ph = implode(',', array_fill(0, count($accountNames), '?'));
        $s = $pdo->prepare("
            SELECT ga.login, ga.web_user_id, u.email
            FROM game_accounts ga
            LEFT JOIN users u ON u.id = ga.web_user_id
            WHERE ga.login IN ($ph)
        ");
        $s->execute(array_values($accountNames));
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $accMap[strtolower($r['login'])] = [
                'web_user_id' => (int)$r['web_user_id'],
                'email'       => $r['email'],
            ];
        }
    }

    // Helper to enrich rows
    $enrich = function(array $rows) use ($accMap): array {
        foreach ($rows as &$r) {
            $key = strtolower((string)($r['account_name'] ?? ''));
            $ctx = $accMap[$key] ?? null;
            $r['charId']       = isset($r['charId'])     ? (int)$r['charId']     : 0;
            $r['level']        = isset($r['level'])      ? (int)$r['level']      : 0;
            $r['classid']      = isset($r['classid'])    ? (int)$r['classid']    : 0;
            if (isset($r['onlineTime'])) $r['onlineTime'] = (int)$r['onlineTime'];
            if (isset($r['is_gm']))      $r['is_gm']     = (int)$r['is_gm'] === 1;
            if (isset($r['type']))       $r['type']      = (int)$r['type'];
            if (isset($r['since_ms']))   $r['since_ms']  = (int)$r['since_ms'];
            $r['web_email']    = $ctx['email']       ?? null;
            $r['web_user_id']  = $ctx['web_user_id'] ?? null;
        }
        unset($r);
        return $rows;
    };

    $online       = $enrich($online);
    $offlineTrade = $enrich($offlineTrade);

    // ─── Stats ──────────────────────────────────────────────
    $stats = [
        'online_total'             => count($online),
        'offline_trade_total'      => count($offlineTrade),
        'online_gm'                => 0,
        'online_unique_web_users'  => 0,
        'online_by_level'          => ['1-39'=>0, '40-59'=>0, '60-79'=>0, '80+'=>0],
    ];
    $webUidSet = [];
    foreach ($online as $p) {
        if (!empty($p['is_gm'])) $stats['online_gm']++;
        if (!empty($p['web_user_id'])) $webUidSet[$p['web_user_id']] = true;
        $lv = (int)$p['level'];
        if      ($lv >= 80) $stats['online_by_level']['80+']++;
        else if ($lv >= 60) $stats['online_by_level']['60-79']++;
        else if ($lv >= 40) $stats['online_by_level']['40-59']++;
        else                $stats['online_by_level']['1-39']++;
    }
    $stats['online_unique_web_users'] = count($webUidSet);

    echo json_encode([
        'ok'            => true,
        'online'        => $online,
        'offline_trade' => $offlineTrade,
        'stats'         => $stats,
    ]);

} catch (Throwable $e) {
    error_log('[admin/list_online_players] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'SERVER_ERROR',
    ]);
}
