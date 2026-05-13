<?php
/**
 * Save changelog entry — create or update.
 *
 * POST JSON:
 *   { id?: int (pro update), title_cs, title_en?, body_cs, body_en?,
 *     category: 'feature'|'fix'|'change'|'important'|'event',
 *     is_published: 0|1 }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../api/admin/_bootstrap.php';

try {
    assert_admin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'METHOD_NOT_ALLOWED']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;

    $id           = (int)($input['id'] ?? 0);
    $titleCs      = trim((string)($input['title_cs'] ?? ''));
    $titleEn      = trim((string)($input['title_en'] ?? ''));
    $bodyCs       = trim((string)($input['body_cs'] ?? ''));
    $bodyEn       = trim((string)($input['body_en'] ?? ''));
    $category     = (string)($input['category'] ?? 'change');
    $isPublished  = (int)($input['is_published'] ?? 1) === 1 ? 1 : 0;

    // Validace
    if ($titleCs === '' || $bodyCs === '') {
        echo json_encode(['ok' => false, 'error' => 'TITLE_AND_BODY_CS_REQUIRED']);
        exit;
    }
    if (mb_strlen($titleCs) > 200 || mb_strlen($titleEn) > 200) {
        echo json_encode(['ok' => false, 'error' => 'TITLE_TOO_LONG']);
        exit;
    }
    $allowedCats = ['feature','fix','change','important','event'];
    if (!in_array($category, $allowedCats, true)) {
        echo json_encode(['ok' => false, 'error' => 'INVALID_CATEGORY']);
        exit;
    }

    $adminId = (int)$_SESSION['web_user_id'];
    $titleEnVal = ($titleEn === '') ? null : $titleEn;
    $bodyEnVal  = ($bodyEn  === '') ? null : $bodyEn;

    if ($id > 0) {
        // UPDATE
        $stmt = $pdo->prepare("
            UPDATE changelog
            SET title_cs = ?, title_en = ?, body_cs = ?, body_en = ?,
                category = ?, is_published = ?
            WHERE id = ?
        ");
        $stmt->execute([$titleCs, $titleEnVal, $bodyCs, $bodyEnVal, $category, $isPublished, $id]);
        admin_audit($pdo, 'changelog_update', null, ['id' => $id, 'category' => $category]);
    } else {
        // INSERT
        $stmt = $pdo->prepare("
            INSERT INTO changelog
                (title_cs, title_en, body_cs, body_en, category, is_published, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$titleCs, $titleEnVal, $bodyCs, $bodyEnVal, $category, $isPublished, $adminId]);
        $id = (int)$pdo->lastInsertId();
        admin_audit($pdo, 'changelog_create', null, ['id' => $id, 'category' => $category]);
    }

    echo json_encode(['ok' => true, 'id' => $id]);

} catch (Throwable $e) {
    error_log('[admin/save_changelog] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'SERVER_ERROR']);
}
