<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json'); // 🔥 DŮLEŽITÉ

require_once __DIR__ . '/../vendor/autoload.php';

$env = parse_ini_file('/var/www/.env');

if (!$env || !isset($env['STRIPE_SECRET_KEY'])) {
    http_response_code(500);
    echo json_encode(['error' => 'ENV not loaded']);
    exit;
}

\Stripe\Stripe::setApiKey($env['STRIPE_SECRET_KEY']);

// načtení dat
$input = json_decode(file_get_contents('php://input'), true);

$pack = (int)($input['pack'] ?? 0);
$currency = strtolower($input['currency'] ?? 'eur');

$packs = [
    'eur' => [
        20  => 499,
        55  => 999,
        120 => 1999,
        260 => 3999,
        600 => 7999,
    ],
    'czk' => [
        20  => 10000,
        55  => 25000,
        120 => 50000,
        260 => 100000,
        600 => 200000,
    ]
];

if (!isset($packs[$currency]) || !isset($packs[$currency][$pack])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid pack or currency']);
    exit;
}

$amount = $packs[$currency][$pack];

try {
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'mode' => 'payment',

        'line_items' => [[
            'price_data' => [
                'currency' => $currency,
                'product_data' => [
                    'name' => $pack . ' Dragon Coins',
                ],
                'unit_amount' => $amount,
            ],
            'quantity' => 1,
        ]],

        'success_url' => 'https://l2ordo.net/profile/index.html?success=1',
        'cancel_url'  => 'https://l2ordo.net/profile/index.html?canceled=1',

      session_start();

$user_id = $_SESSION['user_id'] ?? 0;

'metadata' => [
    'dc' => $pack,
    'currency' => $currency,
    'user_id' => $user_id
],
    ]);

    echo json_encode(['url' => $session->url]);

}catch (Exception $e) {
    file_put_contents(__DIR__.'/stripe_error.log', $e->getMessage().PHP_EOL, FILE_APPEND);
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null) {
        file_put_contents(__DIR__.'/fatal_error.log', print_r($error, true), FILE_APPEND);
    }
});