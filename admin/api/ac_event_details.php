<?php
/**
 * Admin Anti-Cheat: full details for one suspicious event.
 * Joins ac_economy_audit when applicable for ECONOMY-type events.
 *
 * GET ?id=<event_id>
 *
 * Response:
 *   {
 *     ok: true,
 *     event: { ... full ac_suspicious_events row, web_email, web_user_id ... },
 *     economy_audit: { ... matching ac_economy_audit row or null ... },
 *     parsed_context: { ... context_json parsed as object or null ... }
 *   }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../api/admin/_bootstrap.php';
require_once __DIR__ . '/../../config/db_game.php';     // $pdoGame
require_once __DIR__ . '/../../lib/skill_names.php';    // skill_name()

try {
    assert_admin();

    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'INVALID_ID']);
        exit;
    }

    // ─── Event row ────────────────────────────────
    $stmt = $pdoGame->prepare("
        SELECT *
        FROM ac_suspicious_events
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$event) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'NOT_FOUND']);
        exit;
    }

    // Type-cast
    $event['id']                = (int)$event['id'];
    $event['char_object_id']    = (int)$event['char_object_id'];
    $event['score_added']       = (int)$event['score_added'];
    $event['target_object_id']  = $event['target_object_id'] !== null ? (int)$event['target_object_id'] : null;
    $event['skill_id']          = $event['skill_id']         !== null ? (int)$event['skill_id']         : null;
    $event['item_id']           = $event['item_id']          !== null ? (int)$event['item_id']          : null;
    $event['time_delta_ms']     = $event['time_delta_ms']    !== null ? (int)$event['time_delta_ms']    : null;

    // ─── Parsed context_json ──────────────────────
    $parsedContext = null;
    if (!empty($event['context_json'])) {
        $tmp = json_decode($event['context_json'], true);
        if (is_array($tmp)) $parsedContext = $tmp;
    }

    // ─── Skill name (from cached XML index) ───────
    $event['skill_name'] = $event['skill_id'] ? skill_name((int)$event['skill_id']) : null;

    // ─── Web user resolve via account_name ────────
    $event['web_email']   = null;
    $event['web_user_id'] = null;
    if (!empty($event['account_name'])) {
        $s = $pdo->prepare("
            SELECT ga.web_user_id, u.email
            FROM game_accounts ga
            LEFT JOIN users u ON u.id = ga.web_user_id
            WHERE ga.login = ?
            LIMIT 1
        ");
        $s->execute([$event['account_name']]);
        $ctx = $s->fetch(PDO::FETCH_ASSOC);
        if ($ctx) {
            $event['web_email']   = $ctx['email'];
            $event['web_user_id'] = $ctx['web_user_id'] !== null ? (int)$ctx['web_user_id'] : null;
        }
    }

    // ─── Related economy audit (only for ECONOMY-flavored events) ───
    $economyAudit = null;
    $isEconomy = stripos((string)$event['event_type'], 'ECONOMY') !== false;
    if ($isEconomy) {
        // Match by char_object_id + closest event_time within ±10 seconds
        $s = $pdoGame->prepare("
            SELECT *,
                   ABS(TIMESTAMPDIFF(SECOND, event_time, ?)) AS time_diff_sec
            FROM ac_economy_audit
            WHERE char_object_id = ?
              AND event_time BETWEEN
                    DATE_SUB(?, INTERVAL 10 SECOND) AND DATE_ADD(?, INTERVAL 10 SECOND)
            ORDER BY time_diff_sec ASC
            LIMIT 1
        ");
        $s->execute([
            $event['event_time'],
            $event['char_object_id'],
            $event['event_time'],
            $event['event_time'],
        ]);
        $audit = $s->fetch(PDO::FETCH_ASSOC);
        if ($audit) {
            $audit['id']             = (int)$audit['id'];
            $audit['char_object_id'] = (int)$audit['char_object_id'];
            $audit['item_id']        = $audit['item_id']      !== null ? (int)$audit['item_id']      : null;
            $audit['item_count']     = $audit['item_count']   !== null ? (int)$audit['item_count']   : null;
            $audit['adena_delta']    = $audit['adena_delta']  !== null ? (int)$audit['adena_delta']  : null;
            $audit['npc_id']         = $audit['npc_id']       !== null ? (int)$audit['npc_id']       : null;
            $audit['is_suspicious']  = (int)$audit['is_suspicious'] === 1;
            $economyAudit = $audit;
        }
    }

    echo json_encode([
        'ok'             => true,
        'event'          => $event,
        'economy_audit'  => $economyAudit,
        'parsed_context' => $parsedContext,
    ]);

} catch (Throwable $e) {
    error_log('[admin/ac_event_details] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'SERVER_ERROR']);
}
