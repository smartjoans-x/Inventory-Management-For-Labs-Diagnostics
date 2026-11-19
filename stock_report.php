<?php
/* ============================================================================
   stock_report.php - Live stock + valuation report
   ----------------------------------------------------------------------------
   view=batch (default) : lot wise - every lot with its own rate, expiry, value
   view=item            : item wise summary - total qty and total value
   export=csv           : download the current result set
   Stock value = SUM(quantity_available * unit_price)
   ============================================================================ */
require_once __DIR__ . '/db.php';
require_login();

$view      = get_str('view') === 'item' ? 'item' : 'batch';
$fItem     = get_int('item_id');
$fSupplier = get_int('supplier_id');
$fCat      = get_str('category');
$fStatus   = get_str('status');
$fLot      = get_str('lot');
$showZero  = get_str('zero') === '1';
$export    = get_str('export') === 'csv';

/* ========================= BATCH (LOT) LEVEL ============================= */
$batchSql = 'SELECT b.id, b.lot_number, b.expiry_date, b.unit_price, b.quantity_available,
                    b.purchase_date, i.id AS item_id, i.item_code, i.item_name, i.category,
                    i.unit, i.has_lot, s.supplier_name, po.po_number,
                    (b.quantity_available * b.unit_price) AS batch_value
             FROM item_batches b
             JOIN items i ON i.id = b.item_id
             LEFT JOIN suppliers s ON s.id = b.supplier_id
             LEFT JOIN purchase_orders po ON po.id = b.po_id
             WHERE 1=1';
$args = [];

if (!$showZero) {
    $batchSql .= ' AND b.quantity_available > 0';
}
if ($fItem > 0) {
    $batchSql .= ' AND i.id = ?';
    $args[]    = $fItem;
}
if ($fSupplier > 0) {
    $batchSql .= ' AND b.supplier_id = ?';
    $args[]    = $fSupplier;
}
if ($fCat !== '') {
    $batchSql .= ' AND i.category = ?';
    $args[]    = $fCat;
}
if ($fLot !== '') {
    $batchSql .= ' AND b.lot_number LIKE ?';
    $args[]    = '%' . $fLot . '%';
}
if ($fStatus === 'expired') {
    $batchSql .= ' AND b.expiry_date IS NOT NULL AND b.expiry_date < CURDATE()';
} elseif ($fStatus === 'near') {
    $batchSql .= ' AND b.expiry_date IS NOT NULL AND b.expiry_date >= CURDATE()
                   AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ' . (int)EXPIRY_ALERT_DAYS . ' DAY)';
} elseif ($fStatus === 'valid') {
    $batchSql .= ' AND (b.expiry_date IS NULL
                   OR b.expiry_date > DATE_ADD(CURDATE(), INTERVAL ' . (int)EXPIRY_ALERT_DAYS . ' DAY))';
}
$batchSql .= ' ORDER BY i.item_name ASC, (b.expiry_date IS NULL) ASC, b.expiry_date ASC, b.id ASC';

/* ========================== ITEM LEVEL ================================== */
$itemSql  = 'SELECT v.* FROM v_item_stock v WHERE 1=1';
$iArgs    = [];
if ($fItem > 0) {
    $itemSql .= ' AND v.item_id = ?';
    $iArgs[]  = $fItem;
}
if ($fCat !== '') {
    $itemSql .= ' AND v.category = ?';
    $iArgs[]  = $fCat;
}
if ($fStatus === 'low') {
    $itemSql .= ' AND v.min_stock_alert > 0 AND v.total_qty <= v.min_stock_alert';
} elseif ($fStatus === 'zero') {
    $itemSql .= ' AND v.total_qty <= 0';
} elseif ($fStatus === 'instock') {
    $itemSql .= ' AND v.total_qty > 0';
} elseif (!$showZero) {
    $itemSql .= ' AND (v.total_qty > 0 OR v.is_active = 1)';
}
$itemSql .= ' ORDER BY v.stock_value DESC, v.item_name ASC';

if ($view === 'batch') {
    $st = $pdo->prepare($batchSql);
    $st->execute($args);
    $rows = $st->fetchAll();
} else {
    $st = $pdo->prepare($itemSql);
    $st->execute($iArgs);
    $rows = $st->fetchAll();
}

/* --------------------------------- TOTALS -------------------------------- */
$sumQty   = 0.0;
$sumValue = 0.0;
foreach ($rows as $r) {
    if ($view === 'batch') {
        $sumQty   += (float)$r['quantity_available'];
        $sumValue += (float)$r['batch_value'];
    } else {
        $sumQty   += (float)$r['total_qty'];
        $sumValue += (float)$r['stock_value'];
    }
}

/* overall figure, independent of filters, for the header card */
$grandStockValue = (float)$pdo->query('SELECT COALESCE(SUM(quantity_available * unit_price),0)
                                       FROM item_batches WHERE quantity_available > 0')->fetchColumn();

/* --------------------------------- EXPORT -------------------------------- */
if ($export) {
    $file = 'sl_stock_' . $view . '_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");                    // BOM so Excel reads UTF-8
    fputcsv($out, ['SL Diagnostics - Stock & Valuation Report']);
    fputcsv($out, ['Generated', date('d-m-Y H:i')]);
    fputcsv($out, []);

    if ($view === 'batch') {
        fputcsv($out, ['Item Code', 'Item Name', 'Category', 'Lot No', 'Expiry', 'Supplier',
                       'PO No', 'Purchase Date', 'Unit', 'Qty Available', 'Unit Price', 'Stock Value']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['item_code'], $r['item_name'], $r['category'], lot_label($r['lot_number']),
                dmy($r['expiry_date']), $r['supplier_name'], $r['po_number'], dmy($r['purchase_date']),
                $r['unit'], qty_str($r['quantity_available']),
                number_format((float)$r['unit_price'], 2, '.', ''),
                number_format((float)$r['batch_value'], 2, '.', ''),
            ]);
        }
    } else {
        fputcsv($out, ['Item Code', 'Item Name', 'Category', 'Unit', 'Lot Tracked',
                       'Total Qty', 'Min Stock', 'Lots', 'Next Expiry', 'Stock Value']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['item_code'], $r['item_name'], $r['category'], $r['unit'],
                (int)$r['has_lot'] === 1 ? 'Yes' : 'No', qty_str($r['total_qty']),
                qty_str($r['min_stock_alert']), (int)$r['batch_count'], dmy($r['next_expiry']),
                number_format((float)$r['stock_value'], 2, '.', ''),
            ]);
        }
    }
    fputcsv($out, []);
    fputcsv($out, ['TOTAL', '', '', '', '', '', '', '', '', qty_str($sumQty), '',
                   number_format($sumValue, 2, '.', '')]);
    fclose($out);
    exit;
}

$items      = all_items($pdo, false);
$suppliers  = all_suppliers($pdo, false);
$categories = all_categories($pdo);

$qs = function (array $over = []) use ($view, $fItem, $fSupplier, $fCat, $fStatus, $fLot, $showZero) {
    $base = [
        'view'        => $view,
        'item_id'     => $fItem ?: '',
        'supplier_id' => $fSupplier ?: '',
        'category'    => $fCat,
        'status'      => $fStatus,
        'lot'         => $fLot,
        'zero'        => $showZero ? '1' : '',
    ];
    return 'stock_report.php?' . http_build_query(array_merge($base, $over));
};

$page_title = 'Stock & Valuation';
$page_icon  = 'bi-clipboard-data';
$active     = 'stock_report';
require __DIR__ . '/includes/header.php';
?>

<div class="row g-3 mb-3">
  <div class="col-12 col-md-4">
    <div class="stat">
      <div class="stat-label">Total Stock Value (all items)</div>
      <div class="stat-value"><?= money($grandStockValue) ?></div>
      <div class="stat-sub">SUM(quantity available &times; unit price)</div>
    </div>
  </div>
  <div class="col-6 col-md-4">
    <div class="stat">
      <div class="stat-label">Filtered Value</div>
      <div class="stat-value"><?= money($sumValue) ?></div>
      <div class="stat-sub"><?= count($rows) ?> <?= $view === 'batch' ? 'lot(s)' : 'item(s)' ?> listed</div>
    </div>
  </div>
  <div class="col-6 col-md-4">
    <div class="stat">
      <div class="stat-label">Filtered Quantity</div>
      <div class="stat-value"><?= qty_str($sumQty) ?></div>
      <div class="stat-sub">Mixed units - see rows</div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <i class="bi bi-funnel"></i> Stock Report
    <div class="btn-group btn-group-sm ms-auto" role="group">
      <a class="btn btn-outline-primary <?= $view === 'batch' ? 'active' : '' ?>" href="<?= h($qs(['view' => 'batch'])) ?>">
        <i class="bi bi-layers"></i> Lot wise
      </a>
      <a class="btn btn-outline-primary <?= $view === 'item' ? 'active' : '' ?>" href="<?= h($qs(['view' => 'item'])) ?>">
        <i class="bi bi-box-seam"></i> Item wise
      </a>
    </div>
    <a class="btn btn-sm btn-outline-success ms-2" href="<?= h($qs(['export' => 'csv'])) ?>">
      <i class="bi bi-filetype-csv"></i> Export
    </a>
    <button class="btn btn-sm btn-outline-dark ms-2" onclick="window.print()"><i class="bi bi-printer"></i></button>
  </div>

  <div class="card-body pb-2 no-print">
    <form method="get" class="row g-2 align-items-end">
      <input type="hidden" name="view" value="<?= h($view) ?>">
      <div class="col-12 col-md-3">
        <label class="form-label">Item</label>
        <select name="item_id" class="form-select form-select-sm">
          <option value="">All items</option>
          <?php foreach ($items as $it): ?>
            <option value="<?= (int)$it['id'] ?>" <?= $fItem === (int)$it['id'] ? 'selected' : '' ?>>
              <?= h($it['item_name']) ?> (<?= h($it['item_code']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">Category</label>
        <select name="category" class="form-select form-select-sm">
          <option value="">All</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= h($c) ?>" <?= $fCat === $c ? 'selected' : '' ?>><?= h($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($view === 'batch'): ?>
      <div class="col-6 col-md-2">
        <label class="form-label">Supplier</label>
        <select name="supplier_id" class="form-select form-select-sm">
          <option value="">All</option>
          <?php foreach ($suppliers as $s): ?>
            <option value="<?= (int)$s['id'] ?>" <?= $fSupplier === (int)$s['id'] ? 'selected' : '' ?>>
              <?= h($s['supplier_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">Lot No</label>
        <input type="text" name="lot" class="form-control form-control-sm mono" value="<?= h($fLot) ?>" placeholder="any">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">Expiry</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">All</option>
          <option value="expired" <?= $fStatus === 'expired' ? 'selected' : '' ?>>Expired</option>
          <option value="near" <?= $fStatus === 'near' ? 'selected' : '' ?>>Near expiry (<?= EXPIRY_ALERT_DAYS ?>d)</option>
          <option value="valid" <?= $fStatus === 'valid' ? 'selected' : '' ?>>Valid</option>
        </select>
      </div>
      <?php else: ?>
      <div class="col-6 col-md-3">
        <label class="form-label">Stock Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">All</option>
          <option value="low" <?= $fStatus === 'low' ? 'selected' : '' ?>>Low stock</option>
          <option value="zero" <?= $fStatus === 'zero' ? 'selected' : '' ?>>Zero stock</option>
          <option value="instock" <?= $fStatus === 'instock' ? 'selected' : '' ?>>In stock</option>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-6 col-md-2">
        <div class="form-check mb-1">
          <input class="form-check-input" type="checkbox" name="zero" value="1" id="zeroChk" <?= $showZero ? 'checked' : '' ?>>
          <label class="form-check-label help" for="zeroChk">Include nil stock</label>
        </div>
        <button class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-search"></i> Apply</button>
      </div>
      <div class="col-6 col-md-2">
        <a href="stock_report.php?view=<?= h($view) ?>" class="btn btn-sm btn-outline-secondary w-100">Clear filters</a>
      </div>
    </form>
  </div>

  <!-- ============================ LOT WISE ============================ -->
  <?php if ($view === 'batch'): ?>
  <div class="table-wrap">
    <table class="table table-hover table-striped table-sticky mb-0">
      <thead>
        <tr>
          <th>Item</th>
          <th>Lot No</th>
          <th>Expiry</th>
          <th>Supplier</th>
          <th>Purchased</th>
          <th class="num">Qty</th>
          <th class="num">Unit Price</th>
          <th class="num">Stock Value</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="8"><div class="empty-state"><i class="bi bi-inboxes"></i>No stock lots match these filters.</div></td></tr>
      <?php else: foreach ($rows as $r): $e = expiry_meta($r['expiry_date']); ?>
        <tr>
          <td>
            <div class="fw-semibold"><?= h($r['item_name']) ?></div>
            <div class="help mono"><?= h($r['item_code']) ?> &middot; <?= h($r['category'] ?: 'Uncategorised') ?></div>
          </td>
          <td>
            <span class="lot-chip<?= lot_label($r['lot_number']) === GENERAL_LOT ? ' general' : '' ?>"><?= h(lot_label($r['lot_number'])) ?></span>
          </td>
          <td class="text-nowrap">
            <?= dmy($r['expiry_date']) ?><br>
            <span class="badge badge-soft-<?= $e['css'] === 'secondary' ? 'secondary' : ($e['css'] === 'success' ? 'success' : ($e['css'] === 'warning' ? 'warning' : 'danger')) ?>">
              <?= h($e['label']) ?>
            </span>
          </td>
          <td><?= h($r['supplier_name'] ?: '-') ?></td>
          <td class="text-nowrap">
            <?= dmy($r['purchase_date']) ?>
            <?php if ($r['po_number']): ?><div class="help mono"><?= h($r['po_number']) ?></div><?php endif; ?>
          </td>
          <td class="num <?= (float)$r['quantity_available'] <= 0 ? 'text-muted' : '' ?>">
            <?= qty_str($r['quantity_available']) ?> <?= h($r['unit']) ?>
          </td>
          <td class="num"><?= money($r['unit_price'], false) ?></td>
          <td class="num fw-semibold"><?= money($r['batch_value'], false) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
      <?php if ($rows): ?>
      <tfoot>
        <tr class="table-light fw-bold">
          <td colspan="5" class="text-end">Total</td>
          <td class="num"><?= qty_str($sumQty) ?></td>
          <td></td>
          <td class="num"><?= money($sumValue, false) ?></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>

  <!-- ============================ ITEM WISE =========================== -->
  <?php else: ?>
  <div class="table-wrap">
    <table class="table table-hover table-striped table-sticky mb-0">
      <thead>
        <tr>
          <th>Code</th>
          <th>Item</th>
          <th>Lot</th>
          <th class="num">Total Qty</th>
          <th class="num">Min</th>
          <th class="num">Lots</th>
          <th>Next Expiry</th>
          <th class="num">Rate Range</th>
          <th class="num">Stock Value</th>
          <th class="text-end">Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="10"><div class="empty-state"><i class="bi bi-inboxes"></i>No items match these filters.</div></td></tr>
      <?php else: foreach ($rows as $r):
          $low = (float)$r['min_stock_alert'] > 0 && (float)$r['total_qty'] <= (float)$r['min_stock_alert'];
          $e   = expiry_meta($r['next_expiry']); ?>
        <tr>
          <td class="mono"><?= h($r['item_code']) ?></td>
          <td>
            <div class="fw-semibold"><?= h($r['item_name']) ?></div>
            <div class="help"><?= h($r['category'] ?: 'Uncategorised') ?></div>
          </td>
          <td>
            <?php if ((int)$r['has_lot'] === 1): ?>
              <span class="badge badge-soft-success">Lot wise</span>
            <?php else: ?>
              <span class="badge badge-soft-secondary">General</span>
            <?php endif; ?>
          </td>
          <td class="num">
            <span class="<?= $low ? 'badge badge-soft-' . ((float)$r['total_qty'] <= 0 ? 'danger' : 'warning') : '' ?>">
              <?= qty_str($r['total_qty']) ?> <?= h($r['unit']) ?>
            </span>
          </td>
          <td class="num"><?= qty_str($r['min_stock_alert']) ?></td>
          <td class="num"><?= (int)$r['batch_count'] ?></td>
          <td class="text-nowrap">
            <?= dmy($r['next_expiry']) ?>
            <?php if ($r['next_expiry']): ?>
              <div><span class="badge badge-soft-<?= $e['css'] === 'secondary' ? 'secondary' : ($e['css'] === 'success' ? 'success' : ($e['css'] === 'warning' ? 'warning' : 'danger')) ?>"><?= h($e['label']) ?></span></div>
            <?php endif; ?>
          </td>
          <td class="num">
            <?php if ((float)$r['batch_count'] > 0): ?>
              <?= money($r['min_rate'], false) ?><?= (float)$r['min_rate'] !== (float)$r['max_rate'] ? ' - ' . money($r['max_rate'], false) : '' ?>
            <?php else: ?>-<?php endif; ?>
          </td>
          <td class="num fw-semibold"><?= money($r['stock_value'], false) ?></td>
          <td class="text-end text-nowrap">
            <a href="<?= h($qs(['view' => 'batch', 'item_id' => (int)$r['item_id'], 'status' => ''])) ?>"
               class="btn btn-sm btn-outline-secondary" title="View lots"><i class="bi bi-layers"></i></a>
            <a href="stock_out.php?item_id=<?= (int)$r['item_id'] ?>" class="btn btn-sm btn-outline-primary" title="Issue">
              <i class="bi bi-box-arrow-up"></i>
            </a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
      <?php if ($rows): ?>
      <tfoot>
        <tr class="table-light fw-bold">
          <td colspan="3" class="text-end">Total</td>
          <td class="num"><?= qty_str($sumQty) ?></td>
          <td colspan="4"></td>
          <td class="num"><?= money($sumValue, false) ?></td>
          <td></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
  <?php endif; ?>

  <div class="card-footer help">
    Valuation method: every lot keeps the rate it was purchased at, so the stock value is
    <b>SUM(quantity available &times; that lot's unit price)</b> - supplier-wise and lot-wise rate
    differences are reflected exactly.
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
