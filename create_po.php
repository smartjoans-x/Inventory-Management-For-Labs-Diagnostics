<?php
/* ============================================================================
   create_po.php - Raise a purchase order, or edit one that is still a Draft
   ----------------------------------------------------------------------------
   A purchase order is ALWAYS saved as a Draft - this is the order document,
   "what we asked for". It never touches live stock.
   Stock is created later, on receive_po.php, where the quantity, the rate and
   the lot numbers actually delivered are confirmed before finalizing.
   ============================================================================ */
require_once __DIR__ . '/db.php';
require_login();

$items     = all_items($pdo);
$suppliers = all_suppliers($pdo);
$poId      = get_int('id');
$po        = null;
$poLines   = [];

/* ----------------------- load an existing draft PO ----------------------- */
if ($poId > 0) {
    $st = $pdo->prepare('SELECT * FROM purchase_orders WHERE id = ?');
    $st->execute([$poId]);
    $po = $st->fetch() ?: null;

    if (!$po) {
        flash('Purchase Order not found.', 'warning');
        redirect('po_list.php');
    }
    if ($po['status'] === 'Final') {
        flash('PO <b>' . h($po['po_number']) . '</b> is already Final and cannot be edited.', 'warning');
        redirect('view_po.php?id=' . $poId);
    }
    $st = $pdo->prepare('SELECT * FROM po_items WHERE po_id = ? ORDER BY id');
    $st->execute([$poId]);
    $poLines = $st->fetchAll();
}

/* ------------------------------- SAVE / POST ----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $poId       = post_int('po_id');
    $supplierId = post_int('supplier_id');
    $poDate     = clean_date(post_str('po_date')) ?? today();
    $notes      = nullify(post_str('notes'));

    $rawItems = isset($_POST['item_id'])   && is_array($_POST['item_id'])   ? $_POST['item_id']   : [];
    $rawQty   = isset($_POST['quantity'])  && is_array($_POST['quantity'])  ? $_POST['quantity']  : [];
    $rawRate  = isset($_POST['unit_rate']) && is_array($_POST['unit_rate']) ? $_POST['unit_rate'] : [];

    $known = [];
    foreach ($items as $it) {
        $known[(int)$it['id']] = true;
    }

    $lines  = [];
    $errors = [];

    foreach ($rawItems as $i => $itemIdRaw) {
        $itemId = (int)$itemIdRaw;
        if ($itemId <= 0) {
            continue;                           // blank row - ignore
        }
        if (!isset($known[$itemId])) {
            $errors[] = 'Row ' . ($i + 1) . ': item not found or inactive.';
            continue;
        }

        $qty  = (float)($rawQty[$i]  ?? 0);
        $rate = (float)($rawRate[$i] ?? 0);

        if ($qty <= 0) {
            $errors[] = 'Row ' . ($i + 1) . ': quantity must be greater than zero.';
            continue;
        }
        if ($rate < 0) {
            $errors[] = 'Row ' . ($i + 1) . ': rate cannot be negative.';
            continue;
        }

        $lines[] = [
            'item_id'      => $itemId,
            'quantity'     => $qty,
            'unit_rate'    => $rate,
            'total_amount' => round($qty * $rate, 2),
        ];
    }

    if ($supplierId <= 0) {
        $errors[] = 'Please select a supplier.';
    }
    if (!$lines) {
        $errors[] = 'Add at least one item with a quantity.';
    }

    if ($errors) {
        flash(implode('<br>', array_map('h', $errors)), 'danger');
    } else {
        try {
            $pdo->beginTransaction();

            if ($poId > 0) {
                $st = $pdo->prepare('SELECT status, po_number FROM purchase_orders WHERE id = ? FOR UPDATE');
                $st->execute([$poId]);
                $cur = $st->fetch();
                if (!$cur) {
                    throw new RuntimeException('Purchase Order not found.');
                }
                if ($cur['status'] === 'Final') {
                    throw new RuntimeException('PO ' . $cur['po_number'] . ' is already Final and cannot be edited.');
                }

                $pdo->prepare('UPDATE purchase_orders SET supplier_id=?, po_date=?, notes=? WHERE id=?')
                    ->execute([$supplierId, $poDate, $notes, $poId]);
                /* replacing the ordered lines drops any half-entered receipt rows */
                $pdo->prepare('DELETE FROM po_receipts WHERE po_id = ?')->execute([$poId]);
                $pdo->prepare('DELETE FROM po_items WHERE po_id = ?')->execute([$poId]);
                $poNumber = $cur['po_number'];
                $isNew    = false;
            } else {
                $poNumber = next_po_number($pdo, $poDate);
                $pdo->prepare("INSERT INTO purchase_orders
                                 (po_number, supplier_id, po_date, status, grand_total, notes, created_by)
                               VALUES (?,?,?, 'Draft', 0, ?, ?)")
                    ->execute([$poNumber, $supplierId, $poDate, $notes, user_id()]);
                $poId  = (int)$pdo->lastInsertId();
                $isNew = true;
            }

            $ins = $pdo->prepare('INSERT INTO po_items (po_id, item_id, quantity, unit_rate, total_amount)
                                  VALUES (?,?,?,?,?)');
            foreach ($lines as $ln) {
                $ins->execute([$poId, $ln['item_id'], $ln['quantity'], $ln['unit_rate'], $ln['total_amount']]);
            }

            recalc_po_total($pdo, $poId);
            recalc_received_total($pdo, $poId);
            $pdo->commit();

            flash('Purchase Order <b>' . h($poNumber) . '</b> saved as <b>DRAFT</b>'
                . ($isNew ? '' : ' (updated)') . '. Live stock is untouched. '
                . 'When the goods arrive, open <b>Receive (GRN)</b> to enter the quantity, '
                . 'rate and lot numbers actually delivered.', 'info');
            redirect('view_po.php?id=' . $poId);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('Could not save the purchase order: ' . h($e->getMessage()), 'danger');
        }
    }

    /* on error: fall through and redisplay what the user typed */
    $po = [
        'id'          => $poId,
        'po_number'   => $po['po_number'] ?? '',
        'supplier_id' => $supplierId,
        'po_date'     => $poDate,
        'notes'       => $notes,
        'status'      => 'Draft',
    ];
    $poLines = [];
    foreach ($rawItems as $i => $itemIdRaw) {
        if ((int)$itemIdRaw <= 0) {
            continue;
        }
        $poLines[] = [
            'item_id'   => (int)$itemIdRaw,
            'quantity'  => (string)($rawQty[$i]  ?? ''),
            'unit_rate' => (string)($rawRate[$i] ?? ''),
        ];
    }
}

/* ============================= ROW RENDERER ============================== */
/**
 * One ordered line. $row = null renders the blank template row.
 */
function po_row(array $items, ?array $row = null): string
{
    $sel  = $row ? (int)$row['item_id'] : 0;
    $qty  = $row ? (string)$row['quantity'] : '';
    $rate = $row ? (string)$row['unit_rate'] : '';
    $unit = '';
    $lotTracked = false;
    foreach ($items as $it) {
        if ((int)$it['id'] === $sel) {
            $unit       = (string)$it['unit'];
            $lotTracked = (int)$it['has_lot'] === 1;
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

    return '
    <tr class="po-row">
      <td data-label="Item">
        <select name="item_id[]" class="form-select form-select-sm po-item">' . $opts . '</select>
        <div class="help po-lot-note">' . ($lotTracked ? 'Lot tracked - lot numbers are entered when received' : '') . '</div>
      </td>
      <td data-label="Qty" class="num">
        <div class="input-group input-group-sm">
          <input type="number" name="quantity[]" class="form-control form-control-sm po-qty text-end"
                 step="0.01" min="0" value="' . h($qty) . '" placeholder="0">
          <span class="input-group-text po-unit" style="min-width:48px">' . h($unit !== '' ? $unit : '-') . '</span>
        </div>
      </td>
      <td data-label="Rate" class="num">
        <input type="number" name="unit_rate[]" class="form-control form-control-sm po-rate text-end"
               step="0.01" min="0" value="' . h($rate) . '" placeholder="0.00">
        <div class="rate-hint po-rate-hint"></div>
      </td>
      <td data-label="Amount" class="num">
        <span class="row-total po-total">' . $total . '</span>
      </td>
      <td data-label="" class="text-end">
        <button type="button" class="btn btn-sm btn-outline-danger po-del" title="Remove row">
          <i class="bi bi-x-lg"></i>
        </button>
      </td>
    </tr>';
}

$previewNo  = !empty($po['po_number']) ? $po['po_number'] : next_po_number($pdo);
$page_title = $poId > 0 ? 'Edit Draft PO' : 'Create Purchase Order';
$page_icon  = 'bi-file-earmark-plus';
$active     = 'create_po';
require __DIR__ . '/includes/header.php';
?>

<?php if (!$items || !$suppliers): ?>
  <div class="alert alert-warning">
    <b>Set up the masters first.</b>
    <?php if (!$suppliers): ?> No active supplier found - <a href="suppliers.php">add a supplier</a>.<?php endif; ?>
    <?php if (!$items): ?> No active item found - <a href="items.php">add an item</a>.<?php endif; ?>
  </div>
<?php else: ?>

<div class="workflow-strip mb-3">
  <div class="wf-step active">
    <span class="wf-num">1</span>
    <div><b>Order</b><div class="help">Item, quantity and expected rate. Saved as Draft.</div></div>
  </div>
  <i class="bi bi-chevron-right wf-sep"></i>
  <div class="wf-step">
    <span class="wf-num">2</span>
    <div><b>Receive (GRN)</b><div class="help">Correct the quantity / rate, enter lot numbers.</div></div>
  </div>
  <i class="bi bi-chevron-right wf-sep"></i>
  <div class="wf-step">
    <span class="wf-num">3</span>
    <div><b>Finalize</b><div class="help">Stock and valuation update.</div></div>
  </div>
</div>

<form method="post" id="poForm" autocomplete="off">
  <?= csrf_field() ?>
  <input type="hidden" name="po_id" value="<?= (int)($po['id'] ?? 0) ?>">

  <div class="card mb-3">
    <div class="card-header">
      <i class="bi bi-file-earmark-text"></i>
      <?= $poId > 0 ? 'Draft PO ' . h($po['po_number']) : 'Purchase Order Details' ?>
      <span class="ms-auto badge badge-soft-secondary">PO No: <?= h($previewNo) ?></span>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-12 col-md-5">
          <label class="form-label req">Supplier</label>
          <select name="supplier_id" id="supplierSel" class="form-select" required>
            <option value="">-- select supplier --</option>
            <?php foreach ($suppliers as $s): ?>
              <option value="<?= (int)$s['id'] ?>"
                <?= (int)($po['supplier_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>>
                <?= h($s['supplier_name']) ?><?= $s['gst_no'] ? ' - ' . h($s['gst_no']) : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="help">Changing the supplier refreshes the suggested rates.</div>
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label req">PO Date</label>
          <input type="date" name="po_date" class="form-control" required
                 value="<?= h($po['po_date'] ?? today()) ?>">
        </div>
        <div class="col-6 col-md-4">
          <label class="form-label">Notes / Reference</label>
          <input type="text" name="notes" class="form-control" maxlength="500"
                 value="<?= h($po['notes'] ?? '') ?>" placeholder="Quotation ref, delivery terms...">
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">
      <i class="bi bi-list-ol"></i> Items Ordered
      <button type="button" class="btn btn-sm btn-teal ms-auto" id="addRow">
        <i class="bi bi-plus-lg"></i> Add Row
      </button>
    </div>
    <div class="table-wrap">
      <table class="table po-table mb-0" id="poTable">
        <thead>
          <tr>
            <th style="min-width:260px">Item</th>
            <th style="min-width:150px" class="num">Quantity</th>
            <th style="min-width:140px" class="num">Expected Rate</th>
            <th style="min-width:110px" class="num">Amount</th>
            <th style="width:46px"></th>
          </tr>
        </thead>
        <tbody id="poBody">
          <?php
          if ($poLines) {
              foreach ($poLines as $ln) {
                  echo po_row($items, $ln);
              }
          } else {
              echo po_row($items, null);
          }
          ?>
        </tbody>
        <tfoot>
          <tr class="sticky-total">
            <td colspan="2" class="d-none d-md-table-cell"></td>
            <td class="text-end fw-bold">Order Value</td>
            <td class="num fw-bold fs-6"><?= CURRENCY ?> <span id="grandTotal">0.00</span></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <div class="card-footer help">
      <i class="bi bi-info-circle"></i>
      Lot numbers and expiry dates are <b>not</b> entered here - they are captured when the
      goods arrive, so one ordered line can be received as several different lots.
    </div>
  </div>

  <div class="card">
    <div class="card-body d-flex flex-wrap gap-2 align-items-center">
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-save"></i> <?= $poId > 0 ? 'Update Draft PO' : 'Save Purchase Order (Draft)' ?>
      </button>
      <a href="po_list.php" class="btn btn-link text-decoration-none">Cancel</a>
      <div class="ms-auto help" style="max-width:430px">
        <i class="bi bi-shield-check"></i>
        A purchase order can only be saved as a <b>Draft</b>. Stock is updated only after
        <b>Receive (GRN)</b>, so nothing enters the valuation until it is physically in the store.
      </div>
    </div>
  </div>
</form>

<!-- blank row template (inert, never submitted) -->
<template id="rowTpl"><?= po_row($items, null) ?></template>

<script>
(function () {
  'use strict';

  var body     = document.getElementById('poBody');
  var tpl      = document.getElementById('rowTpl');
  var form     = document.getElementById('poForm');
  var supplier = document.getElementById('supplierSel');
  var grandOut = document.getElementById('grandTotal');

  function recalcAll() {
    var grand = 0;
    body.querySelectorAll('tr.po-row').forEach(function (tr) {
      var q = SL.num(tr.querySelector('.po-qty').value);
      var r = SL.num(tr.querySelector('.po-rate').value);
      tr.querySelector('.po-total').textContent = SL.money(q * r);
      grand += q * r;
    });
    grandOut.textContent = SL.money(grand);
  }

  function applyItemRules(tr) {
    var sel  = tr.querySelector('.po-item');
    var opt  = sel.options[sel.selectedIndex];
    var unit = tr.querySelector('.po-unit');
    var note = tr.querySelector('.po-lot-note');

    if (!opt || !opt.value) {
      unit.textContent = '-';
      note.textContent = '';
      return;
    }
    unit.textContent = opt.getAttribute('data-unit') || '-';
    note.textContent = opt.getAttribute('data-has-lot') === '1'
      ? 'Lot tracked - lot numbers are entered when received' : '';
  }

  function fetchRate(tr, force) {
    var sel    = tr.querySelector('.po-item');
    var rate   = tr.querySelector('.po-rate');
    var hint   = tr.querySelector('.po-rate-hint');
    var itemId = SL.num(sel.value);
    if (!itemId) { hint.textContent = ''; return; }
    if (!force && rate.dataset.touched === '1') { return; }

    hint.textContent = 'checking last rate...';
    SL.getJSON('ajax/get_last_rate.php?item_id=' + itemId + '&supplier_id=' + SL.num(supplier.value))
      .then(function (res) {
        if (!res || !res.ok) { hint.textContent = ''; return; }
        if (force || rate.dataset.touched !== '1') {
          rate.value = Number(res.rate).toFixed(2);
          delete rate.dataset.touched;
        }
        hint.textContent = res.source || '';
        if (res.stock_text) { tr.querySelector('.po-unit').title = res.stock_text; }
        recalcAll();
      });
  }

  body.addEventListener('change', function (e) {
    var tr = e.target.closest('tr.po-row');
    if (!tr) return;
    if (e.target.matches('.po-item')) {
      applyItemRules(tr);
      fetchRate(tr, true);
      recalcAll();
    }
  });

  body.addEventListener('input', function (e) {
    var tr = e.target.closest('tr.po-row');
    if (!tr) return;
    if (e.target.matches('.po-rate')) { e.target.dataset.touched = '1'; }
    if (e.target.matches('.po-qty, .po-rate')) { recalcAll(); }
  });

  body.addEventListener('click', function (e) {
    if (!e.target.closest('.po-del')) return;
    var rows = body.querySelectorAll('tr.po-row');
    var tr   = e.target.closest('tr.po-row');
    if (rows.length === 1) {
      tr.querySelectorAll('input').forEach(function (i) { i.value = ''; delete i.dataset.touched; });
      tr.querySelector('.po-item').value = '';
      applyItemRules(tr);
      tr.querySelector('.po-rate-hint').textContent = '';
    } else {
      tr.remove();
    }
    recalcAll();
  });

  document.getElementById('addRow').addEventListener('click', function () {
    var clone = tpl.content.firstElementChild.cloneNode(true);
    body.appendChild(clone);
    clone.querySelector('.po-item').focus();
    recalcAll();
  });

  supplier.addEventListener('change', function () {
    body.querySelectorAll('tr.po-row').forEach(function (tr) { fetchRate(tr, false); });
  });

  form.addEventListener('submit', function (e) {
    if (!supplier.value) { e.preventDefault(); alert('Please select a supplier.'); supplier.focus(); return; }
    var ok = false, bad = null;
    body.querySelectorAll('tr.po-row').forEach(function (tr) {
      var item = tr.querySelector('.po-item').value;
      var q    = SL.num(tr.querySelector('.po-qty').value);
      if (item && q > 0) { ok = true; }
      if (item && q <= 0 && !bad) { bad = tr; }
    });
    if (bad) { e.preventDefault(); alert('Please enter a quantity for every selected item.');
               bad.querySelector('.po-qty').focus(); return; }
    if (!ok)  { e.preventDefault(); alert('Add at least one item with a quantity.'); }
  });

  body.querySelectorAll('tr.po-row').forEach(function (tr) {
    applyItemRules(tr);
    if (tr.querySelector('.po-rate').value) { tr.querySelector('.po-rate').dataset.touched = '1'; }
  });
  recalcAll();
})();
</script>

<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
