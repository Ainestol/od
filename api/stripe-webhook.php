<?php
http_response_code(200);
echo "WEBHOOK HIT";
exit;
require_once __DIR__ . '/../vendor/autoload.php';

// 🔐 ENV
$env = parse_ini_file('/var/www/.env');
$endpoint_secret = $env['STRIPE_WEBHOOK_SECRET'] ?? '';

// 📥 RAW DATA
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// 📝 DEBUG RAW (doporučeno)
file_put_contents(__DIR__.'/webhook_debug.log', $payload.PHP_EOL, FILE_APPEND);

// 🔒 VERIFY STRIPE SIGNATURE
try {
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sig_header,
        $endpoint_secret
    );
} catch (\Exception $e) {
    http_response_code(400);
    exit('Invalid payload');
}

// 🎯 MAIN EVENT
if ($event->type === 'checkout.session.completed') {

    $session = $event->data->object;

    // 🛑 musí být zaplaceno
    if ($session->payment_status !== 'paid') {
        http_response_code(400);
        exit('Not paid');
    }

    $dc = (int)($session->metadata->dc ?? 0);
    $user_id = (int)($session->metadata->user_id ?? 0);

    // 🔒 VALIDACE
    if ($user_id <= 0 || $dc <= 0) {
        http_response_code(400);
        exit('Invalid metadata');
    }

    // 🗄️ DB
    require_once __DIR__ . '/../config/db.php';

    try {
        $pdo->beginTransaction();

        // 🧠 ID eventu (pro duplicity)
        $event_id = $event->id;

        // 🔁 kontrola duplicity
        $stmt = $pdo->prepare("
            SELECT id FROM wallet_ledger WHERE note = ?
        ");
        $stmt->execute(["stripe:$event_id"]);

        if ($stmt->fetch()) {
            http_response_code(200);
            exit('Already processed');
        }

        // 🧾 ledger zápis
        $stmt = $pdo->prepare("
            INSERT INTO wallet_ledger (user_id, currency, amount, type, note)
            VALUES (?, 'DC', ?, 'CREDIT', ?)
        ");
        $stmt->execute([$user_id, $dc, "stripe:$event_id"]);

        // 💰 balance update
        $stmt = $pdo->prepare("
            INSERT INTO wallet_balances (user_id, currency, balance)
            VALUES (?, 'DC', ?)
            ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance)
        ");
        $stmt->execute([$user_id, $dc]);

        $pdo->commit();

        // 📝 log OK
        file_put_contents(__DIR__.'/webhook.log', "OK user:$user_id DC:$dc event:$event_id\n", FILE_APPEND);

    } catch (Exception $e) {
        $pdo->rollBack();
        file_put_contents(__DIR__.'/webhook_error.log', $e->getMessage().PHP_EOL, FILE_APPEND);
    }
}

http_response_code(200);