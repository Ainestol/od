<?php
/**
 * Admin economy stats — totals + holders count per currency.
 *
 * GET — no params.
 *
 * Response:
 *   {
 *     ok: true,
 *     totals: { VOTE_COIN: 198, DC: 305 },
 *     holders: { VOTE_COIN: 7, DC: 4 }
 *   }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../api/admin/_bootstrap.php';

try {
    assert_admin();

    $stmt = $pdo->query("
        SELECT
            currency,
            SUM(balance)              AS total,
            COUNT(DISTINCT owner_id)  AS holders
        FROM wallet_balances
        WHERE owner_type = 'WEB'
          AND balance > 0
        GROUP BY currency
    ");

    $totals  = [];
    $holders = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $totals [$r['currency']] = (int)$r['total'];
        $holders[$r['currency']] = (int)$r['holders'];
    }

    echo json_encode([
        'ok'      => true,
        'totals'  => $totals,
        'holders' => $holders,
    ]);

} catch (Throwable $e) {
    error_log('[admin/economy_stats] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'SERVER_ERROR']);
}
