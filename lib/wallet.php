<?php

function wallet_get_balance(PDO $pdo, int $webUserId, string $currency = 'DC'): int {
    $stmt = $pdo->prepare("
        SELECT balance
        FROM wallet_balances
        WHERE owner_type = 'WEB'
          AND owner_id = ?
          AND currency = ?
    ");
    $stmt->execute([$webUserId, $currency]);
    return (int)($stmt->fetchColumn() ?? 0);
}

function wallet_add(
    PDO $pdo,
    int $webUserId,
    int $amount,
    string $reason,
    ?string $refType = null,
    ?int $refId = null,
    ?string $note = null
): void {
    if ($amount <= 0) {
        throw new Exception('wallet_add: amount must be positive');
    }

    try {
        // UPSERT balance
        $pdo->prepare("
            INSERT INTO wallet_balances (owner_type, owner_id, currency, balance)
            VALUES ('WEB', ?, 'DC', ?)
            ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance)
        ")->execute([$webUserId, $amount]);

        // ledger
        $pdo->prepare("
            INSERT INTO wallet_ledger
              (owner_type, owner_id, currency, amount, reason, ref_type, ref_id, note)
            VALUES
              ('WEB', ?, 'DC', ?, ?, ?, ?, ?)
        ")->execute([
            $webUserId,
            $amount,
            $reason,
            $refType,
            $refId,
            $note
        ]);
    } catch (Throwable $e) {
        // NEřešíme rollback – o ten se stará volající
        throw $e;
    }
}

function wallet_spend(
PDO $pdo,
    int $webUserId,
    int $amount,
    string $reason,
    ?string $refType = null,
    ?int $refId = null,
    ?string $note = null
): void {
   
error_log("DEBUG wallet_spend webUserId={$webUserId}");

 if ($amount <= 0) {
        throw new Exception('wallet_spend: amount must be positive');
    }

    try {
        $balance = wallet_get_balance($pdo, $webUserId, 'DC');
error_log("DEBUG balance={$balance}, amount={$amount}");
        if ($balance < $amount) {
            throw new Exception('INSUFFICIENT_FUNDS');
        }

        // update balance
        $pdo->prepare("
            UPDATE wallet_balances
            SET balance = balance - ?
            WHERE owner_type='WEB' AND owner_id=? AND currency='DC'
        ")->execute([$amount, $webUserId]);

        // ledger
        $pdo->prepare("
            INSERT INTO wallet_ledger
              (owner_type, owner_id, currency, amount, reason, ref_type, ref_id, note)
            VALUES
              ('WEB', ?, 'DC', ?, ?, ?, ?, ?)
        ")->execute([
            $webUserId,
            -$amount,
            $reason,
            $refType,
            $refId,
            $note
        ]);
    } catch (Throwable $e) {
        throw $e;
    }
}
