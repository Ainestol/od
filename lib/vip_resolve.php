<?php
// /lib/vip_resolve.php

require_once __DIR__ . '/vip.php'; // obsahuje vip_get_active()

/**
 * Resolve effective VIP for a character with precedence:
 * CHAR > GAME > WEB
 *
 * @return array {
 *   found: bool,
 *   scope: ?string,
 *   level_id: ?int,
 *   start_at: ?string,
 *   end_at: ?string,
 *   seconds_left: ?int,
 *   char_id: int,
 *   account_name: ?string,
 *   game_account_id: ?int,
 *   web_user_id: ?int
 * }
 */
function vip_get_effective_for_character(PDO $pdoWeb, PDO $pdoGame, int $charId): array
{
    // 0) CHAR grant?
    $charGrant = vip_get_active($pdoWeb, 'CHAR', $charId);
    if ($charGrant) {
        return vip_resolve_pack_result($charId, null, null, $charGrant);
    }

    // 1) get account_name from l2game.characters
    $stmt = $pdoGame->prepare("
        SELECT account_name
        FROM characters
        WHERE charId = ?
        LIMIT 1
    ");
    $stmt->execute([$charId]);
    $accountName = $stmt->fetchColumn();

    if (!$accountName) {
        return [
            'found' => false,
            'scope' => null,
            'level_id' => null,
            'start_at' => null,
            'end_at' => null,
            'seconds_left' => null,
            'char_id' => $charId,
            'account_name' => null,
            'game_account_id' => null,
            'web_user_id' => null,
            'error' => 'CHAR_NOT_FOUND'
        ];
    }

    // 2) map account_name -> game_accounts.id + web_user_id
    $stmt = $pdoWeb->prepare("
        SELECT id, web_user_id
        FROM game_accounts
        WHERE login = ?
        LIMIT 1
    ");
    $stmt->execute([$accountName]);
    $ga = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ga) {
        return [
            'found' => false,
            'scope' => null,
            'level_id' => null,
            'start_at' => null,
            'end_at' => null,
            'seconds_left' => null,
            'char_id' => $charId,
            'account_name' => $accountName,
            'game_account_id' => null,
            'web_user_id' => null,
            'error' => 'GAME_ACCOUNT_NOT_LINKED'
        ];
    }

    $gameAccountId = (int)$ga['id'];
    $webUserId     = (int)$ga['web_user_id'];

    // 3) GAME grant?
    $gameGrant = vip_get_active($pdoWeb, 'GAME', $gameAccountId);
    if ($gameGrant) {
        return vip_resolve_pack_result($charId, $accountName, ['game_account_id'=>$gameAccountId,'web_user_id'=>$webUserId], $gameGrant);
    }

    // 4) WEB grant?
    $webGrant = vip_get_active($pdoWeb, 'WEB', $webUserId);
    if ($webGrant) {
        return vip_resolve_pack_result($charId, $accountName, ['game_account_id'=>$gameAccountId,'web_user_id'=>$webUserId], $webGrant);
    }

    // 5) none
    return [
        'found' => false,
        'scope' => null,
        'level_id' => null,
        'start_at' => null,
        'end_at' => null,
        'seconds_left' => null,
        'char_id' => $charId,
        'account_name' => $accountName,
        'game_account_id' => $gameAccountId,
        'web_user_id' => $webUserId
    ];
}

/**
 * Helper to unify output.
 */
function vip_resolve_pack_result(int $charId, ?string $accountName, ?array $ids, array $grant): array
{
    $secondsLeft = null;
    if (!empty($grant['end_at'])) {
        $end = strtotime($grant['end_at']);
        $secondsLeft = max(0, $end - time());
    }

    return [
        'found' => true,
        'scope' => $grant['scope'] ?? null,
        'level_id' => isset($grant['level_id']) ? (int)$grant['level_id'] : null,
        'start_at' => $grant['start_at'] ?? null,
        'end_at' => $grant['end_at'] ?? null,
        'seconds_left' => $secondsLeft,
        'char_id' => $charId,
        'account_name' => $accountName,
        'game_account_id' => $ids['game_account_id'] ?? null,
        'web_user_id' => $ids['web_user_id'] ?? null,
        'vip_grant_id' => isset($grant['id']) ? (int)$grant['id'] : null
    ];
}
