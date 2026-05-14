<?php
/**
 * Admin Anti-Cheat: players with risk score — list + filter + sort.
 *
 * GET params (all optional):
 *   q       — search (char_name OR account_name LIKE)
 *   filter  — CSV of: high_risk | ban_recommended | manual_watch | active_24h
 *   sort    — current_score | score_24h | total_events | last_event_time
 *             teleport_events | speed_events | range_events | economy_events
 *   dir     — asc | desc (default desc)
 *   limit   — default 200, max 1000
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../api/admin/_bootstrap.php';
require_once __DIR__ . '/../../config/db_game.php'; // $pdoGame

try {
    assert_admin();

    $q       = trim((string)($_GET['q'] ?? ''));
    $filters = array_values(array_filter(array_map(
        'trim',
        explode(',', (string)($_GET['filter'] ?? ''))
    )));
    $sortWl = ['current_score','score_24h','total_events','last_event_time',
               'teleport_events','speed_events','range_events','economy_events'];
    $sort = in_array(($_GET['sort'] ?? ''), $sortWl, true) ? $_GET['sort'] : 'current_score';
    $dir  = (($_GET['dir'] ?? '') === 'asc') ? 'ASC' : 'DESC';
    $limit = max(1, min(1000, (int)($_GET['limit'] ?? 200)));

    $where  = [];
    $params = [];

    if ($q !== '') {
        $where[] = '(char_name LIKE ? OR account_name LIKE ?)';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }

    foreach ($filters as $f) {
        switch ($f) {
            case 'high_risk':       $where[] = 'high_risk_flag = 1';    break;
            case 'ban_recommended': $where[] = 'ban_recommended = 1';   break;
            case 'manual_watch':    $where[] = 'manual_watch_flag = 1'; break;
            case 'active_24h':      $where[] = 'last_event_time >= NOW() - INTERVAL 24 HOUR'; break;
        }
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $sql = "
        SELECT
            char_object_id, account_name, char_name,
            current_score, score_24h, total_events,
            teleport_events, speed_events, range_events, economy_events,
            last_event_time, high_risk_flag, manual_watch_flag,
            ban_recommended, updated_at
        FROM ac_player_risk
        $whereSql
        ORDER BY $sort $dir
        LIMIT $limit
    ";
    $stmt = $pdoGame->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Resolve web email per account
    if (!empty($rows)) {
        $accounts = array_unique(array_filter(array_column($rows, 'account_name')));
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
        foreach ($rows as &$r) {
            $key = strtolower((string)$r['account_name']);
            $ctx = $accMap[$key] ?? null;
            $r['char_object_id']    = (int)$r['char_object_id'];
            $r['current_score']     = (int)$r['current_score'];
            $r['score_24h']         = (int)$r['score_24h'];
            $r['total_events']      = (int)$r['total_events'];
            $r['teleport_events']   = (int)$r['teleport_events'];
            $r['speed_events']      = (int)$r['speed_events'];
            $r['range_events']      = (int)$r['range_events'];
            $r['economy_events']    = (int)$r['economy_events'];
            $r['high_risk_flag']    = (int)$r['high_risk_flag'] === 1;
            $r['ban_recommended']   = (int)$r['ban_recommended'] === 1;
            $r['manual_watch_flag'] = (int)$r['manual_watch_flag'] === 1;
            $r['web_email']         = $ctx['email'] ?? null;
            $r['web_user_id']       = $ctx['web_user_id'] ?? null;
        }
        unset($r);
    }

    echo json_encode([
        'ok'      => true,
        'data'    => $rows,
        'total'   => count($rows),
        'q'       => $q,
        'filters' => $filters,
        'sort'    => $sort,
        'dir'     => strtolower($dir),
    ]);

} catch (Throwable $e) {
    error_log('[admin/ac_players_list] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'SERVER_ERROR']);
}
