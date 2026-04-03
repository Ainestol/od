<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

$env = parse_ini_file('/var/www/.env');

if (!$env || !isset($env['STRIPE_SECRET_KEY'])) {
    http_response_code(500);
    echo json_encode(['error' => 'ENV not loaded']);
    exit;
}

\Stripe\Stripe::setApiKey($env['STRIPE_SECRET_KEY']);

// načtení dat z requestu
$input = json_decode(file_get_contents('php://input'), true);

$pack = (int)($input['pack'] ?? 0);
$currency = strtolower($input['currency'] ?? 'eur');

// mapování balíčků (v nejmenší jednotce: haléře / centy)
$packs = [
    'eur' => [
        20  => 499,   // 4.99 €
        55  => 999,   // 9.99 €
        120 => 1999,  // 19.99 €
        260 => 3999,  // 39.99 €
        600 => 7999,  // 79.99 €
    ],
    'czk' => [
        20  => 10000, // 100 Kč
        55  => 25000, // 250 Kč
        120 => 50000, // 500 Kč
        260 => 100000,// 1000 Kč
        600 => 200000,// 2000 Kč
    ]
];

// validace
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

        'metadata' => [
            'dc' => $pack,
            'currency' => $currency
        ],
    ]);

    echo json_encode([
        'url' => $session->url
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}