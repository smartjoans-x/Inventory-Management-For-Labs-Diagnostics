<?php
/* ============================================================================
   stock_out.php - Issue voucher : one slip, one department, many items
   ----------------------------------------------------------------------------
   Header : date, department, voucher number, who collected it (typed by hand)
   Lines  : as many items as needed, each taken FIFO (oldest expiry first) or
            from a lot picked by hand
   Saving is all-or-nothing: if one line is short of stock, the whole voucher
   is rolled back and nothing is issued.
   ============================================================================ */
require_once __DIR__ . '/db.php';
require_login();

/* ------------------------------- ACTIONS --------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post_str('action');

    if ($action === 'issue') {
        $deptId = post_int('department_id');
        $deptNm = $deptId > 0
                ? (string)scalar($pdo, 'SELECT dept_name FROM departments WHERE id = ?', [$deptId])
                : post_str('issued_to');

        $rawItems = isset($_POST['item_id']) && is_array($_POST['item_id']) ? $_POST['item_id'] : [];
        $rawQty   = isset($_POST['qty'])     && is_array($_POST['qty'])     ? $_POST['qty']     : [];
        $rawBatch = isset($_POST['batch_id'])&& is_array($_POST['batch_id'])? $_POST['batch_id']: [];

        $lines = [];
        foreach ($rawItems as $i => $itemId) {
            if ((int)$itemId <= 0) {
                continue;
            }
            $lines[] = [
                'item_id'  => (int)$itemId,
                'qty'      => (float)($rawQty[$i] ?? 0),
                'batch_id' => (int)($rawBatch[$i] ?? 0),
            ];
        }

        $res = issue_voucher($pdo, [
            'out_date'        => post_str('out_date'),
            'department_id'   => $deptId,
            'department_name' => $deptNm,
            'voucher_no'      => post_str('voucher_no'),
            'received_by'     => post_str('received_by'),
            'purpose'         => post_str('purpose'),
        ], $lines, user_id());

        flash($res['message'], $res['ok'] ? 'success' : 'danger');
        redirect('stock_out.php' . ($res['ok'] ? '?voucher=' . urlencode($res['voucher']) : ''));
    }

    if ($action === 'reverse') {
        $res = reverse_stock_out($pdo, post_int('id'));
        flash(h($res['message']), $res['ok'] ? 'success' : 'danger');
        redirect('stock_out.php');
    }

    if ($action === 'reverse_voucher') {
        $vno = post_str('voucher_no');
        $st  = $pdo->prepare('SELECT id FROM stock_out WHERE voucher_no = ? ORDER BY id DESC');
        $st->execute([$vno]);
        $ids = $st->fetchAll(PDO::FETCH_COLUMN);

        $done = 0;
        $err  = '';
        try {
            $pdo->beginTransaction();
            foreach ($ids as $id) {
                $r = reverse_stock_out($pdo, (int)$id);
                if (!$r['ok']) {
                    throw new RuntimeException($r['message']);
                }
                $done++;
            }
            $pdo->commit();
            flash('Voucher <b>' . h($vno) . '</b> reversed - ' . $done
                . ' line(s) returned to their lots.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('Could not reverse the voucher: ' . h($e->getMessage()), 'danger');
        }
        redirect('stock_out.php');
    }
}

/* -------------------------------- FILTERS -------------------------------- */
$fFrom    = clean_date(get_str('from')) ?? date('Y-m-01');
$fTo      = clean_date(get_str('to'))   ?? today();
$fItem    = get_int('f_item');
$fDeptF   = get_int('f_dept');
$vSearch  = get_str('v');
$showVch  = get_str('voucher');

$sql  = 'SELECT o.*, i.item_name, i.item_code, i.unit, b.lot_number, b.expiry_date,
                u.full_name AS issued_by_name, d.dept_name
         FROM stock_out o
         JOIN items i ON i.id = o.item_id
         JOIN item_batches b ON b.id = o.batch_id
         LEFT JOIN users u ON u.id = o.issued_by
         LEFT JOIN departments d ON d.id = o.department_id
         WHERE o.out_date BETWEEN ? AND ?';
$args = [$fFrom, $fTo];
if ($fItem > 0) {
    $sql   .= ' AND o.item_id = ?';
    $args[] = $fItem;
}
if ($fDeptF > 0) {
    $sql   .= ' AND o.department_id = ?';
    $args[] = $fDeptF;
}
if ($vSearch !== '') {
    $sql   .= ' AND (o.voucher_no LIKE ? OR o.received_by LIKE ?)';
    $args[] = '%' . $vSearch . '%';
    $args[] = '%' . $vSearch . '%';
}
$sql .= ' ORDER BY o.out_date DESC, o.voucher_no DESC, o.id DESC LIMIT 400';
$st = $pdo->prepare($sql);
$st->execute($args);
$outRows = $st->fetchAll();

$outValue = 0.0;
$outQty   = 0.0;
$vouchers = [];
foreach ($outRows as $r) {
    $outValue += (float)$r['quantity'] * (float)$r['unit_price'];
    $outQty   += (float)$r['quantity'];
    $vouchers[(string)$r['voucher_no']] = true;
}

/* the voucher just saved, for the printable slip */
$slip = [];
if ($showVch !== '') {
    $st = $pdo->prepare('SELECT o.*, i.item_name, i.item_code, i.unit, b.lot_number, b.expiry_date,
                                d.dept_name, u.full_name AS issued_by_name
                         FROM stock_out o
                         JOIN items i ON i.id = o.item_id
                         JOIN item_batches b ON b.id = o.batch_id
                         LEFT JOIN departments d ON d.id = o.department_id
                         LEFT JOIN users u ON u.id = o.issued_by
                         WHERE o.voucher_no = ?
                         ORDER BY o.id');
    $st->execute([$showVch]);
    $slip = $st->fetchAll();
}

$items       = all_items($pdo);
$allItems    = all_items($pdo, false);
$departments = all_departments($pdo);
$nextVoucher = next_voucher_no($pdo);

$stockMap = [];
foreach ($pdo->query('SELECT item_id, COALESCE(SUM(quantity_available),0) q
                      FROM item_batches GROUP BY item_id')->fetchAll() as $r) {
    $stockMap[(int)$r['item_id']] = (float)$r['q'];
}

$page_title = 'Stock Out / Issue Voucher';
$page_icon  = 'bi-box-arrow-up';
$active     = 'stock_out';
require __DIR__ . '/includes/header.php';
?>

<?php if ($slip): $s0 = $slip[0]; $sVal = 0.0; foreach ($slip as $sl) { $sVal += (float)$sl['quantity'] * (float)$sl['unit_price']; } ?>
<!-- ======================= PRINTABLE VOUCHER SLIP ====================== -->
<div class="card mb-3 no-print">
  <div class="card-body d-flex flex-wrap gap-2 align-items-center">
    <i class="bi bi-check-circle-fill text-success fs-4"></i>
    <div class="flex-grow-1">
      <b>Voucher <?= h($showVch) ?></b> saved.
      <div class="help"><?= count($slip) ?> item(s) &middot; <?= money($sVal) ?>
        &middot; <?= h($s0['dept_name'] ?: $s0['issued_to']) ?></div>
    </div>
    <button class="btn btn-sm btn-outline-dark" onclick="window.print()"><i class="bi bi-printer"></i> Print slip</button>
    <a href="stock_out.php" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> New voucher</a>
  </div>
</div>

<div class="print-sheet mb-3">
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
      <div class="small"><?php if (APP_ADDRESS !== ''): ?><div><?= h(APP_ADDRESS) ?></div><?php endif; ?></div>
    </div>
    <div class="text-end small">
      <div><b>Voucher No:</b> <span class="mono"><?= h($showVch) ?></span></div>
      <div><b>Date:</b> <?= dmy($s0['out_date']) ?></div>
      <div><b>Department:</b> <?= h($s0['dept_name'] ?: $s0['issued_to'] ?: '-') ?></div>
    </div>
  </div>

  <div class="print-title">Stock Issue Voucher</div>

  <table class="table table-bordered align-middle">
    <thead>
      <tr><th style="width:38px">#</th><th>Item</th><th>Lot</th><th>Expiry</th>
          <th class="num">Qty</th><th class="num">Rate</th><th class="num">Value</th></tr>
    </thead>
    <tbody>
    <?php $n = 0; foreach ($slip as $sl): $n++; ?>
      <tr>
        <td><?= $n ?></td>
        <td><div class="fw-semibold"><?= h($sl['item_name']) ?></div>
            <div class="help mono"><?= h($sl['item_code']) ?></div></td>
        <td><span class="lot-chip<?= lot_label($sl['lot_number']) === GENERAL_LOT ? ' general' : '' ?>"><?= h(lot_label($sl['lot_number'])) ?></span></td>
        <td><?= dmy($sl['expiry_date']) ?></td>
        <td class="num"><?= qty_str($sl['quantity']) ?> <?= h($sl['unit']) ?></td>
        <td class="num"><?= money($sl['unit_price'], false) ?></td>
        <td class="num fw-semibold"><?= money((float)$sl['quantity'] * (float)$sl['unit_price'], false) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr class="table-light">
        <td colspan="6" class="text-end fw-bold">Total</td>
        <td class="num fw-bold"><?= money($sVal) ?></td>
      </tr>
    </tfoot>
  </table>

  <?php if ($s0['purpose']): ?>
    <div class="small"><b>Purpose:</b> <?= h($s0['purpose']) ?></div>
  <?php endif; ?>

  <div class="sign-row">
    <div class="sign-box">Issued By<?= $s0['issued_by_name'] ? '<br><span class="small text-muted">' . h($s0['issued_by_name']) . '</span>' : '' ?></div>
    <div class="sign-box">Received By<?= $s0['received_by'] ? '<br><span class="small text-muted">' . h($s0['received_by']) . '</span>' : '' ?></div>
    <div class="sign-box">Store In-charge<br><span class="brand-font"><?= h(APP_BRAND) ?></span></div>
  </div>
</div>
<?php endif; ?>

<div class="row g-3">
  <!-- ============================ NEW VOUCHER ========================== -->
  <div class="col-12 col-xxl-7 no-print">
    <form method="post" id="outForm" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="issue">

      <div class="card mb-3">
        <div class="card-header"><i class="bi bi-receipt"></i> New Issue Voucher</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-6 col-md-3">
              <label class="form-label req">Date</label>
              <input type="date" name="out_date" class="form-control" required value="<?= h(today()) ?>">
            </div>
            <div class="col-6 col-md-3">
              <label class="form-label <?= $departments ? 'req' : '' ?>">Department</label>
              <?php if ($departments): ?>
                <select name="department_id" class="form-select" required>
                  <option value="">-- select --</option>
                  <?php foreach ($departments as $d): ?>
                    <option value="<?= (int)$d['id'] ?>"><?= h($d['dept_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              <?php else: ?>
                <input type="text" name="issued_to" class="form-control" maxlength="120" placeholder="e.g. Biochemistry">
                <div class="help"><a href="departments.php">Add departments</a> for department-wise reporting.</div>
              <?php endif; ?>
            </div>
            <div class="col-6 col-md-3">
              <label class="form-label req">Voucher No</label>
              <input type="text" name="voucher_no" class="form-control mono" maxlength="40" required
                     value="<?= h($nextVoucher) ?>">
              <div class="help">Auto - change it to match your register book.</div>
            </div>
            <div class="col-6 col-md-3">
              <label class="form-label">Received By</label>
              <input type="text" name="received_by" class="form-control" maxlength="120"
                     placeholder="who collected it">
              <div class="help">Type the person's name.</div>
            </div>
            <div class="col-12">
              <label class="form-label">Purpose / Remarks</label>
              <input type="text" name="purpose" class="form-control" maxlength="190"
                     placeholder="e.g. Daily run / QC / monthly indent">
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">
          <i class="bi bi-list-ol"></i> Items
          <button type="button" class="btn btn-sm btn-teal ms-auto" id="addRow">
            <i class="bi bi-plus-lg"></i> Add Item
          </button>
        </div>
        <div class="table-wrap">
          <table class="table po-table mb-0" id="outTable">
            <thead>
              <tr>
                <th style="min-width:230px">Item</th>
                <th style="min-width:210px">Lot</th>
                <th style="min-width:130px" class="num">Qty</th>
                <th style="min-width:110px" class="num">Value</th>
                <th style="width:46px"></th>
              </tr>
            </thead>
            <tbody id="outBody"></tbody>
            <tfoot>
              <tr class="sticky-total">
                <td colspan="2" class="d-none d-md-table-cell"></td>
                <td class="text-end fw-bold">Total</td>
                <td class="num fw-bold fs-6"><?= CURRENCY ?> <span id="grandTotal">0.00</span></td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2 align-items-center">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-box-arrow-up"></i> Issue Stock
          </button>
          <div class="ms-auto help" style="max-width:420px">
            <i class="bi bi-info-circle"></i>
            FIFO takes the lot expiring first and can span several lots. The whole voucher is
            saved together - if one item is short, nothing is issued.
          </div>
        </div>
      </div>
    </form>
  </div>

  <!-- ========================== ISSUE REGISTER ========================= -->
  <div class="col-12 col-xxl-5">
    <div class="row g-3 mb-3 no-print">
      <div class="col-6">
        <div class="stat">
          <div class="stat-label">Issued <?= dmy($fFrom) ?> - <?= dmy($fTo) ?></div>
          <div class="stat-value"><?= money($outValue) ?></div>
          <div class="stat-sub"><?= count($vouchers) ?> voucher(s), <?= count($outRows) ?> line(s)</div>
        </div>
      </div>
      <div class="col-6">
        <div class="stat">
          <div class="stat-label">Current stock value</div>
          <div class="stat-value">
            <?= money((float)$pdo->query('SELECT COALESCE(SUM(quantity_available*unit_price),0)
                                          FROM item_batches WHERE quantity_available > 0')->fetchColumn()) ?>
          </div>
          <div class="stat-sub">After every issue posted</div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <i class="bi bi-journal-check"></i> Issue Register
      </div>
      <div class="card-body pb-2 no-print">
        <form method="get" class="row g-2">
          <div class="col-6 col-md-3">
            <label class="form-label">From</label>
            <input type="date" name="from" class="form-control form-control-sm" value="<?= h($fFrom) ?>">
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label">To</label>
            <input type="date" name="to" class="form-control form-control-sm" value="<?= h($fTo) ?>">
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label">Department</label>
            <select name="f_dept" class="form-select form-select-sm">
              <option value="">All</option>
              <?php foreach ($departments as $d): ?>
                <option value="<?= (int)$d['id'] ?>" <?= $fDeptF === (int)$d['id'] ? 'selected' : '' ?>>
                  <?= h($d['dept_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label">Item</label>
            <select name="f_item" class="form-select form-select-sm">
              <option value="">All items</option>
              <?php foreach ($allItems as $it): ?>
                <option value="<?= (int)$it['id'] ?>" <?= $fItem === (int)$it['id'] ? 'selected' : '' ?>>
                  <?= h($it['item_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 d-flex gap-2">
            <input type="text" name="v" class="form-control form-control-sm"
                   placeholder="voucher no / received by" value="<?= h($vSearch) ?>">
            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
            <a href="stock_out.php" class="btn btn-sm btn-outline-secondary">Clear</a>
          </div>
        </form>
      </div>

      <div class="table-wrap">
        <table class="table table-hover table-striped mb-0">
          <thead>
            <tr>
              <th>Voucher / Date</th>
              <th>Item &amp; Lot</th>
              <th class="num">Qty</th>
              <th class="num">Value</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$outRows): ?>
            <tr><td colspan="5"><div class="empty-state"><i class="bi bi-journal"></i>No issues in this period.</div></td></tr>
          <?php else:
            $lastV = null;
            foreach ($outRows as $r):
              $v = (string)$r['voucher_no'];
              $newVoucher = ($v !== $lastV);
              $lastV = $v; ?>
            <tr<?= $newVoucher ? ' class="border-top border-2"' : '' ?>>
              <td>
                <?php if ($newVoucher): ?>
                  <a class="mono fw-semibold text-decoration-none"
                     href="stock_out.php?voucher=<?= urlencode($v) ?>"><?= h($v !== '' ? $v : '-') ?></a>
                  <div class="help"><?= dmy($r['out_date']) ?>
                    &middot; <?= h($r['dept_name'] ?: $r['issued_to'] ?: '-') ?></div>
                  <?php if ($r['received_by']): ?>
                    <div class="help"><i class="bi bi-person-check"></i> <?= h($r['received_by']) ?></div>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="help">&#8942;</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="fw-semibold"><?= h($r['item_name']) ?></div>
                <span class="lot-chip<?= lot_label($r['lot_number']) === GENERAL_LOT ? ' general' : '' ?>"><?= h(lot_label($r['lot_number'])) ?></span>
                <?php if ($r['purpose']): ?><div class="help"><?= h($r['purpose']) ?></div><?php endif; ?>
              </td>
              <td class="num"><?= qty_str($r['quantity']) ?> <?= h($r['unit']) ?></td>
              <td class="num fw-semibold"><?= money((float)$r['quantity'] * (float)$r['unit_price'], false) ?></td>
              <td class="text-end text-nowrap">
                <form method="post" class="d-inline"
                      data-confirm="Reverse this line? <?= qty_str($r['quantity']) ?> <?= h($r['unit']) ?> goes back to lot <?= h(lot_label($r['lot_number'])) ?>.">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="reverse">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" title="Reverse this line">
                    <i class="bi bi-arrow-counterclockwise"></i>
                  </button>
                </form>
                <?php if ($newVoucher && $v !== ''): ?>
                  <form method="post" class="d-inline"
                        data-confirm="Reverse the WHOLE voucher <?= h($v) ?>? Every line on it goes back to its lot.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reverse_voucher">
                    <input type="hidden" name="voucher_no" value="<?= h($v) ?>">
                    <button class="btn btn-sm btn-outline-warning" title="Reverse the whole voucher">
                      <i class="bi bi-x-octagon"></i>
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
          <?php if ($outRows): ?>
          <tfoot>
            <tr class="table-light fw-bold">
              <td colspan="2" class="text-end">Total</td>
              <td class="num"><?= qty_str($outQty) ?></td>
              <td class="num"><?= money($outValue, false) ?></td>
              <td></td>
            </tr>
          </tfoot>
          <?php endif; ?>
        </table>
      </div>
      <div class="card-footer help">
        Latest 400 lines for the period. Reversing returns the quantity to the same lot -
        one line, or the whole voucher.
      </div>
    </div>
  </div>
</div>

<!-- one blank item row, cloned by the Add Item button -->
<template id="rowTpl">
  <tr class="out-row">
    <td data-label="Item">
      <select name="item_id[]" class="form-select form-select-sm out-item">
        <option value="">-- select item --</option>
        <?php foreach ($items as $it): ?>
          <option value="<?= (int)$it['id'] ?>" data-unit="<?= h($it['unit']) ?>"
                  data-stock="<?= h(qty_str($stockMap[(int)$it['id']] ?? 0)) ?>">
            <?= h($it['item_name']) ?> (<?= h($it['item_code']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
      <div class="help out-stock"></div>
    </td>
    <td data-label="Lot">
      <select name="batch_id[]" class="form-select form-select-sm out-batch">
        <option value="0">FIFO - oldest expiry first</option>
      </select>
      <div class="help out-lotinfo"></div>
    </td>
    <td data-label="Qty" class="num">
      <div class="input-group input-group-sm">
        <input type="number" name="qty[]" class="form-control form-control-sm text-end out-qty"
               step="0.01" min="0" placeholder="0">
        <span class="input-group-text out-unit" style="min-width:46px">-</span>
      </div>
    </td>
    <td data-label="Value" class="num"><span class="row-total out-value">0.00</span></td>
    <td class="text-end">
      <button type="button" class="btn btn-sm btn-outline-danger out-del" title="Remove">
        <i class="bi bi-x-lg"></i>
      </button>
    </td>
  </tr>
</template>

<script>
(function () {
  'use strict';

  var body = document.getElementById('outBody');
  var tpl  = document.getElementById('rowTpl');
  var form = document.getElementById('outForm');
  if (!body || !form) { return; }

  function recalc() {
    var grand = 0;
    body.querySelectorAll('tr.out-row').forEach(function (tr) {
      var rate = parseFloat(tr.dataset.rate || '0') || 0;
      var v    = SL.num(tr.querySelector('.out-qty').value) * rate;
      tr.querySelector('.out-value').textContent = SL.money(v);
      grand += v;
    });
    document.getElementById('grandTotal').textContent = SL.money(grand);
  }

  function loadLots(tr) {
    var sel   = tr.querySelector('.out-item');
    var opt   = sel.options[sel.selectedIndex];
    var bsel  = tr.querySelector('.out-batch');
    var unit  = tr.querySelector('.out-unit');
    var stock = tr.querySelector('.out-stock');
    var info  = tr.querySelector('.out-lotinfo');
    var id    = SL.num(sel.value);

    bsel.innerHTML = '<option value="0">FIFO - oldest expiry first</option>';
    info.textContent = '';
    tr.dataset.rate = '0';

    if (!id) { unit.textContent = '-'; stock.textContent = ''; recalc(); return; }

    unit.textContent = opt.getAttribute('data-unit') || '-';
    stock.textContent = 'loading lots...';

    SL.getJSON('ajax/get_batches.php?item_id=' + id).then(function (res) {
      if (!res || !res.ok) { stock.textContent = 'Could not load the lots.'; return; }
      stock.textContent = 'In stock: ' + res.total_qty + ' ' + res.item.unit
                        + ' in ' + res.batches.length + ' lot(s)';
      tr.dataset.maxqty = res.total_qty;
      tr.dataset.lotsum = res.total_qty;   /* remembered, so switching back to FIFO restores it */
      res.batches.forEach(function (b, i) {
        var o = document.createElement('option');
        o.value = b.id;
        o.textContent = b.label;
        o.setAttribute('data-rate', b.rate);
        o.setAttribute('data-qty', b.qty);
        bsel.appendChild(o);
        if (i === 0) { tr.dataset.rate = b.rate; }   /* FIFO starts at this lot */
      });
      if (!res.batches.length) { stock.textContent = 'No stock available for this item.'; }
      recalc();
    });
  }

  body.addEventListener('change', function (e) {
    var tr = e.target.closest('tr.out-row');
    if (!tr) { return; }
    if (e.target.matches('.out-item')) { loadLots(tr); }
    if (e.target.matches('.out-batch')) {
      var o = e.target.options[e.target.selectedIndex];
      var info = tr.querySelector('.out-lotinfo');
      if (e.target.value === '0') {
        info.textContent = 'Spans lots automatically if one is short.';
        var first = e.target.options[1];
        tr.dataset.rate   = first ? (first.getAttribute('data-rate') || '0') : '0';
        tr.dataset.maxqty = tr.dataset.lotsum || tr.dataset.maxqty;
      } else {
        info.textContent = 'This lot only - ' + o.getAttribute('data-qty') + ' available.';
        tr.dataset.rate   = o.getAttribute('data-rate') || '0';
        tr.dataset.maxqty = o.getAttribute('data-qty') || '0';
      }
      recalc();
    }
  });

  body.addEventListener('input', function (e) {
    if (e.target.matches('.out-qty')) { recalc(); }
  });

  body.addEventListener('click', function (e) {
    if (!e.target.closest('.out-del')) { return; }
    var rows = body.querySelectorAll('tr.out-row');
    if (rows.length === 1) {
      addRow();
    }
    e.target.closest('tr.out-row').remove();
    recalc();
  });

  function addRow() {
    var row = tpl.content.firstElementChild.cloneNode(true);
    body.appendChild(row);
    return row;
  }

  document.getElementById('addRow').addEventListener('click', function () {
    addRow().querySelector('.out-item').focus();
  });

  form.addEventListener('submit', function (e) {
    var any = false, bad = null, over = null;
    body.querySelectorAll('tr.out-row').forEach(function (tr) {
      var id  = tr.querySelector('.out-item').value;
      var qty = SL.num(tr.querySelector('.out-qty').value);
      var max = parseFloat(tr.dataset.maxqty || '0') || 0;
      if (id && qty > 0) {
        any = true;
        if (max > 0 && qty > max && !over) { over = tr; }
      }
      if (id && qty <= 0 && !bad) { bad = tr; }
    });
    if (bad)  { e.preventDefault(); alert('Enter a quantity for every item you selected.');
                bad.querySelector('.out-qty').focus(); return; }
    if (over) { e.preventDefault(); alert('That quantity is more than the stock available for this item or lot.');
                over.querySelector('.out-qty').focus(); return; }
    if (!any) { e.preventDefault(); alert('Add at least one item with a quantity.'); }
  });

  addRow();   /* start with one empty line */
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
