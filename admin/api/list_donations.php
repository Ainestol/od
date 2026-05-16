<?php
/**
 * Admin: list donations s filtry.
 *
 * GET parametry (vše volitelné):
 *   status      — 'pending' | 'approved' | 'rejected' | 'all' (default: 'pending')
 *   user_id     — filtr na konkrétního hráče
 *   from        — YYYY-MM-DD, dolní mez paid_at
 *   to          — YYYY-MM-DD, horní mez paid_at
 *   limit       — max počet záznamů (default 200, max 1000)
 *   range       — 'last30' | 'all' (default 'last30' pro status != pending)
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../api/admin/_bootstrap.php';

try {
    assert_admin();

    $status   = $_GET['status']  ?? 'pending';
    $userId   = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
    $from     = $_GET['from']    ?? null;
    $to       = $_GET['to']      ?? null;
    $limit    = (int)($_GET['limit'] ?? 200);
    $range    = $_GET['range']   ?? null;

    if ($limit < 1) $limit = 200;
    if ($limit > 1000) $limit = 1000;

    $allowedStatus = ['pending', 'approved', 'rejected', 'all'];
    if (!in_array($status, $allowedStatus, true)) {
        $status = 'pending';
    }

    if ($range === null || $range === '') {
        $range = ($status === 'pending') ? 'all' : 'last30';
    }

    $where  = [];
    $params = [];

    if ($status !== 'all') {
        $where[]  = "d.status = ?";
        $params[] = $status;
    }
    if ($userId) {
        $where[]  = "d.web_user_id = ?";
        $params[] = $userId;
    }
    if ($from) {
        $where[]  = "d.paid_at >= ?";
        $params[] = $from;
    }
    if ($to) {
        $where[]  = "d.paid_at <= ?";
        $params[] = $to;
    }
    if ($range === 'last30') {
        $where[]  = "d.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $sql = "
        SELECT
            d.id, d.web_user_id, d.amount, d.currency, d.paid_at,
            d.variable_symbol, d.note, d.lang, d.status, d.dc_credited,
            d.admin_note, d.created_at, d.reviewed_at, d.reviewed_by,
            u.email AS user_email,
            au.email AS admin_email
        FROM donations d
        LEFT JOIN users u  ON u.id  = d.web_user_id
        LEFT JOIN users au ON au.id = d.reviewed_by
        $whereSql
        ORDER BY d.created_at DESC
        LIMIT $limit
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $r['id']          = (int)$r['id'];
        $r['web_user_id'] = (int)$r['web_user_id'];
        $r['amount']      = (int)$r['amount'];
        $r['dc_credited'] = (int)$r['dc_credited'];
        $r['reviewed_by'] = $r['reviewed_by'] !== null ? (int)$r['reviewed_by'] : null;
    }
    unset($r);

    // ─── Summary — vždy přes všechny APPROVED v rámci date/user filtrů ──
    //     (nezávisle na statusu, který filtruje listu nahoře)
    $sumWhere  = ["d.status = 'approved'"];
    $sumParams = [];
    if ($userId) {
        $sumWhere[]  = "d.web_user_id = ?";
        $sumParams[] = $userId;
    }
    if ($from) {
        $sumWhere[]  = "d.paid_at >= ?";
        $sumParams[] = $from;
    }
    if ($to) {
        $sumWhere[]  = "d.paid_at <= ?";
        $sumParams[] = $to;
    }
    if ($range === 'last30') {
        $sumWhere[]  = "d.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    }
    $sumSql = "
        SELECT
            COUNT(*) AS approved_count,
            COALESCE(SUM(CASE WHEN d.currency = 'CZK' THEN d.amount ELSE 0 END), 0) AS approved_czk,
            COALESCE(SUM(CASE WHEN d.currency = 'EUR' THEN d.amount ELSE 0 END), 0) AS approved_eur,
            COALESCE(SUM(d.dc_credited), 0) AS approved_dc
        FROM donations d
        WHERE " . implode(' AND ', $sumWhere);
    $sumStmt = $pdo->prepare($sumSql);
    $sumStmt->execute($sumParams);
    $sumRow = $sumStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $approvedCount = (int)($sumRow['approved_count'] ?? 0);
    $approvedTotal = (int)($sumRow['approved_czk']   ?? 0);
    $approvedEur   = (int)($sumRow['approved_eur']   ?? 0);
    $approvedDc    = (int)($sumRow['approved_dc']    ?? 0);

    echo json_encode([
        'ok'        => true,
        'donations' => $rows,
        'summary'   => [
            'approved_count' => $approvedCount,
            'approved_czk'   => $approvedTotal,
            'approved_eur'   => $approvedEur,
            'approved_dc'    => $approvedDc,
        ],
        'filter' => [
            'status'  => $status,
            'range'   => $range,
            'from'    => $from,
            'to'      => $to,
            'user_id' => $userId
        ]
    ]);

} catch (Throwable $e) {
    error_log('[admin/list_donations] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'SERVER_ERROR']);
}
