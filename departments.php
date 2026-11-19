<?php
/* ============================================================================
   departments.php - Departments and the equipment / analysers inside them
   ----------------------------------------------------------------------------
   Items are mapped to a department and (for reagents) to the machine they run
   on, which is how the monthly budget is grouped.
   ============================================================================ */
require_once __DIR__ . '/db.php';
require_login();

$editDept  = null;
$editEquip = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post_str('action');

    /* ------------------------------ departments ------------------------------ */
    if ($action === 'save_dept') {
        $id   = post_int('id');
        $name = post_str('dept_name');
        $act  = post_int('is_active', 1) === 1 ? 1 : 0;

        if ($name === '') {
            flash('Department name is required.', 'danger');
        } else {
            try {
                if ($id > 0) {
                    $pdo->prepare('UPDATE departments SET dept_name=?, is_active=? WHERE id=?')
                        ->execute([$name, $act, $id]);
                    flash('Department <b>' . h($name) . '</b> updated.');
                } else {
                    $pdo->prepare('INSERT INTO departments (dept_name, is_active) VALUES (?,?)')
                        ->execute([$name, $act]);
                    flash('Department <b>' . h($name) . '</b> added.');
                }
            } catch (PDOException $e) {
                flash((int)$e->getCode() === 23000
                    ? 'A department named <b>' . h($name) . '</b> already exists.'
                    : 'Could not save the department: ' . h($e->getMessage()), 'danger');
            }
        }
        redirect('departments.php');
    }

    if ($action === 'delete_dept') {
        $id    = post_int('id');
        $name  = (string)scalar($pdo, 'SELECT dept_name FROM departments WHERE id = ?', [$id]);
        $items = (int)scalar($pdo, 'SELECT COUNT(*) FROM items WHERE department_id = ?', [$id]);
        $outs  = (int)scalar($pdo, 'SELECT COUNT(*) FROM stock_out WHERE department_id = ?', [$id]);

        if ($items > 0 || $outs > 0) {
            flash('<b>' . h($name) . '</b> is used by ' . $items . ' item(s) and ' . $outs
                . ' issue entry(ies). Mark it inactive instead of deleting it.', 'warning');
        } else {
            $pdo->prepare('DELETE FROM departments WHERE id = ?')->execute([$id]);
            flash('Department <b>' . h($name) . '</b> deleted.');
        }
        redirect('departments.php');
    }

    /* ------------------------------- equipment ------------------------------- */
    if ($action === 'save_equip') {
        $id   = post_int('id');
        $name = post_str('equipment_name');
        $dept = post_int('department_id') ?: null;
        $act  = post_int('is_active', 1) === 1 ? 1 : 0;

        if ($name === '') {
            flash('Equipment name is required.', 'danger');
        } else {
            try {
                if ($id > 0) {
                    $pdo->prepare('UPDATE equipments SET equipment_name=?, department_id=?, is_active=? WHERE id=?')
                        ->execute([$name, $dept, $act, $id]);
                    flash('Equipment <b>' . h($name) . '</b> updated.');
                } else {
                    $pdo->prepare('INSERT INTO equipments (equipment_name, department_id, is_active) VALUES (?,?,?)')
                        ->execute([$name, $dept, $act]);
                    flash('Equipment <b>' . h($name) . '</b> added.');
                }
            } catch (PDOException $e) {
                flash((int)$e->getCode() === 23000
                    ? '<b>' . h($name) . '</b> already exists in that department.'
                    : 'Could not save the equipment: ' . h($e->getMessage()), 'danger');
            }
        }
        redirect('departments.php');
    }

    if ($action === 'delete_equip') {
        $id    = post_int('id');
        $name  = (string)scalar($pdo, 'SELECT equipment_name FROM equipments WHERE id = ?', [$id]);
        $items = (int)scalar($pdo, 'SELECT COUNT(*) FROM items WHERE equipment_id = ?', [$id]);

        if ($items > 0) {
            flash('<b>' . h($name) . '</b> is mapped to ' . $items . ' item(s). '
                . 'Change those items first, or mark the equipment inactive.', 'warning');
        } else {
            $pdo->prepare('DELETE FROM equipments WHERE id = ?')->execute([$id]);
            flash('Equipment <b>' . h($name) . '</b> deleted.');
        }
        redirect('departments.php');
    }
}

if (get_int('edit_dept') > 0) {
    $st = $pdo->prepare('SELECT * FROM departments WHERE id = ?');
    $st->execute([get_int('edit_dept')]);
    $editDept = $st->fetch() ?: null;
}
if (get_int('edit_equip') > 0) {
    $st = $pdo->prepare('SELECT * FROM equipments WHERE id = ?');
    $st->execute([get_int('edit_equip')]);
    $editEquip = $st->fetch() ?: null;
}

$depts = $pdo->query('SELECT d.*,
                        (SELECT COUNT(*) FROM items i WHERE i.department_id = d.id) AS item_count,
                        (SELECT COUNT(*) FROM equipments e WHERE e.department_id = d.id) AS equip_count,
                        (SELECT COALESCE(SUM(o.quantity * o.unit_price),0) FROM stock_out o
                          WHERE o.department_id = d.id
                            AND o.out_date >= DATE_FORMAT(CURDATE(), \'%Y-%m-01\')) AS month_value
                      FROM departments d
                      ORDER BY d.is_active DESC, d.dept_name')->fetchAll();

$equips = $pdo->query('SELECT e.*, d.dept_name,
                        (SELECT COUNT(*) FROM items i WHERE i.equipment_id = e.id) AS item_count
                       FROM equipments e
                       LEFT JOIN departments d ON d.id = e.department_id
                       ORDER BY d.dept_name IS NULL, d.dept_name, e.equipment_name')->fetchAll();

$deptList = all_departments($pdo, false);

$page_title = 'Departments & Equipment';
$page_icon  = 'bi-diagram-3';
$active     = 'departments';
require __DIR__ . '/includes/header.php';
?>

<div class="row g-3">
  <!-- =========================== DEPARTMENTS =========================== -->
  <div class="col-12 col-xl-6">
    <div class="card">
      <div class="card-header">
        <i class="bi bi-building"></i> <?= $editDept ? 'Edit Department' : 'Add Department' ?>
      </div>
      <div class="card-body">
        <form method="post" class="row g-2 align-items-end">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save_dept">
          <input type="hidden" name="id" value="<?= $editDept ? (int)$editDept['id'] : 0 ?>">
          <div class="col-12 col-sm-6">
            <label class="form-label req">Department Name</label>
            <input type="text" name="dept_name" class="form-control" maxlength="80" required
                   value="<?= h($editDept['dept_name'] ?? '') ?>" placeholder="e.g. Biochemistry">
          </div>
          <div class="col-6 col-sm-3">
            <label class="form-label">Status</label>
            <select name="is_active" class="form-select">
              <option value="1" <?= (!$editDept || (int)$editDept['is_active'] === 1) ? 'selected' : '' ?>>Active</option>
              <option value="0" <?= ($editDept && (int)$editDept['is_active'] === 0) ? 'selected' : '' ?>>Inactive</option>
            </select>
          </div>
          <div class="col-6 col-sm-3 d-flex gap-2">
            <button class="btn btn-primary flex-grow-1"><i class="bi bi-save"></i> Save</button>
            <?php if ($editDept): ?><a href="departments.php" class="btn btn-outline-secondary">x</a><?php endif; ?>
          </div>
        </form>
      </div>
      <div class="table-wrap">
        <table class="table table-hover table-striped mb-0">
          <thead>
            <tr><th>Department</th><th class="num">Items</th><th class="num">Equipment</th>
                <th class="num">Issued this month</th><th class="text-end">Action</th></tr>
          </thead>
          <tbody>
          <?php if (!$depts): ?>
            <tr><td colspan="5"><div class="empty-state"><i class="bi bi-building"></i>No departments yet.</div></td></tr>
          <?php else: foreach ($depts as $d): ?>
            <tr>
              <td>
                <span class="fw-semibold"><?= h($d['dept_name']) ?></span>
                <?php if ((int)$d['is_active'] === 0): ?>
                  <span class="badge badge-soft-secondary">Inactive</span>
                <?php endif; ?>
              </td>
              <td class="num"><?= (int)$d['item_count'] ?></td>
              <td class="num"><?= (int)$d['equip_count'] ?></td>
              <td class="num"><?= money($d['month_value'], false) ?></td>
              <td class="text-end text-nowrap">
                <a href="budget.php?dept_id=<?= (int)$d['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Budget">
                  <i class="bi bi-calendar3"></i>
                </a>
                <a href="departments.php?edit_dept=<?= (int)$d['id'] ?>" class="btn btn-sm btn-outline-primary">
                  <i class="bi bi-pencil"></i>
                </a>
                <form method="post" class="d-inline" data-confirm="Delete department &quot;<?= h($d['dept_name']) ?>&quot;?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete_dept">
                  <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ============================ EQUIPMENT ============================ -->
  <div class="col-12 col-xl-6">
    <div class="card">
      <div class="card-header">
        <i class="bi bi-cpu"></i> <?= $editEquip ? 'Edit Equipment' : 'Add Equipment / Analyser' ?>
      </div>
      <div class="card-body">
        <form method="post" class="row g-2 align-items-end">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save_equip">
          <input type="hidden" name="id" value="<?= $editEquip ? (int)$editEquip['id'] : 0 ?>">
          <div class="col-12 col-sm-5">
            <label class="form-label req">Equipment Name</label>
            <input type="text" name="equipment_name" class="form-control" maxlength="100" required
                   value="<?= h($editEquip['equipment_name'] ?? '') ?>" placeholder="e.g. AU480">
          </div>
          <div class="col-12 col-sm-4">
            <label class="form-label">Department</label>
            <select name="department_id" class="form-select">
              <option value="">-- none --</option>
              <?php foreach ($deptList as $d): ?>
                <option value="<?= (int)$d['id'] ?>"
                  <?= (int)($editEquip['department_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>>
                  <?= h($d['dept_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 col-sm-3 d-flex gap-2">
            <button class="btn btn-primary flex-grow-1"><i class="bi bi-save"></i> Save</button>
            <?php if ($editEquip): ?><a href="departments.php" class="btn btn-outline-secondary">x</a><?php endif; ?>
          </div>
        </form>
      </div>
      <div class="table-wrap">
        <table class="table table-hover table-striped mb-0">
          <thead>
            <tr><th>Equipment</th><th>Department</th><th class="num">Items</th><th class="text-end">Action</th></tr>
          </thead>
          <tbody>
          <?php if (!$equips): ?>
            <tr><td colspan="4"><div class="empty-state"><i class="bi bi-cpu"></i>No equipment yet.</div></td></tr>
          <?php else: foreach ($equips as $e): ?>
            <tr>
              <td>
                <span class="fw-semibold"><?= h($e['equipment_name']) ?></span>
                <?php if ((int)$e['is_active'] === 0): ?>
                  <span class="badge badge-soft-secondary">Inactive</span>
                <?php endif; ?>
              </td>
              <td><?= h($e['dept_name'] ?: '-') ?></td>
              <td class="num"><?= (int)$e['item_count'] ?></td>
              <td class="text-end text-nowrap">
                <a href="items.php?dept_id=<?= (int)$e['department_id'] ?>" class="btn btn-sm btn-outline-secondary" title="Items">
                  <i class="bi bi-box-seam"></i>
                </a>
                <a href="departments.php?edit_equip=<?= (int)$e['id'] ?>" class="btn btn-sm btn-outline-primary">
                  <i class="bi bi-pencil"></i>
                </a>
                <form method="post" class="d-inline" data-confirm="Delete equipment &quot;<?= h($e['equipment_name']) ?>&quot;?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete_equip">
                  <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer help">
        Reagents are mapped to the analyser they run on, so the budget can be read
        machine wise - the way the Excel sheet groups Beckman, AU480, Mini Vidas and the rest.
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
