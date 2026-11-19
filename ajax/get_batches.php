<?php
/* ============================================================================
   ajax/get_batches.php
   GET  item_id
   ->   { ok, item:{...}, total_qty, batches:[ {id,lot,expiry,expiry_label,
          expiry_css,rate,qty,supplier,label} ] }
   Used by stock_out.php to list the lots available for issue (FIFO order).
   ============================================================================ */
require_once __DIR__ . '/../db.php';
require_login_ajax();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$itemId = get_int('item_id');

if ($itemId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'item_id is required']);
    exit;
}

try {
    $st = $pdo->prepare('SELECT id, item_code, item_name, unit, has_lot FROM items WHERE id = ?');
    $st->execute([$itemId]);
    $item = $st->fetch();

    if (!$item) {
        echo json_encode(['ok' => false, 'error' => 'Item not found']);
        exit;
    }

    $st = $pdo->prepare('SELECT b.id, b.lot_number, b.expiry_date, b.unit_price,
                                b.quantity_available, b.purchase_date, s.supplier_name
                         FROM item_batches b
                         LEFT JOIN suppliers s ON s.id = b.supplier_id
                         WHERE b.item_id = ? AND b.quantity_available > 0
                         ORDER BY (b.expiry_date IS NULL) ASC, b.expiry_date ASC,
                                  b.purchase_date ASC, b.id ASC');
    $st->execute([$itemId]);
    $rows = $st->fetchAll();

    $batches = [];
    $total   = 0.0;
    foreach ($rows as $r) {
        $e      = expiry_meta($r['expiry_date']);
        $total += (float)$r['quantity_available'];
        $batches[] = [
            'id'           => (int)$r['id'],
            'lot'          => lot_label($r['lot_number']),
            'expiry'       => $r['expiry_date'],
            'expiry_dmy'   => dmy($r['expiry_date']),
            'expiry_label' => $e['label'],
            'expiry_css'   => $e['css'],
            'rate'         => round((float)$r['unit_price'], 2),
            'qty'          => round((float)$r['quantity_available'], 2),
            'supplier'     => $r['supplier_name'],
            'label'        => 'Lot ' . lot_label($r['lot_number'])
                            . ' | Qty ' . qty_str($r['quantity_available']) . ' ' . $item['unit']
                            . ' | Exp ' . dmy($r['expiry_date'])
                            . ' | Rate ' . number_format((float)$r['unit_price'], 2),
        ];
    }

    echo json_encode([
        'ok'        => true,
        'item'      => [
            'id'        => (int)$item['id'],
            'name'      => $item['item_name'],
            'code'      => $item['item_code'],
            'unit'      => $item['unit'],
            'has_lot'   => (int)$item['has_lot'],
        ],
        'total_qty' => round($total, 2),
        'batches'   => $batches,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error while reading the lots']);
}
