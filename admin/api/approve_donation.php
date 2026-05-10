<?php
/**
 * Admin: schválí donate žádost, připíše DC do wallet, pošle email hráči.
 *
 * POST JSON: { donation_id: int }
 *
 * Conversion rate: CZK 5:1 DC, EUR 1:5 DC.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../api/admin/_bootstrap.php';
require_once __DIR__ . '/../../lib/wallet.php';
require_once __DIR__ . '/../../config/mail.php';
require_once __DIR__ . '/../../api/_smtp_mail.php';

try {
    assert_admin();
    // CSRF check už proběhl v _bootstrap.php (POST → csrf_check())

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'METHOD_NOT_ALLOWED']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;

    $donationId = (int)($input['donation_id'] ?? 0);
    if ($donationId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'INVALID_ID']);
        exit;
    }

    $adminId = (int)$_SESSION['web_user_id'];
    $donation = null;
    $dcAmount = 0;

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            SELECT d.*, u.email AS user_email
            FROM donations d
            LEFT JOIN users u ON u.id = d.web_user_id
            WHERE d.id = ?
            FOR UPDATE
        ");
        $stmt->execute([$donationId]);
        $donation = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$donation) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'NOT_FOUND']);
            exit;
        }

        if ($donation['status'] !== 'pending') {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'error' => 'ALREADY_REVIEWED',
                'current_status' => $donation['status']
            ]);
            exit;
        }

        $amount   = (int)$donation['amount'];
        $currency = $donation['currency'];
        if ($currency === 'CZK') {
            $dcAmount = intdiv($amount, 5);
        } elseif ($currency === 'EUR') {
            $dcAmount = $amount * 5;
        } else {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'UNKNOWN_CURRENCY']);
            exit;
        }

        if ($dcAmount <= 0) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'AMOUNT_TOO_LOW_FOR_DC']);
            exit;
        }

        $upd = $pdo->prepare("
            UPDATE donations
            SET status      = 'approved',
                dc_credited = ?,
                reviewed_at = NOW(),
                reviewed_by = ?
            WHERE id = ? AND status = 'pending'
        ");
        $upd->execute([$dcAmount, $adminId, $donationId]);

        if ($upd->rowCount() === 0) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['ok' => false, 'error' => 'RACE_CONDITION']);
            exit;
        }

        $userId = (int)$donation['web_user_id'];
        wallet_add(
            $pdo,
            $userId,
            $dcAmount,
            'donation',
            'donation',
            $donationId,
            "Donation #{$donationId} approved (amount={$amount} {$currency})"
        );

        admin_audit($pdo, 'donation_approve', $userId, [
            'donation_id' => $donationId,
            'amount'      => $amount,
            'currency'    => $currency,
            'dc_credited' => $dcAmount
        ]);

        $pdo->commit();

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    $newBalance = wallet_get_balance($pdo, (int)$donation['web_user_id'], 'DC');

    if (!empty($donation['user_email'])) {
        $isEn = ($donation['lang'] === 'en');
        $subject = $isEn
            ? "[Ordo Draconis] Donation approved — {$dcAmount} DC credited"
            : "[Ordo Draconis] Donate schválen — připsáno {$dcAmount} DC";

        if ($isEn) {
            $html = "
                <html><body style='font-family:Arial,sans-serif;line-height:1.5;color:#333'>
                <h2>Thank you for your support!</h2>
                <p>Your donation has been approved and <strong>{$dcAmount} DC</strong> has been
                credited to your wallet.</p>
                <p>Use it for cosmetic items, mounts, cloaks or VIP rewards in the marketplace.</p>
                <hr style='border:none;border-top:1px solid #ddd;margin:24px 0'>
                <p>Thanks for your support — we really appreciate it.<br>
                <strong>Ordo Team</strong></p>
                </body></html>
            ";
        } else {
            $html = "
                <html><body style='font-family:Arial,sans-serif;line-height:1.5;color:#333'>
                <h2>Děkujeme za podporu!</h2>
                <p>Tvůj příspěvek byl schválen a do tvé peněženky bylo připsáno
                <strong>{$dcAmount} DC</strong>.</p>
                <p>Můžeš si za ně koupit kosmetické věci, mounty, pláště nebo VIP odměny v Tržišti.</p>
                <hr style='border:none;border-top:1px solid #ddd;margin:24px 0'>
                <p>Mockrát děkujeme za podporu, vážíme si toho.<br>
                <strong>S pozdravem Ordo Team</strong></p>
                </body></html>
            ";
        }

        $err = null;
        smtp_send_mail($donation['user_email'], $subject, $html, SMTP_USER, 'Ordo Draconis', $err);
        if ($err) {
            error_log("[approve_donation] mail to user failed: " . $err);
        }
    }

    echo json_encode([
        'ok'           => true,
        'donation_id'  => $donationId,
        'dc_credited'  => $dcAmount,
        'new_balance'  => $newBalance
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('[approve_donation] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'SERVER_ERROR']);
}
