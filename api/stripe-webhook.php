<?php
// ============================================================
// 🚫 STRIPE WEBHOOK DEAKTIVOVÁN (Stripe zavřel účet)
// ------------------------------------------------------------
// Endpoint je vypnutý. Původní logika níže zachována
// pro případné pozdější znovuzapnutí.
// ============================================================
http_response_code(410);
exit('Webhook disabled');
// ============================================================
// ⬇️ PŮVODNÍ KÓD – NEAKTIVNÍ (ponecháno jako reference)
// ============================================================
/*
file_put_contents(__DIR__.'/HIT.log', "HIT\n", FILE_APPEND);
require_once __DIR__ . '/../vendor/autoload.php';

$env = parse_ini_file('/var/www/.env');
$endpoint_secret = $env['STRIPE_WEBHOOK_SECRET'] ?? '';

$payload    = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
} catch (\Exception $e) {
    file_put_contents(__DIR__.'/webhook_error.log', "SIGNATURE ERROR: ".$e->getMessage().PHP_EOL, FILE_APPEND);
    http_response_code(400);
    exit('Invalid signature');
}

if (!$event->livemode) {
    http_response_code(200);
    exit('Test mode ignored');
}

if ($event->type !== 'checkout.session.completed') {
    http_response_code(200);
    exit('Ignored event type');
}

$session = $event->data->object;

if ($session->payment_status !== 'paid') {
    http_response_code(400);
    exit('Not paid');
}

$currency = strtolower($session->currency ?? '');
if (!in_array($currency, ['eur', 'czk'], true)) {
    file_put_contents(__DIR__.'/webhook_error.log', "INVALID CURRENCY: $currency\n", FILE_APPEND);
    http_response_code(400);
    exit('Invalid currency');
}

$amount = (int)($session->amount_total ?? 0);

$priceMap = [
    'eur' => [
        499   => 20,
        999   => 60,
        1999  => 140,
        3999  => 320,
        7999  => 800,
    ],
    'czk' => [
        12500  => 20,
        25000  => 60,
        50000  => 140,
        100000 => 320,
        200000 => 800,
    ],
];

if (!isset($priceMap[$currency][$amount])) {
    file_put_contents(__DIR__.'/webhook_error.log', "INVALID AMOUNT: $currency $amount\n", FILE_APPEND);
    http_response_code(400);
    exit('Invalid amount');
}

$dc      = $priceMap[$currency][$amount];
$user_id = (int)($session->metadata->user_id ?? 0);

if ($user_id <= 0) {
    file_put_contents(__DIR__.'/webhook_error.log', "INVALID USER_ID\n", FILE_APPEND);
    http_response_code(400);
    exit('Invalid user_id');
}

require_once __DIR__ . '/../config/db.php';

try {
    $pdo->beginTransaction();

    $event_id = $event->id;

    $stmt = $pdo->prepare("SELECT id FROM wallet_ledger WHERE stripe_event_id = ?");
    $stmt->execute([$event_id]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        http_response_code(200);
        exit('Already processed');
    }

    $stmt = $pdo->prepare("
        INSERT INTO wallet_ledger (owner_type, owner_id, currency, amount, reason, ref_type, note, stripe_event_id)
        VALUES ('WEB', ?, 'DC', ?, 'STRIPE_PURCHASE', 'stripe_event', ?, ?)
    ");
    $stmt->execute([$user_id, $dc, "stripe:$event_id", $event_id]);

    $stmt = $pdo->prepare("
        INSERT INTO wallet_balances (owner_type, owner_id, currency, balance)
        VALUES ('WEB', ?, 'DC', ?)
        ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance)
    ");
    $stmt->execute([$user_id, $dc]);

    $pdo->commit();

    file_put_contents(__DIR__.'/webhook.log', "OK user:$user_id DC:$dc event:$event_id\n", FILE_APPEND);

} catch (Exception $e) {
    $pdo->rollBack();
    file_put_contents(__DIR__.'/webhook_error.log', "DB ERROR: ".$e->getMessage().PHP_EOL, FILE_APPEND);
    http_response_code(500);
    exit('DB error');
}

http_response_code(200);
echo 'OK';
exit;
*/
