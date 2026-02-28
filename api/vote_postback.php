<?php
// /var/www/ordodraconis/api/vote_postback.php
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

function log_line(string $msg): void {
  @file_put_contents(__DIR__ . '/_postback.log', date('c') . ' ' . $msg . "\n", FILE_APPEND);
}

// ======================================================
// 1) L2Network POSTBACK (server -> server)
// POST: userid (your attempt_id), userip, voted
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['userid'])) {

  $allowedIps = ['51.195.47.198', '104.26.4.193', '104.26.5.193'];
  $remoteIp   = $_SERVER['REMOTE_ADDR'] ?? '';

  $attemptId  = (int)($_POST['userid'] ?? 0);
  $voted      = (int)($_POST['voted'] ?? 0);
  $userIp     = (string)($_POST['userip'] ?? '');

  log_line("L2NETWORK REMOTE_IP={$remoteIp} POST=" . json_encode($_POST));

  if (!in_array($remoteIp, $allowedIps, true)) {
    http_response_code(403);
    echo "FORBIDDEN_IP";
    exit;
  }

  if ($attemptId <= 0 || $voted !== 1) {
    http_response_code(400);
    echo "BAD_POST";
    exit;
  }

  // Ověř + označ attempt jako verified (jen pokud existuje, není used a patří L2Network site_id=3)
  // Pozn.: pokud bys někdy změnil id L2Network v DB, uprav "vote_site_id = 3".
  $st = $pdo->prepare("
    UPDATE vote_attempts
    SET verified_at = NOW()
    WHERE id = ?
      AND vote_site_id = 3
      AND verified_at IS NULL
      AND used_at IS NULL
  ");
  $st->execute([$attemptId]);

  if ($st->rowCount() < 1) {
    // buď attempt neexistuje, nebo už je verified/used, nebo není L2Network
    echo "NO_MATCH";
    exit;
  }

  echo "OK";
  exit;
}


// ======================================================
// 2) GENERIC POSTBACK (nonce + secret key v URL)
// GET: site_id, nonce, key
// ======================================================
$siteId = (int)($_GET['site_id'] ?? 0);
$nonce  = preg_replace('~[^a-f0-9]~', '', strtolower((string)($_GET['nonce'] ?? '')));
$key    = (string)($_GET['key'] ?? '');

if (!$siteId || strlen($nonce) !== 32 || $key === '') {
  http_response_code(400);
  echo "BAD_REQUEST";
  exit;
}

try {
  log_line("GENERIC REMOTE_IP=" . ($_SERVER['REMOTE_ADDR'] ?? '') . " GET=" . json_encode($_GET));

  // ověř secret pro daný site
  $st = $pdo->prepare("
    SELECT postback_secret
    FROM vote_sites
    WHERE id = ? AND is_active = 1 AND verify_method = 'POSTBACK'
  ");
  $st->execute([$siteId]);
  $secret = (string)$st->fetchColumn();

  if ($secret === '' || !hash_equals($secret, $key)) {
    http_response_code(403);
    echo "FORBIDDEN";
    exit;
  }

  // spáruj attempt (časové okno 2h) a označ verified
  $st = $pdo->prepare("
    SELECT id
    FROM vote_attempts
    WHERE vote_site_id = ?
      AND nonce = ?
      AND created_at >= (NOW() - INTERVAL 2 HOUR)
      AND verified_at IS NULL
      AND used_at IS NULL
    ORDER BY id DESC
    LIMIT 1
  ");
  $st->execute([$siteId, $nonce]);
  $attemptId = (int)$st->fetchColumn();

  if (!$attemptId) {
    echo "NO_MATCH";
    exit;
  }

  $pdo->prepare("UPDATE vote_attempts SET verified_at = NOW() WHERE id = ?")->execute([$attemptId]);

  echo "OK";

} catch (Throwable $e) {
  log_line("ERROR " . $e->getMessage());
  http_response_code(500);
  echo "ERROR";
}
