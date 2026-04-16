<?php
// /var/www/ordodraconis/lib/ip_helper.php

/**
 * Získá klientovu skutečnou IP.
 * Priorita: CF-Connecting-IP > X-Forwarded-For (první) > REMOTE_ADDR
 */
function get_client_ip(): string {
    // CloudFlare posílá skutečnou klientovu IP (IPv4 nebo IPv6)
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
 * Získá IPv4 verzi klienta.
 * - Pokud klient má IPv4 → vrátí ji
 * - Pokud má jen IPv6 → zkusí CF-Pseudo-IPv4 (musí být zapnuto v CloudFlare)
 * - Jinak vrátí NULL (fallback na voter_ref / MANUAL)
 */
function get_client_ipv4(): ?string {
    // 1. CloudFlare Pseudo IPv4 (když je zapnuto v CF dashboard)
    if (!empty($_SERVER['HTTP_CF_PSEUDO_IPV4'])) {
        $ip = trim($_SERVER['HTTP_CF_PSEUDO_IPV4']);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $ip;
        }
    }

    // 2. Pokud je CF-Connecting-IP přímo IPv4, použij ji
    $cfIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
    if ($cfIp && filter_var($cfIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return $cfIp;
    }

    // 3. Pokud REMOTE_ADDR je IPv4, použij ji
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($remote && filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return $remote;
    }

    // 4. Žádná IPv4 není k dispozici
    return null;
}

/**
 * True = IP je IPv6
 */
function is_ipv6(string $ip): bool {
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
}