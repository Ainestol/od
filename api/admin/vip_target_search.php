<?php
/**
 * Admin: autocomplete picker for VIP target.
 *
 * GET params:
 *   scope      — WEB | GAME | CHAR (required)
 *   q          — search query (min 1 char, or empty when webUserId is set for browse mode)
 *   webUserId  — optional, filters GAME and CHAR results to those owned by this web user.
 *                When set with empty q, returns all of that user's accounts (browse mode).
 *   limit      — max results (default 10, max 30)
 *
 * Response shape (scope-aware fields, plus existing_grant if any):
 *   {
 *     ok: true,
 *     scope: "...",
 *     results: [{
 *       id,                      // target_id (web_user_id | game_account_id | charId)
 *       label,                   // main display: email | login | char_name
 *       context,                 // secondary info (e.g. email under login, level under char_name)
 *       extra: { ... },          // scope-specific (game_accounts count for WEB, etc.)
 *       existing_grant: null | {
 *         id, level_id, level_name, end_at, days_remaining
 *       }
 *     }, ...]
 *   }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../config/db_game_write.php'; // $pdoPremium

try {
    assert_admin();

    $scope = $_GET['scope'] ?? '';
    if (!in_array($scope, ['WEB','GAME','CHAR'], true)) {
        echo json_encode(['ok' => false, 'error' => 'INVALID_SCOPE']);
        exit;
    }

    $q         = trim((string)($_GET['q'] ?? ''));
    $qint      = ($q !== '' && ctype_digit($q)) ? (int)$q : 0;
    $webUserId = max(0, (int)($_GET['webUserId'] ?? 0));
    $limit     = max(1, min(30, (int)($_GET['limit'] ?? 10)));

    // Empty q is allowed only when browsing by webUserId (cascade mode for GAME/CHAR)
    if ($q === '' && $webUserId <= 0) {
        echo json_encode(['ok' => true, 'scope' => $scope, 'results' => []]);
        exit;
    }

    $results = [];

    if ($scope === 'WEB') {
        $stmt = $pdo->prepare("
            SELECT
                u.id, u.email,
                (SELECT COUNT(*) FROM game_accounts ga WHERE ga.web_user_id = u.id) AS ga_count
            FROM users u
            WHERE u.email LIKE ?
               OR (? > 0 AND u.id = ?)
            ORDER BY u.email ASC
            LIMIT $limit
        ");
        $stmt->execute(['%' . $q . '%', $qint, $qint]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $results[] = [
                'id'      => (int)$u['id'],
                'label'   => $u['email'],
                'context' => '#' . (int)$u['id'],
                'extra'   => ['game_account_count' => (int)$u['ga_count']],
            ];
        }
    } elseif ($scope === 'GAME') {
        // Build query conditionally based on cascade mode
        $where = [];
        $params = [];

        if ($q === '') {
            // Browse mode: webUserId is required (already validated above)
            $where[] = 'ga.web_user_id = ?';
            $params[] = $webUserId;
        } else {
            $where[] = '(ga.login LIKE ? OR (? > 0 AND ga.id = ?))';
            $params[] = '%' . $q . '%';
            $params[] = $qint;
            $params[] = $qint;
            if ($webUserId > 0) {
                $where[] = 'ga.web_user_id = ?';
                $params[] = $webUserId;
            }
        }

        $sql = "
            SELECT
                ga.id, ga.login, ga.web_user_id, u.email AS web_email
            FROM game_accounts ga
            LEFT JOIN users u ON u.id = ga.web_user_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY ga.is_primary DESC, ga.login ASC
            LIMIT $limit
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $gameAccs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // batch char_count from game DB
        $charCounts = [];
        if (!empty($gameAccs)) {
            $logins = array_column($gameAccs, 'login');
            $ph     = implode(',', array_fill(0, count($logins), '?'));
            $cs = $pdoPremium->prepare("
                SELECT account_name, COUNT(*) AS cnt
                FROM characters
                WHERE account_name IN ($ph)
                GROUP BY account_name
            ");
            $cs->execute($logins);
            foreach ($cs->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $charCounts[strtolower($r['account_name'])] = (int)$r['cnt'];
            }
        }

        foreach ($gameAccs as $g) {
            $results[] = [
                'id'      => (int)$g['id'],
                'label'   => $g['login'],
                'context' => ($g['web_email'] ? $g['web_email'] : 'web #' . (int)$g['web_user_id']),
                'extra'   => [
                    'web_user_id'     => (int)$g['web_user_id'],
                    'character_count' => $charCounts[strtolower($g['login'])] ?? 0,
                ],
            ];
        }
    } else { // CHAR
        $stmt = $pdoPremium->prepare("
            SELECT charId, char_name, account_name, level, classid, online
            FROM characters
            WHERE char_name LIKE ?
               OR (? > 0 AND charId = ?)
            ORDER BY char_name ASC
            LIMIT $limit
        ");
        $stmt->execute(['%' . $q . '%', $qint, $qint]);
        $charRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // resolve account → web user email + game_account_id
        $accountNames = array_unique(array_column($charRows, 'account_name'));
        $accCtx = [];
        if (!empty($accountNames)) {
            $ph = implode(',', array_fill(0, count($accountNames), '?'));
            $s = $pdo->prepare("
                SELECT ga.id AS game_account_id, ga.login, u.email
                FROM game_accounts ga
                LEFT JOIN users u ON u.id = ga.web_user_id
                WHERE ga.login IN ($ph)
            ");
            $s->execute($accountNames);
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $accCtx[strtolower($r['login'])] = [
                    'game_account_id' => (int)$r['game_account_id'],
                    'email'           => $r['email'],
                ];
            }
        }

        foreach ($charRows as $c) {
            $accKey = strtolower($c['account_name']);
            $ctx    = $accCtx[$accKey] ?? null;
            $results[] = [
                'id'      => (int)$c['charId'],
                'label'   => $c['char_name'],
                'context' => ($c['account_name'] ?? '')
                           . ($c['level'] !== null ? ' · Lv.' . (int)$c['level'] : '')
                           . ($ctx['email'] ?? '' ? ' · ' . $ctx['email'] : ''),
                'extra'   => [
                    'level'           => $c['level'] !== null ? (int)$c['level'] : 0,
                    'classid'         => $c['classid'] !== null ? (int)$c['classid'] : 0,
                    'online'          => (int)$c['online'] === 1,
                    'account_name'    => $c['account_name'],
                    'game_account_id' => $ctx['game_account_id'] ?? null,
                ],
            ];
        }
    }

    // ─── Enrich results with existing active grant (per scope/target) ──
    if (!empty($results)) {
        $ids = array_column($results, 'id');
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$scope], $ids);
        $g = $pdo->prepare("
            SELECT vg.id, vg.target_id, vg.level_id, vg.end_at,
                   vl.name AS level_name,
                   TIMESTAMPDIFF(SECOND, NOW(), vg.end_at) AS sec_remaining
            FROM vip_grants vg
            LEFT JOIN vip_levels vl ON vl.level_id = vg.level_id
            WHERE vg.scope = ?
              AND vg.target_id IN ($ph)
              AND vg.end_at > NOW()
            ORDER BY vg.end_at DESC
        ");
        $g->execute($params);
        $grantsByTarget = [];
        foreach ($g->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $tid = (int)$row['target_id'];
            // keep newest end_at per target (already ordered desc)
            if (!isset($grantsByTarget[$tid])) {
                $grantsByTarget[$tid] = [
                    'id'             => (int)$row['id'],
                    'level_id'       => (int)$row['level_id'],
                    'level_name'     => $row['level_name'],
                    'end_at'         => $row['end_at'],
                    'days_remaining' => (int)ceil($row['sec_remaining'] / 86400),
                ];
            }
        }
        foreach ($results as &$r) {
            $r['existing_grant'] = $grantsByTarget[$r['id']] ?? null;
        }
        unset($r);
    }

    echo json_encode([
        'ok'      => true,
        'scope'   => $scope,
        'results' => $results,
    ]);

} catch (Throwable $e) {
    error_log('[admin/vip_target_search] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'SERVER_ERROR']);
}
