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

$attemptId      = (int)($data['attempt_id'] ?? 0);
$manualConfirm  = (bool)($data['confirm'] ?? false);

if (!$attemptId) {
  echo json_encode(['ok' => false, 'error' => 'INVALID_ATTEMPT']);
  exit;
}

function http_get_json(string $url, int $timeout = 8): ?array {
  $ctx = stream_context_create([
    'http' => ['timeout' => $timeout],
    'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true]
  ]);
  $raw = @file_get_contents($url, false, $ctx);
  if ($raw === false) return null;
  $j = json_decode($raw, true);
  return is_array($j) ? $j : null;
}

function log_l2(string $line): void {
  @file_put_contents(__DIR__ . '/_l2network_api.log', date('c') . ' ' . $line . "\n", FILE_APPEND);
}

try {
  $pdo->beginTransaction();

  // attempt + site (zamknout kvůli odměně)
  $st = $pdo->prepare("
    SELECT
      a.*,
      UNIX_TIMESTAMP(a.created_at) AS created_at_ts,
      s.cooldown_hours,
      s.verify_method,
      s.api_provider,
      s.api_key
    FROM vote_attempts a
    JOIN vote_sites s ON s.id = a.vote_site_id
    WHERE a.id = ? AND a.web_user_id = ?
    FOR UPDATE
  ");
  $st->execute([$attemptId, $userId]);
  $a = $st->fetch(PDO::FETCH_ASSOC);
  if (!$a) throw new Exception('ATTEMPT_NOT_FOUND');

  // už použitý attempt
  if (!empty($a['used_at'])) {
    $pdo->commit();
    echo json_encode(['ok' => true, 'status' => 'USED']);
    exit;
  }

  // === COOLDOWN kontrola (před ověřováním, ať nespamujeme toplisty) ===
  $st = $pdo->prepare("
    SELECT voted_at
    FROM vote_logs
    WHERE web_user_id = ? AND vote_site_id = ?
    ORDER BY voted_at DESC
    LIMIT 1
    FOR UPDATE
  ");
  $st->execute([$userId, (int)$a['vote_site_id']]);
  $lastVote = $st->fetchColumn();

  if ($lastVote) {
    $nextAllowed = strtotime($lastVote) + ((int)$a['cooldown_hours'] * 3600);
    if (time() < $nextAllowed) {
      throw new Exception('COOLDOWN');
    }
  }

  $method = $a['verify_method'] ?? 'MANUAL';

  // ---- 1) POSTBACK
  if ($method === 'POSTBACK') {
    if (empty($a['verified_at'])) {
      $pdo->commit();
      echo json_encode(['ok' => true, 'status' => 'PENDING']);
      exit;
    }
  }

  // ---- 2) IPCHECK
  elseif ($method === 'IPCHECK') {

    $provider  = $a['api_provider'] ?? null;
    $apiKey    = $a['api_key'] ?? null;
    $ip        = $a['ip'] ?? null;
    $voterRef  = $a['voter_ref'] ?? null;

    if (!$provider || !$apiKey) throw new Exception('IPCHECK_NOT_CONFIGURED');

    $voted = false;

    // čas attemptu (timezone-safe)
    $attemptTs = (int)($a['created_at_ts'] ?? 0);
    $skew = 300; // 5 min tolerance

    if ($provider === 'HOPZONE') {
      if (!$ip) throw new Exception('IPCHECK_NOT_CONFIGURED');

      $url = "https://api.hopzone.net/lineage2/vote?token=" . urlencode($apiKey)
           . "&ip_address=" . urlencode($ip);

      $j = http_get_json($url, 8);
      if (!$j) {
        $pdo->commit();
        echo json_encode(['ok' => true, 'status' => 'PENDING']);
        exit;
      }

      $voted = (isset($j['status_code']) && (int)$j['status_code'] === 200 && !empty($j['voted']));
    }

    elseif ($provider === 'RANKZONE') {
      if (!$ip) throw new Exception('IPCHECK_NOT_CONFIGURED');

      $url = "https://l2rankzone.com/api/vote-reward?apiKey=" . urlencode($apiKey)
           . "&ip=" . urlencode($ip);

      $j = http_get_json($url, 8);
      if (!$j) {
        $pdo->commit();
        echo json_encode(['ok' => true, 'status' => 'PENDING']);
        exit;
      }

      $votedFlag = !empty($j['voted']);
      $voteTs    = (int)($j['voteTime'] ?? 0);

      if ($votedFlag && $voteTs) {
        if (!$attemptTs || ($voteTs + $skew >= $attemptTs)) {
          $voted = true;
        }
      }
    }

    elseif ($provider === 'L2NETWORK') {
      // L2Network type=2 = last vote timestamp for "player"
      if (!$voterRef) throw new Exception('IPCHECK_NOT_CONFIGURED');

      $postData = http_build_query([
        'apiKey' => $apiKey,
        'type'   => 2,
        'player' => $voterRef
      ], '', '&');

      $ctx = stream_context_create([
        'http' => [
          'method'  => 'POST',
          'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
          'content' => $postData,
          'timeout' => 8
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]
      ]);

      $raw = @file_get_contents('https://l2network.eu/api.php', false, $ctx);

      // loguj jen problémové odpovědi (ať to nespamuje disk)
      if ($raw === false) {
        log_l2("attempt_id={$attemptId} voterRef={$voterRef} raw=(false)");
      } else {
        $t = trim((string)$raw);
        if ($t === '' || $t === '0') {
          log_l2("attempt_id={$attemptId} voterRef={$voterRef} raw={$raw}");
        }
      }

      if ($raw === false) {
        $pdo->commit();
        echo json_encode(['ok' => true, 'status' => 'PENDING']);
        exit;
      }

      $trim = trim((string)$raw);
      $j = json_decode($raw, true);

      $voteTs = 0;

      // čisté číslo jako text (nejčastější)
      if ($trim !== '' && ctype_digit($trim)) {
        $voteTs = (int)$trim;
      }
      // JSON číslo
      elseif (is_int($j) || is_float($j)) {
        $voteTs = (int)$j;
      }
      // JSON objekt
      elseif (is_array($j)) {
        foreach (['voteTime','lastVote','last_vote','time','timestamp'] as $k) {
          if (isset($j[$k]) && is_numeric($j[$k])) { $voteTs = (int)$j[$k]; break; }
        }
      }

      if ($voteTs > 0) {
        if (!$attemptTs || ($voteTs + $skew >= $attemptTs)) {
          $voted = true;
        }
      }
    }

elseif ($provider === 'HOTSERVERS') {
  if (!$ip) throw new Exception('IPCHECK_NOT_CONFIGURED');

  // api_key = TOKEN|SERVER_ID
  $parts = explode('|', (string)$apiKey, 2);
  $token = trim($parts[0] ?? '');
  $serverId = trim($parts[1] ?? '');

  if ($token === '' || $serverId === '') {
    throw new Exception('IPCHECK_NOT_CONFIGURED');
  }

  // ✅ správný endpoint (vrací vote_time + server_time)
  $url = "https://hotservers.org/api/servers/" . rawurlencode($serverId)
       . "/voteCheck?api_token=" . urlencode($token)
       . "&ip_address=" . urlencode($ip);

  $j = http_get_json($url, 8);
  if (!$j) {
    $pdo->commit();
    echo json_encode(['ok' => true, 'status' => 'PENDING']);
    exit;
  }

  // očekáváme:
  // {"has_voted":true,"server_time":"YYYY-mm-dd HH:ii:ss","vote_time":"YYYY-mm-dd HH:ii:ss",...}

  $hasVoted = !empty($j['has_voted']);
  $serverTime = (string)($j['server_time'] ?? '');
  $voteTime   = (string)($j['vote_time'] ?? '');

  if (!$hasVoted || $serverTime === '' || $voteTime === '') {
    $voted = false;
  } else {
    $remoteServerTs = strtotime($serverTime);
    $remoteVoteTs   = strtotime($voteTime);

    if (!$remoteServerTs || !$remoteVoteTs) {
      $voted = false;
    } else {
      // přepočet do našeho času (kompenzace jejich timezone)
     	$now = time();
	$offset      = $remoteServerTs - $now;
	$voteLocalTs = $remoteVoteTs - $offset;
      

      $attemptTs = (int)($a['created_at_ts'] ?? 0);

      // tolerance kvůli lagům / zaokrouhlení
      $tol = 120;

      // ✅ odměníme jen když vote_time je po vytvoření attemptu
      $voted = ($attemptTs && $voteLocalTs >= ($attemptTs - $tol) && $voteLocalTs <= ($now + 300));
    }
  }
}
elseif ($provider === 'L2TOP') {
  if (!$ip) throw new Exception('IPCHECK_NOT_CONFIGURED');

  $url = "https://l2top.org/api/" . rawurlencode($apiKey) . "/ip/" . rawurlencode($ip) . "/";

  $j = http_get_json($url, 8);
  if (!$j) {
    $pdo->commit();
    echo json_encode(['ok' => true, 'status' => 'PENDING']);
    exit;
  }

  // očekávané: {"error":0,"result":{"is_voted":true,"vote_time":156..., "server_time":...}}
  if (isset($j['error']) && (int)$j['error'] !== 0) {
    $pdo->commit();
    echo json_encode(['ok' => true, 'status' => 'PENDING']);
    exit;
  }

  $isVoted = !empty($j['result']['is_voted']);
  $voteTs  = (int)($j['result']['vote_time'] ?? 0);

  if ($isVoted) {
    // pokud máme čas hlasu, porovnáme s attemptem (stejně jako Rankzone/L2Network)
    if (!$voteTs || !$attemptTs || ($voteTs + $skew >= $attemptTs)) {
      $voted = true;
    }
  }
}

    else {
      throw new Exception('IPCHECK_PROVIDER_UNSUPPORTED');
    }

    if (!$voted) {
      $pdo->commit();
      echo json_encode(['ok' => true, 'status' => 'PENDING']);
      exit;
    }
  }

  // ---- 3) MANUAL
   else {
    if (!$manualConfirm) {
      $pdo->commit();
      echo json_encode(['ok' => true, 'status' => 'WAITING_CONFIRM']);
      exit;
    }
  }

  // === odměna (jen jednou) ===
  $pdo->prepare("
    INSERT INTO wallet_balances (owner_type, owner_id, currency, balance)
    VALUES ('WEB', ?, 'VOTE_COIN', 1)
    ON DUPLICATE KEY UPDATE balance = balance + 1
  ")->execute([$userId]);

  $pdo->prepare("
    INSERT INTO vote_logs (web_user_id, vote_site_id, voted_at)
    VALUES (?, ?, NOW())
  ")->execute([$userId, (int)$a['vote_site_id']]);

  $pdo->prepare("
    INSERT INTO wallet_ledger (owner_type, owner_id, currency, amount, reason, ref_type, ref_id)
    VALUES ('WEB', ?, 'VOTE_COIN', 1, 'VOTE_REWARD', 'VOTE_ATTEMPT', ?)
  ")->execute([$userId, $attemptId]);

  $pdo->prepare("UPDATE vote_attempts SET used_at = NOW() WHERE id = ?")->execute([$attemptId]);

  $pdo->commit();
  echo json_encode(['ok' => true, 'status' => 'REWARDED']);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}