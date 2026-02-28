<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../lib/csrf.php';

echo json_encode([
    'ok' => true,
    'token' => $_SESSION['csrf_token']
]);