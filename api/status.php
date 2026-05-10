<?php
/**
 * Server status endpoint.
 * Vrací stav Login Serveru, Game Serveru a počet online hráčů.
 *
 * Reálný stav LS / GS se zjišťuje TCP socket connectem na localhost porty.
 * Výsledek se cachuje 5 sekund — i kdyby endpoint byl voláný často,
 * skutečné socket checky proběhnou max jednou za 5 s.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store');

// --- KONFIGURACE ----------------------------------------------------------
const STATUS_LS_HOST       = '127.0.0.1';
const STATUS_LS_PORT       = 2106;
const STATUS_GS_HOST       = '127.0.0.1';
const STATUS_GS_PORT       = 7777;
const STATUS_SOCKET_TIMEOUT = 1; // sekundy
const STATUS_CACHE_TTL     = 5;  // sekundy

// --- POMOCNÉ FUNKCE -------------------------------------------------------
function status_check_port(string $host, int $port, int $timeout): bool {
    if (!function_exists('fsockopen')) {
        return false;
    }
    $errno  = 0;
    $errstr = '';
    $sock = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if (!$sock) {
        return false;
    }
    fclose($sock);
    return true;
}

function status_count_players(): int {
    try {
        require __DIR__ . '/../config/db_game.php';
        if (!isset($pdoGameStatus)) {
            return 0;
        }
        $stmt = $pdoGameStatus->query("SELECT COUNT(*) FROM characters WHERE online = 1");
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

// --- CACHE LAYER ----------------------------------------------------------
$cacheFile = sys_get_temp_dir() . '/od_status_cache.json';

if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < STATUS_CACHE_TTL) {
    $cached = @file_get_contents($cacheFile);
    if ($cached !== false) {
        echo $cached;
        exit;
    }
}

// --- SKUTEČNÝ CHECK -------------------------------------------------------
$loginOk = status_check_port(STATUS_LS_HOST, STATUS_LS_PORT, STATUS_SOCKET_TIMEOUT);
$gameOk  = status_check_port(STATUS_GS_HOST, STATUS_GS_PORT, STATUS_SOCKET_TIMEOUT);

$players = $gameOk ? status_count_players() : 0;

$payload = [
    'login'   => $loginOk,
    'game'    => $gameOk,
    'players' => $players,
    // backward-compat: starý JS čekal 'online' boolean
    'online'  => ($loginOk && $gameOk),
];

$json = json_encode($payload);

// pokus o atomický zápis cache (best effort, selhání není fatální)
@file_put_contents($cacheFile, $json, LOCK_EX);

echo $json;
