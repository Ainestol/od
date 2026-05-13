<?php
/**
 * Delete changelog entry.
 * POST JSON: { id: int }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../api/admin/_bootstrap.php';

try {
    assert_admin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'METHOD_NOT_ALLOWED']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;

    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'INVALID_ID']);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM changelog WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['ok' => false, 'error' => 'NOT_FOUND']);
        exit;
    }

    admin_audit($pdo, 'changelog_delete', null, ['id' => $id]);

    echo json_encode(['ok' => true, 'id' => $id]);

} catch (Throwable $e) {
    error_log('[admin/delete_changelog] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'SERVER_ERROR']);
}
