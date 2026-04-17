<?php
// /var/www/ordodraconis/lib/ip_helper.php

function get_client_ip(): string {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return trim($_SERVER['HTTP_CF_CONNECTING_IP']);
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * True pokud IP je v Cloudflare Pseudo IPv4 range (240.0.0.0/4).
 * Tyto IP jsou bezcenné pro externí API kontroly.
 */
function is_pseudo_ipv4(string $ip): bool {
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return false;
    }
    // Class E range 240.0.0.0 - 255.255.255.255
    $long = ip2long($ip);
    return $long !== false && $long >= ip2long('240.0.0.0');
}

/**
 * Získá nejlepší dostupnou REÁLNOU IPv4 klienta.
 * Preferuje IPv4 kterou poslal klient JS → pak server-side detekce.
 * Vylučuje Pseudo IPv4 (bezcenné pro vote listy).
 *
 * @param ?string $clientProvided IPv4 kterou klient poslal přes JS (může být NULL)
 */
function get_client_ipv4(?string $clientProvided = null): ?string {
    // 1. Preferuj IPv4 od klienta (JS fetch na api.ipify.org)
    //    Tohle je NEJSPOLEHLIVĚJŠÍ zdroj pro dual-stack klienty
    if ($clientProvided && filter_var($clientProvided, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        if (!is_pseudo_ipv4($clientProvided)) {
            return $clientProvided;
        }
    }

    // 2. CF-Connecting-IP pokud je to IPv4
    $cfIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
    if ($cfIp && filter_var($cfIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        if (!is_pseudo_ipv4($cfIp)) {
            return $cfIp;
        }
    }

    // 3. REMOTE_ADDR pokud je to IPv4
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($remote && filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        if (!is_pseudo_ipv4($remote)) {
            return $remote;
        }
    }

    // 4. CF-Pseudo-IPv4 jako poslední možnost, ALE jen pokud je to 
    //    reálná IP (což Pseudo-IPv4 není, takže tohle nikdy neprojde)
    //    Ponechávám kód pro jistotu, ale return null.
    return null;
}

function is_ipv6(string $ip): bool {
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
}