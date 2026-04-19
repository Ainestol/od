<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/_bootstrap.php';

if (empty($_SESSION['web_user_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false]);
  exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/logger.php';
require_once __DIR__ . '/../lib/vote_streak.php';
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
        system_log(
        $pdo,
        'VOTE',
        'VOTE_PENDING',
        $userId,
        (int)$a['vote_site_id'],
        'INFO',
        [
            'attempt_id' => $attemptId,
            'reason' => 'POSTBACK_WAITING_VERIFICATION'
        ]
    );
      echo json_encode(['ok' => true, 'status' => 'PENDING']);
      exit;
    }
  }

  // ---- 2) IPCHECK
 elseif ($method === 'IPCHECK') {

    $provider  = $a['api_provider'] ?? null;
    $apiKey    = $a['api_key'] ?? null;
    $voterRef  = $a['voter_ref'] ?? null;
    
    // 🔑 KLÍČOVÁ ZMĚNA: preferuj IPv4, protože vote listy IPv6 nepodporují
    // (kromě L2Network, která používá voter_ref, ne IP)
    $ip   = $a['ip'] ?? null;
    $ipV4 = $a['ip_v4'] ?? null;
    
    // Pro IP-based providery použij IPv4 verzi pokud existuje
    $ipForCheck = $ipV4 ?: $ip;
    
    // Pokud hráč nemá IPv4 a provider je IP-based → nelze ověřit
    $ipBasedProviders = ['HOPZONE', 'RANKZONE', 'HOTSERVERS', 'L2TOP'];
    if (in_array($provider, $ipBasedProviders, true) && !$ipV4) {
        // Klient je pure-IPv6 → tenhle provider ho nemůže ověřit
        $pdo->commit();
        system_log(
            $pdo, 'VOTE', 'VOTE_IPV6_UNSUPPORTED',
            $userId, (int)$a['vote_site_id'], 'INFO',
            [
                'attempt_id' => $attemptId,
                'provider' => $provider,
                'ip' => $ip,
                'reason' => 'IPV6_CLIENT_IPV4_PROVIDER_MISMATCH'
            ]
        );
        echo json_encode([
            'ok' => true, 
            'status' => 'PENDING',
            'hint' => 'IPV6_CLIENT'
        ]);
        exit;
    }

    if (!$provider || !$apiKey) throw new Exception('IPCHECK_NOT_CONFIGURED');

    $voted = false;

    // čas attemptu (timezone-safe)
    $attemptTs = (int)($a['created_at_ts'] ?? 0);
    $skew = 300; // 5 min tolerance

    if ($provider === 'HOPZONE') {
      if (!$ipForCheck) throw new Exception('IPCHECK_NOT_CONFIGURED');

      $url = "https://api.hopzone.net/lineage2/vote?token=" . urlencode($apiKey)
           . "&ip_address=" . urlencode($ipForCheck);

      $j = http_get_json($url, 8);
      if (!$j) {
        $pdo->commit();
          system_log(
        $pdo,
        'VOTE',
        'VOTE_PENDING',
        $userId,
        (int)$a['vote_site_id'],
        'INFO',
        [
            'attempt_id' => $attemptId,
            'reason' => 'POSTBACK_WAITING_VERIFICATION'
        ]
    );
        echo json_encode(['ok' => true, 'status' => 'PENDING']);
        exit;
      }

      $voted = (isset($j['status_code']) && (int)$j['status_code'] === 200 && !empty($j['voted']));
    }

    elseif ($provider === 'RANKZONE') {
      if (!$ipForCheck) throw new Exception('IPCHECK_NOT_CONFIGURED');

      $url = "https://l2rankzone.com/api/vote-reward?apiKey=" . urlencode($apiKey)
           . "&ip=" . urlencode($ipForCheck);

      $j = http_get_json($url, 8);
      if (!$j) {
        $pdo->commit();
          system_log(
        $pdo,
        'VOTE',
        'VOTE_PENDING',
        $userId,
        (int)$a['vote_site_id'],
        'INFO',
        [
            'attempt_id' => $attemptId,
            'reason' => 'POSTBACK_WAITING_VERIFICATION'
        ]
    );

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
    if (!$voterRef) throw new Exception('IPCHECK_NOT_CONFIGURED');

    $ch = curl_init('https://l2network.eu/api.php');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'apiKey' => $apiKey,
            'type'   => 2,
            'player' => $voterRef,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; L2OrdoBot/1.0)',
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        log_l2("attempt_id={$attemptId} voterRef={$voterRef} CURL_ERR={$err}");
        $pdo->commit();
        echo json_encode(['ok' => true, 'status' => 'PENDING']);
        exit;
    }

    $trim = trim((string)$raw);
    if ($trim === '' || $trim === '0') {
        log_l2("attempt_id={$attemptId} voterRef={$voterRef} raw={$raw}");
    }

    $j = json_decode($raw, true);
    $voteTs = 0;

    if ($trim !== '' && ctype_digit($trim)) {
        $voteTs = (int)$trim;
    } elseif (is_int($j) || is_float($j)) {
        $voteTs = (int)$j;
    } elseif (is_array($j)) {
        foreach (['voteTime','lastVote','last_vote','time','timestamp'] as $k) {
            if (isset($j[$k]) && is_numeric($j[$k])) { $voteTs = (int)$j[$k]; break; }
        }
    }

    if ($voteTs > 0 && (!$attemptTs || ($voteTs + $skew >= $attemptTs))) {
        $voted = true;
    }
}

elseif ($provider === 'HOTSERVERS') {
  if (!$ipForCheck) throw new Exception('IPCHECK_NOT_CONFIGURED');

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
       . "&ip_address=" . urlencode($ipForCheck);

  $j = http_get_json($url, 8);
  if (!$j) {
    $pdo->commit();
      system_log(
        $pdo,
        'VOTE',
        'VOTE_PENDING',
        $userId,
        (int)$a['vote_site_id'],
        'INFO',
        [
            'attempt_id' => $attemptId,
            'reason' => 'POSTBACK_WAITING_VERIFICATION'
        ]
    );

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
  if (!$ipForCheck) throw new Exception('IPCHECK_NOT_CONFIGURED');

  $url = "https://l2top.org/api/" . rawurlencode($apiKey) . "/ip/" . rawurlencode($ipForCheck) . "/";

  $j = http_get_json($url, 8);
  if (!$j) {
    $pdo->commit();
      system_log(
        $pdo,
        'VOTE',
        'VOTE_PENDING',
        $userId,
        (int)$a['vote_site_id'],
        'INFO',
        [
            'attempt_id' => $attemptId,
            'reason' => 'POSTBACK_WAITING_VERIFICATION'
        ]
    );

    echo json_encode(['ok' => true, 'status' => 'PENDING']);
    exit;
  }

  // očekávané: {"error":0,"result":{"is_voted":true,"vote_time":156..., "server_time":...}}
  if (isset($j['error']) && (int)$j['error'] !== 0) {
    $pdo->commit();
      system_log(
        $pdo,
        'VOTE',
        'VOTE_PENDING',
        $userId,
        (int)$a['vote_site_id'],
        'INFO',
        [
            'attempt_id' => $attemptId,
            'reason' => 'POSTBACK_WAITING_VERIFICATION'
        ]
    );

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
        system_log(
        $pdo,
        'VOTE',
        'VOTE_PENDING',
        $userId,
        (int)$a['vote_site_id'],
        'INFO',
        [
            'attempt_id' => $attemptId,
            'reason' => 'POSTBACK_WAITING_VERIFICATION'
        ]
    );

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

 // === odměna (jen jednou) === 2 VC
  $pdo->prepare("
    INSERT INTO wallet_balances (owner_type, owner_id, currency, balance)
    VALUES ('WEB', ?, 'VOTE_COIN', 2)
    ON DUPLICATE KEY UPDATE balance = balance + 2
  ")->execute([$userId]);

  $pdo->prepare("
    INSERT INTO vote_logs (web_user_id, vote_site_id, voted_at)
    VALUES (?, ?, NOW())
  ")->execute([$userId, (int)$a['vote_site_id']]);

  $pdo->prepare("
    INSERT INTO wallet_ledger (owner_type, owner_id, currency, amount, reason, ref_type, ref_id)
    VALUES ('WEB', ?, 'VOTE_COIN', 2, 'VOTE_REWARD', 'VOTE_ATTEMPT', ?)
  ")->execute([$userId, $attemptId]);

  $pdo->prepare("UPDATE vote_attempts SET used_at = NOW() WHERE id = ?")->execute([$attemptId]);

  // === STREAK BONUS check (5 dní × všech 4 sites) ===
  $streakResult = award_streak_bonus_if_eligible($pdo, $userId);

  $pdo->commit();

system_log(
    $pdo,
    'VOTE',
    'VOTE_REWARD',
    $userId,
    (int)$a['vote_site_id'],
    'SUCCESS',
    [
        'attempt_id' => $attemptId,
        'currency' => 'VOTE_COIN',
        'amount' => 2,
        'provider' => $a['api_provider'] ?? null,
        'streak'   => $streakResult
    ]
);

$response = ['ok' => true, 'status' => 'REWARDED'];
if (!empty($streakResult['awarded'])) {
    $response['streak_bonus'] = VOTE_STREAK_REWARD;
}
echo json_encode($response);

} catch (Throwable $e) {

  if ($pdo->inTransaction()) $pdo->rollBack();

  try {
      system_log(
          $pdo,
          'VOTE',
          'VOTE_FAIL',
          $userId ?? null,
          null,
          'FAIL',
          [
              'attempt_id' => $attemptId ?? null,
              'error' => $e->getMessage()
          ]
      );
  } catch (Throwable $ignore) {}

  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}