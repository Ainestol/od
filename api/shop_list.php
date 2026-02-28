<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (empty($_SESSION['web_user_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false]);
  exit;
}

require_once __DIR__ . '/../config/db.php';

try {
  // jazyk: buď z URL (?lang=en), nebo ze session
  $lang = $_GET['lang'] ?? ($_SESSION['lang'] ?? 'cs');

  $st = $pdo->query("
    SELECT
  id, code, item_id, category, price_dc, is_repeatable,
    name, description,
    name AS name_en, description_en
    FROM shop_products
    WHERE is_active = 1
    ORDER BY category, price_dc, id
  ");

  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  // přepni texty na EN, pokud je EN a sloupce jsou vyplněné
  if ($lang === 'en') {
    foreach ($rows as &$r) {
      if (!empty($r['name_en']))        $r['name'] = $r['name_en'];
      if (!empty($r['description_en'])) $r['description'] = $r['description_en'];
      unset($r['name_en'], $r['description_en']); // volitelně, ať to neposíláš ven
    }
    unset($r);
  } else {
    // volitelně odstraň EN sloupce i v CS
    foreach ($rows as &$r) {
      unset($r['name_en'], $r['description_en']);
    }
    unset($r);
  }

  echo json_encode([
    'ok' => true,
    'products' => $rows
  ]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}