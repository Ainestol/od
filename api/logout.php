<?php
require_once __DIR__ . '/_bootstrap.php';
$lang = $_GET['lang'] ?? ($_SESSION['lang'] ?? 'cs');

session_unset();
session_destroy();

$to = ($lang === 'en') ? '/index-en.html' : '/';
header("Location: $to");
exit;
