<?php
/* ============================================================================
   index.php - Dashboard
   Live stock valuation, low stock alerts, expiry alerts, purchase summary
   ============================================================================ */
require_once __DIR__ . '/db.php';
require_login();

/* ---------------------------- headline figures ---------------------------- */
$totalStockValue = (float)$pdo->query('SELECT COALESCE(SUM(quantity_available * unit_price),0)
                                       FROM item_batches WHERE quantity_available > 0')->fetchColumn();
$totalQty        = (float)$pdo->query('SELECT COALESCE(SUM(quantity_available),0)
                                       FROM item_batches WHERE quantity_available > 0')->fetchColumn();
$itemCount       = (int)$pdo->query('SELECT COUNT(*) FROM items WHERE is_active = 1')->fetchColumn();
$supplierCount   = (int)$pdo->query('SELECT COUNT(*) FROM suppliers WHERE is_active = 1')->fetchColumn();
$liveLots        = (int)$pdo->query('SELECT COUNT(*) FROM item_batches WHERE quantity_available > 0')->fetchColumn();

$lowStockCount   = (int)$pdo->query('SELECT COUNT(*) FROM v_item_stock
                                     WHERE is_active = 1 AND min_stock_alert > 0
                                       AND total_qty <= min_stock_alert')->fetchColumn();
$outOfStockCount = (int)$pdo->query('SELECT COUNT(*) FROM v_item_stock
                                     WHERE is_active = 1 AND total_qty <= 0')->fetchColumn();

$expiredRow = $pdo->query('SELECT COUNT(*) c, COALESCE(SUM(quantity_available * unit_price),0) v
                           FROM item_batches
                           WHERE quantity_available > 0 AND expiry_date IS NOT NULL
                             AND expiry_date < CURDATE()')->fetch();

$nearRow = $pdo->query('SELECT COUNT(*) c, COALESCE(SUM(quantity_available * unit_price),0) v
                        FROM item_batches
                        WHERE quantity_available > 0 AND expiry_date IS NOT NULL
                          AND expiry_date >= CURDATE()
                          AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL ' . (int)EXPIRY_ALERT_DAYS . ' DAY)')
                 ->fetch();

$draftCount = (int)$pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status = 'Draft'")->fetchColumn();
$finalCount = (int)$pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status = 'Final'")->fetchColumn();

/* what we actually paid this month = received value of Final POs */
$monthPurchase = (float)$pdo->query("SELECT COALESCE(SUM(received_total),0) FROM purchase_orders
                                     WHERE status = 'Final'
                                       AND YEAR(COALESCE(receive_date, po_date)) = YEAR(CURDATE())
                                       AND MONTH(COALESCE(receive_date, po_date)) = MONTH(CURDATE())")->fetchColumn();
$draftValue = (float)$pdo->query("SELECT COALESCE(SUM(grand_total),0) FROM purchase_orders
                                  WHERE status = 'Draft'")->fetchColumn();

$pendingPOs = $pdo->query("SELECT po.*, s.supplier_name,
                                  (SELECT COUNT(*) FROM po_items pi WHERE pi.po_id = po.id) AS line_count
                           FROM purchase_orders po
                           JOIN suppliers s ON s.id = po.supplier_id
                           WHERE po.status = 'Draft'
                           ORDER BY po.po_date ASC, po.id ASC
                           LIMIT 6")->fetchAll();
$monthConsumption = (float)$pdo->query('SELECT COALESCE(SUM(quantity * unit_price),0) FROM stock_out
                                        WHERE YEAR(out_date) = YEAR(CURDATE())
                                          AND MONTH(out_date) = MONTH(CURDATE())')->fetchColumn();

/* ------------------------------- lists ----------------------------------- */
$lowStock = $pdo->query('SELECT * FROM v_item_stock
                         WHERE is_active = 1 AND min_stock_alert > 0 AND total_qty <= min_stock_alert
                         ORDER BY (total_qty / min_stock_alert) ASC, item_name ASC
                         LIMIT 8')->fetchAll();

$nearExpiry = $pdo->query('SELECT b.*, i.item_name, i.item_code, i.unit, s.supplier_name
                           FROM item_batches b
                           JOIN items i ON i.id = b.item_id
                           LEFT JOIN suppliers s ON s.id = b.supplier_id
                           WHERE b.quantity_available > 0 AND b.expiry_date IS NOT NULL
                             AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ' . (int)EXPIRY_ALERT_DAYS . ' DAY)
                           ORDER BY b.expiry_date ASC
                           LIMIT 8')->fetchAll();

$topValue = $pdo->query('SELECT * FROM v_item_stock
                         WHERE total_qty > 0
                         ORDER BY stock_value DESC
                         LIMIT 8')->fetchAll();

$recentPOs = $pdo->query('SELECT po.*, s.supplier_name
                          FROM purchase_orders po
                          JOIN suppliers s ON s.id = po.supplier_id
                          ORDER BY po.id DESC LIMIT 8')->fetchAll();

$byCategory = $pdo->query("SELECT COALESCE(NULLIF(i.category,''),'Uncategorised') AS cat,
                                  COUNT(DISTINCT i.id) AS items,
                                  COALESCE(SUM(b.quantity_available * b.unit_price),0) AS value
                           FROM items i
                           LEFT JOIN item_batches b ON b.item_id = i.id AND b.quantity_available > 0
                           GROUP BY cat
                           ORDER BY value DESC")->fetchAll();

$page_title = 'Dashboard';
$page_icon  = 'bi-speedometer2';
$active     = 'dashboard';
require __DIR__ . '/includes/header.php';
?>

<?php if ($itemCount === 0 || $supplierCount === 0): ?>
<!-- ======================= FIRST RUN GUIDE ========================= -->
<div class="card mb-3 border-primary">
  <div class="card-header"><i class="bi bi-flag"></i> Getting Started</div>
  <div class="card-body">
    <p class="mb-2">The database is empty. Enter the masters first, then purchase orders will load the stock.</p>
    <ol class="mb-2 ps-3">
      <li class="mb-1">
        <a href="suppliers.php">Add your suppliers</a>
        <?= $supplierCount > 0 ? '<span class="badge badge-soft-success">done - ' . $supplierCount . '</span>'
                              : '<span class="badge badge-soft-warning">pending</span>' ?>
      </li>
      <li class="mb-1">
        <a href="items.php">Add your items</a> - mark reagents and kits as <b>lot tracked</b>,
        and consumables such as gloves or tubes as <b>no lot number</b>
        <?= $itemCount > 0 ? '<span class="badge badge-soft-success">done - ' . $itemCount . '</span>'
                           : '<span class="badge badge-soft-warning">pending</span>' ?>
      </li>
      <li class="mb-1"><a href="create_po.php">Create a purchase order</a> - it is always saved
        as a <b>Draft</b> and does not touch stock.</li>
      <li class="mb-1">When the goods arrive, open <b>Receive (GRN)</b> on that PO: correct the
        quantity and rate if the supplier changed them, enter the lot numbers (splitting a line
        across lots if needed), then <b>Finalize</b>. That is what puts stock into the store.</li>
      <li>Issue daily consumption through <a href="stock_out.php">Stock Out</a>.</li>
    </ol>
    <?php if (is_admin()): ?>
      <div class="help"><i class="bi bi-shield-lock"></i>
        You are an Admin - create logins for your staff in <a href="users.php">User Management</a>.</div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- ============================ STAT CARDS ============================ -->
<div class="row g-3 mb-3">
  <div class="col-12 col-md-6 col-xl-3">
    <div class="stat d-flex gap-3">
      <div class="stat-icon bg-navy"><i class="bi bi-cash-stack"></i></div>
      <div class="flex-grow-1">
        <div class="stat-label">Current Stock Value</div>
        <div class="stat-value"><?= money($totalStockValue) ?></div>
        <div class="stat-sub"><?= $liveLots ?> live lot(s) &middot; <?= qty_str($totalQty) ?> total units</div>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-6 col-xl-3">
    <div class="stat d-flex gap-3">
      <div class="stat-icon bg-teal"><i class="bi bi-box-seam"></i></div>
      <div class="flex-grow-1">
        <div class="stat-label">Items &amp; Suppliers</div>
        <div class="stat-value"><?= $itemCount ?> <span class="fs-6 text-muted">items</span></div>
        <div class="stat-sub"><?= $supplierCount ?> active supplier(s)</div>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-6 col-xl-3">
    <div class="stat d-flex gap-3">
      <div class="stat-icon bg-amber"><i class="bi bi-exclamation-triangle"></i></div>
      <div class="flex-grow-1">
        <div class="stat-label">Low / Out of Stock</div>
        <div class="stat-value"><?= $lowStockCount ?></div>
        <div class="stat-sub"><?= $outOfStockCount ?> item(s) at zero stock</div>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-6 col-xl-3">
    <div class="stat d-flex gap-3">
      <div class="stat-icon bg-rose"><i class="bi bi-calendar-x"></i></div>
      <div class="flex-grow-1">
        <div class="stat-label">Expiry Alerts</div>
        <div class="stat-value"><?= (int)$nearRow['c'] ?> <span class="fs-6 text-muted">near</span></div>
        <div class="stat-sub">
          <?= (int)$expiredRow['c'] ?> expired &middot; blocked value <?= money((float)$expiredRow['v']) ?>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-12 col-md-4">
    <div class="stat">
      <div class="stat-label">Received this month (what we paid)</div>
      <div class="stat-value"><?= money($monthPurchase) ?></div>
      <div class="stat-sub"><?= $finalCount ?> received &middot; <?= $draftCount ?> awaiting receipt (<?= money($draftValue, false) ?>)</div>
    </div>
  </div>
  <div class="col-12 col-md-4">
    <div class="stat">
      <div class="stat-label">Consumption this month</div>
      <div class="stat-value"><?= money($monthConsumption) ?></div>
      <div class="stat-sub">Value of items issued from stock</div>
    </div>
  </div>
  <div class="col-12 col-md-4">
    <div class="stat">
      <div class="stat-label">Near expiry value (<?= EXPIRY_ALERT_DAYS ?> days)</div>
      <div class="stat-value"><?= money((float)$nearRow['v']) ?></div>
      <div class="stat-sub">Use these lots first (FIFO)</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- ---------------------------- LOW STOCK ---------------------------- -->
  <div class="col-12 col-xl-6">
    <div class="card h-100">
      <div class="card-header">
        <i class="bi bi-exclamation-triangle"></i> Low Stock Alerts
        <a href="stock_report.php?view=item&amp;status=low" class="ms-auto small text-decoration-none">View all</a>
      </div>
      <div class="table-wrap">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Item</th>
              <th class="num">In Stock</th>
              <th class="num">Min</th>
              <th class="num">Value</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$lowStock): ?>
            <tr><td colspan="4"><div class="empty-state"><i class="bi bi-check2-circle"></i>All items are above their minimum level.</div></td></tr>
          <?php else: foreach ($lowStock as $r): ?>
            <tr>
              <td>
                <div class="fw-semibold"><?= h($r['item_name']) ?></div>
                <div class="help mono"><?= h($r['item_code']) ?></div>
              </td>
              <td class="num">
                <span class="badge <?= (float)$r['total_qty'] <= 0 ? 'badge-soft-danger' : 'badge-soft-warning' ?>">
                  <?= qty_str($r['total_qty']) ?> <?= h($r['unit']) ?>
                </span>
              </td>
              <td class="num"><?= qty_str($r['min_stock_alert']) ?></td>
              <td class="num"><?= money($r['stock_value'], false) ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ---------------------------- NEAR EXPIRY -------------------------- -->
  <div class="col-12 col-xl-6">
    <div class="card h-100">
      <div class="card-header">
        <i class="bi bi-calendar-x"></i> Expiring / Expired Lots
        <a href="stock_report.php?status=near" class="ms-auto small text-decoration-none">View all</a>
      </div>
      <div class="table-wrap">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Item / Lot</th>
              <th>Expiry</th>
              <th class="num">Qty</th>
              <th class="num">Value</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$nearExpiry): ?>
            <tr><td colspan="4"><div class="empty-state"><i class="bi bi-shield-check"></i>No lot is expiring in the next <?= EXPIRY_ALERT_DAYS ?> days.</div></td></tr>
          <?php else: foreach ($nearExpiry as $b): $e = expiry_meta($b['expiry_date']); ?>
            <tr>
              <td>
                <div class="fw-semibold"><?= h($b['item_name']) ?></div>
                <span class="lot-chip<?= lot_label($b['lot_number']) === GENERAL_LOT ? ' general' : '' ?>"><?= h(lot_label($b['lot_number'])) ?></span>
              </td>
              <td>
                <?= dmy($b['expiry_date']) ?><br>
                <span class="badge badge-soft-<?= $e['css'] === 'danger' ? 'danger' : 'warning' ?>"><?= h($e['label']) ?></span>
              </td>
              <td class="num"><?= qty_str($b['quantity_available']) ?> <?= h($b['unit']) ?></td>
              <td class="num"><?= money((float)$b['quantity_available'] * (float)$b['unit_price'], false) ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- --------------------------- TOP STOCK VALUE ----------------------- -->
  <div class="col-12 col-xl-6">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-graph-up"></i> Highest Value Items</div>
      <div class="table-wrap">
        <table class="table table-hover mb-0">
          <thead>
            <tr><th>Item</th><th class="num">Qty</th><th class="num">Lots</th><th class="num">Stock Value</th></tr>
          </thead>
          <tbody>
          <?php if (!$topValue): ?>
            <tr><td colspan="4"><div class="empty-state"><i class="bi bi-inboxes"></i>No stock yet. Finalize a purchase order to load stock.</div></td></tr>
          <?php else: foreach ($topValue as $r): ?>
            <tr>
              <td>
                <div class="fw-semibold"><?= h($r['item_name']) ?></div>
                <div class="help"><?= h($r['category'] ?: 'Uncategorised') ?></div>
              </td>
              <td class="num"><?= qty_str($r['total_qty']) ?> <?= h($r['unit']) ?></td>
              <td class="num"><?= (int)$r['batch_count'] ?></td>
              <td class="num fw-semibold"><?= money($r['stock_value'], false) ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ------------------- DRAFT POs AWAITING RECEIPT -------------------- -->
  <?php if ($pendingPOs): ?>
  <div class="col-12">
    <div class="card border-primary">
      <div class="card-header">
        <i class="bi bi-box-arrow-in-down"></i> Purchase Orders Awaiting Receipt
        <span class="badge badge-soft-warning ms-1"><?= $draftCount ?></span>
        <a href="po_list.php?status=Draft" class="ms-auto small text-decoration-none">View all</a>
      </div>
      <div class="table-wrap">
        <table class="table table-hover mb-0">
          <thead>
            <tr><th>PO Number</th><th>Supplier</th><th>Ordered On</th><th class="num">Lines</th><th class="num">Order Value</th><th class="text-end">Action</th></tr>
          </thead>
          <tbody>
          <?php foreach ($pendingPOs as $p): ?>
            <tr>
              <td><a class="mono text-decoration-none" href="view_po.php?id=<?= (int)$p['id'] ?>"><?= h($p['po_number']) ?></a></td>
              <td><?= h($p['supplier_name']) ?></td>
              <td><?= dmy($p['po_date']) ?></td>
              <td class="num"><?= (int)$p['line_count'] ?></td>
              <td class="num"><?= money($p['grand_total'], false) ?></td>
              <td class="text-end">
                <a href="receive_po.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-teal">
                  <i class="bi bi-box-arrow-in-down"></i> Receive
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer help">
        These orders are not in stock yet. Receiving one confirms the quantity, rate and lot
        numbers actually delivered, and only then updates stock and valuation.
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ----------------------------- RECENT POs -------------------------- -->
  <div class="col-12 col-xl-6">
    <div class="card h-100">
      <div class="card-header">
        <i class="bi bi-journal-text"></i> Recent Purchase Orders
        <a href="po_list.php" class="ms-auto small text-decoration-none">View all</a>
      </div>
      <div class="table-wrap">
        <table class="table table-hover mb-0">
          <thead>
            <tr><th>PO No</th><th>Supplier</th><th>Date</th><th class="num">Total</th><th>Status</th></tr>
          </thead>
          <tbody>
          <?php if (!$recentPOs): ?>
            <tr><td colspan="5"><div class="empty-state"><i class="bi bi-file-earmark-plus"></i>No purchase orders yet. <a href="create_po.php">Create the first PO</a>.</div></td></tr>
          <?php else: foreach ($recentPOs as $po): ?>
            <tr>
              <td><a class="mono text-decoration-none" href="view_po.php?id=<?= (int)$po['id'] ?>"><?= h($po['po_number']) ?></a></td>
              <td><?= h($po['supplier_name']) ?></td>
              <td><?= dmy($po['po_date']) ?></td>
              <td class="num"><?= money($po['grand_total'], false) ?></td>
              <td>
                <span class="badge <?= $po['status'] === 'Final' ? 'badge-soft-success' : 'badge-soft-secondary' ?>">
                  <?= h($po['status']) ?>
                </span>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- --------------------------- CATEGORY SUMMARY ---------------------- -->
  <div class="col-12">
    <div class="card">
      <div class="card-header"><i class="bi bi-tags"></i> Stock Value by Category</div>
      <div class="table-wrap">
        <table class="table table-hover mb-0">
          <thead>
            <tr><th>Category</th><th class="num">Items</th><th class="num">Stock Value</th><th class="num">Share</th></tr>
          </thead>
          <tbody>
          <?php foreach ($byCategory as $c):
              $share = $totalStockValue > 0 ? ((float)$c['value'] / $totalStockValue) * 100 : 0; ?>
            <tr>
              <td><?= h($c['cat']) ?></td>
              <td class="num"><?= (int)$c['items'] ?></td>
              <td class="num"><?= money($c['value'], false) ?></td>
              <td class="num"><?= number_format($share, 1) ?>%</td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="table-light fw-bold">
              <td>Total</td>
              <td class="num"><?= $itemCount ?></td>
              <td class="num"><?= money($totalStockValue, false) ?></td>
              <td class="num">100%</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
