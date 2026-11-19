<?php
/* ============================================================================
   po_list.php - Purchase Order register (Draft + Final)
   Draft -> can be edited, finalized or deleted
   Final -> view / print only (stock already posted)
   ============================================================================ */
require_once __DIR__ . '/db.php';
require_login();

/* ------------------------------- ACTIONS --------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post_str('action');
    $id     = post_int('id');

    if ($action === 'delete') {
        $res = delete_draft_po($pdo, $id);
        flash(h($res['message']), $res['ok'] ? 'success' : 'warning');
    }
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    redirect('po_list.php' . ($qs !== '' ? '?' . $qs : ''));
}

/* -------------------------------- FILTERS -------------------------------- */
$fStatus   = get_str('status');
$fSupplier = get_int('supplier_id');
$fFrom     = clean_date(get_str('from'));
$fTo       = clean_date(get_str('to'));
$q         = get_str('q');

$sql  = 'SELECT po.*, s.supplier_name, cu.full_name AS created_by_name, fu.full_name AS finalized_by_name,
                (SELECT COUNT(*) FROM po_items pi WHERE pi.po_id = po.id) AS line_count,
                (SELECT COALESCE(SUM(pi.quantity),0) FROM po_items pi WHERE pi.po_id = po.id) AS total_qty
         FROM purchase_orders po
         JOIN suppliers s ON s.id = po.supplier_id
         LEFT JOIN users cu ON cu.id = po.created_by
         LEFT JOIN users fu ON fu.id = po.finalized_by
         WHERE 1=1';
$args = [];

if ($fStatus === 'Draft' || $fStatus === 'Final') {
    $sql   .= ' AND po.status = ?';
    $args[] = $fStatus;
} elseif ($fStatus === 'direct') {
    $sql .= ' AND po.is_direct = 1';
} elseif ($fStatus === 'po') {
    $sql .= ' AND po.is_direct = 0';
}
if ($fSupplier > 0) {
    $sql   .= ' AND po.supplier_id = ?';
    $args[] = $fSupplier;
}
if ($fFrom) {
    $sql   .= ' AND po.po_date >= ?';
    $args[] = $fFrom;
}
if ($fTo) {
    $sql   .= ' AND po.po_date <= ?';
    $args[] = $fTo;
}
if ($q !== '') {
    $sql   .= ' AND (po.po_number LIKE ? OR po.notes LIKE ? OR s.supplier_name LIKE ?)';
    $args[] = '%' . $q . '%';
    $args[] = '%' . $q . '%';
    $args[] = '%' . $q . '%';
}
$sql .= ' ORDER BY po.po_date DESC, po.id DESC';

$st = $pdo->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll();

$sumOrdered    = 0.0;   // every listed PO, ordered value
$sumDraft      = 0.0;   // drafts: ordered, not yet in stock
$sumFinalOrder = 0.0;   // finals: what we asked
$sumFinalRecv  = 0.0;   // finals: what they gave
foreach ($rows as $r) {
    $sumOrdered += (float)$r['grand_total'];
    if ($r['status'] === 'Final') {
        $sumFinalOrder += (float)$r['grand_total'];
        $sumFinalRecv  += (float)$r['received_total'];
    } else {
        $sumDraft += (float)$r['grand_total'];
    }
}
$finalDiff = $sumFinalRecv - $sumFinalOrder;

$suppliers  = all_suppliers($pdo, false);
$page_title = 'Purchase Orders';
$page_icon  = 'bi-journal-text';
$active     = 'po_list';
require __DIR__ . '/includes/header.php';
?>

<div class="row g-3 mb-3">
  <div class="col-6 col-xl-3">
    <div class="stat">
      <div class="stat-label">Draft - awaiting receipt</div>
      <div class="stat-value"><?= money($sumDraft) ?></div>
      <div class="stat-sub">Ordered, not yet in stock</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat">
      <div class="stat-label">Final - what we asked</div>
      <div class="stat-value"><?= money($sumFinalOrder) ?></div>
      <div class="stat-sub">Order value of received POs</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat">
      <div class="stat-label">Final - what they gave</div>
      <div class="stat-value"><?= money($sumFinalRecv) ?></div>
      <div class="stat-sub">Actually received &amp; posted</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat">
      <div class="stat-label">Difference on received POs</div>
      <div class="stat-value <?= $finalDiff > 0.005 ? 'text-danger' : ($finalDiff < -0.005 ? 'text-warning' : '') ?>">
        <?= ($finalDiff > 0 ? '+ ' : ($finalDiff < 0 ? '- ' : '')) . money(abs($finalDiff)) ?>
      </div>
      <div class="stat-sub"><?= $finalDiff > 0 ? 'Paid more than ordered' : ($finalDiff < 0 ? 'Short / cheaper than ordered' : 'Exactly as ordered') ?></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <i class="bi bi-funnel"></i> Filters
    <a href="create_po.php" class="btn btn-sm btn-primary ms-auto"><i class="bi bi-plus-lg"></i> New PO</a>
    <a href="direct_grn.php" class="btn btn-sm btn-teal" title="Goods received without a purchase order">
      <i class="bi bi-box-arrow-in-down"></i> Direct GRN
    </a>
  </div>
  <div class="card-body pb-2">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-6 col-md-2">
        <label class="form-label">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">All</option>
          <option value="Draft" <?= $fStatus === 'Draft' ? 'selected' : '' ?>>Draft (ordered)</option>
          <option value="Final" <?= $fStatus === 'Final' ? 'selected' : '' ?>>Final (received)</option>
          <option value="direct" <?= $fStatus === 'direct' ? 'selected' : '' ?>>Direct GRN only</option>
          <option value="po"     <?= $fStatus === 'po'     ? 'selected' : '' ?>>Against a PO only</option>
        </select>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label">Supplier</label>
        <select name="supplier_id" class="form-select form-select-sm">
          <option value="0">All suppliers</option>
          <?php foreach ($suppliers as $s): ?>
            <option value="<?= (int)$s['id'] ?>" <?= $fSupplier === (int)$s['id'] ? 'selected' : '' ?>>
              <?= h($s['supplier_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">From</label>
        <input type="date" name="from" class="form-control form-control-sm" value="<?= h($fFrom ?? '') ?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">To</label>
        <input type="date" name="to" class="form-control form-control-sm" value="<?= h($fTo ?? '') ?>">
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Search</label>
        <div class="d-flex gap-2">
          <input type="text" name="q" class="form-control form-control-sm" placeholder="PO no / notes" value="<?= h($q) ?>">
          <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
          <a href="po_list.php" class="btn btn-sm btn-outline-secondary">Clear</a>
        </div>
      </div>
    </form>
  </div>

  <div class="table-wrap">
    <table class="table table-hover table-striped mb-0">
      <thead>
        <tr>
          <th>PO Number</th>
          <th>Supplier</th>
          <th>PO Date</th>
          <th class="num">Lines</th>
          <th class="num">Ordered</th>
          <th class="num">Received</th>
          <th>Status</th>
          <th class="text-end">Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="8">
          <div class="empty-state"><i class="bi bi-journal-x"></i>
            No purchase orders match these filters.
            <div class="mt-2"><a href="create_po.php" class="btn btn-sm btn-primary">Create a purchase order</a></div>
          </div>
        </td></tr>
      <?php else: foreach ($rows as $r): $isDraft = $r['status'] === 'Draft'; ?>
        <tr>
          <td>
            <a class="mono fw-semibold text-decoration-none" href="view_po.php?id=<?= (int)$r['id'] ?>">
              <?= h($r['po_number']) ?>
            </a>
            <?php if ((int)$r['is_direct'] === 1): ?>
              <span class="badge badge-soft-warning" title="Received without a purchase order">Direct GRN</span>
            <?php endif; ?>
            <?php if ($r['notes']): ?><div class="help"><?= h($r['notes']) ?></div><?php endif; ?>
            <?php if ($r['created_by_name']): ?>
              <div class="help"><i class="bi bi-person"></i> <?= h($r['created_by_name']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= h($r['supplier_name']) ?></td>
          <td><?= dmy($r['po_date']) ?></td>
          <td class="num"><?= (int)$r['line_count'] ?></td>
          <td class="num"><?= money($r['grand_total'], false) ?>
              <div class="help"><?= qty_str($r['total_qty']) ?> unit(s)</div></td>
          <td class="num">
            <?php if ($isDraft): ?>
              <?php if ((float)$r['received_total'] > 0): ?>
                <span class="badge badge-soft-warning">receipt saved</span>
                <div class="help"><?= money($r['received_total'], false) ?></div>
              <?php else: ?>
                <span class="text-muted">-</span>
              <?php endif; ?>
            <?php else: ?>
              <span class="fw-semibold"><?= money($r['received_total'], false) ?></span>
              <div><?= diff_badge((float)$r['received_total'] - (float)$r['grand_total']) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge <?= $isDraft ? 'badge-soft-secondary' : 'badge-soft-success' ?>">
              <i class="bi <?= $isDraft ? 'bi-pencil' : 'bi-check2-circle' ?>"></i> <?= h($r['status']) ?>
            </span>
            <?php if (!$isDraft && $r['finalized_at']): ?>
              <div class="help"><?= dmy(substr((string)$r['finalized_at'], 0, 10)) ?></div>
            <?php endif; ?>
          </td>
          <td class="text-end text-nowrap">
            <a href="view_po.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-secondary" title="View / Print">
              <i class="bi bi-eye"></i>
            </a>
            <?php if ($isDraft): ?>
              <a href="create_po.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit draft">
                <i class="bi bi-pencil"></i>
              </a>
              <a href="receive_po.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-teal"
                 title="Receive (GRN) - enter what actually arrived, then finalize">
                <i class="bi bi-box-arrow-in-down"></i>
              </a>
              <form method="post" class="d-inline" data-confirm="Delete draft <?= h($r['po_number']) ?>?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-sm btn-outline-danger" title="Delete draft"><i class="bi bi-trash"></i></button>
              </form>
            <?php else: ?>
              <a href="view_po.php?id=<?= (int)$r['id'] ?>&amp;print=1" class="btn btn-sm btn-outline-dark" title="Print">
                <i class="bi bi-printer"></i>
              </a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
      <?php if ($rows): ?>
      <tfoot>
        <tr class="table-light fw-bold">
          <td colspan="4" class="text-end">Total</td>
          <td class="num"><?= money($sumOrdered, false) ?></td>
          <td class="num"><?= money($sumFinalRecv, false) ?></td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
