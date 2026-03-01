<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../lib/csrf.php';
csrf_check();
if (empty($_SESSION['web_user_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
  exit;
}

require_once __DIR__ . '/../lib/vip.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/db_game.php';        // reader (SELECT)
require_once __DIR__ . '/../config/db_game_write.php';  // writer (INSERT)
require_once __DIR__ . '/../lib/wallet.php';

$userId = (int)$_SESSION['web_user_id'];
$input = $_POST ?: json_decode(file_get_contents('php://input'), true);

$productId     = (int)($input['product_id'] ?? 0);
$gameAccountId = (int)($input['game_account_id'] ?? 0); // jen pro VIP GAME scope
$charId        = (int)($input['char_id'] ?? 0);         // jen pro MOUNT

if (!$productId) {
  echo json_encode(['ok' => false, 'error' => 'MISSING_product_id']);
  exit;
}

/**
 * VIP produkty mapované podle code -> parametry.
 * Uprav si levelId podle vip_levels.
 */
$EFFECTS = [
  'PREM_GAME_30D' => ['scope' => 'GAME', 'levelId' => 2, 'days' => 30],
  'PREM_WEB_30D'  => ['scope' => 'WEB',  'levelId' => 3, 'days' => 30],
];

try {
  $pdo->beginTransaction();

  // 1) produkt (zamknout)
  $st = $pdo->prepare("
    SELECT id, code, category, item_id, price_dc, is_active, is_repeatable
    FROM shop_products
    WHERE id = ?
    FOR UPDATE
  ");
  $st->execute([$productId]);
  $p = $st->fetch(PDO::FETCH_ASSOC);

  if (!$p || (int)$p['is_active'] !== 1) throw new Exception('PRODUCT_NOT_FOUND');

  $code     = (string)$p['code'];
  $category = (string)$p['category'];
  $price    = (int)$p['price_dc'];

  // 2) validace podle category
 if ($category === 'VIP') {
  if (!isset($EFFECTS[$code])) throw new Exception('PRODUCT_NOT_IMPLEMENTED');
} elseif ($category === 'MOUNT' || $category === 'COSMETIC') {
  if (empty($p['item_id'])) throw new Exception('MISSING_item_id');
  if (!$charId) throw new Exception('MISSING_char_id');
} else {
  throw new Exception('PRODUCT_NOT_IMPLEMENTED');
}
  // 3) duplicita (pokud není repeatable)
  if ((int)$p['is_repeatable'] === 0) {
    $st = $pdo->prepare("
      SELECT 1 FROM shop_orders
      WHERE web_user_id=? AND product_id=? AND status='PAID'
      LIMIT 1
      FOR UPDATE
    ");
    $st->execute([$userId, $productId]);
    if ($st->fetchColumn()) throw new Exception('ALREADY_PURCHASED');
  }

  // 4) parametry pro VIP / nebo ověř ownership postavy pro MOUNT
  $scope = null; $targetId = null; $levelId = null; $days = null;

  if ($category === 'VIP') {
    $scope   = (string)$EFFECTS[$code]['scope'];
    $levelId = (int)$EFFECTS[$code]['levelId'];
    $days    = (int)$EFFECTS[$code]['days'];

    if ($scope === 'WEB') {
      $targetId = $userId;
    } elseif ($scope === 'GAME') {
      if (!$gameAccountId) throw new Exception('MISSING_game_account_id');

      $stOwn = $pdo->prepare("SELECT 1 FROM game_accounts WHERE id=? AND web_user_id=? LIMIT 1");
      $stOwn->execute([$gameAccountId, $userId]);
      if (!$stOwn->fetchColumn()) throw new Exception('GAME_ACCOUNT_NOT_OWNED');

      $targetId = $gameAccountId;
    } else {
      throw new Exception('INVALID_SCOPE');
    }
  }

  if ($category === 'MOUNT' || $category === 'COSMETIC') {
    // zjisti account_name postavy v game DB (reader)
    $stCh = $pdoGame->prepare("SELECT account_name FROM characters WHERE charId=? LIMIT 1");
    $stCh->execute([$charId]);
    $accName = $stCh->fetchColumn();
    if (!$accName) throw new Exception('CHAR_NOT_FOUND');

    // ověř, že account patří userovi ve web DB
    $stOwn = $pdo->prepare("SELECT 1 FROM game_accounts WHERE login=? AND web_user_id=? LIMIT 1");
    $stOwn->execute([$accName, $userId]);
    if (!$stOwn->fetchColumn()) throw new Exception('CHAR_NOT_OWNED');
  }

  // 5) stržení DC + ledger
  wallet_spend($pdo, $userId, $price, 'shop_buy', 'shop_product', $productId, $code);

  // 6) order log
  $pdo->prepare("
    INSERT INTO shop_orders (web_user_id, product_id, price_dc, status)
    VALUES (?, ?, ?, 'PAID')
  ")->execute([$userId, $productId, $price]);

  $orderId = (int)$pdo->lastInsertId();

  // 7) deliver
  $vipGrantId = null;

  if ($category === 'VIP') {
    $vipGrantId = vip_grant_extend_or_create(
      $pdo,
      $scope,
      (int)$targetId,
      (int)$levelId,
      (int)$days,
      $userId,
      'PURCHASE',
      true
    );

    vip_sync_account_premium($pdo, $pdoGame, $scope, (int)$targetId, (int)$vipGrantId);
  }

  if ($category === 'MOUNT' || $category === 'COSMETIC') {
    $itemId = (int)$p['item_id'];

    // bezpečné generování object_id přes sekvenci object_id_seq (bez LOCK TABLES)
    $pdoGameWrite->beginTransaction();

    // posuň sekvenci a vrať nové ID
    $pdoGameWrite->exec("UPDATE object_id_seq SET next_id = LAST_INSERT_ID(next_id + 1) WHERE id = 1");
    $newObjectId = (int)$pdoGameWrite->lastInsertId();

    $stIns = $pdoGameWrite->prepare("
      INSERT INTO items
        (owner_id, object_id, item_id, count, enchant_level, loc, loc_data, time_of_use,
         custom_type1, custom_type2, mana_left, time)
      VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stIns->execute([
      $charId,        // owner_id (postava)
      $newObjectId,   // object_id (unikátní)
      $itemId,        // item_id (mount)
      1,              // count
      0,              // enchant_level
      'INVENTORY',    // loc
      0,              // loc_data
      0,              // time_of_use
      0,              // custom_type1
      0,              // custom_type2
      -1,             // mana_left
      0               // time
    ]);

    $pdoGameWrite->commit();
  }

  $pdo->commit();
// ADMIN AUDIT LOG - SUCCESS
$logStmt = $pdo->prepare("
  INSERT INTO admin_audit_log
  (account, character_name, action_type, item_id, item_name, amount, currency, price, status, ip_address)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$logStmt->execute([
  $_SESSION['web_user_id'],
  $charId ?: null,
  'SHOP_PURCHASE',
  $p['item_id'] ?? null,
  $code,
  1,
  'DC',
  $price,
  'SUCCESS',
  $_SERVER['REMOTE_ADDR'] ?? null
]);
  echo json_encode([
    'ok' => true,
    'order_id' => $orderId,
    'vip_grant_id' => $vipGrantId
  ]);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();

  if (isset($pdoGameWrite) && $pdoGameWrite instanceof PDO) {
    if ($pdoGameWrite->inTransaction()) $pdoGameWrite->rollBack();
  }

  $msg = $e->getMessage();
// ADMIN AUDIT LOG - FAIL
try {
  $logStmt = $pdo->prepare("
    INSERT INTO admin_audit_log
    (account, character_name, action_type, item_id, item_name, amount, currency, price, status, ip_address)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  ");

  $logStmt->execute([
    $_SESSION['web_user_id'] ?? null,
    $charId ?? null,
    'SHOP_PURCHASE',
    $productId ?? null,
    'UNKNOWN',
    1,
    'DC',
    $price ?? 0,
    'FAIL: ' . $msg,
    $_SERVER['REMOTE_ADDR'] ?? null
  ]);
} catch (Throwable $logError) {
  // log failure nesmí rozbít response
}
  $known = [
    'INSUFFICIENT_FUNDS',
    'PRODUCT_NOT_FOUND',
    'PRODUCT_NOT_IMPLEMENTED',
    'ALREADY_PURCHASED',
    'MISSING_game_account_id',
    'GAME_ACCOUNT_NOT_OWNED',
    'GAME_ACCOUNT_NOT_FOUND',
    'VIP_ENDDATE_NOT_FOUND',
    'MISSING_item_id',
    'MISSING_char_id',
    'CHAR_NOT_FOUND',
    'CHAR_NOT_OWNED',
    'INVALID_SCOPE'
  ];

  if (in_array($msg, $known, true)) {
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
  }

  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $msg]);
}