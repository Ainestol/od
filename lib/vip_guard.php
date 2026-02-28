<?php

/**
 * Ověří, že webUserId vlastní target podle scope.
 * Vyhodí Exception při porušení.
 */
function vip_assert_ownership(
    PDO $pdoWeb,
    PDO $pdoGame,
    int $webUserId,
    string $scope,
    int $targetId
): void {

    if ($scope === 'WEB') {
        if ($targetId !== $webUserId) {
            throw new Exception('FORBIDDEN_WEB_TARGET');
        }
        return;
    }

    if ($scope === 'GAME') {
        $stmt = $pdoWeb->prepare("
            SELECT web_user_id
            FROM game_accounts
            WHERE id = ?
        ");
        $stmt->execute([$targetId]);
        $owner = $stmt->fetchColumn();

        if ((int)$owner !== $webUserId) {
            throw new Exception('FORBIDDEN_GAME_TARGET');
        }
        return;
    }

    if ($scope === 'CHAR') {
        // charId -> account_name
        $stmt = $pdoGame->prepare("
            SELECT account_name
            FROM characters
            WHERE charId = ?
        ");
        $stmt->execute([$targetId]);
        $login = $stmt->fetchColumn();

        if (!$login) {
            throw new Exception('CHAR_NOT_FOUND');
        }

        // account_name -> web_user_id
        $stmt = $pdoWeb->prepare("
            SELECT web_user_id
            FROM game_accounts
            WHERE login = ?
        ");
        $stmt->execute([$login]);
        $owner = $stmt->fetchColumn();

        if ((int)$owner !== $webUserId) {
            throw new Exception('FORBIDDEN_CHAR_TARGET');
        }
        return;
    }

    throw new Exception('INVALID_SCOPE');
}
