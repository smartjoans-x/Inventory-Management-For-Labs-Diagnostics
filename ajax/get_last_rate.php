<?php
/* ============================================================================
   ajax/get_last_rate.php
   GET  item_id, supplier_id
   ->   { ok, rate, source, item_name, unit, has_lot, stock, stock_text,
          last_lot, last_expiry }
   Used by create_po.php to auto-fill the Unit Rate (still user editable).
   ============================================================================ */
require_once __DIR__ . '/../db.php';
require_login_ajax();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$itemId     = get_int('item_id');
$supplierId = get_int('supplier_id');

if ($itemId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'item_id is required']);
    exit;
}

try {
    $st = $pdo->prepare('SELECT id, item_code, item_name, unit, has_lot, min_stock_alert
                         FROM items WHERE id = ?');
    $st->execute([$itemId]);
    $item = $st->fetch();

    if (!$item) {
        echo json_encode(['ok' => false, 'error' => 'Item not found']);
        exit;
    }

    $rate  = last_rate($pdo, $itemId, $supplierId);
    $stock = item_stock_qty($pdo, $itemId);

    /* last lot / expiry purchased for this item - handy while re-ordering */
    $lastLot = ['lot_number' => null, 'expiry_date' => null];
    if ((int)$item['has_lot'] === 1) {
        $st = $pdo->prepare('SELECT lot_number, expiry_date FROM item_batches
                             WHERE item_id = ?
                             ORDER BY purchase_date DESC, id DESC LIMIT 1');
        $st->execute([$itemId]);
        $row = $st->fetch();
        if ($row) {
            $lastLot = $row;
        }
    }

    echo json_encode([
        'ok'          => true,
        'rate'        => round((float)$rate['rate'], 2),
        'source'      => $rate['source'],
        'item_name'   => $item['item_name'],
        'item_code'   => $item['item_code'],
        'unit'        => $item['unit'],
        'has_lot'     => (int)$item['has_lot'],
        'stock'       => round($stock, 2),
        'stock_text'  => 'In stock: ' . qty_str($stock) . ' ' . $item['unit'],
        'last_lot'    => $lastLot['lot_number'],
        'last_expiry' => $lastLot['expiry_date'],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error while reading the last rate']);
}
