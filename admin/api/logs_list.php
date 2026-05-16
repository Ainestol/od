<?php
/**
 * Admin: system logs list with filters + pagination.
 *
 * GET params (all optional):
 *   q          — free-text search (action LIKE, meta LIKE, ip_address LIKE)
 *   log_type   — exact match (CSV for OR: 'VOTE,ADMIN')
 *   status     — exact match (CSV for OR: 'SUCCESS,FAIL')
 *   user_id    — exact match
 *   target_id  — exact match
 *   from       — created_at >= (datetime 'YYYY-MM-DD HH:MM:SS' or just date)
 *   to         — created_at <=
 *   sort       — created_at | action | status | log_type | id (default: created_at)
 *   dir        — asc | desc (default: desc)
 *   page       — 1-based (default: 1)
 *   per_page   — default 50, max 200
 *
 * Response:
 *   {
 *     ok: true,
 *     data: [{ id, log_type, action, user_id, target_id, status, ip_address, meta,
 *              created_at, user_email, target_email }, ...],
 *     total: int, page: int, per_page: int, pages: int,
 *     facets: { log_types: [...], statuses: [...] }   // distinct values for chips
 *   }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../api/admin/_bootstrap.php';

try {
    assert_admin();

    $q          = trim((string)($_GET['q'] ?? ''));
    $logTypeCsv = trim((string)($_GET['log_type'] ?? ''));
    $statusCsv  = trim((string)($_GET['status']   ?? ''));
    $userId     = isset($_GET['user_id'])   ? (int)$_GET['user_id']   : 0;
    $targetId   = isset($_GET['target_id']) ? (int)$_GET['target_id'] : 0;
    $from       = trim((string)($_GET['from'] ?? ''));
    $to         = trim((string)($_GET['to']   ?? ''));

    $sortWl = ['created_at','action','status','log_type','id'];
    $sort   = in_array(($_GET['sort'] ?? ''), $sortWl, true) ? $_GET['sort'] : 'created_at';
    $dir    = (($_GET['dir'] ?? '') === 'asc') ? 'ASC' : 'DESC';
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $perPage = max(10, min(200, (int)($_GET['per_page'] ?? 50)));
    $offset  = ($page - 1) * $perPage;

    $where  = [];
    $params = [];

    if ($q !== '') {
        $where[]  = '(action LIKE ? OR ip_address LIKE ? OR meta LIKE ?)';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }

    if ($logTypeCsv !== '') {
        $arr = array_values(array_filter(array_map('trim', explode(',', $logTypeCsv))));
        if (!empty($arr)) {
            $ph = implode(',', array_fill(0, count($arr), '?'));
            $where[] = "log_type IN ($ph)";
            foreach ($arr as $v) $params[] = $v;
        }
    }

    if ($statusCsv !== '') {
        $arr = array_values(array_filter(array_map('trim', explode(',', $statusCsv))));
        if (!empty($arr)) {
            $ph = implode(',', array_fill(0, count($arr), '?'));
            $where[] = "status IN ($ph)";
            foreach ($arr as $v) $params[] = $v;
        }
    }

    if ($userId > 0)   { $where[] = 'user_id = ?';   $params[] = $userId; }
    if ($targetId > 0) { $where[] = 'target_id = ?'; $params[] = $targetId; }
    if ($from !== '')  { $where[] = 'created_at >= ?'; $params[] = $from; }
    if ($to   !== '')  { $where[] = 'created_at <= ?'; $params[] = $to; }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM system_logs $whereSql");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $pages = $total > 0 ? (int)ceil($total / $perPage) : 1;

    // Page
    $sql = "
        SELECT id, log_type, action, user_id, target_id, status, ip_address, meta, created_at
        FROM system_logs
        $whereSql
        ORDER BY $sort $dir
        LIMIT $perPage OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // target_id má různý význam podle log_type:
    //   ADMIN_*, AUTH_* → web user ID  (resolvujeme na email)
    //   VOTE_*          → vote_site_id (NEresolvujeme — labelujeme zvlášť)
    //   Ostatní         → nejasné, neresolvujeme
    $userTargetKinds  = ['ADMIN','AUTH'];

    // Sbírej user_id z všech řádků + target_id jen u relevantních log_types
    $userIds   = [];
    $targetUserIds = [];
    foreach ($rows as $r) {
        if (!empty($r['user_id'])) $userIds[(int)$r['user_id']] = true;
        if (!empty($r['target_id']) && in_array($r['log_type'], $userTargetKinds, true)) {
            $targetUserIds[(int)$r['target_id']] = true;
        }
    }
    $idsToResolve = array_keys($userIds + $targetUserIds);

    $emailMap = [];
    if (!empty($idsToResolve)) {
        $ph = implode(',', array_fill(0, count($idsToResolve), '?'));
        $eStmt = $pdo->prepare("SELECT id, email FROM users WHERE id IN ($ph)");
        $eStmt->execute($idsToResolve);
        foreach ($eStmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $emailMap[(int)$u['id']] = $u['email'];
        }
    }

    foreach ($rows as &$r) {
        $r['id']        = (int)$r['id'];
        $r['user_id']   = $r['user_id']   !== null ? (int)$r['user_id']   : null;
        $r['target_id'] = $r['target_id'] !== null ? (int)$r['target_id'] : null;
        $r['user_email']   = $r['user_id'] ? ($emailMap[$r['user_id']] ?? null) : null;
        // Target — jen pro user-related log types
        $isUserTarget = in_array($r['log_type'], $userTargetKinds, true);
        $r['target_email'] = ($isUserTarget && $r['target_id'])
            ? ($emailMap[$r['target_id']] ?? null) : null;
        $r['target_kind']  = $isUserTarget ? 'user'
            : (str_starts_with((string)$r['log_type'], 'VOTE') ? 'vote_site' : 'other');
    }
    unset($r);

    // Facets — distinct log_types + statuses for chip generation (over all rows, not just current page)
    $facetTypes = $pdo->query("SELECT DISTINCT log_type FROM system_logs ORDER BY log_type ASC")
                      ->fetchAll(PDO::FETCH_COLUMN);
    $facetStatuses = $pdo->query("SELECT DISTINCT status FROM system_logs ORDER BY status ASC")
                         ->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'ok'       => true,
        'data'     => $rows,
        'total'    => $total,
        'page'     => $page,
        'per_page' => $perPage,
        'pages'    => $pages,
        'sort'     => $sort,
        'dir'      => strtolower($dir),
        'facets'   => [
            'log_types' => $facetTypes,
            'statuses'  => $facetStatuses,
        ],
    ]);

} catch (Throwable $e) {
    error_log('[admin/logs_list] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'SERVER_ERROR']);
}
