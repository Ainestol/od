<?php

require_once __DIR__ . '/../vendor/autoload.php';

$endpoint_secret = 'whsec_7QiTzW8VUxU6KPTInEVHvBMXhMvSrG65';

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

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

if ($event->type === 'checkout.session.completed') {

    $session = $event->data->object;

    $dc = (int)($session->metadata->dc ?? 0);

    // TODO: tady doplníme usera + DB
    file_put_contents(__DIR__.'/webhook.log', "DC: $dc\n", FILE_APPEND);
}

http_response_code(200);