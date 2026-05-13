<?php
/**
 * Admin: list of VIP levels + per-scope price map (for level dropdown).
 *
 * GET — no params.
 *
 * Response:
 *   {
 *     ok: true,
 *     levels: [{ level_id, name, is_active }, ...],
 *     prices: {
 *       "WEB":  { "3": { currency, price, duration_days } },
 *       "GAME": { "2": {...} },
 *       "CHAR": { "1": {...} }
 *     }
 *   }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/_bootstrap.php';

try {
    assert_admin();

    $levels = $pdo->query("
        SELECT level_id, name, is_active
        FROM vip_levels
        WHERE is_active = 1
        ORDER BY level_id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $prices = $pdo->query("
        SELECT scope, level_id, currency, price, duration_days
        FROM vip_prices
    ")->fetchAll(PDO::FETCH_ASSOC);

    $priceMap = ['WEB' => [], 'GAME' => [], 'CHAR' => []];
    foreach ($prices as $p) {
        $priceMap[$p['scope']][(int)$p['level_id']] = [
            'currency'      => $p['currency'],
            'price'         => (int)$p['price'],
            'duration_days' => (int)$p['duration_days'],
        ];
    }

    foreach ($levels as &$l) {
        $l['level_id']  = (int)$l['level_id'];
        $l['is_active'] = (int)$l['is_active'] === 1;
    }
    unset($l);

    echo json_encode([
        'ok'     => true,
        'levels' => $levels,
        'prices' => $priceMap,
    ]);

} catch (Throwable $e) {
    error_log('[admin/vip_levels_list] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'SERVER_ERROR']);
}
