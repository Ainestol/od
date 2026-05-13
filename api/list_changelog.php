<?php
/**
 * Public changelog list.
 * GET ?limit=N (default 5, max 100), ?lang=cs|en
 *
 * Vrací nejnovější publikované záznamy. EN: pokud title_en/body_en je NULL,
 * fallback na CS + flag `lang_fallback: true`.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/_bootstrap.php';

try {
    $limit = (int)($_GET['limit'] ?? 5);
    if ($limit < 1) $limit = 5;
    if ($limit > 100) $limit = 100;

    $lang = (($_GET['lang'] ?? 'cs') === 'en') ? 'en' : 'cs';

    $stmt = $pdo->prepare("
        SELECT id, title_cs, title_en, body_cs, body_en, category, created_at
        FROM changelog
        WHERE is_published = 1
        ORDER BY created_at DESC
        LIMIT $limit
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $entries = [];
    foreach ($rows as $r) {
        $useEn = ($lang === 'en');
        $titleEnEmpty = ($r['title_en'] === null || $r['title_en'] === '');
        $bodyEnEmpty  = ($r['body_en']  === null || $r['body_en']  === '');
        $fallback = $useEn && ($titleEnEmpty || $bodyEnEmpty);

        $entries[] = [
            'id'             => (int)$r['id'],
            'title'          => ($useEn && !$titleEnEmpty) ? $r['title_en'] : $r['title_cs'],
            'body'           => ($useEn && !$bodyEnEmpty)  ? $r['body_en']  : $r['body_cs'],
            'category'       => $r['category'],
            'created_at'     => $r['created_at'],
            'lang_fallback'  => $fallback,
        ];
    }

    echo json_encode(['ok' => true, 'entries' => $entries]);

} catch (Throwable $e) {
    error_log('[list_changelog] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'SERVER_ERROR']);
}
