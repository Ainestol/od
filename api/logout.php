<?php
require_once __DIR__ . '/_bootstrap.php';

$lang = $_GET['lang'] ?? ($_SESSION['lang'] ?? 'cs');

// 🔥 kompletní vyčištění session
$_SESSION = [];

// 🔥 smaž cookie (KLÍČOVÉ!)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 🔥 znič session
session_destroy();

// redirect
$to = ($lang === 'en') ? '/index-en.html' : '/';
header("Location: $to");
exit;