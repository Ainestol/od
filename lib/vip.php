<?php

/**
 * Najde aktivní VIP grant (end_at > NOW()) pro daný scope + target.
 */
function vip_get_active(PDO $pdo, string $scope, int $targetId): ?array {
    $stmt = $pdo->prepare("
        SELECT *
        FROM vip_grants
        WHERE scope = ?
          AND target_id = ?
          AND end_at > NOW()
        ORDER BY end_at DESC
        LIMIT 1
    ");
    $stmt->execute([$scope, $targetId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Vrátí cenu a délku v dnech pro VIP level.
 */
function vip_get_price(PDO $pdo, string $scope, int $levelId): array {
    $stmt = $pdo->prepare("
        SELECT price, duration_days
        FROM vip_prices
        WHERE scope = ?
          AND level_id = ?
    ");
    $stmt->execute([$scope, $levelId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new Exception('VIP_PRICE_NOT_FOUND');
    }
    return $row;
}

/**
 * 🔥 SPOLEČNÁ LOGIKA PRO SHOP I ADMIN:
 * - pokud existuje aktivní grant pro scope+target -> prodlouží end_at o $days
 * - pokud neexistuje -> vloží nový (NOW() + $days)
 *
 * Volitelně:
 * - nedovolí downgrade levelu (aby admin omylem nesnížil aktivní VIP)
 * - pokud je nový level vyšší, tak "upgrade" provede bez resetu času (zachová end_at + prodloužení)
 *
 * $source: 'PURCHASE' (shop) nebo 'ADMIN'
 */
function vip_grant_extend_or_create(
    PDO $pdoWeb,
    string $scope,
    int $targetId,
    int $levelId,
    int $days,
    int $createdBy,
    string $source = 'ADMIN',
    bool $preventDowngrade = true
): int {

    // Zamkneme případný aktivní grant (ochrana proti race condition při 2 nákupech rychle po sobě)
    $st = $pdoWeb->prepare("
        SELECT id, level_id
        FROM vip_grants
        WHERE scope = ?
          AND target_id = ?
          AND end_at > NOW()
        ORDER BY end_at DESC
        LIMIT 1
        FOR UPDATE
    ");
    $st->execute([$scope, $targetId]);
    $active = $st->fetch(PDO::FETCH_ASSOC);

    if ($active) {
        $currentLevel = (int)$active['level_id'];

        if ($preventDowngrade && $levelId < $currentLevel) {
            throw new Exception('CANNOT_DOWNGRADE_VIP');
        }

        // Prodloužení vždy (přičítání dní)
        // Level v případě vyššího levelId upgraduje, ale NEresetuje čas.
        $pdoWeb->prepare("
            UPDATE vip_grants
            SET
              end_at = DATE_ADD(end_at, INTERVAL ? DAY),
              level_id = CASE WHEN level_id < ? THEN ? ELSE level_id END,
              source = ?,
              created_by = ?
            WHERE id = ?
        ")->execute([
            $days,
            $levelId, $levelId,
            $source,
            $createdBy,
            (int)$active['id']
        ]);

        return (int)$active['id'];
    }

    // Žádné aktivní VIP => vytvoříme nové
    $stIns = $pdoWeb->prepare("
        INSERT INTO vip_grants
          (scope, target_id, level_id, start_at, end_at, source, created_by)
        VALUES
          (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), ?, ?)
    ");
    $stIns->execute([$scope, $targetId, $levelId, $days, $source, $createdBy]);

    return (int)$pdoWeb->lastInsertId();
}

/**
 * Sync VIP do l2game.account_premium (enddate v milisekundách).
 * - WEB scope: aplikuje na všechny game_accounts pod web_user_id
 * - GAME scope: aplikuje jen na konkrétní game_account (podle id)
 */
function vip_sync_account_premium(PDO $pdoWeb, PDO $pdoGame, string $scope, int $targetId, int $vipGrantId): void {

    $stEnd = $pdoWeb->prepare("SELECT end_at FROM vip_grants WHERE id=? LIMIT 1");
    $stEnd->execute([$vipGrantId]);
    $endAt = $stEnd->fetchColumn();

    if (!$endAt) {
        throw new Exception('VIP_ENDDATE_NOT_FOUND');
    }

    $endMs = strtotime($endAt) * 1000;

    if ($scope === 'WEB') {

        // targetId je web_user_id
        $stAcc = $pdoWeb->prepare("SELECT login FROM game_accounts WHERE web_user_id=?");
        $stAcc->execute([$targetId]);
        $accounts = $stAcc->fetchAll(PDO::FETCH_COLUMN);

        foreach ($accounts as $login) {
            $pdoGame->prepare("
                INSERT INTO account_premium (account_name, enddate)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE enddate = VALUES(enddate)
            ")->execute([$login, $endMs]);
        }

    } elseif ($scope === 'GAME') {

        // targetId je game_accounts.id (ve WEB DB)
        $stAcc = $pdoWeb->prepare("SELECT login FROM game_accounts WHERE id=? LIMIT 1");
        $stAcc->execute([$targetId]);
        $login = $stAcc->fetchColumn();

        if (!$login) {
            throw new Exception('GAME_ACCOUNT_NOT_FOUND');
        }

        $pdoGame->prepare("
            INSERT INTO account_premium (account_name, enddate)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE enddate = VALUES(enddate)
        ")->execute([$login, $endMs]);

    } else {
        throw new Exception('INVALID_SCOPE');
    }
}

/**
 * Původní purchase funkce – zachováno.
 * Upravena tak, aby používala společný helper vip_grant_extend_or_create()
 * a tím se chovala stejně jako shop/admin (prodloužení, nikoliv reset).
 *
 * Pozn.: sync do account_premium se tady nedělá, protože funkce nemá $pdoGame.
 * Pokud chceš sync i tady, udělej variantu vip_purchase_with_sync(..., $pdoGame).
 */
function vip_purchase(
    PDO $pdo,
    int $webUserId,
    string $scope,
    int $targetId,
    int $levelId
): int {
    try {
        $pdo->beginTransaction();

        $price = vip_get_price($pdo, $scope, $levelId);

        // vyžaduje wallet_spend z lib/wallet.php (volající musí mít require)
        wallet_spend(
            $pdo,
            $webUserId,
            (int)$price['price'],
            'vip_purchase',
            'vip',
            null,
            "$scope VIP level $levelId"
        );

        // společná logika prodlužování / vytvoření
        $grantId = vip_grant_extend_or_create(
            $pdo,
            $scope,
            $targetId,
            $levelId,
            (int)$price['duration_days'],
            $webUserId,
            'PURCHASE',
            true
        );

        $pdo->commit();
        return $grantId;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}