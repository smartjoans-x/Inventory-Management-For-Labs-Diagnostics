<?php
/* ============================================================================
   profile.php - My Account : own name + own password  (Admin and User)
   ============================================================================ */
require_once __DIR__ . '/db.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post_str('action');

    /* ---------- own display name ---------- */
    if ($action === 'name') {
        $fullName = post_str('full_name');
        if ($fullName === '') {
            flash('Full name cannot be blank.', 'danger');
        } else {
            $pdo->prepare('UPDATE users SET full_name = ? WHERE id = ?')->execute([$fullName, user_id()]);
            $_SESSION['user']['full_name'] = $fullName;
            flash('Your name has been updated.');
        }
        redirect('profile.php');
    }

    /* ---------- own password ---------- */
    if ($action === 'password') {
        $cur   = (string)($_POST['current_password'] ?? '');
        $new   = (string)($_POST['new_password'] ?? '');
        $new2  = (string)($_POST['new_password2'] ?? '');
        $hash  = (string)scalar($pdo, 'SELECT password_hash FROM users WHERE id = ?', [user_id()]);

        $errors = [];
        if (!password_verify($cur, $hash)) {
            $errors[] = 'Your current password is not correct.';
        }
        if (strlen($new) < 6) {
            $errors[] = 'The new password must be at least 6 characters.';
        }
        if ($new !== $new2) {
            $errors[] = 'The two new passwords do not match.';
        }
        if (!$errors && $new === $cur) {
            $errors[] = 'The new password must be different from the current one.';
        }

        if ($errors) {
            flash(implode('<br>', array_map('h', $errors)), 'danger');
        } else {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), user_id()]);
            flash('Your password has been changed.');
        }
        redirect('profile.php');
    }
}

$st = $pdo->prepare('SELECT u.*, c.full_name AS created_by_name
                     FROM users u LEFT JOIN users c ON c.id = u.created_by
                     WHERE u.id = ?');
$st->execute([user_id()]);
$me = $st->fetch();

$myPO  = (int)scalar($pdo, 'SELECT COUNT(*) FROM purchase_orders WHERE created_by = ?', [user_id()]);
$myOut = (int)scalar($pdo, 'SELECT COUNT(*) FROM stock_out WHERE issued_by = ?', [user_id()]);

$page_title = 'My Account';
$page_icon  = 'bi-person-circle';
$active     = 'profile';
require __DIR__ . '/includes/header.php';
?>

<div class="row g-3">
  <div class="col-12 col-lg-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-person-badge"></i> My Login</div>
      <div class="card-body">
        <table class="table table-sm mb-0">
          <tr><th class="text-muted">Name</th><td><?= h($me['full_name']) ?></td></tr>
          <tr><th class="text-muted">Username</th><td class="mono"><?= h($me['username']) ?></td></tr>
          <tr><th class="text-muted">Role</th><td><?= role_badge($me['role']) ?></td></tr>
          <tr><th class="text-muted">Last login</th>
              <td><?= $me['last_login'] ? h(date('d-m-Y H:i', strtotime((string)$me['last_login']))) : '-' ?></td></tr>
          <tr><th class="text-muted">Account created</th><td><?= dmy(substr((string)$me['created_at'], 0, 10)) ?></td></tr>
          <?php if ($me['created_by_name']): ?>
            <tr><th class="text-muted">Added by</th><td><?= h($me['created_by_name']) ?></td></tr>
          <?php endif; ?>
          <tr><th class="text-muted">My activity</th>
              <td><?= $myPO ?> purchase order(s), <?= $myOut ?> stock issue(s)</td></tr>
        </table>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-pencil"></i> Change My Name</div>
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="name">
          <div class="mb-3">
            <label class="form-label req">Full Name</label>
            <input type="text" name="full_name" class="form-control" maxlength="120" required
                   value="<?= h($me['full_name']) ?>">
            <div class="help">This name is shown on the purchase orders and stock entries you create.</div>
          </div>
          <button class="btn btn-primary w-100"><i class="bi bi-save"></i> Save Name</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-key"></i> Change My Password</div>
      <div class="card-body">
        <form method="post" autocomplete="off">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="password">
          <div class="mb-2">
            <label class="form-label req">Current Password</label>
            <input type="password" name="current_password" class="form-control" required maxlength="190">
          </div>
          <div class="mb-2">
            <label class="form-label req">New Password</label>
            <input type="password" name="new_password" class="form-control" required maxlength="190"
                   placeholder="min 6 characters">
          </div>
          <div class="mb-3">
            <label class="form-label req">Confirm New Password</label>
            <input type="password" name="new_password2" class="form-control" required maxlength="190">
          </div>
          <button class="btn btn-primary w-100"><i class="bi bi-shield-check"></i> Update Password</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
