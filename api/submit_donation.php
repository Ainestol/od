<?php
/**
 * Submit donation request — uživatel ohlašuje že provedl bankovní převod.
 * Záznam jde do `donations` jako `pending`, admin to manuálně schválí
 * (a tím se přidělí Dragon Coiny) nebo zamítne.
 *
 * POST JSON:
 *   { amount: int, currency: 'CZK'|'EUR', paid_at: 'YYYY-MM-DD', note?: string, lang?: 'cs'|'en' }
 *
 * Odpověď:
 *   200 { ok: true, donation_id, variable_symbol }
 *   400 { ok: false, error: 'INVALID_*' / 'AMOUNT_TOO_LOW' / ... }
 *   401 { ok: false, error: 'NOT_LOGGED_IN' }
 *   429 { ok: false, error: 'RATE_LIMITED' / 'TOO_MANY_PENDING' / 'COOLDOWN' }
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/_smtp_mail.php';

try {
    // === 1) auth ===
    if (empty($_SESSION['web_user_id'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'NOT_LOGGED_IN']);
        exit;
    }
    $userId = (int)$_SESSION['web_user_id'];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'METHOD_NOT_ALLOWED']);
        exit;
    }

    // === 2) CSRF ===
    csrf_check();

    // === 3) IP rate limit (5 submissions / hour) ===
    $ip = client_ip();
    try {
        rate_limit($pdo, "donation_submit:$ip", 5, 3600);
    } catch (Exception $e) {
        if ($e->getMessage() === 'RATE_LIMITED') {
            http_response_code(429);
            echo json_encode(['ok' => false, 'error' => 'RATE_LIMITED']);
            exit;
        }
        throw $e;
    }

    // === 4) parse + validate input ===
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;

    $amount   = (int)($input['amount'] ?? 0);
    $currency = strtoupper(trim((string)($input['currency'] ?? 'CZK')));
    $paidAt   = trim((string)($input['paid_at'] ?? ''));
    $note     = trim((string)($input['note'] ?? ''));
    $lang     = (($input['lang'] ?? 'cs') === 'en') ? 'en' : 'cs';

    if (!in_array($currency, ['CZK', 'EUR'], true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'INVALID_CURRENCY']);
        exit;
    }

    if ($amount <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'INVALID_AMOUNT']);
        exit;
    }

    // Min částka: 25 CZK (= 5 DC) / 5 EUR (= 25 DC)
    $minAmount = ($currency === 'CZK') ? 25 : 5;
    if ($amount < $minAmount) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'AMOUNT_TOO_LOW',
            'min'   => $minAmount,
            'currency' => $currency
        ]);
        exit;
    }

    // Datum platby — formát YYYY-MM-DD, nesmí být v budoucnu, ne víc než 30 dní zpět
    // !Y-m-d → zeroes out time (00:00:00) so comparison against `today` works
    $dt = DateTime::createFromFormat('!Y-m-d', $paidAt);
    if (!$dt || $dt->format('Y-m-d') !== $paidAt) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'INVALID_DATE']);
        exit;
    }
    $today    = new DateTime('today');
    $thirtyAgo = (new DateTime('today'))->modify('-30 days');
    if ($dt > $today) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'DATE_IN_FUTURE']);
        exit;
    }
    if ($dt < $thirtyAgo) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'DATE_TOO_OLD']);
        exit;
    }

    if (mb_strlen($note) > 500) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'NOTE_TOO_LONG']);
        exit;
    }

    // === 5) anti-abuse: max 3 pending od jednoho uživatele ===
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM donations
        WHERE web_user_id = ? AND status = 'pending'
    ");
    $stmt->execute([$userId]);
    $pendingCount = (int)$stmt->fetchColumn();
    if ($pendingCount >= 3) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'error' => 'TOO_MANY_PENDING']);
        exit;
    }

    // === 6) anti-abuse: 24h cooldown mezi submissions ===
    $stmt = $pdo->prepare("
        SELECT created_at FROM donations
        WHERE web_user_id = ?
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$userId]);
    $lastCreated = $stmt->fetchColumn();
    if ($lastCreated) {
        $lastTs = strtotime($lastCreated);
        if ((time() - $lastTs) < 24 * 3600) {
            $wait = 24 * 3600 - (time() - $lastTs);
            http_response_code(429);
            echo json_encode([
                'ok'    => false,
                'error' => 'COOLDOWN',
                'wait_seconds' => $wait
            ]);
            exit;
        }
    }

    // === 7) generate variabilní symbol = userId + rok + pořadí (per user/year) ===
    // Příklad: user #1, 1. platba v 2026 → "120261", 2. → "120262", …
    //          user #12, 1. platba v 2026 → "1220261"
    // Bere se YEAR(paid_at), tedy rok dle data převodu (ne data odeslání).
    $year = (int)$dt->format('Y');

    $seqStmt = $pdo->prepare("
        SELECT COUNT(*) FROM donations
        WHERE web_user_id = ?
          AND YEAR(paid_at) = ?
    ");
    $seqStmt->execute([$userId, $year]);
    $seq = (int)$seqStmt->fetchColumn() + 1;

    $vs = $userId . $year . $seq;

    // VS pro bankovní převod má být numeric do 10 znaků; rezerva 20.
    if (strlen($vs) > 20) {
        $vs = substr($vs, 0, 20);
    }

    // === 8) insert ===
    $stmt = $pdo->prepare("
        INSERT INTO donations
            (web_user_id, amount, currency, paid_at, variable_symbol, note, lang, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([
        $userId,
        $amount,
        $currency,
        $paidAt,
        $vs,
        ($note === '' ? null : $note),
        $lang
    ]);
    $donationId = (int)$pdo->lastInsertId();

    // === 9) email notifikace adminovi (best effort, neblokuje response) ===
    if (defined('DONATION_ADMIN_EMAIL') && DONATION_ADMIN_EMAIL) {
        $userEmailStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $userEmailStmt->execute([$userId]);
        $userEmail = (string)$userEmailStmt->fetchColumn();

        $expectedDc = ($currency === 'CZK')
            ? intdiv($amount, 5)
            : ($amount * 5);

        $subject = "[Ordo Draconis] Nová žádost o donate (#{$donationId})";
        $html = "
            <html><body style='font-family:Arial,sans-serif;line-height:1.5'>
            <h2>Nová žádost o donate</h2>
            <p><strong>Hráč:</strong> {$userEmail} (Web ID: {$userId})</p>
            <p><strong>Částka:</strong> {$amount} {$currency}</p>
            <p><strong>Datum platby:</strong> {$paidAt}</p>
            <p><strong>VS:</strong> {$vs}</p>
            <p><strong>Předpokládané DC:</strong> {$expectedDc}</p>
            <p><strong>Poznámka hráče:</strong> " . htmlspecialchars($note ?: '—') . "</p>
            <p><strong>Donation ID:</strong> #{$donationId}</p>
            <hr>
            <p>Otevři admin panel a žádost schvalte/zamítni:<br>
               <a href='" . APP_BASE_URL . "/admin/donations.html'>" . APP_BASE_URL . "/admin/donations.html</a></p>
            </body></html>
        ";

        $err = null;
        smtp_send_mail(DONATION_ADMIN_EMAIL, $subject, $html, SMTP_USER, 'Ordo Draconis', $err);
        if ($err) {
            error_log("[submit_donation] admin notify mail failed: " . $err);
        }
    }

    echo json_encode([
        'ok' => true,
        'donation_id'     => $donationId,
        'variable_symbol' => $vs
    ]);

} catch (Throwable $e) {
    error_log('[submit_donation] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'SERVER_ERROR']);
}
