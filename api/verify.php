<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail.php';

$token = (string)($_GET['token'] ?? '');
$lang  = (($_GET['lang'] ?? 'cs') === 'en') ? 'en' : 'cs';

// Lang redirect cíle
$okRedirect   = ($lang === 'en') ? '/auth/login-en.html' : '/auth/login.html';
$failRedirect = ($lang === 'en') ? '/pages/gdpr-en.html' : '/pages/gdpr.html'; // klidně změň na /pages/verify-fail.html apod.

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
  header("Location: " . $failRedirect);
  exit;
}

$tokenHash = hash('sha256', $token);

$stmt = $pdo->prepare("
  SELECT id, user_id, expires_at, used_at
  FROM user_tokens
  WHERE token_hash = ? AND type = 'verify_email'
  LIMIT 1
");
$stmt->execute([$tokenHash]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
  header("Location: " . $failRedirect);
  exit;
}

if (!empty($row['used_at'])) {
  // už použito => bereme jako OK (uživatel je pravděpodobně ověřen)
  header("Location: " . $okRedirect);
  exit;
}

$now = new DateTime();
$exp = new DateTime($row['expires_at']);
if ($now > $exp) {
  header("Location: " . $failRedirect);
  exit;
}

try {
  $pdo->beginTransaction();

  // označit token used
  $stmt = $pdo->prepare("UPDATE user_tokens SET used_at = NOW() WHERE id = ?");
  $stmt->execute([(int)$row['id']]);

  // ověřit uživatele
  $stmt = $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
  $stmt->execute([(int)$row['user_id']]);

  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  header("Location: " . $failRedirect);
  exit;
}

header("Location: " . $okRedirect);
exit;
