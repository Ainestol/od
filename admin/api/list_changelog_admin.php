<?php
/**
 * Admin changelog list — vrací všechny záznamy včetně is_published=0,
 * obě jazykové verze, podle data DESC.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../api/admin/_bootstrap.php';

try {
    assert_admin();

    $limit  = (int)($_GET['limit']  ?? 200);
    $offset = (int)($_GET['offset'] ?? 0);
    if ($limit < 1) $limit = 200;
    if ($limit > 1000) $limit = 1000;
    if ($offset < 0) $offset = 0;

    $sql = "
        SELECT c.id, c.title_cs, c.title_en, c.body_cs, c.body_en, c.category,
               c.is_published, c.created_at, c.created_by,
               u.email AS admin_email
        FROM changelog c
        LEFT JOIN users u ON u.id = c.created_by
        ORDER BY c.created_at DESC
        LIMIT $limit OFFSET $offset
    ";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
        $r['is_published'] = (int)$r['is_published'];
        $r['created_by'] = $r['created_by'] !== null ? (int)$r['created_by'] : null;
    }
    unset($r);

    echo json_encode(['ok' => true, 'entries' => $rows]);

} catch (Throwable $e) {
    error_log('[admin/list_changelog_admin] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'SERVER_ERROR']);
}
