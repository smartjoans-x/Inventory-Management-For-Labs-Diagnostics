<?php
/* ============================================================================
   items.php - Item master
   has_lot = 1 -> multiple lot numbers with their own expiry / rate / supplier
   has_lot = 0 -> single 'GENERAL' lot, no lot number / expiry asked for
   ============================================================================ */
require_once __DIR__ . '/db.php';
require_login();

$editing = null;

/** Suggest the next item code for a category prefix, e.g. RGT-0007 */
function suggest_item_code(PDO $pdo, string $prefix = 'ITM'): string
{
    $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $prefix) ?: 'ITM');
    $prefix = substr($prefix, 0, 6);
    $last   = scalar($pdo, 'SELECT item_code FROM items WHERE item_code LIKE ?
                            ORDER BY item_code DESC LIMIT 1', [$prefix . '-%']);
    $seq = $last ? (int)substr((string)$last, strrpos((string)$last, '-') + 1) : 0;
    do {
        $seq++;
        $code   = $prefix . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
        $exists = (int)scalar($pdo, 'SELECT COUNT(*) FROM items WHERE item_code = ?', [$code]);
    } while ($exists > 0 && $seq < 100000);
    return $code;
}

/* ------------------------------- ACTIONS --------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post_str('action');

    if ($action === 'save') {
        $id       = post_int('id');
        $name     = post_str('item_name');
        $category = nullify(post_str('category'));
        $unit     = post_str('unit') !== '' ? post_str('unit') : 'Nos';
        $hasLot   = post_int('has_lot') === 1 ? 1 : 0;
        $minAlert = max(0, post_float('min_stock_alert'));
        $isActive = post_int('is_active', 1) === 1 ? 1 : 0;
        $code     = strtoupper(post_str('item_code'));
        $deptId   = post_int('department_id') ?: null;
        $equipId  = post_int('equipment_id') ?: null;
        $supId    = post_int('default_supplier_id') ?: null;

        $errors = [];
        if ($name === '') {
            $errors[] = 'Item name is required.';
        }
        if ($code === '') {
            $code = suggest_item_code($pdo, $category ? substr(preg_replace('/[^A-Za-z]/', '', $category), 0, 3) : 'ITM');
        }

        if ($errors) {
            flash(implode('<br>', array_map('h', $errors)), 'danger');
            redirect('items.php' . ($id ? '?edit=' . $id : ''));
        }

        try {
            if ($id > 0) {
                /* guard: switching an item that already holds stock from
                   lot-tracked to non-lot would orphan its lot numbers */
                $liveLots = (int)scalar($pdo, 'SELECT COUNT(*) FROM item_batches
                                               WHERE item_id = ? AND quantity_available > 0
                                                 AND lot_number <> ?', [$id, GENERAL_LOT]);
                $oldHasLot = (int)scalar($pdo, 'SELECT has_lot FROM items WHERE id = ?', [$id]);
                if ($oldHasLot === 1 && $hasLot === 0 && $liveLots > 0) {
                    flash('This item still has ' . $liveLots . ' lot(s) in stock, so lot tracking cannot be '
                        . 'switched off yet. Issue or clear those lots first.', 'warning');
                    redirect('items.php?edit=' . $id);
                }

                $st = $pdo->prepare('UPDATE items SET item_code=?, item_name=?, category=?, unit=?,
                                     has_lot=?, min_stock_alert=?, department_id=?, equipment_id=?,
                                     default_supplier_id=?, is_active=? WHERE id=?');
                $st->execute([$code, $name, $category, $unit, $hasLot, $minAlert,
                              $deptId, $equipId, $supId, $isActive, $id]);
                flash('Item <b>' . h($name) . '</b> updated.');
            } else {
                $st = $pdo->prepare('INSERT INTO items
                                     (item_code, item_name, category, unit, has_lot, min_stock_alert,
                                      department_id, equipment_id, default_supplier_id, is_active)
                                     VALUES (?,?,?,?,?,?,?,?,?,?)');
                $st->execute([$code, $name, $category, $unit, $hasLot, $minAlert,
                              $deptId, $equipId, $supId, $isActive]);
                flash('Item <b>' . h($name) . '</b> added with code <b>' . h($code) . '</b>.');
            }
        } catch (PDOException $e) {
            if ((int)$e->getCode() === 23000) {
                flash('Item code <b>' . h($code) . '</b> is already in use. Please use another code.', 'danger');
            } else {
                flash('Could not save the item: ' . h($e->getMessage()), 'danger');
            }
        }
        redirect('items.php');
    }

    if ($action === 'delete') {
        $id   = post_int('id');
        $name = (string)scalar($pdo, 'SELECT item_name FROM items WHERE id = ?', [$id]);
        $inPo  = (int)scalar($pdo, 'SELECT COUNT(*) FROM po_items WHERE item_id = ?', [$id]);
        $inOut = (int)scalar($pdo, 'SELECT COUNT(*) FROM stock_out WHERE item_id = ?', [$id]);
        $qty   = item_stock_qty($pdo, $id);

        if ($inPo > 0 || $inOut > 0) {
            flash('<b>' . h($name) . '</b> cannot be deleted because it is used in '
                . $inPo . ' purchase order line(s) and ' . $inOut . ' stock-out entry(ies). '
                . 'Mark it inactive instead.', 'warning');
        } elseif ($qty > 0) {
            flash('<b>' . h($name) . '</b> still has ' . qty_str($qty)
                . ' in stock. Issue the stock first or mark the item inactive.', 'warning');
        } else {
            try {
                $pdo->prepare('DELETE FROM items WHERE id = ?')->execute([$id]);
                flash('Item <b>' . h($name) . '</b> deleted.');
            } catch (PDOException $e) {
                flash('Could not delete the item: ' . h($e->getMessage()), 'danger');
            }
        }
        redirect('items.php');
    }
}

/* ------------------------------- EDIT LOAD ------------------------------- */
if (get_int('edit') > 0) {
    $st = $pdo->prepare('SELECT * FROM items WHERE id = ?');
    $st->execute([get_int('edit')]);
    $editing = $st->fetch() ?: null;
    if (!$editing) {
        flash('Item not found.', 'warning');
        redirect('items.php');
    }
}

/* -------------------------------- LISTING -------------------------------- */
$q        = get_str('q');
$fCat     = get_str('category');
$fLot     = get_str('has_lot');
$fDept    = get_int('dept_id');

$sql  = 'SELECT v.*, i.created_at FROM v_item_stock v JOIN items i ON i.id = v.item_id WHERE 1=1';
$args = [];
if ($fDept > 0) {
    $sql   .= ' AND v.department_id = ?';
    $args[] = $fDept;
}
if ($q !== '') {
    $sql   .= ' AND (v.item_name LIKE ? OR v.item_code LIKE ?)';
    $args[] = '%' . $q . '%';
    $args[] = '%' . $q . '%';
}
if ($fCat !== '') {
    $sql   .= ' AND v.category = ?';
    $args[] = $fCat;
}
if ($fLot === '1' || $fLot === '0') {
    $sql   .= ' AND v.has_lot = ?';
    $args[] = (int)$fLot;
}
$sql .= ' ORDER BY v.is_active DESC, v.item_name';
$st = $pdo->prepare($sql);
$st->execute($args);
$items = $st->fetchAll();

$categories  = all_categories($pdo);
$departments = all_departments($pdo);
$equipments  = all_equipments($pdo);
$supList     = all_suppliers($pdo);
$units       = ['Kit', 'Box', 'Bottle', 'Vial', 'Pack', 'ml', 'Ltr', 'gm', 'Kg', 'Nos', 'Roll', 'Strip', 'Test'];

$equipByDept = [];
foreach ($equipments as $e) {
    $equipByDept[$e['dept_name'] ?: 'Other'][] = $e;
}

$page_title = 'Item Master';
$page_icon  = 'bi-box-seam';
$active     = 'items';
require __DIR__ . '/includes/header.php';
?>

<div class="row g-3">
  <!-- ------------------------------ FORM ------------------------------ -->
  <div class="col-12 col-xl-4">
    <div class="card">
      <div class="card-header">
        <i class="bi <?= $editing ? 'bi-pencil-square' : 'bi-plus-circle' ?>"></i>
        <?= $editing ? 'Edit Item' : 'Add New Item' ?>
      </div>
      <div class="card-body">
        <form method="post" autocomplete="off">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= $editing ? (int)$editing['id'] : 0 ?>">

          <div class="mb-2">
            <label class="form-label req">Item Name</label>
            <input type="text" name="item_name" class="form-control" maxlength="190" required
                   value="<?= h($editing['item_name'] ?? '') ?>" placeholder="e.g. Glucose GOD-POD Reagent">
          </div>

          <div class="row g-2">
            <div class="col-12 col-sm-6 mb-2">
              <label class="form-label">Item Code</label>
              <input type="text" name="item_code" class="form-control mono text-uppercase" maxlength="40"
                     value="<?= h($editing['item_code'] ?? '') ?>" placeholder="auto">
              <div class="help">Leave blank to auto-generate.</div>
            </div>
            <div class="col-12 col-sm-6 mb-2">
              <label class="form-label">Category</label>
              <input type="text" name="category" class="form-control" maxlength="80" list="catList"
                     value="<?= h($editing['category'] ?? '') ?>" placeholder="e.g. Biochemistry Reagent">
              <datalist id="catList">
                <?php foreach ($categories as $c): ?><option value="<?= h($c) ?>"></option><?php endforeach; ?>
              </datalist>
            </div>
          </div>

          <div class="row g-2">
            <div class="col-12 col-sm-6 mb-2">
              <label class="form-label">Unit</label>
              <input type="text" name="unit" class="form-control" maxlength="20" list="unitList"
                     value="<?= h($editing['unit'] ?? 'Nos') ?>">
              <datalist id="unitList">
                <?php foreach ($units as $u): ?><option value="<?= h($u) ?>"></option><?php endforeach; ?>
              </datalist>
            </div>
            <div class="col-12 col-sm-6 mb-2">
              <label class="form-label">Min Stock Alert</label>
              <input type="number" name="min_stock_alert" class="form-control num" step="0.01" min="0"
                     value="<?= h($editing['min_stock_alert'] ?? '0') ?>">
            </div>
          </div>

          <div class="row g-2">
            <div class="col-12 col-sm-6 mb-2">
              <label class="form-label">Department</label>
              <select name="department_id" class="form-select">
                <option value="">-- none --</option>
                <?php foreach ($departments as $d): ?>
                  <option value="<?= (int)$d['id'] ?>"
                    <?= (int)($editing['department_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>>
                    <?= h($d['dept_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-sm-6 mb-2">
              <label class="form-label">Equipment / Analyser</label>
              <select name="equipment_id" class="form-select">
                <option value="">-- none --</option>
                <?php foreach ($equipByDept as $dept => $list): ?>
                  <optgroup label="<?= h($dept) ?>">
                    <?php foreach ($list as $e): ?>
                      <option value="<?= (int)$e['id'] ?>"
                        <?= (int)($editing['equipment_id'] ?? 0) === (int)$e['id'] ? 'selected' : '' ?>>
                        <?= h($e['equipment_name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="mb-2">
            <label class="form-label">Default Supplier</label>
            <select name="default_supplier_id" class="form-select">
              <option value="">-- none --</option>
              <?php foreach ($supList as $s): ?>
                <option value="<?= (int)$s['id'] ?>"
                  <?= (int)($editing['default_supplier_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>>
                  <?= h($s['supplier_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="help">Used by the Budget page to raise one purchase order per supplier.</div>
          </div>

          <div class="mb-2">
            <label class="form-label">Lot / Batch Tracking</label>
            <div class="border rounded p-2">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="has_lot" id="lot1" value="1"
                       <?= (!$editing || (int)$editing['has_lot'] === 1) ? 'checked' : '' ?>>
                <label class="form-check-label" for="lot1">
                  <b>Lot tracked</b>
                  <div class="help">Reagents, kits, controls - each lot keeps its own lot number, expiry date, supplier and rate.</div>
                </label>
              </div>
              <div class="form-check mt-2">
                <input class="form-check-input" type="radio" name="has_lot" id="lot0" value="0"
                       <?= ($editing && (int)$editing['has_lot'] === 0) ? 'checked' : '' ?>>
                <label class="form-check-label" for="lot0">
                  <b>No lot number</b>
                  <div class="help">Gloves, tubes, stationery - stock is held under a single <span class="lot-chip general">GENERAL</span> lot.</div>
                </label>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="is_active" class="form-select">
              <option value="1" <?= (!$editing || (int)$editing['is_active'] === 1) ? 'selected' : '' ?>>Active</option>
              <option value="0" <?= ($editing && (int)$editing['is_active'] === 0) ? 'selected' : '' ?>>Inactive</option>
            </select>
          </div>

          <div class="d-flex gap-2">
            <button class="btn btn-primary flex-grow-1">
              <i class="bi bi-save"></i> <?= $editing ? 'Update Item' : 'Save Item' ?>
            </button>
            <?php if ($editing): ?><a href="items.php" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ------------------------------ LIST ------------------------------ -->
  <div class="col-12 col-xl-8">
    <div class="card">
      <div class="card-header">
        <i class="bi bi-list-ul"></i> Item List
        <span class="badge badge-soft-secondary ms-1"><?= count($items) ?></span>
        <form class="ms-auto d-flex flex-wrap gap-2" method="get">
          <input type="text" name="q" class="form-control form-control-sm" style="min-width:150px"
                 placeholder="Search item..." value="<?= h($q) ?>">
          <select name="dept_id" class="form-select form-select-sm" style="min-width:140px">
            <option value="">All departments</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?= (int)$d['id'] ?>" <?= $fDept === (int)$d['id'] ? 'selected' : '' ?>>
                <?= h($d['dept_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <select name="category" class="form-select form-select-sm" style="min-width:140px">
            <option value="">All categories</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= h($c) ?>" <?= $fCat === $c ? 'selected' : '' ?>><?= h($c) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="has_lot" class="form-select form-select-sm" style="min-width:120px">
            <option value="">Lot: all</option>
            <option value="1" <?= $fLot === '1' ? 'selected' : '' ?>>Lot tracked</option>
            <option value="0" <?= $fLot === '0' ? 'selected' : '' ?>>No lot</option>
          </select>
          <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
          <?php if ($q !== '' || $fCat !== '' || $fLot !== ''): ?>
            <a href="items.php" class="btn btn-sm btn-outline-secondary">Clear</a>
          <?php endif; ?>
        </form>
      </div>
      <div class="table-wrap">
        <table class="table table-hover table-striped mb-0">
          <thead>
            <tr>
              <th>Code</th>
              <th>Item</th>
              <th>Lot</th>
              <th class="num">Stock</th>
              <th class="num">Min</th>
              <th class="num">Lots</th>
              <th class="num">Stock Value</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$items): ?>
            <tr><td colspan="8"><div class="empty-state"><i class="bi bi-box-seam"></i>No items found.</div></td></tr>
          <?php else: foreach ($items as $it):
              $low = (float)$it['min_stock_alert'] > 0 && (float)$it['total_qty'] <= (float)$it['min_stock_alert']; ?>
            <tr>
              <td class="mono"><?= h($it['item_code']) ?></td>
              <td>
                <div class="fw-semibold"><?= h($it['item_name']) ?></div>
                <div class="help">
                  <?= h($it['dept_name'] ?: ($it['category'] ?: 'Uncategorised')) ?>
                  <?php if ($it['equipment_name']): ?> &middot; <?= h($it['equipment_name']) ?><?php endif; ?>
                  &middot; <?= h($it['unit']) ?>
                </div>
                <?php if ($it['default_supplier_name']): ?>
                  <div class="help"><i class="bi bi-truck"></i> <?= h($it['default_supplier_name']) ?></div>
                <?php endif; ?>
                <?php if ((int)$it['is_active'] === 0): ?><span class="badge badge-soft-secondary">Inactive</span><?php endif; ?>
              </td>
              <td>
                <?php if ((int)$it['has_lot'] === 1): ?>
                  <span class="badge badge-soft-success">Lot wise</span>
                <?php else: ?>
                  <span class="badge badge-soft-secondary">General</span>
                <?php endif; ?>
              </td>
              <td class="num">
                <span class="<?= $low ? 'badge badge-soft-warning' : '' ?>">
                  <?= qty_str($it['total_qty']) ?> <?= h($it['unit']) ?>
                </span>
              </td>
              <td class="num"><?= qty_str($it['min_stock_alert']) ?></td>
              <td class="num"><?= (int)$it['batch_count'] ?></td>
              <td class="num"><?= money($it['stock_value'], false) ?></td>
              <td class="text-end text-nowrap">
                <a href="stock_report.php?item_id=<?= (int)$it['item_id'] ?>" class="btn btn-sm btn-outline-secondary" title="Lots">
                  <i class="bi bi-layers"></i>
                </a>
                <a href="items.php?edit=<?= (int)$it['item_id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                  <i class="bi bi-pencil"></i>
                </a>
                <form method="post" class="d-inline"
                      data-confirm="Delete item &quot;<?= h($it['item_name']) ?>&quot;?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$it['item_id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
