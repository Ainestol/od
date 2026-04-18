<?php
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');

// 🔐 Autoload (Stripe)
require_once __DIR__ . '/../lib/session.php'; // 🔥 MUSÍ být první
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

// 🔐 ENV
$env = parse_ini_file('/var/www/.env');

if (!$env || !isset($env['STRIPE_SECRET_KEY'])) {
    http_response_code(500);
    echo json_encode(['error' => 'ENV not loaded']);
    exit;
}

// 🔐 Stripe init
\Stripe\Stripe::setApiKey($env['STRIPE_SECRET_KEY']);

// 📥 Načtení vstupu
$input = json_decode(file_get_contents('php://input'), true);

$pack = (int)($input['pack'] ?? 0);
$currency = strtolower($input['currency'] ?? 'eur');

// 💰 Ceník
$packs = [
    'eur' => [
        20  => 499,
        55  => 999,
        120 => 1999,
        260 => 3999,
        600 => 7999,
    ],
    'czk' => [
        20  => 12500,
        55  => 25000,
        120 => 50000,
        260 => 100000,
        600 => 200000,
    ]
];
// 🎁 Bonusy DC (pack => celkový počet DC včetně bonusu)
$bonuses = [
    20  => 20,
    55  => 60,
    120 => 140,
    260 => 320,
    600 => 800,
];
$total_dc = $bonuses[$pack] ?? $pack;

// ❌ Validace
if (!isset($packs[$currency]) || !isset($packs[$currency][$pack])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid pack or currency']);
    exit;
}

$amount = $packs[$currency][$pack];

// 👤 User ze session
$user_id = $_SESSION['web_user_id'] ?? 0;

// 🔍 DEBUG (můžeš pak smazat)
file_put_contents(__DIR__.'/debug_session.log', print_r($_SESSION, true));

// ❌ Bez usera nepouštět platbu
if ($user_id <= 0) {
    http_response_code(401);
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

try {

    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'mode' => 'payment',

        'line_items' => [[
            'price_data' => [
                'currency' => $currency,
                'product_data' => [
                    'name' => $total_dc . ' Dragon Coins',
                ],
                'unit_amount' => $amount,
            ],
            'quantity' => 1,
        ]],

        'success_url' => 'https://l2ordo.net/profile/' . ($currency === 'eur' ? 'index-en.html' : 'index.html') . '?success=1',
'cancel_url'  => 'https://l2ordo.net/profile/' . ($currency === 'eur' ? 'index-en.html' : 'index.html') . '?canceled=1',

        // 🔥 KLÍČOVÉ PRO WEBHOOK
        'metadata' => [
            'dc'       => $total_dc,
            'currency' => $currency,
            'user_id' => $user_id
        ],
    ]);

    echo json_encode(['url' => $session->url]);

} catch (Exception $e) {

    file_put_contents(__DIR__.'/stripe_error.log', $e->getMessage().PHP_EOL, FILE_APPEND);

    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}