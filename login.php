<?php
/* ============================================================================
   login.php - Sign in
   Desktop: two panel layout (brand panel + form). Mobile: single column card.
   ============================================================================ */
require_once __DIR__ . '/db.php';

$next = safe_next(get_str('next'));

if (is_logged_in()) {
    redirect($next);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $next = safe_next(post_str('next'));
    $res  = login_user($pdo, post_str('username'), (string)($_POST['password'] ?? ''));

    if ($res['ok']) {
        flash(h($res['message']));
        redirect($next);
    }
    flash(h($res['message']), 'danger');
    redirect('login.php?next=' . urlencode($next));
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0b3b5c">
<title>Login &middot; <?= h(APP_NAME) ?></title>
<?php if (has_logo()): ?><link rel="icon" href="<?= h(APP_LOGO) ?>"><?php endif; ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="login-body">

<div class="login-shell">

  <!-- ===================== BRAND PANEL (desktop) ===================== -->
  <section class="login-panel">
    <div class="login-panel-inner">
      <div class="login-logo">
        <?php if (has_logo()): ?>
          <?= logo_img('login-logo-img') ?>
        <?php else: ?>
          <div class="brand-font login-brand"><?= h(APP_BRAND) ?></div>
        <?php endif; ?>
      </div>

      <h1 class="login-headline">Inventory &amp; Stock Management</h1>
      <p class="login-lede">
        Multi-lot reagent tracking, purchase orders and live stock valuation
        for the laboratory store.
      </p>

      <ul class="login-points">
        <li><i class="bi bi-layers"></i>
          <div><b>Lot-wise stock</b><span>Every lot keeps its own expiry, supplier and rate</span></div></li>
        <li><i class="bi bi-file-earmark-check"></i>
          <div><b>Order then receive</b><span>Confirm what actually arrived before stock moves</span></div></li>
        <li><i class="bi bi-cash-stack"></i>
          <div><b>Live valuation</b><span>Stock value from the real purchase rate of each lot</span></div></li>
        <li><i class="bi bi-calendar-x"></i>
          <div><b>Expiry alerts</b><span>FIFO consumption so nothing expires on the shelf</span></div></li>
      </ul>

      <div class="login-panel-foot">
        <?= h(APP_ADDRESS) ?>
      </div>
    </div>
  </section>

  <!-- ========================= SIGN IN FORM ========================= -->
  <section class="login-form-side">
    <div class="login-form-box">

      <div class="login-mobile-brand">
        <?php if (has_logo()): ?>
          <?= logo_img('login-logo-img') ?>
        <?php else: ?>
          <div class="brand-font login-brand"><?= h(APP_BRAND) ?></div>
        <?php endif; ?>
        <div class="login-sub"><?= h(APP_TAGLINE) ?></div>
      </div>

      <h2 class="login-title">Sign in</h2>
      <p class="login-hint">Enter the login given to you by the administrator.</p>

      <?= render_flash() ?>

      <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= h($next) ?>">

        <div class="mb-3">
          <label class="form-label">Username</label>
          <div class="input-group input-group-lg">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" name="username" class="form-control" required autofocus
                   maxlength="60" placeholder="username">
          </div>
        </div>

        <div class="mb-4">
          <label class="form-label">Password</label>
          <div class="input-group input-group-lg">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" name="password" id="pwd" class="form-control" required
                   maxlength="190" placeholder="password">
            <button class="btn btn-outline-secondary" type="button" id="togglePwd" tabindex="-1" title="Show / hide">
              <i class="bi bi-eye" id="pwdIcon"></i>
            </button>
          </div>
        </div>

        <button class="btn btn-primary btn-lg w-100">
          <i class="bi bi-box-arrow-in-right"></i> Sign In
        </button>
      </form>

      <div class="login-foot">
        <i class="bi bi-shield-lock"></i> Authorised users only
        <span class="mx-1">&middot;</span> <?= h(date('d M Y')) ?>
      </div>
    </div>
  </section>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('togglePwd').addEventListener('click', function () {
  var p = document.getElementById('pwd'), i = document.getElementById('pwdIcon');
  if (p.type === 'password') { p.type = 'text';     i.className = 'bi bi-eye-slash'; }
  else                       { p.type = 'password'; i.className = 'bi bi-eye'; }
  p.focus();
});
</script>
</body>
</html>
