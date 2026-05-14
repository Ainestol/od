<?php
/**
 * Admin Anti-Cheat: update review_status / review_note of a suspicious event.
 *
 * POST JSON:
 *   {
 *     event_id: int,
 *     review_status: 'NEW' | 'REVIEWED' | 'IGNORED' | 'CONFIRMED',
 *     review_note?: string (max 512 chars)
 *   }
 *
 * Pozn.: vyžaduje UPDATE grant pro $pdoGameWrite (od_shop) na ac_suspicious_events.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../api/admin/_bootstrap.php';
require_once __DIR__ . '/../../config/db_game_write.php'; // $pdoGameWrite

try {
    assert_admin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'METHOD_NOT_ALLOWED']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;

    $eventId      = (int)($input['event_id'] ?? 0);
    $reviewStatus = strtoupper(trim((string)($input['review_status'] ?? '')));
    $reviewNote   = trim((string)($input['review_note'] ?? ''));

    if ($eventId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'INVALID_EVENT_ID']);
        exit;
    }

    $allowed = ['NEW','REVIEWED','IGNORED','CONFIRMED'];
    if (!in_array($reviewStatus, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'INVALID_STATUS']);
        exit;
    }

    if (mb_strlen($reviewNote) > 512) {
        $reviewNote = mb_substr($reviewNote, 0, 512);
    }

    $stmt = $pdoGameWrite->prepare("
        UPDATE ac_suspicious_events
        SET review_status = ?,
            review_note   = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $reviewStatus,
        $reviewNote !== '' ? $reviewNote : null,
        $eventId,
    ]);

    if ($stmt->rowCount() === 0) {
        // Buď event neexistuje, nebo hodnoty se nezměnily — pro UX vrátíme 200 ok=true
        // s flagem changed=false.
        echo json_encode(['ok' => true, 'changed' => false]);
        exit;
    }

    admin_audit($pdo, 'ac_event_review', null, [
        'event_id'      => $eventId,
        'review_status' => $reviewStatus,
        'has_note'      => $reviewNote !== '',
    ]);

    echo json_encode(['ok' => true, 'changed' => true]);

} catch (Throwable $e) {
    error_log('[admin/ac_event_review] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'SERVER_ERROR']);
}
