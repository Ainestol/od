<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (empty($_SESSION['web_user_id'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false]);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$userId = (int)$_SESSION['web_user_id'];

$sites = $pdo->query("SELECT * FROM vote_sites WHERE is_active = 1")->fetchAll();

$result = [];

foreach ($sites as $site) {

    $st = $pdo->prepare("
        SELECT voted_at
        FROM vote_logs
        WHERE web_user_id = ?
          AND vote_site_id = ?
        ORDER BY voted_at DESC
        LIMIT 1
    ");
    $st->execute([$userId, $site['id']]);
    $lastVote = $st->fetchColumn();

    $cooldown = $site['cooldown_hours'] * 3600;

    $remaining = 0;
    if ($lastVote) {
        $remaining = max(0, strtotime($lastVote) + $cooldown - time());
    }

    $result[] = [
        'id' => $site['id'],
        'name' => $site['name'],
        'url' => $site['url'],
        'remaining' => $remaining
    ];
}

echo json_encode(['ok'=>true, 'sites'=>$result]);
