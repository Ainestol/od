<?php
/**
 * Admin users list — enriched with inline metadata + search + filters.
 *
 * GET params (all optional):
 *   q       — search query (matches: email, web user id, game login,
 *             game account id, character name, character id)
 *   filter  — CSV of filter keys (combined with AND):
 *               verified | unverified | admin | twofa
 *               has_premium | no_game_account | multi_account
 *   sort    — sort key (whitelist):
 *               id | email | created_at | last_access_ms
 *               game_account_count | character_count
 *               vc_balance | dc_balance
 *   dir     — asc | desc (default desc)
 *
 * Response (extends previous shape — additive, backward compatible):
 *   {
 *     ok: true,
 *     data: [{
 *       id, email, role, is_verified, twofa_enabled, created_at,
 *       game_account_count, character_count,
 *       vc_balance, dc_balance, premium_active,
 *       any_online, last_access_ms
 *     }, ...],
 *     total: int,
 *     q: string,
 *     filters: string[]
 *   }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../config/db_game_write.php'; // $pdoPremium (game DB read)

try {
    assert_admin();

    $q       = trim((string)($_GET['q'] ?? ''));
    $filters = array_values(array_filter(array_map(
        'trim',
        explode(',', (string)($_GET['filter'] ?? ''))
    )));
    $qint    = ($q !== '' && ctype_digit($q)) ? (int)$q : 0;

    $sortWhitelist = [
        'id', 'email', 'created_at',
        'game_account_count', 'character_count',
        'vc_balance', 'dc_balance',
        'last_access_ms',
    ];
    $sort = in_array(($_GET['sort'] ?? ''), $sortWhitelist, true) ? $_GET['sort'] : 'id';
    $dir  = (($_GET['dir'] ?? '') === 'asc') ? 'asc' : 'desc';

    // ─────────────────────────────────────────────────────────
    // STEP A — pre-collect matching account_names from game DB
    //          (covers char_name / charId search)
    // ─────────────────────────────────────────────────────────
    $charMatchAccountNames = [];
    if ($q !== '') {
        $stmt = $pdoPremium->prepare("
            SELECT DISTINCT account_name
            FROM characters
            WHERE char_name LIKE ?
               OR (? > 0 AND charId = ?)
            LIMIT 200
        ");
        $stmt->execute(['%' . $q . '%', $qint, $qint]);
        $charMatchAccountNames = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // ─────────────────────────────────────────────────────────
    // STEP B — main query on web DB
    // ─────────────────────────────────────────────────────────
    $where  = [];
    $params = [];

    if ($q !== '') {
        $or = [
            'u.email LIKE ?',
            '(? > 0 AND u.id = ?)',
            "EXISTS (
                SELECT 1 FROM game_accounts ga
                WHERE ga.web_user_id = u.id
                  AND (ga.login LIKE ? OR (? > 0 AND ga.id = ?))
            )",
        ];
        // matched order of '?' placeholders above:
        array_push($params,
            '%' . $q . '%',   // u.email LIKE
            $qint, $qint,     // (qint > 0 AND u.id = qint)
            '%' . $q . '%',   // ga.login LIKE
            $qint, $qint      // (qint > 0 AND ga.id = qint)
        );

        if (!empty($charMatchAccountNames)) {
            $ph = implode(',', array_fill(0, count($charMatchAccountNames), '?'));
            $or[] = "EXISTS (
                SELECT 1 FROM game_accounts gax
                WHERE gax.web_user_id = u.id
                  AND gax.login IN ($ph)
            )";
            foreach ($charMatchAccountNames as $name) {
                $params[] = $name;
            }
        }

        $where[] = '(' . implode(' OR ', $or) . ')';
    }

    // Filter chips — combine with AND
    foreach ($filters as $f) {
        switch ($f) {
            case 'verified':
                $where[] = 'u.is_verified = 1';
                break;
            case 'unverified':
                $where[] = 'u.is_verified = 0';
                break;
            case 'admin':
                $where[] = "u.role = 'admin'";
                break;
            case 'twofa':
                $where[] = 'u.twofa_enabled = 1';
                break;
            case 'has_premium':
                $where[] = "EXISTS (
                    SELECT 1 FROM vip_grants vg
                    WHERE vg.end_at > NOW()
                      AND (
                           (vg.scope = 'WEB'  AND vg.target_id = u.id)
                        OR (vg.scope = 'GAME' AND vg.target_id IN
                              (SELECT id FROM game_accounts WHERE web_user_id = u.id))
                      )
                )";
                break;
            case 'no_game_account':
                $where[] = 'NOT EXISTS (
                    SELECT 1 FROM game_accounts ga WHERE ga.web_user_id = u.id
                )';
                break;
            case 'multi_account':
                $where[] = '(SELECT COUNT(*) FROM game_accounts
                              WHERE web_user_id = u.id) > 1';
                break;
            // unknown filter keys silently ignored
        }
    }

    $sql = "
        SELECT
            u.id,
            u.email,
            u.role,
            u.is_verified,
            u.twofa_enabled,
            u.created_at,
            (SELECT COUNT(*) FROM game_accounts
              WHERE web_user_id = u.id)                          AS game_account_count,
            (SELECT balance FROM wallet_balances
              WHERE owner_type = 'WEB' AND owner_id = u.id
                AND currency = 'VOTE_COIN')                      AS vc_balance,
            (SELECT balance FROM wallet_balances
              WHERE owner_type = 'WEB' AND owner_id = u.id
                AND currency = 'DC')                             AS dc_balance,
            EXISTS (
                SELECT 1 FROM vip_grants vg
                WHERE vg.end_at > NOW()
                  AND (
                       (vg.scope = 'WEB'  AND vg.target_id = u.id)
                    OR (vg.scope = 'GAME' AND vg.target_id IN
                          (SELECT id FROM game_accounts WHERE web_user_id = u.id))
                  )
            )                                                    AS premium_active
        FROM users u
    ";
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY u.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ─────────────────────────────────────────────────────────
    // STEP C — enrich with game-DB aggregates
    //          (character_count, any_online, last_access_ms)
    // ─────────────────────────────────────────────────────────
    if (!empty($users)) {
        $userIds = array_column($users, 'id');
        $idPlace = implode(',', array_fill(0, count($userIds), '?'));

        $gaStmt = $pdo->prepare("
            SELECT web_user_id, login
            FROM game_accounts
            WHERE web_user_id IN ($idPlace)
        ");
        $gaStmt->execute($userIds);
        $gameAccounts = $gaStmt->fetchAll(PDO::FETCH_ASSOC);

        $loginToUid = [];
        foreach ($gameAccounts as $ga) {
            $loginToUid[strtolower($ga['login'])] = (int)$ga['web_user_id'];
        }

        $aggByUid = [];
        if (!empty($gameAccounts)) {
            $logins     = array_column($gameAccounts, 'login');
            $loginPlace = implode(',', array_fill(0, count($logins), '?'));

            $chStmt = $pdoPremium->prepare("
                SELECT account_name,
                       COUNT(*)        AS char_count,
                       MAX(online)     AS any_online,
                       MAX(lastAccess) AS last_access
                FROM characters
                WHERE account_name IN ($loginPlace)
                GROUP BY account_name
            ");
            $chStmt->execute($logins);

            foreach ($chStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $uid = $loginToUid[strtolower($r['account_name'])] ?? null;
                if (!$uid) continue;
                if (!isset($aggByUid[$uid])) {
                    $aggByUid[$uid] = [
                        'char_count'  => 0,
                        'any_online'  => 0,
                        'last_access' => 0,
                    ];
                }
                $aggByUid[$uid]['char_count']  += (int)$r['char_count'];
                $aggByUid[$uid]['any_online']   = max($aggByUid[$uid]['any_online'],  (int)$r['any_online']);
                $aggByUid[$uid]['last_access']  = max($aggByUid[$uid]['last_access'], (int)$r['last_access']);
            }
        }

        foreach ($users as &$u) {
            $agg = $aggByUid[$u['id']] ?? [
                'char_count'  => 0,
                'any_online'  => 0,
                'last_access' => 0,
            ];
            $u['id']                 = (int)$u['id'];
            $u['is_verified']        = (int)$u['is_verified'] === 1;
            $u['twofa_enabled']      = (int)$u['twofa_enabled'] === 1;
            $u['game_account_count'] = (int)$u['game_account_count'];
            $u['character_count']    = (int)$agg['char_count'];
            $u['vc_balance']         = $u['vc_balance'] === null ? 0 : (int)$u['vc_balance'];
            $u['dc_balance']         = $u['dc_balance'] === null ? 0 : (int)$u['dc_balance'];
            $u['premium_active']     = (int)$u['premium_active'] === 1;
            $u['any_online']         = (int)$agg['any_online'] === 1;
            $u['last_access_ms']     = (int)$agg['last_access'];
        }
        unset($u);
    }

    // ─────────────────────────────────────────────────────────
    // STEP D — sort in PHP (cross-DB aggregates already present)
    // ─────────────────────────────────────────────────────────
    if (!empty($users)) {
        usort($users, function ($a, $b) use ($sort, $dir) {
            $av = $a[$sort] ?? 0;
            $bv = $b[$sort] ?? 0;
            if (is_bool($av)) $av = $av ? 1 : 0;
            if (is_bool($bv)) $bv = $bv ? 1 : 0;
            if (is_string($av) || is_string($bv)) {
                $cmp = strnatcasecmp((string)$av, (string)$bv);
            } else {
                $cmp = $av <=> $bv;
            }
            return $dir === 'asc' ? $cmp : -$cmp;
        });
    }

    echo json_encode([
        'ok'      => true,
        'data'    => $users,
        'total'   => count($users),
        'q'       => $q,
        'filters' => $filters,
        'sort'    => $sort,
        'dir'     => $dir,
    ]);

} catch (Throwable $e) {
    error_log('[admin/users_list] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'SERVER_ERROR']);
}
