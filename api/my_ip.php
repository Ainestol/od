<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$ip = $_SERVER['REMOTE_ADDR'] ?? '';

$isIpv4 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
$isIpv6 = !$isIpv4 && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;

echo json_encode([
    'ok'   => $isIpv4,
    'ip'   => $isIpv4 ? $ip : null,
    'ipv6' => $isIpv6,
]);