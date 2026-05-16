<?php
/**
 * Admin Anti-Cheat: suspicious events list + filter + sort.
 *
 * GET params (all optional):
 *   q              — search (char_name OR account_name LIKE)
 *   event_type     — TELEPORT | SPEED | RANGE | ECONOMY | ... (exact)
 *   severity       — LOW | MEDIUM | HIGH | CRITICAL
 *   review_status  — NEW | REVIEWED | IGNORED | CONFIRMED  (CSV)
 *   char_id        — specific char_object_id
 *   account        — specific account_name
 *   from           — datetime 'YYYY-MM-DD HH:MM:SS' (event_time >=)
 *   to             — datetime               (event_time <=)
 *   sort           — event_time | severity | score_added (default event_time)
 *   dir            — asc | desc (default desc)
 *   page           — 1-based page number (default 1)
 *   per_page       — page size (default 50, min 10, max 200)
 *
 * Response:
 *   { ok: true, data: [...], total: int, page: int, per_page: int, pages: int }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../api/admin/_bootstrap.php';
require_once __DIR__ . '/../../config/db_game.php';

try {
    assert_admin();

    $q             = trim((string)($_GET['q'] ?? ''));
    $eventType     = trim((string)($_GET['event_type'] ?? ''));
    $severity      = trim((string)($_GET['severity'] ?? ''));
    $reviewCsv     = trim((string)($_GET['review_status'] ?? ''));
    $reviewStatuses = array_values(array_filter(array_map('trim', explode(',', $reviewCsv))));
    $charId        = isset($_GET['char_id']) ? (int)$_GET['char_id'] : 0;
    $accountName   = trim((string)($_GET['account'] ?? ''));
    $from          = trim((string)($_GET['from'] ?? ''));
    $to            = trim((string)($_GET['to'] ?? ''));

    $sortWl = ['event_time','severity','score_added','event_type','char_name','account_name'];
    $sort   = in_array(($_GET['sort'] ?? ''), $sortWl, true) ? $_GET['sort'] : 'event_time';
    $dir    = (($_GET['dir'] ?? '') === 'asc') ? 'ASC' : 'DESC';
    $page   = max(1, (int)($_GET['page']     ?? 1));
    $perPage = max(10, min(200, (int)($_GET['per_page'] ?? 50)));
    $offset = ($page - 1) * $perPage;

    $where  = [];
    $params = [];

    if ($q !== '') {
        $where[] = '(char_name LIKE ? OR account_name LIKE ?)';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
    if ($eventType !== '') {
        $where[] = 'event_type = ?';
        $params[] = $eventType;
    }
    if ($severity !== '') {
        $where[] = 'severity = ?';
        $params[] = $severity;
    }
    if (!empty($reviewStatuses)) {
        $ph = implode(',', array_fill(0, count($reviewStatuses), '?'));
        $where[] = "review_status IN ($ph)";
        foreach ($reviewStatuses as $s) $params[] = $s;
    }
    if ($charId > 0) {
        $where[] = 'char_object_id = ?';
        $params[] = $charId;
    }
    if ($accountName !== '') {
        $where[] = 'account_name = ?';
        $params[] = $accountName;
    }
    if ($from !== '') {
        $where[] = 'event_time >= ?';
        $params[] = $from;
    }
    if ($to !== '') {
        $where[] = 'event_time <= ?';
        $params[] = $to;
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    // Count first — pro pagination UI
    $countSql  = "SELECT COUNT(*) FROM ac_suspicious_events $whereSql";
    $countStmt = $pdoGame->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $pages = $total > 0 ? (int)ceil($total / $perPage) : 1;

    // Stránka samotná
    $sql = "
        SELECT
            id, event_time, account_name, char_name, char_object_id,
            ip_address, hwid_hash, event_type, severity, score_added,
            x_from, y_from, z_from, x_to, y_to, z_to,
            distance, time_delta_ms, expected_speed, effective_speed,
            target_object_id, skill_id, item_id, context_json,
            review_status, review_note
        FROM ac_suspicious_events
        $whereSql
        ORDER BY $sort $dir
        LIMIT $perPage OFFSET $offset
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
            $r['id']              = (int)$r['id'];
            $r['char_object_id']  = (int)$r['char_object_id'];
            $r['score_added']     = (int)$r['score_added'];
            $r['time_delta_ms']   = $r['time_delta_ms']   !== null ? (int)$r['time_delta_ms']   : null;
            $r['target_object_id']= $r['target_object_id']!== null ? (int)$r['target_object_id']: null;
            $r['skill_id']        = $r['skill_id']        !== null ? (int)$r['skill_id']        : null;
            $r['item_id']         = $r['item_id']         !== null ? (int)$r['item_id']         : null;
            $r['web_email']       = $ctx['email'] ?? null;
            $r['web_user_id']     = $ctx['web_user_id'] ?? null;
        }
        unset($r);
    }

    echo json_encode([
        'ok'       => true,
        'data'     => $rows,
        'total'    => $total,
        'page'     => $page,
        'per_page' => $perPage,
        'pages'    => $pages,
        'sort'     => $sort,
        'dir'      => strtolower($dir),
    ]);

} catch (Throwable $e) {
    error_log('[admin/ac_events_list] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'SERVER_ERROR']);
}
