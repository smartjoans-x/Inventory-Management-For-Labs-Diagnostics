<?php
/* ============================================================================
   SL Diagnostics - Inventory Management
   db.php : application constants + session + PDO connection + shared includes
   ----------------------------------------------------------------------------
   Every page starts with:   require_once __DIR__ . '/db.php';
   and then (except login.php):   require_login();   /  require_admin();
   ============================================================================ */

/* ------------------------------ APPLICATION ------------------------------- */
define('APP_NAME',    'Diagnostics - Inventory Management');
define('APP_BRAND',   'Diagnostics');
define('APP_TAGLINE', 'Inventory Management');
define('APP_ADDRESS', 'Hyderabad, Telangana');  // printed on the PO header
define('APP_GST',     '');                      // own GSTIN, printed if filled
define('APP_ROOT',    __DIR__);
define('APP_LOGO',    'logo.png');              // sits next to this file; used on
                                                // the PO print, login and sidebar
define('CURRENCY',    '₹');
define('EXPIRY_ALERT_DAYS', 60);                // "near expiry" window

/* ------------------------------- DOCUMENTS --------------------------------
   Three documents, three number series:
     Purchase Order    PO/2026-27/0001   what we ordered
     Goods Receive Note GRN/2026-27/0001 goods received WITHOUT a purchase order
     Issue Voucher     ISS/2026-27/0001  stock issued to a department
   Receiving against a purchase order keeps that order's PO number - the
   received copy of it is the goods receive note.
   --------------------------------------------------------------------------- */
define('DOC_NAME',     'Purchase Order');       // the order document
define('DOC_SHORT',    'PO');
define('DOC_PREFIX',   'PO');                   // PO/2026-27/0001
define('GRN_NAME',     'Goods Receive Note');   // the receiving document
define('GRN_SHORT',    'GRN');
define('GRN_PREFIX',   'GRN');                  // GRN/2026-27/0001 - direct receipt
define('ISSUE_PREFIX', 'ISS');                  // ISS/2026-27/0001 - issue voucher

/* -------------------------------- SECURITY -------------------------------- */
define('SESSION_IDLE_MINUTES', 240);            // auto logout after inactivity (0 = never)
define('LOGIN_MAX_ATTEMPTS',   5);              // failed logins before a lockout
define('LOGIN_LOCK_MINUTES',   5);              // lockout length

/* -------------------------------- DATABASE -------------------------------- */
define('DB_HOST', 'localhost');
define('DB_NAME', 'sl_inventory');
define('DB_USER', 'root');
define('DB_PASS', '1234567');
define('DB_PORT', '3306');

date_default_timezone_set('Asia/Kolkata');

/* --------------------------------- SESSION -------------------------------- */
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, '/', '', $secure, true);
    }
    session_name('SLINVSESS');
    session_start();
}

/* ----------------------------- PDO CONNECTION ----------------------------- */
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    // keep MySQL DATE/NOW() aligned with IST regardless of server timezone
    $pdo->exec("SET time_zone = '+05:30'");
} catch (PDOException $e) {
    http_response_code(500);
    $isAjax = (strpos($_SERVER['REQUEST_URI'] ?? '', '/ajax/') !== false);
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Database connection failed']);
    } else {
        echo '<!doctype html><meta charset="utf-8">'
           . '<div style="font:15px/1.6 system-ui;margin:40px;max-width:640px">'
           . '<h2 style="font-family:\'Times New Roman\',serif">SL Diagnostics - Inventory Management</h2>'
           . '<p><b>Database connection failed.</b></p>'
           . '<p>Check the DB_HOST / DB_NAME / DB_USER / DB_PASS values at the top of '
           . '<code>db.php</code> and make sure <code>database.sql</code> has been imported.</p>'
           . '<pre style="background:#f4f6f9;padding:12px;border-radius:6px;white-space:pre-wrap">'
           . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre></div>';
    }
    exit;
}

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
