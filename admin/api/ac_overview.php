<?php
/**
 * Admin Anti-Cheat: overview / dashboard stats.
 *
 * GET — no params.
 *
 * Response:
 *   {
 *     ok: true,
 *     players: {
 *       high_risk: int,             // ac_player_risk.high_risk_flag = 1
 *       ban_recommended: int,
 *       manual_watch: int,
 *       total_with_score: int,
 *       top: [{ char_object_id, char_name, account_name, current_score, score_24h,
 *               total_events, high_risk_flag, ban_recommended, manual_watch_flag,
 *               web_email }, ...]
 *     },
 *     events: {
 *       new_total: int,             // review_status='NEW'
 *       last_24h: int,
 *       last_1h:  int,
 *       by_type: { TELEPORT: int, SPEED: int, RANGE: int, ECONOMY: int, ... },
 *       by_severity: { LOW: int, MEDIUM: int, HIGH: int, CRITICAL: int }
 *     }
 *   }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../api/admin/_bootstrap.php';
require_once __DIR__ . '/../../config/db_game.php'; // $pdoGame (l2_reader)

try {
    assert_admin();

    // ─── Player aggregates ─────────────────────────────────────
    $playerAgg = $pdoGame->query("
        SELECT
            SUM(CASE WHEN high_risk_flag    = 1 THEN 1 ELSE 0 END) AS high_risk,
            SUM(CASE WHEN ban_recommended   = 1 THEN 1 ELSE 0 END) AS ban_recommended,
            SUM(CASE WHEN manual_watch_flag = 1 THEN 1 ELSE 0 END) AS manual_watch,
            SUM(CASE WHEN current_score    > 0 THEN 1 ELSE 0 END) AS total_with_score
        FROM ac_player_risk
    ")->fetch(PDO::FETCH_ASSOC) ?: [];

    $players = [
        'high_risk'        => (int)($playerAgg['high_risk']        ?? 0),
        'ban_recommended'  => (int)($playerAgg['ban_recommended']  ?? 0),
        'manual_watch'     => (int)($playerAgg['manual_watch']     ?? 0),
        'total_with_score' => (int)($playerAgg['total_with_score'] ?? 0),
        'top'              => [],
    ];

    // Top 10 by current_score (most active risk)
    $topStmt = $pdoGame->query("
        SELECT
            char_object_id, account_name, char_name,
            current_score, score_24h, total_events,
            teleport_events, speed_events, range_events, economy_events,
            high_risk_flag, ban_recommended, manual_watch_flag,
            last_event_time
        FROM ac_player_risk
        WHERE current_score > 0
        ORDER BY current_score DESC, score_24h DESC
        LIMIT 10
    ");
    $top = $topStmt->fetchAll(PDO::FETCH_ASSOC);

    // ─── Event aggregates ──────────────────────────────────────
    $evAgg = $pdoGame->query("
        SELECT
            SUM(CASE WHEN review_status = 'NEW'                                          THEN 1 ELSE 0 END) AS new_total,
            SUM(CASE WHEN event_time >= NOW() - INTERVAL 24 HOUR                         THEN 1 ELSE 0 END) AS last_24h,
            SUM(CASE WHEN event_time >= NOW() - INTERVAL  1 HOUR                         THEN 1 ELSE 0 END) AS last_1h
        FROM ac_suspicious_events
    ")->fetch(PDO::FETCH_ASSOC) ?: [];

    // Breakdown by event_type — split into ACTIVE (NEW) and ALL (last 7 days)
    // Exclude ADMIN_PRIVILEGE_EVENT from both (it's audit, not suspicion — separate stat below)
    $byTypeAllStmt = $pdoGame->query("
        SELECT event_type, COUNT(*) AS cnt
        FROM ac_suspicious_events
        WHERE event_time >= NOW() - INTERVAL 7 DAY
          AND event_type != 'ADMIN_PRIVILEGE_EVENT'
        GROUP BY event_type
        ORDER BY cnt DESC
    ");
    $byTypeAll = [];
    foreach ($byTypeAllStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $byTypeAll[$r['event_type']] = (int)$r['cnt'];
    }

    $byTypeNewStmt = $pdoGame->query("
        SELECT event_type, COUNT(*) AS cnt
        FROM ac_suspicious_events
        WHERE event_time >= NOW() - INTERVAL 7 DAY
          AND event_type != 'ADMIN_PRIVILEGE_EVENT'
          AND review_status = 'NEW'
        GROUP BY event_type
        ORDER BY cnt DESC
    ");
    $byTypeNew = [];
    foreach ($byTypeNewStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $byTypeNew[$r['event_type']] = (int)$r['cnt'];
    }

    $bySevAllStmt = $pdoGame->query("
        SELECT severity, COUNT(*) AS cnt
        FROM ac_suspicious_events
        WHERE event_time >= NOW() - INTERVAL 7 DAY
          AND event_type != 'ADMIN_PRIVILEGE_EVENT'
        GROUP BY severity
    ");
    $bySevAll = [];
    foreach ($bySevAllStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $bySevAll[$r['severity']] = (int)$r['cnt'];
    }

    $bySevNewStmt = $pdoGame->query("
        SELECT severity, COUNT(*) AS cnt
        FROM ac_suspicious_events
        WHERE event_time >= NOW() - INTERVAL 7 DAY
          AND event_type != 'ADMIN_PRIVILEGE_EVENT'
          AND review_status = 'NEW'
        GROUP BY severity
    ");
    $bySevNew = [];
    foreach ($bySevNewStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $bySevNew[$r['severity']] = (int)$r['cnt'];
    }

    // Admin audit count (separate — not suspicion)
    $adminAuditStmt = $pdoGame->query("
        SELECT COUNT(*) FROM ac_suspicious_events
        WHERE event_type = 'ADMIN_PRIVILEGE_EVENT'
          AND event_time >= NOW() - INTERVAL 7 DAY
    ");
    $adminAudit7d = (int)$adminAuditStmt->fetchColumn();

    $events = [
        'new_total'          => (int)($evAgg['new_total'] ?? 0),
        'last_24h'           => (int)($evAgg['last_24h']  ?? 0),
        'last_1h'            => (int)($evAgg['last_1h']   ?? 0),
        'admin_audit_7d'     => $adminAudit7d,
        'by_type_all'        => $byTypeAll,
        'by_type_new'        => $byTypeNew,
        'by_severity_all'    => $bySevAll,
        'by_severity_new'    => $bySevNew,
    ];

    // ─── Resolve web user emails for top players ──────────────
    if (!empty($top)) {
        $accounts = array_unique(array_filter(array_column($top, 'account_name')));
        $accMap = [];
        if (!empty($accounts)) {
            $ph = implode(',', array_fill(0, count($accounts), '?'));
            $s = $pdo->prepare("
                SELECT ga.login, u.email, ga.web_user_id
                FROM game_accounts ga
                LEFT JOIN users u ON u.id = ga.web_user_id
                WHERE ga.login IN ($ph)
            ");
            $s->execute(array_values($accounts));
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $accMap[strtolower($r['login'])] = [
                    'email'       => $r['email'],
                    'web_user_id' => (int)$r['web_user_id'],
                ];
            }
        }
        foreach ($top as &$t) {
            $key = strtolower((string)$t['account_name']);
            $ctx = $accMap[$key] ?? null;
            $t['char_object_id']    = (int)$t['char_object_id'];
            $t['current_score']     = (int)$t['current_score'];
            $t['score_24h']         = (int)$t['score_24h'];
            $t['total_events']      = (int)$t['total_events'];
            $t['teleport_events']   = (int)$t['teleport_events'];
            $t['speed_events']      = (int)$t['speed_events'];
            $t['range_events']      = (int)$t['range_events'];
            $t['economy_events']    = (int)$t['economy_events'];
            $t['high_risk_flag']    = (int)$t['high_risk_flag'] === 1;
            $t['ban_recommended']   = (int)$t['ban_recommended'] === 1;
            $t['manual_watch_flag'] = (int)$t['manual_watch_flag'] === 1;
            $t['web_email']         = $ctx['email'] ?? null;
            $t['web_user_id']       = $ctx['web_user_id'] ?? null;
        }
        unset($t);
        $players['top'] = $top;
    }

    echo json_encode([
        'ok'      => true,
        'players' => $players,
        'events'  => $events,
    ]);

} catch (Throwable $e) {
    error_log('[admin/ac_overview] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'SERVER_ERROR']);
}
