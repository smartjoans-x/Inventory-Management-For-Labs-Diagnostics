<?php
/* ============================================================================
   users.php - User Management  (ADMIN ONLY)
   ----------------------------------------------------------------------------
   Admin can create / edit / activate / delete logins and set the role.
   A User role login cannot open this page at all - require_admin() blocks it.
   ============================================================================ */
require_once __DIR__ . '/db.php';
require_admin();

$editing = null;

/** How many active admins exist, excluding one id. */
function active_admins(PDO $pdo, int $exceptId = 0): int
{
    return (int)scalar($pdo, "SELECT COUNT(*) FROM users
                              WHERE role = 'Admin' AND is_active = 1 AND id <> ?", [$exceptId]);
}

/* ------------------------------- ACTIONS --------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post_str('action');

    /* ---------- create / update ---------- */
    if ($action === 'save') {
        $id       = post_int('id');
        $fullName = post_str('full_name');
        $username = strtolower(post_str('username'));
        $role     = post_str('role') === 'Admin' ? 'Admin' : 'User';
        $isActive = post_int('is_active', 1) === 1 ? 1 : 0;
        $pass     = (string)($_POST['password'] ?? '');
        $pass2    = (string)($_POST['password2'] ?? '');

        $errors = [];
        if ($fullName === '') {
            $errors[] = 'Full name is required.';
        }
        if (!preg_match('/^[a-z0-9._-]{3,60}$/', $username)) {
            $errors[] = 'Username must be 3-60 characters, using letters, numbers, dot, dash or underscore only.';
        }
        if ($id === 0 || $pass !== '') {
            if (strlen($pass) < 6) {
                $errors[] = 'Password must be at least 6 characters.';
            }
            if ($pass !== $pass2) {
                $errors[] = 'The two passwords do not match.';
            }
        }

        /* never let the last active admin be removed or demoted */
        if ($id > 0) {
            $target = $pdo->prepare('SELECT * FROM users WHERE id = ?');
            $target->execute([$id]);
            $target = $target->fetch();
            if (!$target) {
                $errors[] = 'User not found.';
            } elseif ($target['role'] === 'Admin'
                      && ($role !== 'Admin' || $isActive === 0)
                      && active_admins($pdo, $id) === 0) {
                $errors[] = 'This is the only active Admin. Create or activate another Admin first.';
            }
            if ($id === user_id() && $isActive === 0) {
                $errors[] = 'You cannot deactivate your own login.';
            }
        }

        if ($errors) {
            flash(implode('<br>', array_map('h', $errors)), 'danger');
            redirect('users.php' . ($id ? '?edit=' . $id : ''));
        }

        try {
            if ($id > 0) {
                if ($pass !== '') {
                    $st = $pdo->prepare('UPDATE users SET full_name=?, username=?, role=?, is_active=?,
                                         password_hash=? WHERE id=?');
                    $st->execute([$fullName, $username, $role, $isActive,
                                  password_hash($pass, PASSWORD_DEFAULT), $id]);
                } else {
                    $st = $pdo->prepare('UPDATE users SET full_name=?, username=?, role=?, is_active=? WHERE id=?');
                    $st->execute([$fullName, $username, $role, $isActive, $id]);
                }
                /* keep the live session in step when an admin edits their own row */
                if ($id === user_id()) {
                    $_SESSION['user']['full_name'] = $fullName;
                    $_SESSION['user']['username']  = $username;
                    $_SESSION['user']['role']      = $role;
                }
                flash('Login <b>' . h($username) . '</b> updated.'
                    . ($pass !== '' ? ' Password changed.' : ''));
            } else {
                $st = $pdo->prepare('INSERT INTO users (full_name, username, password_hash, role, is_active, created_by)
                                     VALUES (?,?,?,?,?,?)');
                $st->execute([$fullName, $username, password_hash($pass, PASSWORD_DEFAULT),
                              $role, $isActive, user_id()]);
                flash('User <b>' . h($fullName) . '</b> created with username <b>' . h($username)
                    . '</b> and role <b>' . h($role) . '</b>.');
            }
        } catch (PDOException $e) {
            if ((int)$e->getCode() === 23000) {
                flash('The username <b>' . h($username) . '</b> is already taken.', 'danger');
            } else {
                flash('Could not save the user: ' . h($e->getMessage()), 'danger');
            }
        }
        redirect('users.php');
    }

    /* ---------- delete ---------- */
    if ($action === 'delete') {
        $id = post_int('id');
        $st = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $st->execute([$id]);
        $u = $st->fetch();

        if (!$u) {
            flash('User not found.', 'warning');
        } elseif ($id === user_id()) {
            flash('You cannot delete the login you are using.', 'warning');
        } elseif ($u['role'] === 'Admin' && active_admins($pdo, $id) === 0) {
            flash('This is the only active Admin and cannot be deleted.', 'warning');
        } else {
            try {
                $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
                flash('Login <b>' . h($u['username']) . '</b> deleted. Records created by this user are kept.');
            } catch (PDOException $e) {
                flash('Could not delete the user: ' . h($e->getMessage()), 'danger');
            }
        }
        redirect('users.php');
    }

    /* ---------- quick activate / deactivate ---------- */
    if ($action === 'toggle') {
        $id = post_int('id');
        $st = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $st->execute([$id]);
        $u = $st->fetch();

        if (!$u) {
            flash('User not found.', 'warning');
        } elseif ($id === user_id()) {
            flash('You cannot deactivate your own login.', 'warning');
        } elseif ((int)$u['is_active'] === 1 && $u['role'] === 'Admin' && active_admins($pdo, $id) === 0) {
            flash('This is the only active Admin and must stay active.', 'warning');
        } else {
            $new = (int)$u['is_active'] === 1 ? 0 : 1;
            $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$new, $id]);
            flash('Login <b>' . h($u['username']) . '</b> is now ' . ($new ? 'active' : 'inactive') . '.');
        }
        redirect('users.php');
    }
}

/* ------------------------------- EDIT LOAD ------------------------------- */
if (get_int('edit') > 0) {
    $st = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([get_int('edit')]);
    $editing = $st->fetch() ?: null;
    if (!$editing) {
        flash('User not found.', 'warning');
        redirect('users.php');
    }
}

/* -------------------------------- LISTING -------------------------------- */
$users = $pdo->query('SELECT u.*, c.full_name AS created_by_name,
                             (SELECT COUNT(*) FROM purchase_orders p WHERE p.created_by = u.id) AS po_count,
                             (SELECT COUNT(*) FROM stock_out o WHERE o.issued_by = u.id) AS out_count
                      FROM users u
                      LEFT JOIN users c ON c.id = u.created_by
                      ORDER BY u.role ASC, u.is_active DESC, u.full_name ASC')->fetchAll();

$adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='Admin' AND is_active=1")->fetchColumn();
$userCount  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='User'  AND is_active=1")->fetchColumn();

$page_title = 'User Management';
$page_icon  = 'bi-people';
$active     = 'users';
require __DIR__ . '/includes/header.php';
?>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="stat">
      <div class="stat-label">Active Admins</div>
      <div class="stat-value"><?= $adminCount ?></div>
      <div class="stat-sub">Full access incl. this page</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat">
      <div class="stat-label">Active Users</div>
      <div class="stat-value"><?= $userCount ?></div>
      <div class="stat-sub">All pages except this one</div>
    </div>
  </div>
  <div class="col-12 col-md-6">
    <div class="stat h-100">
      <div class="stat-label">Role rules</div>
      <div class="help mt-1">
        <b>Admin</b> - dashboard, suppliers, items, purchase orders, stock report, stock out
        <b>and</b> user management.<br>
        <b>User</b> - everything above <b>except</b> user management; a User cannot create logins.
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- ------------------------------ FORM ------------------------------ -->
  <div class="col-12 col-xl-4">
    <div class="card">
      <div class="card-header">
        <i class="bi <?= $editing ? 'bi-pencil-square' : 'bi-person-plus' ?>"></i>
        <?= $editing ? 'Edit Login' : 'Create New Login' ?>
      </div>
      <div class="card-body">
        <form method="post" autocomplete="off">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= $editing ? (int)$editing['id'] : 0 ?>">

          <div class="mb-2">
            <label class="form-label req">Full Name</label>
            <input type="text" name="full_name" class="form-control" maxlength="120" required
                   value="<?= h($editing['full_name'] ?? '') ?>" placeholder="e.g. Ravi Kumar">
          </div>

          <div class="mb-2">
            <label class="form-label req">Username</label>
            <input type="text" name="username" class="form-control mono" maxlength="60" required
                   pattern="[A-Za-z0-9._-]{3,60}"
                   value="<?= h($editing['username'] ?? '') ?>" placeholder="e.g. ravi.k">
            <div class="help">Letters, numbers, dot, dash, underscore. Stored in lower case.</div>
          </div>

          <div class="row g-2">
            <div class="col-12 col-sm-6 mb-2">
              <label class="form-label <?= $editing ? '' : 'req' ?>">Password</label>
              <input type="password" name="password" class="form-control" maxlength="190"
                     <?= $editing ? '' : 'required' ?> placeholder="<?= $editing ? 'leave blank to keep' : 'min 6 characters' ?>">
            </div>
            <div class="col-12 col-sm-6 mb-2">
              <label class="form-label <?= $editing ? '' : 'req' ?>">Confirm Password</label>
              <input type="password" name="password2" class="form-control" maxlength="190"
                     <?= $editing ? '' : 'required' ?> placeholder="repeat password">
            </div>
          </div>

          <div class="mb-2">
            <label class="form-label req">Role</label>
            <div class="border rounded p-2">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="role" id="roleUser" value="User"
                       <?= (!$editing || $editing['role'] === 'User') ? 'checked' : '' ?>>
                <label class="form-check-label" for="roleUser">
                  <b>User</b>
                  <div class="help">Can use every page - dashboard, masters, purchase orders,
                      stock report, stock out. Cannot manage logins.</div>
                </label>
              </div>
              <div class="form-check mt-2">
                <input class="form-check-input" type="radio" name="role" id="roleAdmin" value="Admin"
                       <?= ($editing && $editing['role'] === 'Admin') ? 'checked' : '' ?>>
                <label class="form-check-label" for="roleAdmin">
                  <b>Admin</b>
                  <div class="help">Everything a User can do, plus creating and managing logins.</div>
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
            <div class="help">An inactive login cannot sign in.</div>
          </div>

          <div class="d-flex gap-2">
            <button class="btn btn-primary flex-grow-1">
              <i class="bi bi-save"></i> <?= $editing ? 'Update Login' : 'Create Login' ?>
            </button>
            <?php if ($editing): ?><a href="users.php" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ------------------------------ LIST ------------------------------ -->
  <div class="col-12 col-xl-8">
    <div class="card">
      <div class="card-header">
        <i class="bi bi-people"></i> Logins
        <span class="badge badge-soft-secondary ms-1"><?= count($users) ?></span>
      </div>
      <div class="table-wrap">
        <table class="table table-hover table-striped mb-0">
          <thead>
            <tr>
              <th>Name</th>
              <th>Username</th>
              <th>Role</th>
              <th>Status</th>
              <th>Last Login</th>
              <th class="num">Activity</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($users as $u): $isMe = (int)$u['id'] === user_id(); ?>
            <tr>
              <td>
                <div class="fw-semibold">
                  <?= h($u['full_name']) ?>
                  <?php if ($isMe): ?><span class="badge badge-soft-success">you</span><?php endif; ?>
                </div>
                <?php if ($u['created_by_name']): ?>
                  <div class="help">added by <?= h($u['created_by_name']) ?></div>
                <?php endif; ?>
              </td>
              <td class="mono"><?= h($u['username']) ?></td>
              <td><?= role_badge($u['role']) ?></td>
              <td>
                <?php if ((int)$u['is_active'] === 1): ?>
                  <span class="badge badge-soft-success">Active</span>
                <?php else: ?>
                  <span class="badge badge-soft-secondary">Inactive</span>
                <?php endif; ?>
              </td>
              <td class="text-nowrap">
                <?= $u['last_login'] ? h(date('d-m-Y H:i', strtotime((string)$u['last_login']))) : '<span class="text-muted">never</span>' ?>
              </td>
              <td class="num help">
                <?= (int)$u['po_count'] ?> PO<br><?= (int)$u['out_count'] ?> issues
              </td>
              <td class="text-end text-nowrap">
                <a href="users.php?edit=<?= (int)$u['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit / reset password">
                  <i class="bi bi-pencil"></i>
                </a>
                <?php if (!$isMe): ?>
                  <form method="post" class="d-inline"
                        data-confirm="<?= (int)$u['is_active'] === 1 ? 'Deactivate' : 'Activate' ?> login &quot;<?= h($u['username']) ?>&quot;?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <button class="btn btn-sm btn-outline-secondary"
                            title="<?= (int)$u['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>">
                      <i class="bi <?= (int)$u['is_active'] === 1 ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                    </button>
                  </form>
                  <form method="post" class="d-inline"
                        data-confirm="Delete login &quot;<?= h($u['username']) ?>&quot;? This cannot be undone.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer help">
        Purchase Orders and stock-out entries keep their history even if the login that created
        them is deleted. The last active Admin cannot be deleted, deactivated or demoted.
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
