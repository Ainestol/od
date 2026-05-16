<?php
/**
 * Admin Anti-Cheat: bulk update NEW events to IGNORED for given filters.
 *
 * POST JSON:
 *   {
 *     event_type?: string,          // exact match
 *     severity?: string,
 *     char_id?: int,                // exact char_object_id
 *     account?: string,             // exact account_name
 *     from?: datetime,              // event_time >=
 *     to?: datetime,                // event_time <=
 *     note?: string                 // review_note (default "Bulk admin ignore")
 *   }
 *
 * Only events with review_status = 'NEW' are updated. Returns count.
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

    $eventType = trim((string)($input['event_type'] ?? ''));
    $severity  = trim((string)($input['severity']   ?? ''));
    $charId    = isset($input['char_id']) ? (int)$input['char_id'] : 0;
    $account   = trim((string)($input['account'] ?? ''));
    $from      = trim((string)($input['from'] ?? ''));
    $to        = trim((string)($input['to'] ?? ''));
    $note      = trim((string)($input['note'] ?? ''));

    if ($note === '') {
        $note = 'Bulk admin ignore';
    }
    if (mb_strlen($note) > 512) {
        $note = mb_substr($note, 0, 512);
    }

    // Safety guard — vyžadujeme aspoň jeden filtr, aby si admin omylem
    // neoznačil celou tabulku jediným kliknutím.
    if ($eventType === '' && $severity === '' && $charId <= 0
        && $account === '' && $from === '' && $to === '')
    {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'FILTERS_REQUIRED']);
        exit;
    }

    $where  = ["review_status = 'NEW'"];
    $params = [];

    if ($eventType !== '') {
        $where[]  = 'event_type = ?';
        $params[] = $eventType;
    }
    if ($severity !== '') {
        $where[]  = 'severity = ?';
        $params[] = $severity;
    }
    if ($charId > 0) {
        $where[]  = 'char_object_id = ?';
        $params[] = $charId;
    }
    if ($account !== '') {
        $where[]  = 'account_name = ?';
        $params[] = $account;
    }
    if ($from !== '') {
        $where[]  = 'event_time >= ?';
        $params[] = $from;
    }
    if ($to !== '') {
        $where[]  = 'event_time <= ?';
        $params[] = $to;
    }

    $sql = "
        UPDATE ac_suspicious_events
        SET review_status = 'IGNORED',
            review_note   = ?
        WHERE " . implode(' AND ', $where);
    $stmt = $pdoGameWrite->prepare($sql);
    $stmt->execute(array_merge([$note], $params));
    $updated = $stmt->rowCount();

    admin_audit($pdo, 'ac_bulk_ignore', null, [
        'event_type' => $eventType ?: null,
        'severity'   => $severity  ?: null,
        'char_id'    => $charId    ?: null,
        'account'    => $account   ?: null,
        'from'       => $from      ?: null,
        'to'         => $to        ?: null,
        'updated'    => $updated,
    ]);

    echo json_encode([
        'ok'      => true,
        'updated' => $updated,
    ]);

} catch (Throwable $e) {
    error_log('[admin/ac_bulk_ignore] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'SERVER_ERROR']);
}
