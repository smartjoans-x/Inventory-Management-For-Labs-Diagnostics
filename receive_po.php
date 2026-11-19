<?php
/* ============================================================================
   receive_po.php - Goods Receive Note (GRN) against a Draft purchase order
   ----------------------------------------------------------------------------
   This is where "what we asked" becomes "what they gave":
     - the quantity actually delivered can be lower (or higher) than ordered
     - the rate can be higher than the expected rate on the order
     - lot numbers and expiry dates are entered here
     - one ordered line can be split across several lots
       (10 kits delivered as 5 of lot AS123 and 5 of lot AR123)

   Save receipt  -> stores the confirmed lines, PO stays Draft (part delivery)
   Confirm & Finalize -> finalize_po() posts those lines into item_batches
   ============================================================================ */
require_once __DIR__ . '/db.php';
require_login();

$poId = get_int('id') ?: post_int('po_id');

$st = $pdo->prepare('SELECT po.*, s.supplier_name, s.gst_no, s.contact_person, s.phone
                     FROM purchase_orders po
                     JOIN suppliers s ON s.id = po.supplier_id
                     WHERE po.id = ?');
$st->execute([$poId]);
$po = $st->fetch();

if (!$po) {
    flash('Purchase Order not found.', 'warning');
    redirect('po_list.php');
}
if ($po['status'] === 'Final') {
    flash('PO <b>' . h($po['po_number']) . '</b> is already Final - it has been received and posted to stock.', 'info');
    redirect('view_po.php?id=' . $poId);
}

/* ------------------------------ ordered lines ---------------------------- */
$st = $pdo->prepare('SELECT pi.*, i.item_code, i.item_name, i.unit, i.has_lot
                     FROM po_items pi
                     JOIN items i ON i.id = pi.item_id
                     WHERE pi.po_id = ?
                     ORDER BY pi.id');
$st->execute([$poId]);
$orderLines = $st->fetchAll();

if (!$orderLines) {
    flash('This purchase order has no items. <a href="create_po.php?id=' . (int)$poId . '">Edit the draft</a> first.', 'warning');
    redirect('view_po.php?id=' . $poId);
}

/* ------------------------------- SAVE / POST ----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $doFinalize = post_str('action') === 'finalize';

    $qtyIn    = isset($_POST['qty'])    && is_array($_POST['qty'])    ? $_POST['qty']    : [];
    $rateIn   = isset($_POST['rate'])   && is_array($_POST['rate'])   ? $_POST['rate']   : [];
    $lotIn    = isset($_POST['lot'])    && is_array($_POST['lot'])    ? $_POST['lot']    : [];
    $expIn    = isset($_POST['expiry']) && is_array($_POST['expiry']) ? $_POST['expiry'] : [];

    $receiveDate = clean_date(post_str('receive_date')) ?? today();
    $invoiceNo   = nullify(post_str('invoice_no'));
    $recNotes    = nullify(post_str('receive_notes'));

    /* ordered lines, keyed by po_item id, so nothing from another PO slips in */
    $valid = [];
    foreach ($orderLines as $ol) {
        $valid[(int)$ol['id']] = $ol;
    }

    $rows     = [];
    $errors   = [];
    $received = 0.0;

    foreach ($qtyIn as $poItemId => $qtyList) {
        $poItemId = (int)$poItemId;
        if (!isset($valid[$poItemId]) || !is_array($qtyList)) {
            continue;
        }
        $line   = $valid[$poItemId];
        $hasLot = (int)$line['has_lot'] === 1;

        foreach ($qtyList as $k => $qtyRaw) {
            $qty = (float)$qtyRaw;
            if ($qty <= 0) {
                continue;                         // nothing received on this row
            }
            $rate = (float)($rateIn[$poItemId][$k] ?? 0);
            $lot  = trim((string)($lotIn[$poItemId][$k] ?? ''));
            $exp  = clean_date((string)($expIn[$poItemId][$k] ?? ''));

            if ($rate < 0) {
                $errors[] = h($line['item_name']) . ': rate cannot be negative.';
                continue;
            }
            if ($hasLot && $lot === '') {
                $errors[] = h($line['item_name']) . ': lot number is required for this item.';
                continue;
            }
            if (!$hasLot) {
                $lot = GENERAL_LOT;
                $exp = null;
            }

            $amount    = round($qty * $rate, 2);
            $received += $amount;
            $rows[]    = [
                'po_item_id'   => $poItemId,
                'item_id'      => (int)$line['item_id'],
                'lot_number'   => $lot,
                'expiry_date'  => $exp,
                'quantity'     => $qty,
                'unit_rate'    => $rate,
                'total_amount' => $amount,
            ];
        }
    }

    if ($doFinalize && !$rows) {
        $errors[] = 'Enter at least one received quantity before finalizing.';
    }

    if ($errors) {
        flash(implode('<br>', $errors), 'danger');
        redirect('receive_po.php?id=' . $poId);
    }

    try {
        $pdo->beginTransaction();

        $st = $pdo->prepare('SELECT status, po_number FROM purchase_orders WHERE id = ? FOR UPDATE');
        $st->execute([$poId]);
        $cur = $st->fetch();
        if (!$cur) {
            throw new RuntimeException('Purchase Order not found.');
        }
        if ($cur['status'] === 'Final') {
            throw new RuntimeException('PO ' . $cur['po_number'] . ' was already finalized by another user.');
        }

        /* the receipt is rewritten as a whole each time it is saved */
        $pdo->prepare('DELETE FROM po_receipts WHERE po_id = ?')->execute([$poId]);

        $ins = $pdo->prepare('INSERT INTO po_receipts
                                (po_id, po_item_id, item_id, lot_number, expiry_date,
                                 quantity, unit_rate, total_amount)
                              VALUES (?,?,?,?,?,?,?,?)');
        foreach ($rows as $r) {
            $ins->execute([
                $poId, $r['po_item_id'], $r['item_id'], $r['lot_number'], $r['expiry_date'],
                $r['quantity'], $r['unit_rate'], $r['total_amount'],
            ]);
        }

        $pdo->prepare('UPDATE purchase_orders SET receive_date = ?, invoice_no = ?, receive_notes = ?
                       WHERE id = ?')
            ->execute([$receiveDate, $invoiceNo, $recNotes, $poId]);

        recalc_received_total($pdo, $poId);

        if ($doFinalize) {
            $res = finalize_po($pdo, $poId, user_id());
            if (!$res['ok']) {
                throw new RuntimeException($res['message']);
            }
            $pdo->commit();

            $v   = po_variance(array_merge($po, ['received_total' => $received]));
            $msg = 'PO <b>' . h($cur['po_number']) . '</b> is now <b>FINAL</b>. ' . $res['message']
                 . ' Ordered ' . money($v['ordered']) . ', received ' . money($v['received']);
            if (abs($v['diff']) >= 0.005) {
                $msg .= ' (' . ($v['diff'] > 0 ? 'extra ' : 'short by ') . money(abs($v['diff'])) . ')';
            }
            flash($msg . '.');
            redirect('view_po.php?id=' . $poId);
        }

        $pdo->commit();
        flash('Receipt saved. PO <b>' . h($cur['po_number']) . '</b> is still a <b>Draft</b> - '
            . 'stock has not been updated. Finalize it when the delivery is complete.', 'info');
        redirect('receive_po.php?id=' . $poId);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('Could not save the receipt: ' . h($e->getMessage()), 'danger');
        redirect('receive_po.php?id=' . $poId);
    }
}

/* --------------------- already saved receipt rows, if any ---------------- */
$st = $pdo->prepare('SELECT * FROM po_receipts WHERE po_id = ? ORDER BY po_item_id, id');
$st->execute([$poId]);
$saved = [];
foreach ($st->fetchAll() as $r) {
    $saved[(int)$r['po_item_id']][] = $r;
}
$hasSaved = !empty($saved);

$orderedTotal = (float)$po['grand_total'];

$page_title = 'GRN for ' . $po['po_number'];
$page_icon  = 'bi-box-arrow-in-down';
$active     = 'po_list';
require __DIR__ . '/includes/header.php';
?>

<div class="workflow-strip mb-3">
  <div class="wf-step done">
    <span class="wf-num"><i class="bi bi-check"></i></span>
    <div><b>Order</b><div class="help">Draft <?= h($po['po_number']) ?> &middot; <?= money($orderedTotal) ?></div></div>
  </div>
  <i class="bi bi-chevron-right wf-sep"></i>
  <div class="wf-step active">
    <span class="wf-num">2</span>
    <div><b>Receive (GRN)</b><div class="help">Check what actually arrived.</div></div>
  </div>
  <i class="bi bi-chevron-right wf-sep"></i>
  <div class="wf-step">
    <span class="wf-num">3</span>
    <div><b>Finalize</b><div class="help">Stock and valuation update.</div></div>
  </div>
</div>

<form method="post" id="rcvForm" autocomplete="off">
  <?= csrf_field() ?>
  <input type="hidden" name="po_id" value="<?= (int)$poId ?>">
  <input type="hidden" name="action" id="rcvAction" value="save">

  <div class="card mb-3">
    <div class="card-header">
      <i class="bi bi-truck"></i> <?= h($po['supplier_name']) ?>
      <span class="badge badge-soft-secondary ms-2 mono"><?= h($po['po_number']) ?></span>
      <span class="ms-auto help">Ordered on <?= dmy($po['po_date']) ?> &middot; order value <?= money($orderedTotal) ?></span>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-6 col-md-3">
          <label class="form-label req">Received On</label>
          <input type="date" name="receive_date" class="form-control" required
                 value="<?= h($po['receive_date'] ?: today()) ?>">
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label">Supplier Invoice / DC No</label>
          <input type="text" name="invoice_no" class="form-control mono" maxlength="60"
                 value="<?= h($po['invoice_no'] ?? '') ?>" placeholder="e.g. INV-4471">
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">Receipt Remarks</label>
          <input type="text" name="receive_notes" class="form-control" maxlength="500"
                 value="<?= h($po['receive_notes'] ?? '') ?>"
                 placeholder="e.g. 2 kits short, balance promised next week">
        </div>
      </div>
      <?php if ($hasSaved): ?>
        <div class="alert alert-info mt-3 mb-0 py-2">
          <i class="bi bi-clock-history"></i>
          A saved receipt was loaded. The PO is still a Draft - finalize it when the delivery is complete.
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- =========================== LINE BY LINE =========================== -->
  <?php foreach ($orderLines as $ol):
      $lineId   = (int)$ol['id'];
      $hasLot   = (int)$ol['has_lot'] === 1;
      $ordQty   = (float)$ol['quantity'];
      $ordRate  = (float)$ol['unit_rate'];
      $rows     = $saved[$lineId] ?? [[
          'lot_number'  => $hasLot ? '' : GENERAL_LOT,
          'expiry_date' => '',
          'quantity'    => $ordQty,          // prefilled with what was ordered
          'unit_rate'   => $ordRate,
      ]];
  ?>
  <div class="card mb-3 rcv-group" data-line="<?= $lineId ?>" data-has-lot="<?= $hasLot ? 1 : 0 ?>"
       data-ord-qty="<?= h(number_format($ordQty, 2, '.', '')) ?>"
       data-ord-amt="<?= h(number_format((float)$ol['total_amount'], 2, '.', '')) ?>"
       data-unit="<?= h($ol['unit']) ?>">
    <div class="card-header">
      <div class="min-w-0">
        <div class="fw-semibold text-truncate"><?= h($ol['item_name']) ?></div>
        <div class="help mono"><?= h($ol['item_code']) ?>
          <?= $hasLot ? '<span class="badge badge-soft-success ms-1">Lot tracked</span>'
                      : '<span class="badge badge-soft-secondary ms-1">No lot</span>' ?>
        </div>
      </div>
      <div class="ms-auto text-end small">
        <div class="text-muted text-uppercase" style="letter-spacing:1px;font-size:.68rem">Ordered</div>
        <div><b><?= qty_str($ordQty) ?></b> <?= h($ol['unit']) ?> &times; <?= money($ordRate, false) ?>
             = <b><?= money($ol['total_amount'], false) ?></b></div>
      </div>
    </div>

    <div class="table-wrap">
      <table class="table po-table mb-0">
        <thead>
          <tr>
            <th style="min-width:150px">Lot No<?= $hasLot ? ' *' : '' ?></th>
            <th style="min-width:150px">Expiry</th>
            <th style="min-width:130px" class="num">Qty Received</th>
            <th style="min-width:130px" class="num">Actual Rate</th>
            <th style="min-width:110px" class="num">Amount</th>
            <th style="width:46px"></th>
          </tr>
        </thead>
        <tbody class="rcv-body">
          <?php foreach ($rows as $r): ?>
          <tr class="rcv-row">
            <td data-label="Lot No">
              <input type="text" name="lot[<?= $lineId ?>][]" class="form-control form-control-sm mono rcv-lot"
                     maxlength="80" value="<?= h($hasLot ? ($r['lot_number'] ?? '') : GENERAL_LOT) ?>"
                     placeholder="<?= $hasLot ? 'Lot / Batch No' : 'GENERAL' ?>" <?= $hasLot ? '' : 'readonly' ?>>
            </td>
            <td data-label="Expiry">
              <input type="date" name="expiry[<?= $lineId ?>][]" class="form-control form-control-sm rcv-exp"
                     value="<?= h($r['expiry_date'] ?? '') ?>" <?= $hasLot ? '' : 'readonly' ?>>
            </td>
            <td data-label="Qty" class="num">
              <div class="input-group input-group-sm">
                <input type="number" name="qty[<?= $lineId ?>][]" class="form-control form-control-sm text-end rcv-qty"
                       step="0.01" min="0" value="<?= h(qty_str($r['quantity'] ?? 0)) ?>">
                <span class="input-group-text" style="min-width:46px"><?= h($ol['unit']) ?></span>
              </div>
            </td>
            <td data-label="Rate" class="num">
              <input type="number" name="rate[<?= $lineId ?>][]" class="form-control form-control-sm text-end rcv-rate"
                     step="0.01" min="0" value="<?= h(number_format((float)($r['unit_rate'] ?? 0), 2, '.', '')) ?>">
            </td>
            <td data-label="Amount" class="num"><span class="row-total rcv-amt">0.00</span></td>
            <td class="text-end">
              <button type="button" class="btn btn-sm btn-outline-danger rcv-del" title="Remove lot row">
                <i class="bi bi-x-lg"></i>
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card-footer d-flex flex-wrap gap-2 align-items-center">
      <button type="button" class="btn btn-sm btn-outline-primary rcv-add">
        <i class="bi bi-plus-lg"></i> Add another lot for this item
      </button>
      <?php if ($hasLot): ?>
        <span class="help">Split the delivery when it arrives in more than one lot - for example
          5 of lot <span class="mono">AS123</span> and 5 of lot <span class="mono">AR123</span>.</span>
      <?php endif; ?>
      <div class="ms-auto text-end small">
        Received <b class="rcv-gqty">0</b> <?= h($ol['unit']) ?>
        <span class="rcv-gqty-diff"></span>
        &nbsp;|&nbsp; Value <b class="rcv-gamt">0.00</b>
        <span class="rcv-gamt-diff"></span>
      </div>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- ============================== SUMMARY ============================== -->
  <div class="card sticky-summary">
    <div class="card-body">
      <div class="row g-3 text-center text-md-start">
        <div class="col-4">
          <div class="stat-label">Ordered (what we asked)</div>
          <div class="fs-5 fw-bold text-navy"><?= money($orderedTotal) ?></div>
        </div>
        <div class="col-4">
          <div class="stat-label">Receiving (what they gave)</div>
          <div class="fs-5 fw-bold"><?= CURRENCY ?> <span id="rcvTotal">0.00</span></div>
        </div>
        <div class="col-4">
          <div class="stat-label">Difference</div>
          <div class="fs-5 fw-bold"><span id="rcvDiff">-</span></div>
        </div>
      </div>

      <hr class="my-3">

      <div class="d-flex flex-wrap gap-2 align-items-center">
        <button type="button" class="btn btn-outline-primary" id="btnSave">
          <i class="bi bi-save"></i> Save GRN (keep PO as Draft)
        </button>
        <button type="button" class="btn btn-primary" id="btnFinal">
          <i class="bi bi-check2-circle"></i> Confirm &amp; Post GRN
        </button>
        <a href="view_po.php?id=<?= (int)$poId ?>" class="btn btn-link text-decoration-none">Cancel</a>
        <div class="ms-auto help" style="max-width:420px">
          <i class="bi bi-exclamation-triangle"></i>
          Finalizing posts these exact quantities, rates and lots into stock. The PO becomes
          read-only afterwards.
        </div>
      </div>
    </div>
  </div>
</form>

<script>
(function () {
  'use strict';

  var form    = document.getElementById('rcvForm');
  var action  = document.getElementById('rcvAction');
  var totalEl = document.getElementById('rcvTotal');
  var diffEl  = document.getElementById('rcvDiff');
  var ordered = <?= json_encode(round($orderedTotal, 2)) ?>;

  function diffHtml(diff, isMoney) {
    if (Math.abs(diff) < 0.005) {
      return '<span class="badge badge-soft-success">as ordered</span>';
    }
    var cls = diff > 0 ? 'badge-soft-danger' : 'badge-soft-warning';
    var val = isMoney ? SL.money(Math.abs(diff)) : (Math.round(Math.abs(diff) * 100) / 100);
    return '<span class="badge ' + cls + '">' + (diff > 0 ? '+' : '-') + ' ' + val + '</span>';
  }

  function recalc() {
    var grand = 0;

    document.querySelectorAll('.rcv-group').forEach(function (g) {
      var gQty = 0, gAmt = 0;
      g.querySelectorAll('.rcv-row').forEach(function (tr) {
        var q = SL.num(tr.querySelector('.rcv-qty').value);
        var r = SL.num(tr.querySelector('.rcv-rate').value);
        tr.querySelector('.rcv-amt').textContent = SL.money(q * r);
        gQty += q;
        gAmt += q * r;
      });
      grand += gAmt;

      var ordQty = parseFloat(g.getAttribute('data-ord-qty')) || 0;
      var ordAmt = parseFloat(g.getAttribute('data-ord-amt')) || 0;
      g.querySelector('.rcv-gqty').textContent = Math.round(gQty * 100) / 100;
      g.querySelector('.rcv-gamt').textContent = SL.money(gAmt);
      g.querySelector('.rcv-gqty-diff').innerHTML = diffHtml(gQty - ordQty, false);
      g.querySelector('.rcv-gamt-diff').innerHTML = diffHtml(gAmt - ordAmt, true);
    });

    totalEl.textContent = SL.money(grand);
    diffEl.innerHTML = diffHtml(grand - ordered, true);
  }

  /* add / remove a lot row inside one item group */
  document.addEventListener('click', function (e) {
    var add = e.target.closest('.rcv-add');
    if (add) {
      var g    = add.closest('.rcv-group');
      var body = g.querySelector('.rcv-body');
      var row  = body.querySelector('.rcv-row').cloneNode(true);
      row.querySelectorAll('input').forEach(function (i) {
        if (i.classList.contains('rcv-rate')) { return; }        // keep the rate
        if (i.classList.contains('rcv-lot') && i.readOnly) { return; }
        i.value = '';
      });
      row.querySelector('.rcv-qty').value = '';
      body.appendChild(row);
      row.querySelector('.rcv-lot').focus();
      recalc();
      return;
    }

    var del = e.target.closest('.rcv-del');
    if (del) {
      var body2 = del.closest('.rcv-body');
      var rows  = body2.querySelectorAll('.rcv-row');
      if (rows.length === 1) {
        body2.querySelector('.rcv-qty').value = '0';
      } else {
        del.closest('.rcv-row').remove();
      }
      recalc();
    }
  });

  form.addEventListener('input', function (e) {
    if (e.target.matches('.rcv-qty, .rcv-rate')) { recalc(); }
  });

  function validate(forFinal) {
    var any = false, problem = null;

    document.querySelectorAll('.rcv-group').forEach(function (g) {
      var hasLot = g.getAttribute('data-has-lot') === '1';
      g.querySelectorAll('.rcv-row').forEach(function (tr) {
        var q = SL.num(tr.querySelector('.rcv-qty').value);
        if (q <= 0) { return; }
        any = true;
        if (hasLot && tr.querySelector('.rcv-lot').value.trim() === '' && !problem) {
          problem = tr.querySelector('.rcv-lot');
        }
      });
    });

    if (problem) {
      alert('Enter the lot number for every lot-tracked item you are receiving.');
      problem.focus();
      return false;
    }
    if (forFinal && !any) {
      alert('Enter at least one received quantity before finalizing.');
      return false;
    }
    return true;
  }

  document.getElementById('btnSave').addEventListener('click', function () {
    if (!validate(false)) { return; }
    action.value = 'save';
    form.submit();
  });

  document.getElementById('btnFinal').addEventListener('click', function () {
    if (!validate(true)) { return; }
    var grand = SL.num(totalEl.textContent);
    var msg   = 'Finalize this purchase order?\n\n'
              + 'Ordered  : ' + SL.money(ordered) + '\n'
              + 'Received : ' + SL.money(grand) + '\n\n'
              + 'These quantities, rates and lot numbers will be added to stock '
              + 'and the PO can no longer be edited.';
    if (!confirm(msg)) { return; }
    action.value = 'finalize';
    form.submit();
  });

  recalc();
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
