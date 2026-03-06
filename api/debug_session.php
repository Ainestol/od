<?php
require_once __DIR__ . '/_bootstrap.php';
header('Content-Type: application/json');
echo json_encode($_SESSION);
