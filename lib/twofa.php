<?php

function verify_totp($secret, $code, $window = 1) {
    $timeSlice = floor(time() / 30);

    for ($i = -$window; $i <= $window; $i++) {
        if (generate_totp($secret, $timeSlice + $i) === $code) {
            return true;
        }
    }
    return false;
}

function generate_totp($secret, $timeSlice) {
    $secretKey = base32_decode($secret);

    $time = pack('N*', 0) . pack('N*', $timeSlice);
    $hash = hash_hmac('sha1', $time, $secretKey, true);

    $offset = ord(substr($hash, -1)) & 0x0F;
    $truncatedHash = substr($hash, $offset, 4);

    $value = unpack('N', $truncatedHash)[1];
    $value = $value & 0x7FFFFFFF;

    return str_pad($value % 1000000, 6, '0', STR_PAD_LEFT);
}

function base32_decode($secret) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = strtoupper($secret);

    $binary = '';
    for ($i = 0; $i < strlen($secret); $i++) {
        $binary .= str_pad(base_convert(strpos($alphabet, $secret[$i]), 10, 2), 5, '0', STR_PAD_LEFT);
    }

    $bytes = '';
    for ($i = 0; $i < strlen($binary); $i += 8) {
        $bytes .= chr(bindec(substr($binary, $i, 8)));
    }

    return $bytes;
}