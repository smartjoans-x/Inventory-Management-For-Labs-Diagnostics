<?php
/* ============================================================================
   includes/header.php
   Set  $page_title  and  $active  before including this file.
   Assumes the page has already called require_login() (or require_admin()).
   ============================================================================ */
if (!defined('APP_NAME')) {
    require_once __DIR__ . '/../db.php';
}

$page_title = isset($page_title) ? $page_title : 'Dashboard';
$active     = isset($active) ? $active : '';
$page_icon  = isset($page_icon) ? $page_icon : 'bi-grid-1x2';

$NAV = [
    'Overview' => [
        ['key' => 'dashboard', 'url' => 'index.php',      'label' => 'Dashboard',        'icon' => 'bi-speedometer2', 'admin' => false],
    ],
    'Masters' => [
        ['key' => 'suppliers',   'url' => 'suppliers.php',   'label' => 'Suppliers',   'icon' => 'bi-truck',      'admin' => false],
        ['key' => 'items',       'url' => 'items.php',       'label' => 'Item Master', 'icon' => 'bi-box-seam',   'admin' => false],
        ['key' => 'departments', 'url' => 'departments.php', 'label' => 'Departments', 'icon' => 'bi-diagram-3',  'admin' => false],
    ],
    'Planning' => [
        ['key' => 'budget', 'url' => 'budget.php', 'label' => 'Monthly Budget', 'icon' => 'bi-calendar3', 'admin' => false],
    ],
    'Purchase' => [
        ['key' => 'create_po',  'url' => 'create_po.php',  'label' => 'Create PO',        'icon' => 'bi-file-earmark-plus', 'admin' => false],
        ['key' => 'po_list',    'url' => 'po_list.php',    'label' => 'PO GRN',  'icon' => 'bi-journal-text',      'admin' => false],
        ['key' => 'direct_grn', 'url' => 'direct_grn.php', 'label' => 'Direct GRN',       'icon' => 'bi-box-arrow-in-down', 'admin' => false],
    ],
    'Stock' => [
        ['key' => 'stock_report', 'url' => 'stock_report.php', 'label' => 'Stock & Valuation', 'icon' => 'bi-clipboard-data', 'admin' => false],
        ['key' => 'stock_out',    'url' => 'stock_out.php',    'label' => 'Stock Out / Issue', 'icon' => 'bi-box-arrow-up',   'admin' => false],
    ],
    'Account' => [
        ['key' => 'users',   'url' => 'users.php',   'label' => 'User Management', 'icon' => 'bi-people',          'admin' => true],
        ['key' => 'profile', 'url' => 'profile.php', 'label' => 'My Account',      'icon' => 'bi-person-circle',   'admin' => false],
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<meta name="theme-color" content="#0b3b5c">
<title><?= h($page_title) ?> &middot; <?= h(APP_NAME) ?></title>
<?php if (has_logo()): ?><link rel="icon" href="<?= h(APP_LOGO) ?>"><?php endif; ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="app-wrap">

  <!-- ===================== SIDEBAR ===================== -->
  <aside class="sidebar offcanvas-lg offcanvas-start no-print" tabindex="-1" id="slSidebar" aria-labelledby="slSidebarLabel">
    <div class="sidebar-brand">
      <div class="d-flex align-items-start justify-content-between">
        <div class="min-w-0">
          <?php if (has_logo()): ?>
            <?= logo_img('sidebar-logo') ?>
            <div class="brand-sub mt-1"><?= h(APP_TAGLINE) ?></div>
          <?php else: ?>
            <div class="brand-font fs-5 text-white" id="slSidebarLabel"><?= h(APP_BRAND) ?></div>
            <div class="brand-sub"><?= h(APP_TAGLINE) ?></div>
          <?php endif; ?>
        </div>
        <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#slSidebar" aria-label="Close"></button>
      </div>
    </div>

    <div class="offcanvas-body sidebar-body">
      <nav class="nav flex-column w-100">
        <?php foreach ($NAV as $group => $links):
            /* hide a whole group if the role can see none of its links */
            $visible = array_filter($links, function ($l) { return !$l['admin'] || is_admin(); });
            if (!$visible) { continue; } ?>
          <div class="nav-group-title"><?= h($group) ?></div>
          <?php foreach ($visible as $l): ?>
            <a class="nav-link<?= $active === $l['key'] ? ' active' : '' ?>" href="<?= h($l['url']) ?>">
              <i class="bi <?= h($l['icon']) ?>"></i><span><?= h($l['label']) ?></span>
              <?php if ($l['admin']): ?><i class="bi bi-shield-lock ms-auto opacity-50" title="Admin only"></i><?php endif; ?>
            </a>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </nav>

      <div class="sidebar-foot">
        <div class="d-flex align-items-center gap-2">
          <span class="user-dot"><?= h(strtoupper(substr(user_name() !== '' ? user_name() : 'U', 0, 1))) ?></span>
          <div class="min-w-0">
            <div class="small text-white text-truncate"><?= h(user_name()) ?></div>
            <div class="small opacity-75"><?= h(user_role()) ?></div>
          </div>
          <a href="logout.php" class="btn btn-sm btn-outline-light ms-auto" title="Logout"
             data-confirm="Log out of the inventory system?">
            <i class="bi bi-box-arrow-right"></i>
          </a>
        </div>
        <div class="small opacity-75 mt-2">FY <?= h(fy_label()) ?> &middot; <?= h(date('d-m-Y')) ?></div>
      </div>
    </div>
  </aside>

  <!-- ===================== MAIN ===================== -->
  <div class="app-main">

    <header class="topbar no-print">
      <button class="btn btn-sm btn-light d-lg-none me-2" type="button"
              data-bs-toggle="offcanvas" data-bs-target="#slSidebar" aria-controls="slSidebar" aria-label="Menu">
        <i class="bi bi-list fs-5"></i>
      </button>

      <div class="topbar-title">
        <div class="brand-font topbar-brand"><?= h(APP_NAME) ?></div>
        <div class="topbar-page"><i class="bi <?= h($page_icon) ?>"></i> <?= h($page_title) ?></div>
      </div>

      <div class="ms-auto d-flex align-items-center gap-2">
        <a href="create_po.php" class="btn btn-sm btn-primary">
          <i class="bi bi-plus-lg"></i><span class="d-none d-sm-inline ms-1">New PO</span>
        </a>
        <a href="stock_out.php" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-box-arrow-up"></i><span class="d-none d-sm-inline ms-1">Issue</span>
        </a>

        <div class="dropdown">
          <button class="btn btn-sm btn-light d-flex align-items-center gap-2 dropdown-toggle"
                  type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="user-dot sm"><?= h(strtoupper(substr(user_name() !== '' ? user_name() : 'U', 0, 1))) ?></span>
            <span class="d-none d-md-inline"><?= h(user_name()) ?></span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li class="dropdown-header">
              <?= h(user_name()) ?><br>
              <?= role_badge(user_role()) ?>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-circle"></i> My Account</a></li>
            <?php if (is_admin()): ?>
              <li><a class="dropdown-item" href="users.php"><i class="bi bi-people"></i> User Management</a></li>
            <?php endif; ?>
            <li><hr class="dropdown-divider"></li>
            <li>
              <a class="dropdown-item text-danger" href="logout.php"
                 data-confirm="Log out of the inventory system?">
                <i class="bi bi-box-arrow-right"></i> Logout
              </a>
            </li>
          </ul>
        </div>
      </div>
    </header>

    <main class="app-content">
      <?= render_flash() ?>
