<?php
/**
 * List donations submitted by the currently logged-in user.
 *
 * GET — žádné parametry
 *
 * Odpověď:
 *   200 { ok: true, donations: [ {id, amount, currency, paid_at, variable_symbol,
 *                                  note, status, dc_credited, admin_note,
 *                                  created_at, reviewed_at}, ... ] }
 *   401 { ok: false, error: 'NOT_LOGGED_IN' }
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/_bootstrap.php';

try {
    if (empty($_SESSION['web_user_id'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'NOT_LOGGED_IN']);
        exit;
    }
    $userId = (int)$_SESSION['web_user_id'];

    $stmt = $pdo->prepare("
        SELECT
            id,
            amount,
            currency,
            paid_at,
            variable_symbol,
            note,
            status,
            dc_credited,
            admin_note,
            created_at,
            reviewed_at
        FROM donations
        WHERE web_user_id = ?
        ORDER BY created_at DESC
        LIMIT 100
    ");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // amount typujeme jako int (PDO vrací string z UNSIGNED INT)
    foreach ($rows as &$r) {
        $r['amount']      = (int)$r['amount'];
        $r['dc_credited'] = (int)$r['dc_credited'];
    }
    unset($r);

    // Compute the NEXT expected variable symbol for current year
    // Format: userId + currentYear + nextSeq (kde nextSeq = počet existujících platby v daném roce + 1)
    $currentYear = (int)date('Y');
    $seqStmt = $pdo->prepare("
        SELECT COUNT(*) FROM donations
        WHERE web_user_id = ?
          AND YEAR(paid_at) = ?
    ");
    $seqStmt->execute([$userId, $currentYear]);
    $nextSeq = (int)$seqStmt->fetchColumn() + 1;
    $nextVs  = $userId . $currentYear . $nextSeq;

    echo json_encode([
        'ok'        => true,
        'donations' => $rows,
        'web_id'    => $userId,
        'next_vs'   => $nextVs,
        'next_seq'  => $nextSeq,
        'year'      => $currentYear,
    ]);

} catch (Throwable $e) {
    error_log('[list_my_donations] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'SERVER_ERROR']);
}
