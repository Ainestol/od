<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_bootstrap.php';

if (empty($_SESSION['web_user_id'])) {
  http_response_code(401);
  echo json_encode([
  "ok" => false,
  "logged_in" => false
]);
  exit;
}

// 🔥 WEB VIP kontrola
$vipStmt = $pdo->prepare("
  SELECT end_at
  FROM vip_grants
  WHERE scope = 'WEB'
    AND target_id = ?
    AND end_at > NOW()
  ORDER BY end_at DESC
  LIMIT 1
");
$vipStmt->execute([$_SESSION['web_user_id']]);
$vipEnd = $vipStmt->fetchColumn();

$webVip = null;

if ($vipEnd) {
  $webVip = [
    "end_at" => $vipEnd,
    "days_left" => floor((strtotime($vipEnd) - time()) / 86400)
  ];
}

// 🔐 2FA status
$st2fa = $pdo->prepare("
  SELECT twofa_enabled
  FROM users
  WHERE id = ?
  LIMIT 1
");
$st2fa->execute([$_SESSION['web_user_id']]);
$twofaEnabled = (int)$st2fa->fetchColumn();

// 🌍 LANGUAGE DETECTION (FIX)
$lang = 'cs';

if (!empty($_GET['lang'])) {
    $lang = $_GET['lang'] === 'en' ? 'en' : 'cs';
}
elseif (!empty($_COOKIE['lang'])) {
    $lang = $_COOKIE['lang'] === 'en' ? 'en' : 'cs';
}
elseif (!empty($_SESSION['lang'])) {
    $lang = $_SESSION['lang'] === 'en' ? 'en' : 'cs';
}

// 🔁 synchronizace session
$_SESSION['lang'] = $lang;

echo json_encode([
  "ok"      => true,
  "logged_in" => true,
  "email"   => $_SESSION['web_email'],
  "lang" => $lang,
  "role"    => $_SESSION['role'] ?? 'user', // 🔥 čárka
  "web_vip" => $webVip,
  "twofa_enabled" => $twofaEnabled
]);
