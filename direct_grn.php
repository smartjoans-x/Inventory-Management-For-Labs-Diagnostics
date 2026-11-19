<?php
/* ============================================================================
   direct_grn.php - Direct Goods Receive Note (no purchase order behind it)
   ----------------------------------------------------------------------------
   For stock that simply arrives: an urgent supply, a free replacement, a
   sample, an item bought over the counter. Everything is entered once - item,
   lot, expiry, quantity, rate - and the stock is posted straight away.

   It is stored exactly like a received purchase order (is_direct = 1), with
   the ordered lines mirroring what was received, so the stock report, the
   valuation and the monthly budget treat it the same as any other receipt.
   Its number comes from its own series: GRN/2026-27/0001.
   ============================================================================ */
require_once __DIR__ . '/db.php';
require_login();

$items     = all_items($pdo);
$suppliers = all_suppliers($pdo);

$form = [
    'grn_number'   => next_grn_number($pdo),
    'supplier_id'  => 0,
    'receive_date' => today(),
    'invoice_no'   => '',
    'notes'        => '',
];
$formLines = [];

/* ------------------------------- SAVE / POST ----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $form = [
        'grn_number'   => post_str('grn_number'),
        'supplier_id'  => post_int('supplier_id'),
        'receive_date' => clean_date(post_str('receive_date')) ?? today(),
        'invoice_no'   => post_str('invoice_no'),
        'notes'        => post_str('notes'),
    ];

    $rawItems = isset($_POST['item_id'])     && is_array($_POST['item_id'])     ? $_POST['item_id']     : [];
    $rawLots  = isset($_POST['lot_number'])  && is_array($_POST['lot_number'])  ? $_POST['lot_number']  : [];
    $rawExp   = isset($_POST['expiry_date']) && is_array($_POST['expiry_date']) ? $_POST['expiry_date'] : [];
    $rawQty   = isset($_POST['quantity'])    && is_array($_POST['quantity'])    ? $_POST['quantity']    : [];
    $rawRate  = isset($_POST['unit_rate'])   && is_array($_POST['unit_rate'])   ? $_POST['unit_rate']   : [];

    $flags = [];
    foreach ($items as $it) {
        $flags[(int)$it['id']] = ['has_lot' => (int)$it['has_lot'], 'name' => $it['item_name']];
    }

    $lines  = [];
    $errors = [];

    foreach ($rawItems as $i => $itemIdRaw) {
        $itemId = (int)$itemIdRaw;
        if ($itemId <= 0) {
            continue;
        }
        if (!isset($flags[$itemId])) {
            $errors[] = 'Row ' . ($i + 1) . ': item not found or inactive.';
            continue;
        }

        $qty  = (float)($rawQty[$i]  ?? 0);
        $rate = (float)($rawRate[$i] ?? 0);
        $name = $flags[$itemId]['name'];

        if ($qty <= 0) {
            $errors[] = h($name) . ': quantity must be greater than zero.';
            continue;
        }
        if ($rate < 0) {
            $errors[] = h($name) . ': rate cannot be negative.';
            continue;
        }

        $hasLot = $flags[$itemId]['has_lot'] === 1;
        $lot    = trim((string)($rawLots[$i] ?? ''));
        $expiry = $hasLot ? clean_date((string)($rawExp[$i] ?? '')) : null;

        if ($hasLot && $lot === '') {
            $errors[] = h($name) . ': lot number is required for this item.';
            continue;
        }
        if (!$hasLot) {
            $lot = GENERAL_LOT;
        }

        $lines[] = [
            'item_id'      => $itemId,
            'lot_number'   => $lot,
            'expiry_date'  => $expiry,
            'quantity'     => $qty,
            'unit_rate'    => $rate,
            'total_amount' => round($qty * $rate, 2),
        ];
    }

    if ($form['supplier_id'] <= 0) {
        $errors[] = 'Please select the supplier the goods came from.';
    }
    if ($form['grn_number'] === '') {
        $errors[] = 'GRN number is required.';
    }
    if (!$lines) {
        $errors[] = 'Add at least one item with a quantity.';
    }

    if ($errors) {
        flash(implode('<br>', $errors), 'danger');
    } else {
        try {
            $pdo->beginTransaction();

            $pdo->prepare("INSERT INTO purchase_orders
                             (po_number, supplier_id, po_date, status, is_direct, grand_total,
                              received_total, receive_date, invoice_no, notes, created_by)
                           VALUES (?,?,?, 'Draft', 1, 0, 0, ?, ?, ?, ?)")
                ->execute([
                    $form['grn_number'], $form['supplier_id'], $form['receive_date'],
                    $form['receive_date'], nullify($form['invoice_no']),
                    nullify($form['notes']), user_id(),
                ]);
            $poId = (int)$pdo->lastInsertId();

            /* the ordered line mirrors the received line, so a direct receipt
               shows no variance against "what we asked" */
            $insItem = $pdo->prepare('INSERT INTO po_items (po_id, item_id, quantity, unit_rate, total_amount)
                                      VALUES (?,?,?,?,?)');
            $insRcpt = $pdo->prepare('INSERT INTO po_receipts
                                        (po_id, po_item_id, item_id, lot_number, expiry_date,
                                         quantity, unit_rate, total_amount)
                                      VALUES (?,?,?,?,?,?,?,?)');

            foreach ($lines as $ln) {
                $insItem->execute([$poId, $ln['item_id'], $ln['quantity'], $ln['unit_rate'], $ln['total_amount']]);
                $poItemId = (int)$pdo->lastInsertId();
                $insRcpt->execute([
                    $poId, $poItemId, $ln['item_id'], $ln['lot_number'], $ln['expiry_date'],
                    $ln['quantity'], $ln['unit_rate'], $ln['total_amount'],
                ]);
            }

            recalc_po_total($pdo, $poId);
            recalc_received_total($pdo, $poId);

            $res = finalize_po($pdo, $poId, user_id());
            if (!$res['ok']) {
                throw new RuntimeException($res['message']);
            }

            $pdo->commit();
            flash('Goods Receive Note <b>' . h($form['grn_number']) . '</b> saved and posted to stock. '
                . $res['message']);
            redirect('view_po.php?id=' . $poId);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $msg = $e->getMessage();
            if ($e instanceof PDOException && (int)$e->getCode() === 23000) {
                $msg = 'That GRN number is already used. Try ' . next_grn_number($pdo) . '.';
            }
            flash('Could not save the goods receive note: ' . h($msg), 'danger');
        }
    }

    /* redisplay what was typed */
    foreach ($rawItems as $i => $itemIdRaw) {
        if ((int)$itemIdRaw <= 0) {
            continue;
        }
        $formLines[] = [
            'item_id'     => (int)$itemIdRaw,
            'lot_number'  => (string)($rawLots[$i] ?? ''),
            'expiry_date' => (string)($rawExp[$i] ?? ''),
            'quantity'    => (string)($rawQty[$i] ?? ''),
            'unit_rate'   => (string)($rawRate[$i] ?? ''),
        ];
    }
}

/* ============================= ROW RENDERER ============================== */
function grn_row(array $items, ?array $row = null): string
{
    $sel    = $row ? (int)$row['item_id'] : 0;
    $lot    = $row ? (string)$row['lot_number'] : '';
    $expiry = $row ? (string)$row['expiry_date'] : '';
    $qty    = $row ? (string)$row['quantity'] : '';
    $rate   = $row ? (string)$row['unit_rate'] : '';
    $hasLot = true;
    $unit   = '';
    foreach ($items as $it) {
        if ((int)$it['id'] === $sel) {
            $hasLot = (int)$it['has_lot'] === 1;
            $unit   = (string)$it['unit'];
        }
    }
    $total = ($qty !== '' && $rate !== '') ? number_format((float)$qty * (float)$rate, 2) : '0.00';

    $opts = '<option value="">-- select item --</option>';
    foreach ($items as $it) {
        $opts .= '<option value="' . (int)$it['id'] . '"'
               . ' data-has-lot="' . (int)$it['has_lot'] . '"'
               . ' data-unit="' . h($it['unit']) . '"'
               . ($sel === (int)$it['id'] ? ' selected' : '') . '>'
               . h($it['item_name']) . ' (' . h($it['item_code']) . ')'
               . '</option>';
    }

    $lotVal = ($sel > 0 && !$hasLot) ? GENERAL_LOT : $lot;
    $ro     = ($sel > 0 && !$hasLot) ? ' readonly' : '';

    return '
    <tr class="grn-row">
      <td data-label="Item">
        <select name="item_id[]" class="form-select form-select-sm grn-item">' . $opts . '</select>
      </td>
      <td data-label="Lot No">
        <input type="text" name="lot_number[]" class="form-control form-control-sm grn-lot mono"
               maxlength="80" value="' . h($lotVal) . '" placeholder="Lot / Batch"' . $ro . '>
      </td>
      <td data-label="Expiry">
        <input type="date" name="expiry_date[]" class="form-control form-control-sm grn-expiry"
               value="' . h($expiry) . '"' . $ro . '>
      </td>
      <td data-label="Qty" class="num">
        <div class="input-group input-group-sm">
          <input type="number" name="quantity[]" class="form-control form-control-sm grn-qty text-end"
                 step="0.01" min="0" value="' . h($qty) . '" placeholder="0">
          <span class="input-group-text grn-unit" style="min-width:46px">' . h($unit !== '' ? $unit : '-') . '</span>
        </div>
      </td>
      <td data-label="Rate" class="num">
        <input type="number" name="unit_rate[]" class="form-control form-control-sm grn-rate text-end"
               step="0.01" min="0" value="' . h($rate) . '" placeholder="0.00">
        <div class="rate-hint grn-rate-hint"></div>
      </td>
      <td data-label="Amount" class="num"><span class="row-total grn-total">' . $total . '</span></td>
      <td class="text-end">
        <button type="button" class="btn btn-sm btn-outline-danger grn-del" title="Remove row">
          <i class="bi bi-x-lg"></i>
        </button>
      </td>
    </tr>';
}

$page_title = 'Direct Goods Receive Note';
$page_icon  = 'bi-box-arrow-in-down';
$active     = 'direct_grn';
require __DIR__ . '/includes/header.php';
?>

<?php if (!$items || !$suppliers): ?>
  <div class="alert alert-warning">
    <b>Set up the masters first.</b>
    <?php if (!$suppliers): ?> No active supplier - <a href="suppliers.php">add a supplier</a>.<?php endif; ?>
    <?php if (!$items): ?> No active item - <a href="items.php">add an item</a>.<?php endif; ?>
  </div>
<?php else: ?>

<div class="alert alert-info d-flex gap-2 no-print">
  <i class="bi bi-info-circle mt-1"></i>
  <div>
    <b>Goods received without a purchase order.</b>
    Enter the lot, expiry, quantity and rate as they arrived - stock is posted straight away,
    no draft and no receive step. If an order was raised first, use
    <a href="po_list.php">Purchase Orders</a> and receive it there instead, so the ordered and
    received figures can be compared.
  </div>
</div>

<form method="post" id="grnForm" autocomplete="off">
  <?= csrf_field() ?>

  <div class="card mb-3">
    <div class="card-header">
      <i class="bi bi-receipt"></i> Receive Details
      <span class="ms-auto badge badge-soft-secondary mono"><?= h($form['grn_number']) ?></span>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-6 col-md-3">
          <label class="form-label req">GRN No</label>
          <input type="text" name="grn_number" class="form-control mono" maxlength="40" required
                 value="<?= h($form['grn_number']) ?>">
          <div class="help">Own series, separate from PO numbers.</div>
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label req">Received On</label>
          <input type="date" name="receive_date" class="form-control" required
                 value="<?= h($form['receive_date']) ?>">
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label req">Supplier</label>
          <select name="supplier_id" id="supplierSel" class="form-select" required>
            <option value="">-- select supplier --</option>
            <?php foreach ($suppliers as $s): ?>
              <option value="<?= (int)$s['id'] ?>" <?= (int)$form['supplier_id'] === (int)$s['id'] ? 'selected' : '' ?>>
                <?= h($s['supplier_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label">Invoice / DC No</label>
          <input type="text" name="invoice_no" class="form-control mono" maxlength="60"
                 value="<?= h($form['invoice_no']) ?>" placeholder="e.g. INV-4471">
        </div>
        <div class="col-12">
          <label class="form-label">Remarks</label>
          <input type="text" name="notes" class="form-control" maxlength="500"
                 value="<?= h($form['notes']) ?>" placeholder="e.g. urgent supply, replacement for damaged kit">
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">
      <i class="bi bi-list-ol"></i> Items Received
      <button type="button" class="btn btn-sm btn-teal ms-auto" id="addRow">
        <i class="bi bi-plus-lg"></i> Add Row
      </button>
    </div>
    <div class="table-wrap">
      <table class="table po-table mb-0">
        <thead>
          <tr>
            <th style="min-width:230px">Item</th>
            <th style="min-width:130px">Lot No</th>
            <th style="min-width:150px">Expiry</th>
            <th style="min-width:130px" class="num">Qty</th>
            <th style="min-width:130px" class="num">Rate</th>
            <th style="min-width:110px" class="num">Amount</th>
            <th style="width:46px"></th>
          </tr>
        </thead>
        <tbody id="grnBody">
          <?php
          if ($formLines) {
              foreach ($formLines as $ln) {
                  echo grn_row($items, $ln);
              }
          } else {
              echo grn_row($items, null);
          }
          ?>
        </tbody>
        <tfoot>
          <tr class="sticky-total">
            <td colspan="3" class="d-none d-md-table-cell"></td>
            <td colspan="2" class="text-end fw-bold">Total Received</td>
            <td class="num fw-bold fs-6"><?= CURRENCY ?> <span id="grandTotal">0.00</span></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <div class="card-footer help">
      <i class="bi bi-layers"></i>
      Same item in two lots? Add it on two rows with different lot numbers - each one becomes
      its own stock lot with its own expiry and rate.
    </div>
  </div>

  <div class="card">
    <div class="card-body d-flex flex-wrap gap-2 align-items-center">
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-check2-circle"></i> Save &amp; Post to Stock
      </button>
      <a href="po_list.php" class="btn btn-link text-decoration-none">Cancel</a>
      <div class="ms-auto help" style="max-width:420px">
        <i class="bi bi-exclamation-triangle"></i>
        This posts stock immediately and cannot be edited afterwards - the same rule as a
        finalized purchase order.
      </div>
    </div>
  </div>
</form>

<template id="rowTpl"><?= grn_row($items, null) ?></template>

<script>
(function () {
  'use strict';

  var body     = document.getElementById('grnBody');
  var tpl      = document.getElementById('rowTpl');
  var form     = document.getElementById('grnForm');
  var supplier = document.getElementById('supplierSel');

  function recalc() {
    var grand = 0;
    body.querySelectorAll('tr.grn-row').forEach(function (tr) {
      var v = SL.num(tr.querySelector('.grn-qty').value) * SL.num(tr.querySelector('.grn-rate').value);
      tr.querySelector('.grn-total').textContent = SL.money(v);
      grand += v;
    });
    document.getElementById('grandTotal').textContent = SL.money(grand);
  }

  function applyItemRules(tr) {
    var sel  = tr.querySelector('.grn-item');
    var opt  = sel.options[sel.selectedIndex];
    var lot  = tr.querySelector('.grn-lot');
    var exp  = tr.querySelector('.grn-expiry');
    var unit = tr.querySelector('.grn-unit');

    if (!opt || !opt.value) {
      lot.readOnly = false; exp.readOnly = false; unit.textContent = '-';
      return;
    }
    unit.textContent = opt.getAttribute('data-unit') || '-';

    if (opt.getAttribute('data-has-lot') === '1') {
      lot.readOnly = false;
      exp.readOnly = false;
      if (lot.value === 'GENERAL') { lot.value = ''; }
      lot.placeholder = 'Lot / Batch No';
    } else {
      lot.value = 'GENERAL';
      lot.readOnly = true;
      exp.value = '';
      exp.readOnly = true;
    }
  }

  function fetchRate(tr, force) {
    var sel  = tr.querySelector('.grn-item');
    var rate = tr.querySelector('.grn-rate');
    var hint = tr.querySelector('.grn-rate-hint');
    var id   = SL.num(sel.value);
    if (!id) { hint.textContent = ''; return; }
    if (!force && rate.dataset.touched === '1') { return; }

    hint.textContent = 'checking last rate...';
    SL.getJSON('ajax/get_last_rate.php?item_id=' + id + '&supplier_id=' + SL.num(supplier.value))
      .then(function (res) {
        if (!res || !res.ok) { hint.textContent = ''; return; }
        if (force || rate.dataset.touched !== '1') {
          rate.value = Number(res.rate).toFixed(2);
          delete rate.dataset.touched;
        }
        hint.textContent = res.source || '';
        recalc();
      });
  }

  body.addEventListener('change', function (e) {
    var tr = e.target.closest('tr.grn-row');
    if (!tr) { return; }
    if (e.target.matches('.grn-item')) {
      applyItemRules(tr);
      fetchRate(tr, true);
      recalc();
    }
  });

  body.addEventListener('input', function (e) {
    var tr = e.target.closest('tr.grn-row');
    if (!tr) { return; }
    if (e.target.matches('.grn-rate')) { e.target.dataset.touched = '1'; }
    if (e.target.matches('.grn-qty, .grn-rate')) { recalc(); }
  });

  body.addEventListener('click', function (e) {
    if (!e.target.closest('.grn-del')) { return; }
    var rows = body.querySelectorAll('tr.grn-row');
    var tr   = e.target.closest('tr.grn-row');
    if (rows.length === 1) {
      tr.querySelectorAll('input').forEach(function (i) { i.value = ''; delete i.dataset.touched; });
      tr.querySelector('.grn-item').value = '';
      applyItemRules(tr);
      tr.querySelector('.grn-rate-hint').textContent = '';
    } else {
      tr.remove();
    }
    recalc();
  });

  document.getElementById('addRow').addEventListener('click', function () {
    var clone = tpl.content.firstElementChild.cloneNode(true);
    body.appendChild(clone);
    clone.querySelector('.grn-item').focus();
    recalc();
  });

  supplier.addEventListener('change', function () {
    body.querySelectorAll('tr.grn-row').forEach(function (tr) { fetchRate(tr, false); });
  });

  form.addEventListener('submit', function (e) {
    if (!supplier.value) { e.preventDefault(); alert('Please select the supplier.'); supplier.focus(); return; }
    var ok = false, bad = null, noLot = null;
    body.querySelectorAll('tr.grn-row').forEach(function (tr) {
      var sel = tr.querySelector('.grn-item');
      var opt = sel.options[sel.selectedIndex];
      var q   = SL.num(tr.querySelector('.grn-qty').value);
      if (!sel.value) { return; }
      if (q > 0) { ok = true; } else if (!bad) { bad = tr; }
      if (q > 0 && opt.getAttribute('data-has-lot') === '1'
          && tr.querySelector('.grn-lot').value.trim() === '' && !noLot) {
        noLot = tr;
      }
    });
    if (bad)   { e.preventDefault(); alert('Enter a quantity for every item you selected.');
                 bad.querySelector('.grn-qty').focus(); return; }
    if (noLot) { e.preventDefault(); alert('Enter the lot number for every lot-tracked item.');
                 noLot.querySelector('.grn-lot').focus(); return; }
    if (!ok)   { e.preventDefault(); alert('Add at least one item with a quantity.'); return; }
    if (!confirm('Post this goods receive note to stock?\n\nIt cannot be edited afterwards.')) {
      e.preventDefault();
    }
  });

  body.querySelectorAll('tr.grn-row').forEach(function (tr) {
    applyItemRules(tr);
    if (tr.querySelector('.grn-rate').value) { tr.querySelector('.grn-rate').dataset.touched = '1'; }
  });
  recalc();
})();
</script>

<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
