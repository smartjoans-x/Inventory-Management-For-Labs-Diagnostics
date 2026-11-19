<?php
/* ============================================================================
   suppliers.php - Supplier master (add / edit / activate / delete)
   ============================================================================ */
require_once __DIR__ . '/db.php';
require_login();

$editing = null;

/* ------------------------------- ACTIONS --------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post_str('action');

    /* ---------- save (insert / update) ---------- */
    if ($action === 'save') {
        $id     = post_int('id');
        $data   = [
            'supplier_name'  => post_str('supplier_name'),
            'contact_person' => nullify(post_str('contact_person')),
            'phone'          => nullify(post_str('phone')),
            'email'          => nullify(post_str('email')),
            'gst_no'         => nullify(strtoupper(post_str('gst_no'))),
            'address'        => nullify(post_str('address')),
            'is_active'      => post_int('is_active', 1) === 1 ? 1 : 0,
        ];
        $errors = [];

        if ($data['supplier_name'] === '') {
            $errors[] = 'Supplier name is required.';
        }
        if ($data['email'] !== null && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid e-mail address.';
        }

        if ($errors) {
            flash(implode('<br>', array_map('h', $errors)), 'danger');
            redirect('suppliers.php' . ($id ? '?edit=' . $id : ''));
        }

        try {
            if ($id > 0) {
                $st = $pdo->prepare('UPDATE suppliers SET supplier_name=?, contact_person=?, phone=?,
                                     email=?, gst_no=?, address=?, is_active=? WHERE id=?');
                $st->execute([
                    $data['supplier_name'], $data['contact_person'], $data['phone'], $data['email'],
                    $data['gst_no'], $data['address'], $data['is_active'], $id,
                ]);
                flash('Supplier <b>' . h($data['supplier_name']) . '</b> updated.');
            } else {
                $st = $pdo->prepare('INSERT INTO suppliers
                                     (supplier_name, contact_person, phone, email, gst_no, address, is_active)
                                     VALUES (?,?,?,?,?,?,?)');
                $st->execute([
                    $data['supplier_name'], $data['contact_person'], $data['phone'], $data['email'],
                    $data['gst_no'], $data['address'], $data['is_active'],
                ]);
                flash('Supplier <b>' . h($data['supplier_name']) . '</b> added.');
            }
        } catch (PDOException $e) {
            if ((int)$e->getCode() === 23000) {
                flash('A supplier with this name already exists.', 'danger');
            } else {
                flash('Could not save the supplier: ' . h($e->getMessage()), 'danger');
            }
        }
        redirect('suppliers.php');
    }

    /* ---------- delete ---------- */
    if ($action === 'delete') {
        $id   = post_int('id');
        $name = (string)scalar($pdo, 'SELECT supplier_name FROM suppliers WHERE id = ?', [$id]);
        $poUsed    = (int)scalar($pdo, 'SELECT COUNT(*) FROM purchase_orders WHERE supplier_id = ?', [$id]);
        $batchUsed = (int)scalar($pdo, 'SELECT COUNT(*) FROM item_batches WHERE supplier_id = ?', [$id]);

        if ($poUsed > 0) {
            flash('<b>' . h($name) . '</b> cannot be deleted - ' . $poUsed
                . ' purchase order(s) are linked. Mark the supplier inactive instead.', 'warning');
        } else {
            try {
                $pdo->prepare('DELETE FROM suppliers WHERE id = ?')->execute([$id]);
                flash('Supplier <b>' . h($name) . '</b> deleted.'
                    . ($batchUsed > 0 ? ' ' . $batchUsed . ' stock lot(s) are now shown without a supplier.' : ''));
            } catch (PDOException $e) {
                flash('Could not delete the supplier: ' . h($e->getMessage()), 'danger');
            }
        }
        redirect('suppliers.php');
    }
}

/* ------------------------------- EDIT LOAD ------------------------------- */
if (get_int('edit') > 0) {
    $st = $pdo->prepare('SELECT * FROM suppliers WHERE id = ?');
    $st->execute([get_int('edit')]);
    $editing = $st->fetch() ?: null;
    if (!$editing) {
        flash('Supplier not found.', 'warning');
        redirect('suppliers.php');
    }
}

/* -------------------------------- LISTING -------------------------------- */
$q    = get_str('q');
$sql  = 'SELECT s.*,
                (SELECT COUNT(*) FROM purchase_orders po WHERE po.supplier_id = s.id) AS po_count,
                (SELECT COALESCE(SUM(b.quantity_available * b.unit_price),0)
                   FROM item_batches b WHERE b.supplier_id = s.id) AS stock_value
         FROM suppliers s';
$args = [];
if ($q !== '') {
    $sql .= ' WHERE s.supplier_name LIKE ? OR s.contact_person LIKE ? OR s.phone LIKE ? OR s.gst_no LIKE ?';
    $args = array_fill(0, 4, '%' . $q . '%');
}
$sql .= ' ORDER BY s.is_active DESC, s.supplier_name';
$st = $pdo->prepare($sql);
$st->execute($args);
$suppliers = $st->fetchAll();

$page_title = 'Suppliers';
$page_icon  = 'bi-truck';
$active     = 'suppliers';
require __DIR__ . '/includes/header.php';
?>

<div class="row g-3">
  <!-- ------------------------------ FORM ------------------------------ -->
  <div class="col-12 col-xl-4">
    <div class="card">
      <div class="card-header">
        <i class="bi <?= $editing ? 'bi-pencil-square' : 'bi-plus-circle' ?>"></i>
        <?= $editing ? 'Edit Supplier' : 'Add New Supplier' ?>
      </div>
      <div class="card-body">
        <form method="post" autocomplete="off">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= $editing ? (int)$editing['id'] : 0 ?>">

          <div class="mb-2">
            <label class="form-label req">Supplier Name</label>
            <input type="text" name="supplier_name" class="form-control" maxlength="150" required
                   value="<?= h($editing['supplier_name'] ?? '') ?>" placeholder="e.g. Transasia Bio-Medicals Ltd">
          </div>
          <div class="row g-2">
            <div class="col-12 col-sm-6 mb-2">
              <label class="form-label">Contact Person</label>
              <input type="text" name="contact_person" class="form-control" maxlength="100"
                     value="<?= h($editing['contact_person'] ?? '') ?>">
            </div>
            <div class="col-12 col-sm-6 mb-2">
              <label class="form-label">Phone</label>
              <input type="text" name="phone" class="form-control" maxlength="40"
                     value="<?= h($editing['phone'] ?? '') ?>">
            </div>
          </div>
          <div class="row g-2">
            <div class="col-12 col-sm-6 mb-2">
              <label class="form-label">E-mail</label>
              <input type="email" name="email" class="form-control" maxlength="120"
                     value="<?= h($editing['email'] ?? '') ?>">
            </div>
            <div class="col-12 col-sm-6 mb-2">
              <label class="form-label">GST No</label>
              <input type="text" name="gst_no" class="form-control text-uppercase mono" maxlength="20"
                     value="<?= h($editing['gst_no'] ?? '') ?>" placeholder="36AABCR1234A1Z5">
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label">Address</label>
            <textarea name="address" class="form-control" rows="2" maxlength="500"><?= h($editing['address'] ?? '') ?></textarea>
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
              <i class="bi bi-save"></i> <?= $editing ? 'Update Supplier' : 'Save Supplier' ?>
            </button>
            <?php if ($editing): ?>
              <a href="suppliers.php" class="btn btn-outline-secondary">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ------------------------------ LIST ------------------------------ -->
  <div class="col-12 col-xl-8">
    <div class="card">
      <div class="card-header">
        <i class="bi bi-list-ul"></i> Supplier List
        <span class="badge badge-soft-secondary ms-1"><?= count($suppliers) ?></span>
        <form class="ms-auto d-flex gap-2" method="get">
          <input type="text" name="q" class="form-control form-control-sm" style="min-width:170px"
                 placeholder="Search supplier..." value="<?= h($q) ?>">
          <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
          <?php if ($q !== ''): ?><a href="suppliers.php" class="btn btn-sm btn-outline-secondary">Clear</a><?php endif; ?>
        </form>
      </div>
      <div class="table-wrap">
        <table class="table table-hover table-striped mb-0">
          <thead>
            <tr>
              <th>#</th>
              <th>Supplier</th>
              <th>Contact</th>
              <th>GST No</th>
              <th class="num">POs</th>
              <th class="num">Stock Value</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$suppliers): ?>
            <tr><td colspan="7"><div class="empty-state"><i class="bi bi-truck"></i>No suppliers found.</div></td></tr>
          <?php else: $i = 0; foreach ($suppliers as $s): $i++; ?>
            <tr>
              <td class="text-muted"><?= $i ?></td>
              <td>
                <div class="fw-semibold"><?= h($s['supplier_name']) ?></div>
                <?php if ($s['address']): ?><div class="help"><?= h($s['address']) ?></div><?php endif; ?>
                <?php if ((int)$s['is_active'] === 0): ?>
                  <span class="badge badge-soft-secondary">Inactive</span>
                <?php endif; ?>
              </td>
              <td>
                <?= h($s['contact_person'] ?: '-') ?>
                <?php if ($s['phone']): ?><div class="help"><i class="bi bi-telephone"></i> <?= h($s['phone']) ?></div><?php endif; ?>
                <?php if ($s['email']): ?><div class="help"><i class="bi bi-envelope"></i> <?= h($s['email']) ?></div><?php endif; ?>
              </td>
              <td class="mono"><?= h($s['gst_no'] ?: '-') ?></td>
              <td class="num"><?= (int)$s['po_count'] ?></td>
              <td class="num"><?= money($s['stock_value'], false) ?></td>
              <td class="text-end text-nowrap">
                <a href="suppliers.php?edit=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                  <i class="bi bi-pencil"></i>
                </a>
                <form method="post" class="d-inline"
                      data-confirm="Delete supplier &quot;<?= h($s['supplier_name']) ?>&quot;? This cannot be undone.">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
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
