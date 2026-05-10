<?php
/**
 * DEPRECATED — VIP 24h byl nahrazen Premium 24h.
 * Nový endpoint: /api/activate_premium_24h.php
 *
 * Existujicí VIP 24h granty (vip_grants se scope='CHAR' a character_variables
 * VIP_CHAR='true' / VIP_CHAR_END=...) se NESMAŽOU. Java logika si je pohlídá
 * sama přes VIP_CHAR_END timestamp — naturally expirují do 24 hodin.
 *
 * Tento soubor můžeš na deploy serveru po pár dnech smazat.
 */

http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok'    => false,
    'error' => 'DEPRECATED',
    'hint'  => 'VIP 24h was replaced by Premium 24h. Use /api/activate_premium_24h.php instead.'
]);
