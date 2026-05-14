<?php
/**
 * Admin: VIP grants list — enriched with resolved target names.
 *
 * GET params (all optional):
 *   showExpired  — '1' = include grants where end_at <= NOW
 *   scope        — WEB | GAME | CHAR (filter)
 *   sort         — end_at | start_at | created_at | level_id | scope (default: end_at)
 *   dir          — asc | desc (default: desc)
 *   expiringSoon — '1' = only active grants with < 5 days remaining
 *
 * Response (additive — backward compatible):
 *   {
 *     ok: true,
 *     data: [{
 *       id, scope, target_id, level_id, level_name,
 *       target_label, target_context,
 *       start_at, end_at, source, created_at, created_by,
 *       is_expired, days_remaining
 *     }, ...],
 *     stats: { total, active, expired, by_scope: {WEB, GAME, CHAR}, expiring_soon }
 *   }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../config/db_game_write.php'; // $pdoPremium (game DB read)

try {
    assert_admin();

    $showExpired  = !empty($_GET['showExpired']);
    $expiringSoon = !empty($_GET['expiringSoon']);
    $scopeFilter  = in_array(($_GET['scope'] ?? ''), ['WEB','GAME','CHAR'], true)
                      ? $_GET['scope'] : null;

    $sortWhitelist = ['end_at','start_at','created_at','level_id','scope'];
    $sort = in_array(($_GET['sort'] ?? ''), $sortWhitelist, true) ? $_GET['sort'] : 'end_at';
    $dir  = (($_GET['dir'] ?? '') === 'asc') ? 'ASC' : 'DESC';

    $where  = [];
    $params = [];

    if (!$showExpired) {
        $where[] = 'vg.end_at > NOW()';
    }
    if ($scopeFilter) {
        $where[] = 'vg.scope = ?';
        $params[] = $scopeFilter;
    }
    if ($expiringSoon) {
        $where[] = 'vg.end_at > NOW() AND vg.end_at < DATE_ADD(NOW(), INTERVAL 5 DAY)';
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "
        SELECT
            vg.id,
            vg.scope,
            vg.target_id,
            vg.level_id,
            vg.start_at,
            vg.end_at,
            vg.source,
            vg.created_at,
            vg.created_by,
            vl.name AS level_name,
            (vg.end_at <= NOW())             AS is_expired,
            TIMESTAMPDIFF(SECOND, NOW(), vg.end_at) AS sec_remaining
        FROM vip_grants vg
        LEFT JOIN vip_levels vl ON vl.level_id = vg.level_id
        $whereSql
        ORDER BY $sort $dir
        LIMIT 500
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ─── Resolve target labels per scope ──────────────────
    // Collect ids by scope
    $idsByScope = ['WEB' => [], 'GAME' => [], 'CHAR' => []];
    foreach ($rows as $r) {
        $idsByScope[$r['scope']][] = (int)$r['target_id'];
    }

    $labelByScope = ['WEB' => [], 'GAME' => [], 'CHAR' => []];
    $contextByScope = ['WEB' => [], 'GAME' => [], 'CHAR' => []];

    if (!empty($idsByScope['WEB'])) {
        $ids = array_unique($idsByScope['WEB']);
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $s = $pdo->prepare("SELECT id, email FROM users WHERE id IN ($ph)");
        $s->execute($ids);
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $labelByScope['WEB'][(int)$u['id']] = $u['email'];
        }
    }

    if (!empty($idsByScope['GAME'])) {
        $ids = array_unique($idsByScope['GAME']);
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $s = $pdo->prepare("
            SELECT ga.id, ga.login, u.email AS web_email
            FROM game_accounts ga
            LEFT JOIN users u ON u.id = ga.web_user_id
            WHERE ga.id IN ($ph)
        ");
        $s->execute($ids);
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $g) {
            $labelByScope['GAME'][(int)$g['id']]   = $g['login'];
            $contextByScope['GAME'][(int)$g['id']] = $g['web_email'];
        }
    }

    if (!empty($idsByScope['CHAR'])) {
        $ids = array_unique($idsByScope['CHAR']);
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $s = $pdoPremium->prepare("
            SELECT charId, char_name, account_name, level
            FROM characters
            WHERE charId IN ($ph)
        ");
        $s->execute($ids);
        $charRows = $s->fetchAll(PDO::FETCH_ASSOC);

        // also resolve account → web email for context
        $accountNames = array_unique(array_column($charRows, 'account_name'));
        $accToEmail = [];
        if (!empty($accountNames)) {
            $phA = implode(',', array_fill(0, count($accountNames), '?'));
            $sA = $pdo->prepare("
                SELECT ga.login, u.email
                FROM game_accounts ga
                LEFT JOIN users u ON u.id = ga.web_user_id
                WHERE ga.login IN ($phA)
            ");
            $sA->execute($accountNames);
            foreach ($sA->fetchAll(PDO::FETCH_ASSOC) as $ga) {
                $accToEmail[strtolower($ga['login'])] = $ga['email'];
            }
        }

        foreach ($charRows as $c) {
            $cid = (int)$c['charId'];
            $labelByScope['CHAR'][$cid] = $c['char_name'];
            $email = $accToEmail[strtolower($c['account_name'])] ?? null;
            $contextByScope['CHAR'][$cid] =
                ($c['account_name'] ?? '') .
                ($c['level'] !== null ? ' · Lv.' . (int)$c['level'] : '') .
                ($email ? ' · ' . $email : '');
        }
    }

    // ─── Stats + final shape ─────────────────────────────
    $stats = [
        'total'         => 0,
        'active'        => 0,
        'expired'       => 0,
        'expiring_soon' => 0,
        'by_scope'      => ['WEB' => 0, 'GAME' => 0, 'CHAR' => 0],
        // derived from game DB — how many game accounts actually have Premium in-game
        'game_accounts_with_premium' => 0,
    ];

    // Effective premium accounts (Premium 24h, GAME, or WEB cascade — anything in account_premium)
    try {
        $cStmt = $pdoPremium->query("
            SELECT COUNT(*) AS cnt
            FROM account_premium
            WHERE enddate > UNIX_TIMESTAMP() * 1000
        ");
        $stats['game_accounts_with_premium'] = (int)$cStmt->fetchColumn();
    } catch (Throwable $e) {
        // not critical — just leave it at 0
        error_log('[admin/vip_list] effective count failed: ' . $e->getMessage());
    }

    foreach ($rows as &$r) {
        $r['id']            = (int)$r['id'];
        $r['target_id']     = (int)$r['target_id'];
        $r['level_id']      = (int)$r['level_id'];
        $r['is_expired']    = (int)$r['is_expired'] === 1;
        $r['sec_remaining'] = (int)$r['sec_remaining'];
        $r['days_remaining']= $r['is_expired'] ? 0 : (int)ceil($r['sec_remaining'] / 86400);
        $r['target_label']  = $labelByScope[$r['scope']][$r['target_id']] ?? null;
        $r['target_context']= $contextByScope[$r['scope']][$r['target_id']] ?? null;
        $r['created_by']    = $r['created_by'] !== null ? (int)$r['created_by'] : null;

        $stats['total']++;
        $stats['by_scope'][$r['scope']]++;
        if ($r['is_expired']) {
            $stats['expired']++;
        } else {
            $stats['active']++;
            if ($r['days_remaining'] > 0 && $r['days_remaining'] < 5) {
                $stats['expiring_soon']++;
            }
        }
    }
    unset($r);

    echo json_encode([
        'ok'    => true,
        'data'  => $rows,
        'stats' => $stats,
    ]);

} catch (Throwable $e) {
    error_log('[admin/vip_list] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'SERVER_ERROR']);
}
