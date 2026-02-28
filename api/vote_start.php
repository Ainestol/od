<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (empty($_SESSION['web_user_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false]);
  exit;
}

require_once __DIR__ . '/../config/db.php';

$userId = (int)$_SESSION['web_user_id'];
$data = json_decode(file_get_contents('php://input'), true);

$siteId = (int)($data['site_id'] ?? 0);
if (!$siteId) {
  echo json_encode(['ok' => false, 'error' => 'INVALID_SITE']);
  exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

try {
  // 1) načti site (včetně verify_method/api_provider kvůli URL logice)
  $st = $pdo->prepare("
    SELECT id, name, url, cooldown_hours, is_active, verify_method, api_provider
    FROM vote_sites
    WHERE id = ? AND is_active = 1
  ");
  $st->execute([$siteId]);
  $site = $st->fetch(PDO::FETCH_ASSOC);
  if (!$site) throw new Exception('SITE_NOT_FOUND');

  // 2) cooldown kontrola (podle vote_logs)
  $st = $pdo->prepare("
    SELECT voted_at
    FROM vote_logs
    WHERE web_user_id = ? AND vote_site_id = ?
    ORDER BY voted_at DESC
    LIMIT 1
  ");
  $st->execute([$userId, $siteId]);
  $lastVote = $st->fetchColumn();

  $cooldownSec = ((int)$site['cooldown_hours']) * 3600;
  if ($lastVote) {
    $nextAllowed = strtotime($lastVote) + $cooldownSec;
    if (time() < $nextAllowed) {
      echo json_encode([
        'ok' => false,
        'error' => 'COOLDOWN',
        'remaining' => max(0, $nextAllowed - time())
      ]);
      exit;
    }
  }

  // 3) vytvoř attempt
  $nonce = bin2hex(random_bytes(16));

  // voter_ref: potřebné hlavně pro L2Network (ověření přes "player/id")
  // držíme to stabilní per user, ať je to jednoduché a funguje to.
  $voterRef = 'web_' . $userId;

  $pdo->prepare("
    INSERT INTO vote_attempts (web_user_id, vote_site_id, ip, nonce, voter_ref, created_at)
    VALUES (?, ?, ?, ?, ?, NOW())
  ")->execute([$userId, $siteId, $ip, $nonce, $voterRef]);

  $attemptId = (int)$pdo->lastInsertId();

  // 4) připrav vote_url (nahradíme placeholdery, pokud existují)
  $voteUrl = (string)$site['url'];

  $voteUrl = str_replace('{VOTER}', rawurlencode($voterRef), $voteUrl);
  $voteUrl = str_replace('{IP}', rawurlencode($ip), $voteUrl);
  $voteUrl = str_replace('{ATTEMPT_ID}', rawurlencode((string)$attemptId), $voteUrl);

  // 5) přidáme debug parametry (nevadí, pokud je vote web ignoruje)
  $sep = (strpos($voteUrl, '?') === false) ? '?' : '&';
  $voteUrl .= $sep . 'attempt_id=' . rawurlencode((string)$attemptId) . '&nonce=' . rawurlencode($nonce);

  echo json_encode([
    'ok' => true,
    'attempt_id' => $attemptId,
    'vote_url' => $voteUrl,
    'voter_ref' => $voterRef
  ]);

} catch (Throwable $e) {
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
