<?php
/* ============================================================================
   SL Diagnostics - Inventory Management
   includes/auth.php : login session, roles and page guards
   ----------------------------------------------------------------------------
   Roles
     Admin : every page, including User Management (users.php)
     User  : every page EXCEPT User Management
   Put  require_login();  at the top of every protected page
   Put  require_admin();  additionally on admin-only pages
   ============================================================================ */

/* ==========================================================================
   SESSION STATE
   ========================================================================== */

function is_logged_in(): bool
{
    return !empty($_SESSION['user']['id']);
}

/** @return array|null  id, full_name, username, role */
function current_user(): ?array
{
    return is_logged_in() ? $_SESSION['user'] : null;
}

function user_id(): int
{
    return (int)($_SESSION['user']['id'] ?? 0);
}

function user_name(): string
{
    return (string)($_SESSION['user']['full_name'] ?? '');
}

function user_role(): string
{
    return (string)($_SESSION['user']['role'] ?? '');
}

function is_admin(): bool
{
    return user_role() === 'Admin';
}

/* ==========================================================================
   LOGIN / LOGOUT
   ========================================================================== */

/**
 * Verify credentials and open the session.
 * @return array{ok:bool, message:string}
 */
function login_user(PDO $pdo, string $username, string $password): array
{
    $username = trim($username);

    if ($username === '' || $password === '') {
        return ['ok' => false, 'message' => 'Please enter both the username and the password.'];
    }

    /* simple brute-force brake, per session */
    if (!empty($_SESSION['lock_until']) && time() < (int)$_SESSION['lock_until']) {
        $wait = (int)ceil(((int)$_SESSION['lock_until'] - time()) / 60);
        return ['ok' => false, 'message' => 'Too many failed attempts. Please try again in ' . $wait . ' minute(s).'];
    }

    $st = $pdo->prepare('SELECT id, full_name, username, password_hash, role, is_active
                         FROM users WHERE username = ? LIMIT 1');
    $st->execute([$username]);
    $u = $st->fetch();

    $valid = $u && password_verify($password, (string)$u['password_hash']);

    if (!$valid) {
        $_SESSION['fail_count'] = (int)($_SESSION['fail_count'] ?? 0) + 1;
        if ((int)$_SESSION['fail_count'] >= LOGIN_MAX_ATTEMPTS) {
            $_SESSION['lock_until'] = time() + (LOGIN_LOCK_MINUTES * 60);
            $_SESSION['fail_count'] = 0;
            return ['ok' => false, 'message' => 'Too many failed attempts. Login locked for '
                                              . LOGIN_LOCK_MINUTES . ' minutes.'];
        }
        return ['ok' => false, 'message' => 'Invalid username or password.'];
    }

    if ((int)$u['is_active'] !== 1) {
        return ['ok' => false, 'message' => 'This login is inactive. Please contact the administrator.'];
    }

    /* fresh session id, so a pre-login id cannot be reused */
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $_SESSION['user'] = [
        'id'        => (int)$u['id'],
        'full_name' => $u['full_name'],
        'username'  => $u['username'],
        'role'      => $u['role'],
    ];
    $_SESSION['last_seen']  = time();
    $_SESSION['fail_count'] = 0;
    unset($_SESSION['lock_until']);

    $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([(int)$u['id']]);

    return ['ok' => true, 'message' => 'Welcome, ' . $u['full_name'] . '.'];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies') && !headers_sent()) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
                  $p['path'], $p['domain'], (bool)$p['secure'], (bool)$p['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/* ==========================================================================
   GUARDS
   ========================================================================== */

/** Only known page names may be used as a post-login redirect. */
function safe_next(string $next): string
{
    $allowed = [
        'index.php', 'suppliers.php', 'items.php', 'create_po.php', 'po_list.php',
        'view_po.php', 'stock_report.php', 'stock_out.php', 'users.php', 'profile.php',
    ];
    $next = trim($next);
    if ($next === '' || strpos($next, '//') !== false || strpos($next, ':') !== false) {
        return 'index.php';
    }
    $parts = explode('?', $next, 2);
    $file  = basename($parts[0]);
    if (!in_array($file, $allowed, true)) {
        return 'index.php';
    }
    return $file . (isset($parts[1]) && $parts[1] !== '' ? '?' . $parts[1] : '');
}

/**
 * Block the page for anyone who is not logged in, and log out an idle session.
 */
function require_login(): void
{
    $self = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
    $qs   = $_SERVER['QUERY_STRING'] ?? '';
    $next = urlencode($self . ($qs !== '' ? '?' . $qs : ''));

    if (!is_logged_in()) {
        redirect('login.php?next=' . $next);
    }

    /* idle timeout */
    $idle = time() - (int)($_SESSION['last_seen'] ?? time());
    if (SESSION_IDLE_MINUTES > 0 && $idle > (SESSION_IDLE_MINUTES * 60)) {
        logout_user();
        session_start();
        flash('Your session timed out after ' . SESSION_IDLE_MINUTES
            . ' minutes of inactivity. Please log in again.', 'warning');
        redirect('login.php?next=' . $next);
    }
    $_SESSION['last_seen'] = time();
}

/** Admin-only pages. */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        flash('Only an <b>Admin</b> user can open User Management.', 'danger');
        redirect('index.php');
    }
}

/** JSON guard for the ajax endpoints. */
function require_login_ajax(): void
{
    if (!is_logged_in()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Session expired. Please log in again.']);
        exit;
    }
    $_SESSION['last_seen'] = time();
}

/* ==========================================================================
   DISPLAY HELPERS
   ========================================================================== */

function role_badge(?string $role): string
{
    $role = (string)$role;
    $css  = $role === 'Admin' ? 'badge-soft-danger' : 'badge-soft-secondary';
    $icon = $role === 'Admin' ? 'bi-shield-lock' : 'bi-person';
    return '<span class="badge ' . $css . '"><i class="bi ' . $icon . '"></i> ' . h($role ?: 'User') . '</span>';
}

/** Name of a user id, for audit columns. */
function user_label(PDO $pdo, $userId): string
{
    $userId = (int)$userId;
    if ($userId <= 0) {
        return '-';
    }
    static $cache = [];
    if (!array_key_exists($userId, $cache)) {
        $cache[$userId] = (string)scalar($pdo, 'SELECT full_name FROM users WHERE id = ?', [$userId]);
    }
    return $cache[$userId] !== '' ? $cache[$userId] : '-';
}
