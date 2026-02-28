<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/../config/db.php'; // 🔥 chybělo

if (empty($_SESSION['web_user_id'])) {
  http_response_code(401);
  echo json_encode(["ok" => false]);
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

echo json_encode([
  "ok"      => true,
  "email"   => $_SESSION['web_email'],
  "lang"    => $_SESSION['lang'] ?? 'cs',
  "role"    => $_SESSION['role'] ?? 'user', // 🔥 čárka
  "web_vip" => $webVip
]);
