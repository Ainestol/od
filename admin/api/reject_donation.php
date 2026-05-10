<?php
/**
 * Admin: zamítne donate žádost s povinným důvodem (admin_note).
 *
 * POST JSON: { donation_id: int, reason: string }
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../api/admin/_bootstrap.php';
require_once __DIR__ . '/../../config/mail.php';
require_once __DIR__ . '/../../api/_smtp_mail.php';

try {
    assert_admin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'METHOD_NOT_ALLOWED']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;

    $donationId = (int)($input['donation_id'] ?? 0);
    $reason     = trim((string)($input['reason'] ?? ''));

    if ($donationId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'INVALID_ID']);
        exit;
    }
    if ($reason === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'REASON_REQUIRED']);
        exit;
    }
    if (mb_strlen($reason) > 500) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'REASON_TOO_LONG']);
        exit;
    }

    $adminId = (int)$_SESSION['web_user_id'];
    $donation = null;

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

        $upd = $pdo->prepare("
            UPDATE donations
            SET status      = 'rejected',
                admin_note  = ?,
                reviewed_at = NOW(),
                reviewed_by = ?
            WHERE id = ? AND status = 'pending'
        ");
        $upd->execute([$reason, $adminId, $donationId]);

        if ($upd->rowCount() === 0) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['ok' => false, 'error' => 'RACE_CONDITION']);
            exit;
        }

        admin_audit($pdo, 'donation_reject', (int)$donation['web_user_id'], [
            'donation_id' => $donationId,
            'reason'      => $reason
        ]);

        $pdo->commit();

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    if (!empty($donation['user_email'])) {
        $isEn = ($donation['lang'] === 'en');
        $reasonEsc = htmlspecialchars($reason);

        $subject = $isEn
            ? "[Ordo Draconis] Donation request rejected"
            : "[Ordo Draconis] Donate žádost zamítnuta";

        if ($isEn) {
            $html = "
                <html><body style='font-family:Arial,sans-serif;line-height:1.5;color:#333'>
                <h2>Donation request rejected</h2>
                <p>Your donation request has been reviewed and could not be approved.</p>
                <p><strong>Reason:</strong><br>{$reasonEsc}</p>
                <p>If you believe this is a mistake, please contact us on our Discord
                or submit a new donation request with corrected details.</p>
                <hr style='border:none;border-top:1px solid #ddd;margin:24px 0'>
                <p><strong>Ordo Team</strong></p>
                </body></html>
            ";
        } else {
            $html = "
                <html><body style='font-family:Arial,sans-serif;line-height:1.5;color:#333'>
                <h2>Žádost o donate zamítnuta</h2>
                <p>Tvoje žádost o donate byla bohužel zamítnuta.</p>
                <p><strong>Důvod:</strong><br>{$reasonEsc}</p>
                <p>Pokud se domníváš, že jde o chybu, ozvi se nám na Discordu
                nebo podej novou žádost s opravenými údaji.</p>
                <hr style='border:none;border-top:1px solid #ddd;margin:24px 0'>
                <p><strong>S pozdravem Ordo Team</strong></p>
                </body></html>
            ";
        }

        $err = null;
        smtp_send_mail($donation['user_email'], $subject, $html, SMTP_USER, 'Ordo Draconis', $err);
        if ($err) {
            error_log("[reject_donation] mail to user failed: " . $err);
        }
    }

    echo json_encode([
        'ok' => true,
        'donation_id' => $donationId
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('[reject_donation] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'SERVER_ERROR']);
}
