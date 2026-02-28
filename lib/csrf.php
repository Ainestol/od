<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Vytvoření tokenu (pokud neexistuje)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Funkce pro ověření
function csrf_check(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? null);

    if (!$token || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo json_encode(['ok'=>false,'error'=>'CSRF_INVALID']);
        exit;
    }
}