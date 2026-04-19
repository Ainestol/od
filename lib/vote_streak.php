<?php
/**
 * vote_streak.php – sdílená logika pro streak bonus
 * Volat POUZE ve stejné transakci po úspěšném vote_logs insertu!
 */

const VOTE_STREAK_DAYS   = 5;
const VOTE_STREAK_REWARD = 15;
const VOTE_TIMEZONE      = 'Europe/Prague';

function award_streak_bonus_if_eligible(PDO $pdo, int $userId): array
{
    // 1) Aktivní vote sites
    $sitesStmt = $pdo->query("SELECT id FROM vote_sites WHERE is_active = 1");
    $activeSiteIds = array_map('intval', $sitesStmt->fetchAll(PDO::FETCH_COLUMN));

    if (count($activeSiteIds) === 0) {
        return ['awarded' => false, 'end_date' => null, 'reason' => 'NO_ACTIVE_SITES'];
    }

    // 2) "dnešek" v Europe/Prague
    $tz = new DateTimeZone(VOTE_TIMEZONE);
    $today = (new DateTimeImmutable('now', $tz))->format('Y-m-d');

    // 3) Posledních 5 dní
    $days = [];
    for ($i = 0; $i < VOTE_STREAK_DAYS; $i++) {
        $days[] = (new DateTimeImmutable("now -{$i} day", $tz))->format('Y-m-d');
    }

    // 4) Načíst vote_logs za toto okno
    $startDay = end($days);
    $start = (new DateTimeImmutable($startDay . ' 00:00:00', $tz))->format('Y-m-d H:i:s');
    $end   = (new DateTimeImmutable($today    . ' 23:59:59', $tz))->format('Y-m-d H:i:s');

    $placeholders = implode(',', array_fill(0, count($activeSiteIds), '?'));
    $sql = "
        SELECT vote_site_id, voted_at
        FROM vote_logs
        WHERE web_user_id = ?
          AND voted_at BETWEEN ? AND ?
          AND vote_site_id IN ($placeholders)
    ";
    $params = array_merge([$userId, $start, $end], $activeSiteIds);
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    // 5) Mapa [den][siteId] = true
    $map = [];
    foreach ($rows as $r) {
        try {
            $utc = new DateTimeImmutable($r['voted_at'], new DateTimeZone(date_default_timezone_get()));
            $localDate = $utc->setTimezone($tz)->format('Y-m-d');
        } catch (Throwable $e) {
            continue;
        }
        $map[$localDate][(int)$r['vote_site_id']] = true;
    }

    // 6) Kontrola: každý z 5 dnů musí mít všechny aktivní sites
    foreach ($days as $d) {
        if (!isset($map[$d])) {
            return ['awarded' => false, 'end_date' => null, 'reason' => 'MISSING_DAY:' . $d];
        }
        foreach ($activeSiteIds as $sid) {
            if (empty($map[$d][$sid])) {
                return ['awarded' => false, 'end_date' => null, 'reason' => "MISSING_SITE:{$d}:{$sid}"];
            }
        }
    }

    // 7) Streak splněn → idempotentní zápis
    try {
        $ins = $pdo->prepare("
            INSERT INTO vote_streak_awards (web_user_id, streak_end_date, vc_amount)
            VALUES (?, ?, ?)
        ");
        $ins->execute([$userId, $today, VOTE_STREAK_REWARD]);
    } catch (PDOException $e) {
        if ((int)$e->getCode() === 23000) {
            return ['awarded' => false, 'end_date' => $today, 'reason' => 'ALREADY_AWARDED_TODAY'];
        }
        throw $e;
    }

    // 8) Připsat VC
    $pdo->prepare("
        INSERT INTO wallet_balances (owner_type, owner_id, currency, balance)
        VALUES ('WEB', ?, 'VOTE_COIN', ?)
        ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance)
    ")->execute([$userId, VOTE_STREAK_REWARD]);

    $pdo->prepare("
        INSERT INTO wallet_ledger
        (owner_type, owner_id, currency, amount, reason, ref_type, note)
        VALUES ('WEB', ?, 'VOTE_COIN', ?, 'VOTE_STREAK_5D', 'VOTE_STREAK', ?)
    ")->execute([$userId, VOTE_STREAK_REWARD, "streak_end:{$today}"]);

    return ['awarded' => true, 'end_date' => $today, 'reason' => 'OK'];
}