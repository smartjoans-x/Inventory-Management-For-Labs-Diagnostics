<?php
/* ============================================================================
   budget.php - Monthly stock budget / indent
   ----------------------------------------------------------------------------
   The page that replaces the Excel budget sheet:

     Opening -> Purchase -> Issues -> Closing -> Required for next month

   grouped Department -> Equipment (or Supplier wise), with the requirement
   editable, exportable to CSV, and convertible into Draft purchase orders -
   one per supplier - in a single click.

   Imported months show the figures brought in from the old Excel sheet.
   Any other month is calculated from this system's own receipts and issues.
   ============================================================================ */
require_once __DIR__ . '/db.php';
require_login();

$month    = get_str('month') !== '' ? get_str('month') : post_str('month');
if (!month_valid($month)) {
    $month = month_key(date('Y-m-d', strtotime('first day of last month')));
}
$coverage = (float)(get_str('coverage') !== '' ? get_str('coverage') : 2);
if ($coverage <= 0 || $coverage > 12) {
    $coverage = 2.0;
}
$view      = get_str('view') === 'supplier' ? 'supplier' : 'dept';
$fDept     = get_int('dept_id');
$fSupplier = get_int('supplier_id');
$onlyReqd  = get_str('only') === '1';
$q         = get_str('q');

/* ------------------------------- ACTIONS --------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post_str('action');
    $figs   = month_figures($pdo, $month);
    $srcRow = $pdo->prepare('SELECT source FROM stock_month_summary WHERE month_key = ? LIMIT 1');
    $srcRow->execute([$month]);
    $source = (string)($srcRow->fetchColumn() ?: 'System');

    /* ---------- save the edited requirement ---------- */
    if ($action === 'save_reqd') {
        $qtyIn  = isset($_POST['reqd']) && is_array($_POST['reqd']) ? $_POST['reqd'] : [];
        $rateIn = isset($_POST['rate']) && is_array($_POST['rate']) ? $_POST['rate'] : [];
        $saved  = 0;

        try {
            $pdo->beginTransaction();
            foreach ($qtyIn as $itemId => $qtyRaw) {
                $itemId = (int)$itemId;
                $qty    = max(0, (float)$qtyRaw);
                $rate   = max(0, (float)($rateIn[$itemId] ?? 0));
                $f      = $figs[$itemId] ?? [
                    'op_qty' => 0, 'op_value' => 0, 'pur_qty' => 0, 'pur_value' => 0,
                    'iss_qty' => 0, 'iss_value' => 0, 'cl_qty' => 0, 'cl_value' => 0,
                ];
                $f['reqd_qty']   = $qty;
                $f['reqd_rate']  = $rate;
                $f['reqd_value'] = round($qty * $rate, 2);
                save_month_row($pdo, $month, $itemId, $f, month_add($month, 1), $source, user_id());
                $saved++;
            }
            $pdo->commit();
            flash('Requirement saved for <b>' . h(month_label(month_add($month, 1)))
                . '</b> on ' . $saved . ' item(s).');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('Could not save the requirement: ' . h($e->getMessage()), 'danger');
        }
        redirect('budget.php?' . http_build_query(['month' => $month, 'view' => $view,
                 'dept_id' => $fDept, 'supplier_id' => $fSupplier, 'coverage' => $coverage]));
    }

    /* ---------- freeze the month, so it becomes the next month's opening ---------- */
    if ($action === 'close_month') {
        try {
            $pdo->beginTransaction();
            foreach ($figs as $itemId => $f) {
                save_month_row($pdo, $month, (int)$itemId, $f, month_add($month, 1), $source, user_id());
            }
            $pdo->commit();
            flash('<b>' . h(month_label($month)) . '</b> closed and saved. Its closing stock is now the '
                . 'opening stock of ' . h(month_label(month_add($month, 1))) . '.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('Could not close the month: ' . h($e->getMessage()), 'danger');
        }
        redirect('budget.php?month=' . urlencode($month) . '&view=' . $view);
    }

    /* ---------- turn the requirement into Draft POs, one per supplier ---------- */
    if ($action === 'make_pos') {
        $st = $pdo->prepare("SELECT s.item_id, s.reqd_qty, s.reqd_rate, i.item_name,
                                    i.default_supplier_id
                             FROM stock_month_summary s
                             JOIN items i ON i.id = s.item_id
                             WHERE s.month_key = ? AND s.reqd_qty > 0 AND i.is_active = 1");
        $st->execute([$month]);
        $lines = $st->fetchAll();

        $bySupplier = [];
        $orphans    = [];
        foreach ($lines as $l) {
            if ((int)$l['default_supplier_id'] > 0) {
                $bySupplier[(int)$l['default_supplier_id']][] = $l;
            } else {
                $orphans[] = $l['item_name'];
            }
        }

        if (!$bySupplier) {
            flash('No requirement to order. Enter the required quantity first, save it, then create the POs.'
                . ($orphans ? ' ' . count($orphans) . ' item(s) have no supplier set in the Item Master.' : ''), 'warning');
            redirect('budget.php?month=' . urlencode($month) . '&view=' . $view);
        }

        $made = [];
        try {
            $pdo->beginTransaction();
            $today = today();
            foreach ($bySupplier as $supId => $rows) {
                $poNo = next_po_number($pdo, $today);
                $pdo->prepare("INSERT INTO purchase_orders
                                 (po_number, supplier_id, po_date, status, grand_total, notes, created_by)
                               VALUES (?,?,?, 'Draft', 0, ?, ?)")
                    ->execute([$poNo, $supId, $today,
                               'Auto-raised from the ' . month_label(month_add($month, 1)) . ' budget', user_id()]);
                $poId = (int)$pdo->lastInsertId();

                $ins = $pdo->prepare('INSERT INTO po_items (po_id, item_id, quantity, unit_rate, total_amount)
                                      VALUES (?,?,?,?,?)');
                foreach ($rows as $r) {
                    $qty  = (float)$r['reqd_qty'];
                    $rate = (float)$r['reqd_rate'];
                    $ins->execute([$poId, (int)$r['item_id'], $qty, $rate, round($qty * $rate, 2)]);
                }
                recalc_po_total($pdo, $poId);
                $made[] = $poNo;
            }
            $pdo->commit();

            flash(count($made) . ' Draft purchase order(s) created from the '
                . h(month_label(month_add($month, 1))) . ' requirement: <b>' . h(implode(', ', $made))
                . '</b>. Check the rates, then receive them when the goods arrive.'
                . ($orphans ? '<br>Skipped ' . count($orphans) . ' item(s) with no supplier in the Item Master: '
                            . h(implode(', ', array_slice($orphans, 0, 8)))
                            . (count($orphans) > 8 ? ' ...' : '') : ''));
            redirect('po_list.php?status=Draft');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('Could not create the purchase orders: ' . h($e->getMessage()), 'danger');
            redirect('budget.php?month=' . urlencode($month) . '&view=' . $view);
        }
    }
}

/* -------------------------------- DATA ----------------------------------- */
$sql  = 'SELECT v.* FROM v_item_stock v WHERE v.is_active = 1';
$args = [];
if ($fDept > 0) {
    $sql   .= ' AND v.department_id = ?';
    $args[] = $fDept;
}
if ($fSupplier > 0) {
    $sql   .= ' AND v.default_supplier_id = ?';
    $args[] = $fSupplier;
}
if ($q !== '') {
    $sql   .= ' AND (v.item_name LIKE ? OR v.item_code LIKE ?)';
    $args[] = '%' . $q . '%';
    $args[] = '%' . $q . '%';
}
$sql .= $view === 'supplier'
      ? ' ORDER BY v.default_supplier_name IS NULL, v.default_supplier_name, v.item_name'
      : ' ORDER BY v.dept_name IS NULL, v.dept_name, v.equipment_name IS NULL, v.equipment_name, v.item_name';
$st = $pdo->prepare($sql);
$st->execute($args);
$items = $st->fetchAll();

$figures = month_figures($pdo, $month);
$isImported = false;
foreach ($figures as $f) {
    if (($f['source'] ?? '') === 'Imported') {
        $isImported = true;
        break;
    }
}

/* build the display rows */
$rows   = [];
$totals = ['op_q' => 0, 'op_v' => 0, 'pu_q' => 0, 'pu_v' => 0, 'is_q' => 0, 'is_v' => 0,
           'cl_q' => 0, 'cl_v' => 0, 'rq_q' => 0, 'rq_v' => 0];

foreach ($items as $it) {
    $id = (int)$it['item_id'];
    $f  = $figures[$id] ?? [
        'op_qty' => 0.0, 'op_value' => 0.0, 'pur_qty' => 0.0, 'pur_value' => 0.0,
        'iss_qty' => 0.0, 'iss_value' => 0.0, 'cl_qty' => 0.0, 'cl_value' => 0.0,
        'reqd_qty' => 0.0, 'reqd_rate' => 0.0, 'reqd_value' => 0.0,
    ];

    /* best known rate for the requirement */
    $rate = (float)$f['reqd_rate'];
    if ($rate <= 0) {
        if ($f['cl_qty'] > 0 && $f['cl_value'] > 0)        { $rate = $f['cl_value'] / $f['cl_qty']; }
        elseif ($f['pur_qty'] > 0 && $f['pur_value'] > 0)  { $rate = $f['pur_value'] / $f['pur_qty']; }
        elseif ($f['iss_qty'] > 0 && $f['iss_value'] > 0)  { $rate = $f['iss_value'] / $f['iss_qty']; }
        elseif ((float)$it['total_qty'] > 0)               { $rate = (float)$it['stock_value'] / (float)$it['total_qty']; }
        else                                               { $rate = (float)$it['max_rate']; }
    }
    $rate = round($rate, 2);

    $suggest = suggest_reqd((float)$f['iss_qty'], (float)$f['cl_qty'], $coverage);
    $reqdQty = (float)$f['reqd_qty'];

    if ($onlyReqd && $reqdQty <= 0 && $suggest <= 0) {
        continue;
    }

    $group = $view === 'supplier'
           ? ($it['default_supplier_name'] ?: 'No supplier set')
           : (($it['dept_name'] ?: 'No department') . ($it['equipment_name'] ? ' / ' . $it['equipment_name'] : ''));

    $row = [
        'item'    => $it,
        'f'       => $f,
        'rate'    => $rate,
        'suggest' => $suggest,
        'reqd'    => $reqdQty,
        'group'   => $group,
    ];
    $rows[$group][] = $row;

    $totals['op_q'] += (float)$f['op_qty'];   $totals['op_v'] += (float)$f['op_value'];
    $totals['pu_q'] += (float)$f['pur_qty'];  $totals['pu_v'] += (float)$f['pur_value'];
    $totals['is_q'] += (float)$f['iss_qty'];  $totals['is_v'] += (float)$f['iss_value'];
    $totals['cl_q'] += (float)$f['cl_qty'];   $totals['cl_v'] += (float)$f['cl_value'];
    $totals['rq_q'] += $reqdQty;              $totals['rq_v'] += $reqdQty * $rate;
}

/* --------------------------------- EXPORT -------------------------------- */
if (get_str('export') === 'csv') {
    $file = 'sl_budget_' . $month . '_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['SL Diagnostics - Stock Budget']);
    fputcsv($out, ['Movement month', month_label($month), 'Requirement for', month_label(month_add($month, 1))]);
    fputcsv($out, ['Generated', date('d-m-Y H:i')]);
    fputcsv($out, []);
    fputcsv($out, ['Dept', 'Equipment', 'Supplier', 'Item Code', 'Item Name', 'Unit Rate',
                   'Op Qty', 'Op Value', 'Purchase Qty', 'Purchase Value',
                   'Issue Qty', 'Issue Value', 'Cl Qty', 'Cl Value', 'Reqd Qty', 'Reqd Value']);
    foreach ($rows as $group => $list) {
        foreach ($list as $r) {
            $it = $r['item']; $f = $r['f'];
            fputcsv($out, [
                $it['dept_name'], $it['equipment_name'], $it['default_supplier_name'],
                $it['item_code'], $it['item_name'], number_format($r['rate'], 2, '.', ''),
                qty_str($f['op_qty']),  number_format((float)$f['op_value'], 2, '.', ''),
                qty_str($f['pur_qty']), number_format((float)$f['pur_value'], 2, '.', ''),
                qty_str($f['iss_qty']), number_format((float)$f['iss_value'], 2, '.', ''),
                qty_str($f['cl_qty']),  number_format((float)$f['cl_value'], 2, '.', ''),
                qty_str($r['reqd']),    number_format($r['reqd'] * $r['rate'], 2, '.', ''),
            ]);
        }
    }
    fputcsv($out, []);
    fputcsv($out, ['TOTAL', '', '', '', '', '',
                   qty_str($totals['op_q']), number_format($totals['op_v'], 2, '.', ''),
                   qty_str($totals['pu_q']), number_format($totals['pu_v'], 2, '.', ''),
                   qty_str($totals['is_q']), number_format($totals['is_v'], 2, '.', ''),
                   qty_str($totals['cl_q']), number_format($totals['cl_v'], 2, '.', ''),
                   qty_str($totals['rq_q']), number_format($totals['rq_v'], 2, '.', '')]);
    fclose($out);
    exit;
}

$departments = all_departments($pdo);
$suppliers   = all_suppliers($pdo, false);
$months      = month_options($pdo);

$page_title = 'Budget ' . month_short($month);
$page_icon  = 'bi-calendar3';
$active     = 'budget';
require __DIR__ . '/includes/header.php';
?>

<form method="get" class="card mb-3 no-print">
  <div class="card-header">
    <i class="bi bi-calendar3"></i> Monthly Stock Budget
    <span class="badge <?= $isImported ? 'badge-soft-warning' : 'badge-soft-success' ?> ms-2">
      <?= $isImported ? 'Imported from Excel' : 'Calculated by the system' ?>
    </span>
    <div class="ms-auto d-flex flex-wrap gap-2">
      <a class="btn btn-sm btn-outline-success"
         href="budget.php?<?= h(http_build_query(['month' => $month, 'view' => $view, 'dept_id' => $fDept,
               'supplier_id' => $fSupplier, 'only' => $onlyReqd ? 1 : '', 'q' => $q,
               'coverage' => $coverage, 'export' => 'csv'])) ?>">
        <i class="bi bi-filetype-csv"></i> Export
      </a>
      <button type="button" class="btn btn-sm btn-outline-dark" onclick="window.print()"><i class="bi bi-printer"></i></button>
    </div>
  </div>
  <div class="card-body pb-2">
    <div class="row g-2 align-items-end">
      <div class="col-6 col-md-2">
        <label class="form-label">Movement month</label>
        <select name="month" class="form-select form-select-sm" onchange="this.form.submit()">
          <?php foreach ($months as $m): ?>
            <option value="<?= h($m) ?>" <?= $m === $month ? 'selected' : '' ?>><?= h(month_label($m)) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="help">Requirement is for <b><?= h(month_label(month_add($month, 1))) ?></b></div>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">Group by</label>
        <select name="view" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="dept" <?= $view === 'dept' ? 'selected' : '' ?>>Dept / Equipment</option>
          <option value="supplier" <?= $view === 'supplier' ? 'selected' : '' ?>>Supplier</option>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">Department</label>
        <select name="dept_id" class="form-select form-select-sm">
          <option value="">All</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= (int)$d['id'] ?>" <?= $fDept === (int)$d['id'] ? 'selected' : '' ?>>
              <?= h($d['dept_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
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
        <label class="form-label">Cover (months)</label>
        <input type="number" name="coverage" class="form-control form-control-sm text-end"
               step="0.5" min="0.5" max="12" value="<?= h((string)$coverage) ?>">
        <div class="help">Suggestion = issues &times; cover &minus; closing</div>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">Search</label>
        <div class="d-flex gap-1">
          <input type="text" name="q" class="form-control form-control-sm" value="<?= h($q) ?>" placeholder="item">
          <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
        </div>
      </div>
      <div class="col-12">
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="checkbox" name="only" value="1" id="onlyChk"
                 <?= $onlyReqd ? 'checked' : '' ?> onchange="this.form.submit()">
          <label class="form-check-label help" for="onlyChk">Show only items that need ordering</label>
        </div>
        <a href="budget.php?month=<?= h($month) ?>" class="btn btn-sm btn-link text-decoration-none">Clear filters</a>
      </div>
    </div>
  </div>
</form>

<!-- ============================== SUMMARY ============================== -->
<div class="row g-3 mb-3">
  <div class="col-6 col-xl-3">
    <div class="stat">
      <div class="stat-label">Opening <?= h(month_short($month)) ?></div>
      <div class="stat-value"><?= money($totals['op_v']) ?></div>
      <div class="stat-sub"><?= qty_str($totals['op_q']) ?> units</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat">
      <div class="stat-label">Purchased</div>
      <div class="stat-value"><?= money($totals['pu_v']) ?></div>
      <div class="stat-sub"><?= qty_str($totals['pu_q']) ?> units in</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat">
      <div class="stat-label">Issued / consumed</div>
      <div class="stat-value"><?= money($totals['is_v']) ?></div>
      <div class="stat-sub"><?= qty_str($totals['is_q']) ?> units out</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat">
      <div class="stat-label">Closing</div>
      <div class="stat-value"><?= money($totals['cl_v']) ?></div>
      <div class="stat-sub"><?= qty_str($totals['cl_q']) ?> units in hand</div>
    </div>
  </div>
</div>

<form method="post" id="budgetForm">
  <?= csrf_field() ?>
  <input type="hidden" name="month" value="<?= h($month) ?>">
  <input type="hidden" name="action" id="budgetAction" value="save_reqd">

  <div class="card">
    <div class="card-header">
      <i class="bi bi-list-check"></i> <?= h(month_label($month)) ?> movement &amp; requirement for
      <?= h(month_label(month_add($month, 1))) ?>
      <span class="badge badge-soft-secondary ms-1"><?= count($items) ?> items</span>
      <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="fillSuggest">
        <i class="bi bi-magic"></i> Fill suggested
      </button>
    </div>

    <div class="table-wrap">
      <table class="table table-sm table-hover table-sticky mb-0 budget-table">
        <thead>
          <tr>
            <th style="min-width:230px">Item</th>
            <th class="num">Rate</th>
            <th class="num">Op Qty</th>
            <th class="num">Op Value</th>
            <th class="num">Pur Qty</th>
            <th class="num">Pur Value</th>
            <th class="num">Iss Qty</th>
            <th class="num">Iss Value</th>
            <th class="num">Cl Qty</th>
            <th class="num">Cl Value</th>
            <th class="num" style="min-width:120px">Reqd Qty</th>
            <th class="num">Reqd Value</th>
          </tr>
        </thead>
        <?php if (!$rows): ?>
          <tbody><tr><td colspan="12"><div class="empty-state"><i class="bi bi-calendar-x"></i>
            No items for these filters.</div></td></tr></tbody>
        <?php else: foreach ($rows as $group => $list):
            $g = ['op_q' => 0, 'op_v' => 0, 'pu_q' => 0, 'pu_v' => 0, 'is_q' => 0, 'is_v' => 0,
                  'cl_q' => 0, 'cl_v' => 0, 'rq_q' => 0, 'rq_v' => 0];
            foreach ($list as $r) {
                $f = $r['f'];
                $g['op_q'] += (float)$f['op_qty'];  $g['op_v'] += (float)$f['op_value'];
                $g['pu_q'] += (float)$f['pur_qty']; $g['pu_v'] += (float)$f['pur_value'];
                $g['is_q'] += (float)$f['iss_qty']; $g['is_v'] += (float)$f['iss_value'];
                $g['cl_q'] += (float)$f['cl_qty'];  $g['cl_v'] += (float)$f['cl_value'];
                $g['rq_q'] += $r['reqd'];           $g['rq_v'] += $r['reqd'] * $r['rate'];
            } ?>
        <tbody>
          <tr class="group-row">
            <td colspan="12">
              <i class="bi <?= $view === 'supplier' ? 'bi-truck' : 'bi-diagram-3' ?>"></i>
              <b><?= h($group) ?></b>
              <span class="help ms-2"><?= count($list) ?> item(s)</span>
            </td>
          </tr>
          <?php foreach ($list as $r): $it = $r['item']; $f = $r['f']; $id = (int)$it['item_id']; ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= h($it['item_name']) ?></div>
              <div class="help mono"><?= h($it['item_code']) ?>
                <?php if ($view === 'supplier' && $it['dept_name']): ?>
                  &middot; <?= h($it['dept_name']) ?>
                <?php elseif ($view !== 'supplier' && $it['default_supplier_name']): ?>
                  &middot; <?= h($it['default_supplier_name']) ?>
                <?php endif; ?>
              </div>
            </td>
            <td class="num">
              <input type="number" name="rate[<?= $id ?>]" class="form-control form-control-sm text-end bg-rate"
                     step="0.01" min="0" value="<?= h(number_format($r['rate'], 2, '.', '')) ?>" style="width:94px">
            </td>
            <td class="num"><?= qty_str($f['op_qty']) ?></td>
            <td class="num"><?= money($f['op_value'], false) ?></td>
            <td class="num"><?= qty_str($f['pur_qty']) ?></td>
            <td class="num"><?= money($f['pur_value'], false) ?></td>
            <td class="num"><?= qty_str($f['iss_qty']) ?></td>
            <td class="num"><?= money($f['iss_value'], false) ?></td>
            <td class="num <?= (float)$f['cl_qty'] <= 0 ? 'text-danger' : '' ?>"><?= qty_str($f['cl_qty']) ?></td>
            <td class="num"><?= money($f['cl_value'], false) ?></td>
            <td class="num">
              <input type="number" name="reqd[<?= $id ?>]" class="form-control form-control-sm text-end bg-reqd"
                     step="0.01" min="0" value="<?= h(qty_str($r['reqd'])) ?>"
                     data-suggest="<?= h(qty_str($r['suggest'])) ?>" style="width:104px">
              <?php if ($r['suggest'] > 0): ?>
                <div class="help">suggest <?= qty_str($r['suggest']) ?></div>
              <?php endif; ?>
            </td>
            <td class="num fw-semibold reqd-val"><?= money($r['reqd'] * $r['rate'], false) ?></td>
          </tr>
          <?php endforeach; ?>
          <tr class="subtotal-row">
            <td class="text-end">Sub total</td>
            <td></td>
            <td class="num"><?= qty_str($g['op_q']) ?></td>
            <td class="num"><?= money($g['op_v'], false) ?></td>
            <td class="num"><?= qty_str($g['pu_q']) ?></td>
            <td class="num"><?= money($g['pu_v'], false) ?></td>
            <td class="num"><?= qty_str($g['is_q']) ?></td>
            <td class="num"><?= money($g['is_v'], false) ?></td>
            <td class="num"><?= qty_str($g['cl_q']) ?></td>
            <td class="num"><?= money($g['cl_v'], false) ?></td>
            <td class="num"><?= qty_str($g['rq_q']) ?></td>
            <td class="num"><?= money($g['rq_v'], false) ?></td>
          </tr>
        </tbody>
        <?php endforeach; endif; ?>
        <tfoot>
          <tr class="table-light fw-bold">
            <td class="text-end">GRAND TOTAL</td>
            <td></td>
            <td class="num"><?= qty_str($totals['op_q']) ?></td>
            <td class="num"><?= money($totals['op_v'], false) ?></td>
            <td class="num"><?= qty_str($totals['pu_q']) ?></td>
            <td class="num"><?= money($totals['pu_v'], false) ?></td>
            <td class="num"><?= qty_str($totals['is_q']) ?></td>
            <td class="num"><?= money($totals['is_v'], false) ?></td>
            <td class="num"><?= qty_str($totals['cl_q']) ?></td>
            <td class="num"><?= money($totals['cl_v'], false) ?></td>
            <td class="num"><?= qty_str($totals['rq_q']) ?></td>
            <td class="num" id="grandReqd"><?= money($totals['rq_v'], false) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="card-footer d-flex flex-wrap gap-2 align-items-center">
      <button type="button" class="btn btn-primary btn-sm" id="btnSaveReqd">
        <i class="bi bi-save"></i> Save Requirement
      </button>
      <button type="button" class="btn btn-teal btn-sm" id="btnMakePo">
        <i class="bi bi-file-earmark-plus"></i> Create Draft POs (supplier wise)
      </button>
      <button type="button" class="btn btn-outline-secondary btn-sm" id="btnClose">
        <i class="bi bi-lock"></i> Close <?= h(month_short($month)) ?>
      </button>
      <div class="ms-auto help" style="max-width:520px">
        <i class="bi bi-info-circle"></i>
        <b>Close</b> freezes this month's figures so its closing stock becomes next month's opening.
        <b>Create Draft POs</b> groups the saved requirement by each item's supplier and raises one
        Draft PO per supplier - nothing enters stock until each one is received.
      </div>
    </div>
  </div>
</form>

<script>
(function () {
  'use strict';
  var form = document.getElementById('budgetForm');
  var act  = document.getElementById('budgetAction');

  function recalc() {
    var grand = 0;
    form.querySelectorAll('tbody tr').forEach(function (tr) {
      var qi = tr.querySelector('.bg-reqd');
      var ri = tr.querySelector('.bg-rate');
      if (!qi || !ri) { return; }
      var v = SL.num(qi.value) * SL.num(ri.value);
      var cell = tr.querySelector('.reqd-val');
      if (cell) { cell.textContent = SL.money(v); }
      grand += v;
    });
    document.getElementById('grandReqd').textContent = SL.money(grand);
  }

  form.addEventListener('input', function (e) {
    if (e.target.matches('.bg-reqd, .bg-rate')) { recalc(); }
  });

  document.getElementById('fillSuggest').addEventListener('click', function () {
    var n = 0;
    form.querySelectorAll('.bg-reqd').forEach(function (i) {
      var s = parseFloat(i.getAttribute('data-suggest')) || 0;
      if (s > 0 && SL.num(i.value) === 0) { i.value = s; n++; }
    });
    recalc();
    if (!n) { alert('Nothing to fill - every suggested quantity is already entered or is zero.'); }
  });

  document.getElementById('btnSaveReqd').addEventListener('click', function () {
    act.value = 'save_reqd';
    form.submit();
  });

  document.getElementById('btnClose').addEventListener('click', function () {
    if (!confirm('Close this month?\n\nIts figures are saved and the closing stock becomes the opening stock of the next month.')) { return; }
    act.value = 'close_month';
    form.submit();
  });

  document.getElementById('btnMakePo').addEventListener('click', function () {
    if (!confirm('Create Draft purchase orders from the SAVED requirement?\n\nOne PO per supplier. Save the requirement first if you have just edited it.')) { return; }
    act.value = 'make_pos';
    form.submit();
  });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
