<?php
/* ============================================================================
   view_po.php - Purchase Order view + clean print layout
   ?id=<po id>            view
   ?id=<po id>&print=1    open the browser print dialog automatically
   ----------------------------------------------------------------------------
   Draft : shows the order and a button to Receive (GRN)
   Final : shows the order AND what was actually received, side by side
   ============================================================================ */
require_once __DIR__ . '/db.php';
require_login();

$poId = get_int('id');

/* ------------------------------- ACTIONS --------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post_str('action');
    $poId   = post_int('id');

    if ($action === 'delete') {
        $res = delete_draft_po($pdo, $poId);
        flash(h($res['message']), $res['ok'] ? 'success' : 'warning');
        redirect($res['ok'] ? 'po_list.php' : 'view_po.php?id=' . $poId);
    }
}

/* --------------------------------- LOAD ---------------------------------- */
$st = $pdo->prepare('SELECT po.*, s.supplier_name, s.contact_person, s.phone, s.email, s.gst_no, s.address,
                            cu.full_name AS created_by_name, fu.full_name AS finalized_by_name
                     FROM purchase_orders po
                     JOIN suppliers s ON s.id = po.supplier_id
                     LEFT JOIN users cu ON cu.id = po.created_by
                     LEFT JOIN users fu ON fu.id = po.finalized_by
                     WHERE po.id = ?');
$st->execute([$poId]);
$po = $st->fetch();

if (!$po) {
    flash('Purchase Order not found.', 'warning');
    redirect('po_list.php');
}

$st = $pdo->prepare('SELECT pi.*, i.item_code, i.item_name, i.unit, i.has_lot
                     FROM po_items pi
                     JOIN items i ON i.id = pi.item_id
                     WHERE pi.po_id = ?
                     ORDER BY pi.id');
$st->execute([$poId]);
$lines = $st->fetchAll();

/* confirmed receipt rows, grouped under their ordered line */
$st = $pdo->prepare('SELECT r.*, i.item_code, i.item_name, i.unit
                     FROM po_receipts r
                     JOIN items i ON i.id = r.item_id
                     WHERE r.po_id = ?
                     ORDER BY r.po_item_id, r.id');
$st->execute([$poId]);
$receipts = [];
foreach ($st->fetchAll() as $r) {
    $receipts[(int)$r['po_item_id']][] = $r;
}

$isDraft  = $po['status'] === 'Draft';
$isDirect = (int)($po['is_direct'] ?? 0) === 1;
$numLabel = $isDirect ? 'GRN No' : 'PO No';
$hasRcpt  = !empty($receipts);
$var      = po_variance($po);
$totalQty = 0.0;
foreach ($lines as $l) {
    $totalQty += (float)$l['quantity'];
}

/* stock lots created by this PO (exist once it is Final) */
$st = $pdo->prepare('SELECT b.*, i.item_name, i.unit
                     FROM item_batches b
                     JOIN items i ON i.id = b.item_id
                     WHERE b.po_id = ?
                     ORDER BY i.item_name, b.lot_number');
$st->execute([$poId]);
$batches = $st->fetchAll();

$page_title = 'PO ' . $po['po_number'];
$page_icon  = 'bi-file-earmark-text';
$active     = 'po_list';
require __DIR__ . '/includes/header.php';
?>

<!-- ---------------------------- ACTION BAR ---------------------------- -->
<div class="card mb-3 no-print">
  <div class="card-body d-flex flex-wrap gap-2 align-items-center">
    <span class="badge <?= $isDraft ? 'badge-soft-secondary' : 'badge-soft-success' ?> fs-6">
      <i class="bi <?= $isDraft ? 'bi-pencil' : 'bi-check2-circle' ?>"></i> <?= h($po['status']) ?>
    </span>
    <?php if ($isDirect): ?>
      <span class="badge badge-soft-warning fs-6"><i class="bi bi-lightning-charge"></i> Direct GRN</span>
    <?php endif; ?>
    <?php if ($isDraft): ?>
      <span class="help">Order record - live stock has <b>not</b> been updated yet.
        <?= $hasRcpt ? 'A receipt has been saved but not finalized.' : '' ?></span>
    <?php else: ?>
      <span class="help">Received<?= $po['receive_date'] ? ' on ' . dmy($po['receive_date']) : '' ?>
        and posted to stock<?= $po['finalized_by_name'] ? ' by ' . h($po['finalized_by_name']) : '' ?>.</span>
    <?php endif; ?>
    <?php if ($po['created_by_name']): ?>
      <span class="help"><i class="bi bi-person"></i> Ordered by <?= h($po['created_by_name']) ?></span>
    <?php endif; ?>

    <div class="ms-auto d-flex flex-wrap gap-2">
      <button class="btn btn-sm btn-outline-dark" onclick="window.print()">
        <i class="bi bi-printer"></i> Print
      </button>
      <?php if ($isDraft): ?>
        <a href="create_po.php?id=<?= (int)$po['id'] ?>" class="btn btn-sm btn-outline-primary">
          <i class="bi bi-pencil"></i> Edit Draft
        </a>
        <a href="receive_po.php?id=<?= (int)$po['id'] ?>" class="btn btn-sm btn-teal">
          <i class="bi bi-box-arrow-in-down"></i> Receive (GRN)
        </a>
        <form method="post" class="d-inline" data-confirm="Delete draft <?= h($po['po_number']) ?>?">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$po['id'] ?>">
          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Delete</button>
        </form>
      <?php endif; ?>
      <a href="po_list.php" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
  </div>
</div>

<?php if (!$isDraft): ?>
<!-- ------------------------ ORDERED vs RECEIVED ----------------------- -->
<div class="row g-3 mb-3 no-print">
  <div class="col-4">
    <div class="stat">
      <div class="stat-label">What we asked</div>
      <div class="stat-value"><?= money($var['ordered']) ?></div>
      <div class="stat-sub">Order value</div>
    </div>
  </div>
  <div class="col-4">
    <div class="stat">
      <div class="stat-label">What they gave</div>
      <div class="stat-value"><?= money($var['received']) ?></div>
      <div class="stat-sub"><?= $po['invoice_no'] ? 'Invoice ' . h($po['invoice_no']) : 'Posted to stock' ?></div>
    </div>
  </div>
  <div class="col-4">
    <div class="stat">
      <div class="stat-label">Difference</div>
      <div class="stat-value <?= $var['diff'] > 0.005 ? 'text-danger' : ($var['diff'] < -0.005 ? 'text-warning' : '') ?>">
        <?= (abs($var['diff']) < 0.005 ? '' : ($var['diff'] > 0 ? '+ ' : '- ')) . money(abs($var['diff'])) ?>
      </div>
      <div class="stat-sub"><?= number_format($var['pct'], 1) ?>% of the order value</div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ---------------------------- PRINT SHEET --------------------------- -->
<div class="print-sheet">

  <div class="print-head d-flex justify-content-between align-items-start flex-wrap gap-3">
    <div class="d-flex align-items-center gap-3">
      <?php if (has_logo()): ?>
        <?= logo_img('po-logo') ?>
      <?php else: ?>
        <div>
          <div class="brand-font co-name"><?= h(APP_BRAND) ?></div>
          <div class="small text-muted"><?= h(APP_TAGLINE) ?></div>
        </div>
      <?php endif; ?>
      <div class="small">
        <?php if (APP_ADDRESS !== ''): ?><div><?= h(APP_ADDRESS) ?></div><?php endif; ?>
        <?php if (APP_GST !== ''): ?><div>GSTIN: <?= h(APP_GST) ?></div><?php endif; ?>
      </div>
    </div>
    <div class="text-end small">
      <div><b><?= h($numLabel) ?>:</b> <span class="mono"><?= h($po['po_number']) ?></span></div>
      <div><b><?= $isDirect ? 'Date' : 'PO Date' ?>:</b> <?= dmy($po['po_date']) ?></div>
      <div><b>Status:</b> <?= h(strtoupper($po['status'])) ?></div>
      <?php if ($isDraft): ?>
        <div class="text-danger"><b>DRAFT - NOT YET RECEIVED</b></div>
      <?php else: ?>
        <div><b>Received:</b> <?= dmy($po['receive_date']) ?></div>
        <?php if ($po['invoice_no']): ?><div><b>Invoice:</b> <span class="mono"><?= h($po['invoice_no']) ?></span></div><?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="print-title">
    <?= $isDraft ? h(DOC_NAME) : h(GRN_NAME) ?>
  </div>
  <?php if ($isDirect): ?>
    <div class="text-center small text-muted mb-3" style="margin-top:-10px">
      Direct receipt - no purchase order was raised
    </div>
  <?php elseif (!$isDraft): ?>
    <div class="text-center small text-muted mb-3" style="margin-top:-10px">
      Received against purchase order <span class="mono"><?= h($po['po_number']) ?></span>
    </div>
  <?php endif; ?>

  <div class="row g-3 mb-3">
    <div class="col-12 col-md-7">
      <div class="border rounded p-2 h-100">
        <div class="small text-muted text-uppercase" style="letter-spacing:1px">Supplier</div>
        <div class="fw-bold"><?= h($po['supplier_name']) ?></div>
        <?php if ($po['address']): ?><div class="small"><?= h($po['address']) ?></div><?php endif; ?>
        <div class="small">
          <?php if ($po['contact_person']): ?><?= h($po['contact_person']) ?><?php endif; ?>
          <?php if ($po['phone']): ?> &middot; <?= h($po['phone']) ?><?php endif; ?>
          <?php if ($po['email']): ?> &middot; <?= h($po['email']) ?><?php endif; ?>
        </div>
        <?php if ($po['gst_no']): ?><div class="small">GSTIN: <span class="mono"><?= h($po['gst_no']) ?></span></div><?php endif; ?>
      </div>
    </div>
    <div class="col-12 col-md-5">
      <div class="border rounded p-2 h-100">
        <div class="small text-muted text-uppercase" style="letter-spacing:1px">Notes / Reference</div>
        <div class="small"><?= $po['notes'] ? nl2br(h($po['notes'])) : '-' ?></div>
      </div>
    </div>
  </div>

  <!-- ordered lines -->
  <div class="table-wrap">
    <table class="table table-bordered align-middle">
      <thead>
        <tr>
          <th style="width:38px">#</th>
          <th>Item</th>
          <th class="num">Qty Ordered</th>
          <th class="num">Rate</th>
          <th class="num">Amount</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$lines): ?>
        <tr><td colspan="5" class="text-center text-muted py-3">No items in this purchase order.</td></tr>
      <?php else: $n = 0; foreach ($lines as $l): $n++; ?>
        <tr>
          <td><?= $n ?></td>
          <td>
            <div class="fw-semibold"><?= h($l['item_name']) ?></div>
            <div class="help mono"><?= h($l['item_code']) ?></div>
          </td>
          <td class="num"><?= qty_str($l['quantity']) ?> <?= h($l['unit']) ?></td>
          <td class="num"><?= money($l['unit_rate'], false) ?></td>
          <td class="num fw-semibold"><?= money($l['total_amount'], false) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
      <tfoot>
        <tr class="table-light">
          <td colspan="2" class="text-end fw-bold">Total</td>
          <td class="num fw-bold"><?= qty_str($totalQty) ?></td>
          <td class="text-end fw-bold">Order Value</td>
          <td class="num fw-bold"><?= money($po['grand_total']) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <div class="small text-muted mt-2">
    Amount in words: <b><?= h(ucwords(amount_in_words((float)$po['grand_total']))) ?></b>
  </div>

  <!-- ===================== RECEIVED AGAINST THIS ORDER ==================== -->
  <?php if ($hasRcpt): ?>
  <div class="print-title mt-4">Goods Received <?= $isDraft ? '(saved, not yet finalized)' : '' ?></div>

  <?php if ($po['receive_notes']): ?>
    <div class="small mb-2"><b>Receipt remarks:</b> <?= h($po['receive_notes']) ?></div>
  <?php endif; ?>

  <div class="table-wrap">
    <table class="table table-bordered align-middle">
      <thead>
        <tr>
          <th style="width:38px">#</th>
          <th>Item</th>
          <th>Lot No</th>
          <th>Expiry</th>
          <th class="num">Ordered</th>
          <th class="num">Received</th>
          <th class="num">Rate</th>
          <th class="num">Amount</th>
        </tr>
      </thead>
      <tbody>
      <?php $n = 0; foreach ($lines as $l):
          $lineId = (int)$l['id'];
          $rws    = $receipts[$lineId] ?? [];
          $n++;
          $gotQty = 0.0;
          foreach ($rws as $rr) { $gotQty += (float)$rr['quantity']; }
          if (!$rws): ?>
            <tr class="table-warning">
              <td><?= $n ?></td>
              <td><?= h($l['item_name']) ?></td>
              <td colspan="2" class="text-muted">not received</td>
              <td class="num"><?= qty_str($l['quantity']) ?> <?= h($l['unit']) ?></td>
              <td class="num">0</td>
              <td class="num">-</td>
              <td class="num">0.00</td>
            </tr>
      <?php continue; endif;
          $first = true;
          foreach ($rws as $rr):
              $isGeneral = lot_label($rr['lot_number']) === GENERAL_LOT; ?>
        <tr>
          <td><?= $first ? $n : '' ?></td>
          <td>
            <?php if ($first): ?>
              <div class="fw-semibold"><?= h($l['item_name']) ?></div>
              <div class="help mono"><?= h($l['item_code']) ?></div>
            <?php else: ?>
              <span class="help">same item, next lot</span>
            <?php endif; ?>
          </td>
          <td><span class="lot-chip<?= $isGeneral ? ' general' : '' ?>"><?= h(lot_label($rr['lot_number'])) ?></span></td>
          <td><?= dmy($rr['expiry_date']) ?></td>
          <td class="num"><?= $first ? qty_str($l['quantity']) . ' ' . h($l['unit']) : '' ?></td>
          <td class="num"><?= qty_str($rr['quantity']) ?> <?= h($l['unit']) ?></td>
          <td class="num">
            <?= money($rr['unit_rate'], false) ?>
            <?php if (abs((float)$rr['unit_rate'] - (float)$l['unit_rate']) >= 0.005): ?>
              <div class="help">ordered <?= money($l['unit_rate'], false) ?></div>
            <?php endif; ?>
          </td>
          <td class="num fw-semibold"><?= money($rr['total_amount'], false) ?></td>
        </tr>
      <?php $first = false; endforeach; ?>
        <?php if (abs($gotQty - (float)$l['quantity']) >= 0.005): ?>
        <tr class="small">
          <td colspan="4"></td>
          <td colspan="2" class="num">
            Line variance: <?= diff_badge($gotQty - (float)$l['quantity'], false) ?> <?= h($l['unit']) ?>
          </td>
          <td colspan="2"></td>
        </tr>
        <?php endif; ?>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr class="table-light">
          <td colspan="6" class="text-end fw-bold">Ordered value</td>
          <td colspan="2" class="num fw-bold"><?= money($var['ordered']) ?></td>
        </tr>
        <tr class="table-light">
          <td colspan="6" class="text-end fw-bold">Received value</td>
          <td colspan="2" class="num fw-bold"><?= money($var['received']) ?></td>
        </tr>
        <tr class="table-light">
          <td colspan="6" class="text-end fw-bold">Difference</td>
          <td colspan="2" class="num fw-bold">
            <?= (abs($var['diff']) < 0.005 ? '' : ($var['diff'] > 0 ? '+ ' : '- ')) . money(abs($var['diff'])) ?>
          </td>
        </tr>
      </tfoot>
    </table>
  </div>

  <div class="small text-muted mt-2">
    Received value in words: <b><?= h(ucwords(amount_in_words((float)$var['received']))) ?></b>
  </div>
  <?php endif; ?>

  <div class="sign-row">
    <div class="sign-box">Prepared By<?= $po['created_by_name'] ? '<br><span class="small text-muted">' . h($po['created_by_name']) . '</span>' : '' ?></div>
    <div class="sign-box">Received &amp; Checked By<?= (!$isDraft && $po['finalized_by_name']) ? '<br><span class="small text-muted">' . h($po['finalized_by_name']) . '</span>' : '' ?></div>
    <div class="sign-box">Authorised Signatory<br><span class="brand-font"><?= h(APP_BRAND) ?></span></div>
  </div>
</div>

<!-- --------------------- DRAFT: nudge to the next step ------------------ -->
<?php if ($isDraft): ?>
<div class="card mt-3 no-print">
  <div class="card-body d-flex flex-wrap align-items-center gap-3">
    <i class="bi bi-box-arrow-in-down fs-3 text-navy"></i>
    <div class="flex-grow-1">
      <b>Goods arrived?</b>
      <div class="help">Open Receive (GRN) to enter the quantity and rate actually delivered,
        add the lot numbers (splitting a line across lots if needed), and finalize. Only then does
        stock and valuation change.</div>
    </div>
    <a href="receive_po.php?id=<?= (int)$po['id'] ?>" class="btn btn-primary">
      <i class="bi bi-box-arrow-in-down"></i> Receive (GRN)
    </a>
  </div>
</div>
<?php endif; ?>

<!-- ------------------------- STOCK POSTED (Final) --------------------- -->
<?php if (!$isDraft && $batches): ?>
<div class="card mt-3 no-print">
  <div class="card-header"><i class="bi bi-layers"></i> Stock Lots Created / Updated by this PO</div>
  <div class="table-wrap">
    <table class="table table-striped mb-0">
      <thead>
        <tr><th>Item</th><th>Lot</th><th>Expiry</th><th class="num">Rate</th><th class="num">Qty Now Available</th><th class="num">Value</th></tr>
      </thead>
      <tbody>
      <?php foreach ($batches as $b): $e = expiry_meta($b['expiry_date']); ?>
        <tr>
          <td><?= h($b['item_name']) ?></td>
          <td><span class="lot-chip<?= lot_label($b['lot_number']) === GENERAL_LOT ? ' general' : '' ?>"><?= h(lot_label($b['lot_number'])) ?></span></td>
          <td><?= dmy($b['expiry_date']) ?>
              <span class="badge badge-soft-<?= $e['css'] === 'secondary' ? 'secondary' : ($e['css'] === 'success' ? 'success' : ($e['css'] === 'warning' ? 'warning' : 'danger')) ?>"><?= h($e['label']) ?></span>
          </td>
          <td class="num"><?= money($b['unit_price'], false) ?></td>
          <td class="num"><?= qty_str($b['quantity_available']) ?> <?= h($b['unit']) ?></td>
          <td class="num"><?= money((float)$b['quantity_available'] * (float)$b['unit_price'], false) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer help">
    Quantity shown is what is <b>available today</b> - it reduces as the lot is issued through Stock Out.
  </div>
</div>
<?php endif; ?>

<?php if (get_int('print') === 1): ?>
<script>window.addEventListener('load', function () { window.print(); });</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
