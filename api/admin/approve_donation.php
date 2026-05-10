<?php
/**
 * MOVED — endpoint byl přesunut do /admin/api/approve_donation.php.
 * Tento soubor můžeš smazat (na deploy serveru i z lokálního repa).
 */
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok'    => false,
    'error' => 'MOVED',
    'hint'  => 'Use /admin/api/approve_donation.php instead.'
]);
