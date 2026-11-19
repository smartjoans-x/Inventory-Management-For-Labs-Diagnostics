<?php
/* ============================================================================
    Inventory Management
   includes/functions.php : shared helpers + all stock business logic
   ============================================================================ */

define('GENERAL_LOT', 'GENERAL');   // reserved lot for items with has_lot = 0

/* ==========================================================================
   OUTPUT / FORMAT HELPERS
   ========================================================================== */

/** HTML escape. */
function h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/** 12345.5 -> "₹ 12,345.50" */
function money($v, bool $symbol = true): string
{
    $s = number_format((float)$v, 2);
    return $symbol ? CURRENCY . ' ' . $s : $s;
}

/** 10.00 -> "10" , 2.50 -> "2.5" */
function qty_str($v): string
{
    $s = number_format((float)$v, 2, '.', '');
    if (strpos($s, '.') !== false) {
        $s = rtrim(rtrim($s, '0'), '.');
    }
    return $s === '' || $s === '-' ? '0' : $s;
}

/** 2026-11-19 -> 19-11-2026 */
function dmy(?string $date): string
{
    if (empty($date) || $date === '0000-00-00') {
        return '-';
    }
    $ts = strtotime($date);
    return $ts ? date('d-m-Y', $ts) : '-';
}

function today(): string
{
    return date('Y-m-d');
}

/** Is logo.png actually present next to db.php? */
function has_logo(): bool
{
    static $found = null;
    if ($found === null) {
        $found = is_file(APP_ROOT . '/' . APP_LOGO);
    }
    return $found;
}

/**
 * <img> for the logo, or '' when the file is missing so nothing breaks.
 * A cache-busting ?v= keeps a replaced logo from showing stale.
 */
function logo_img(string $class = '', string $style = ''): string
{
    if (!has_logo()) {
        return '';
    }
    $v = (int)@filemtime(APP_ROOT . '/' . APP_LOGO);
    return '<img src="' . h(APP_LOGO) . '?v=' . $v . '" alt="' . h(APP_BRAND) . '"'
         . ($class !== '' ? ' class="' . h($class) . '"' : '')
         . ($style !== '' ? ' style="' . h($style) . '"' : '') . '>';
}

/** Lot display: NULL / empty / GENERAL all read as "GENERAL". */
function lot_label(?string $lot): string
{
    $lot = trim((string)$lot);
    return $lot === '' ? GENERAL_LOT : $lot;
}

/**
 * Indian numbering amount in words - used on the printed purchase order.
 * 125400.50 -> "one lakh twenty five thousand four hundred rupees and fifty paise only"
 */
function amount_in_words(float $amount): string
{
    $ones = [
        0 => '', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five',
        6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine', 10 => 'ten',
        11 => 'eleven', 12 => 'twelve', 13 => 'thirteen', 14 => 'fourteen',
        15 => 'fifteen', 16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen',
        19 => 'nineteen',
    ];
    $tens = [
        2 => 'twenty', 3 => 'thirty', 4 => 'forty', 5 => 'fifty',
        6 => 'sixty', 7 => 'seventy', 8 => 'eighty', 9 => 'ninety',
    ];

    $two = function (int $n) use ($ones, $tens): string {
        if ($n <= 0) {
            return '';
        }
        if ($n < 20) {
            return $ones[$n];
        }
        $t = intdiv($n, 10);
        $o = $n % 10;
        return trim($tens[$t] . ($o ? ' ' . $ones[$o] : ''));
    };

    $three = function (int $n) use ($ones, $two): string {
        $h = intdiv($n, 100);
        $r = $n % 100;
        $s = $h ? $ones[$h] . ' hundred' : '';
        if ($r) {
            $s = trim($s . ($s !== '' ? ' and ' : '') . $two($r));
        }
        return $s;
    };

    $amount = round($amount, 2);
    $rupees = (int)floor($amount);
    $paise  = (int)round(($amount - $rupees) * 100);

    if ($rupees === 0 && $paise === 0) {
        return 'zero rupees only';
    }

    $crore  = intdiv($rupees, 10000000);
    $lakh   = intdiv($rupees % 10000000, 100000);
    $thou   = intdiv($rupees % 100000, 1000);
    $rest   = $rupees % 1000;

    $parts = [];
    if ($crore) {
        $parts[] = $three($crore) . ' crore';
    }
    if ($lakh) {
        $parts[] = $three($lakh) . ' lakh';
    }
    if ($thou) {
        $parts[] = $three($thou) . ' thousand';
    }
    if ($rest) {
        $parts[] = $three($rest);
    }

    $words = trim(implode(' ', $parts)) . ' rupees';
    if ($paise > 0) {
        $words .= ' and ' . $two($paise) . ' paise';
    }
    return preg_replace('/\s+/', ' ', $words . ' only');
}

/* ==========================================================================
   REQUEST HELPERS
   ========================================================================== */

function post_str(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_scalar($v) ? trim((string)$v) : $default;
}

function post_float(string $key, float $default = 0.0): float
{
    $v = $_POST[$key] ?? $default;
    return is_scalar($v) ? (float)$v : $default;
}

function post_int(string $key, int $default = 0): int
{
    $v = $_POST[$key] ?? $default;
    return is_scalar($v) ? (int)$v : $default;
}

function get_int(string $key, int $default = 0): int
{
    $v = $_GET[$key] ?? $default;
    return is_scalar($v) ? (int)$v : $default;
}

function get_str(string $key, string $default = ''): string
{
    $v = $_GET[$key] ?? $default;
    return is_scalar($v) ? trim((string)$v) : $default;
}

/** Empty string -> NULL (for nullable DATE / VARCHAR columns). */
function nullify(?string $v): ?string
{
    $v = trim((string)$v);
    return $v === '' ? null : $v;
}

/** Validate a YYYY-MM-DD string, otherwise NULL. */
function clean_date(?string $v): ?string
{
    $v = trim((string)$v);
    if ($v === '') {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : null;
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/* ==========================================================================
   CSRF
   ========================================================================== */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $t = $_POST['csrf_token'] ?? '';
    if (!is_string($t) || $t === '' || !hash_equals(csrf_token(), $t)) {
        http_response_code(400);
        exit('Security token mismatch. Please reload the page and try again.');
    }
}

/* ==========================================================================
   FLASH MESSAGES
   ========================================================================== */

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'][] = ['msg' => $message, 'type' => $type];
}

function render_flash(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $icons = [
        'success' => 'bi-check-circle-fill',
        'danger'  => 'bi-exclamation-octagon-fill',
        'warning' => 'bi-exclamation-triangle-fill',
        'info'    => 'bi-info-circle-fill',
    ];
    $out = '';
    foreach ($_SESSION['flash'] as $f) {
        $type = isset($icons[$f['type']]) ? $f['type'] : 'info';
        $out .= '<div class="alert alert-' . $type . ' alert-dismissible fade show d-flex align-items-start gap-2 no-print" role="alert">'
              . '<i class="bi ' . $icons[$type] . ' mt-1"></i><div class="flex-grow-1">' . $f['msg'] . '</div>'
              . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
    unset($_SESSION['flash']);
    return $out;
}

/* ==========================================================================
   EXPIRY STATUS
   ========================================================================== */

/**
 * @return array{label:string, css:string, days:?int}
 */
function expiry_meta(?string $expiry): array
{
    if (empty($expiry)) {
        return ['label' => 'No Expiry', 'css' => 'secondary', 'days' => null];
    }
    $ts = strtotime($expiry);
    if (!$ts) {
        return ['label' => 'No Expiry', 'css' => 'secondary', 'days' => null];
    }
    $days = (int)floor(($ts - strtotime(today())) / 86400);
    if ($days < 0) {
        return ['label' => 'Expired', 'css' => 'danger', 'days' => $days];
    }
    if ($days <= 30) {
        return ['label' => $days . ' day(s) left', 'css' => 'danger', 'days' => $days];
    }
    if ($days <= EXPIRY_ALERT_DAYS) {
        return ['label' => $days . ' day(s) left', 'css' => 'warning', 'days' => $days];
    }
    return ['label' => 'Valid', 'css' => 'success', 'days' => $days];
}

/* ==========================================================================
   LOOKUP HELPERS
   ========================================================================== */

function all_items(PDO $pdo, bool $activeOnly = true): array
{
    $sql = 'SELECT id, item_code, item_name, category, unit, has_lot, min_stock_alert, is_active
            FROM items ' . ($activeOnly ? 'WHERE is_active = 1 ' : '') . 'ORDER BY item_name';
    return $pdo->query($sql)->fetchAll();
}

function all_suppliers(PDO $pdo, bool $activeOnly = true): array
{
    $sql = 'SELECT id, supplier_name, contact_person, phone, gst_no
            FROM suppliers ' . ($activeOnly ? 'WHERE is_active = 1 ' : '') . 'ORDER BY supplier_name';
    return $pdo->query($sql)->fetchAll();
}

function all_departments(PDO $pdo, bool $activeOnly = true): array
{
    $sql = 'SELECT id, dept_name FROM departments '
         . ($activeOnly ? 'WHERE is_active = 1 ' : '') . 'ORDER BY dept_name';
    return $pdo->query($sql)->fetchAll();
}

function all_equipments(PDO $pdo, int $deptId = 0): array
{
    $sql = 'SELECT e.id, e.equipment_name, e.department_id, d.dept_name
            FROM equipments e
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE e.is_active = 1';
    $args = [];
    if ($deptId > 0) {
        $sql   .= ' AND e.department_id = ?';
        $args[] = $deptId;
    }
    $sql .= ' ORDER BY d.dept_name, e.equipment_name';
    $st = $pdo->prepare($sql);
    $st->execute($args);
    return $st->fetchAll();
}

function all_categories(PDO $pdo): array
{
    return $pdo->query("SELECT DISTINCT category FROM items
                        WHERE category IS NOT NULL AND category <> ''
                        ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
}

function scalar(PDO $pdo, string $sql, array $params = [])
{
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchColumn();
}

/** Live stock quantity of one item across all batches. */
function item_stock_qty(PDO $pdo, int $itemId): float
{
    return (float)scalar($pdo, 'SELECT COALESCE(SUM(quantity_available),0)
                                FROM item_batches WHERE item_id = ?', [$itemId]);
}

/* ==========================================================================
   MONTHS  +  MONTHLY BUDGET ENGINE
   --------------------------------------------------------------------------
   The lab budget sheet reads, per item and per month:
       opening  ->  purchase  ->  issues  ->  closing  ->  required next month
   Opening of a month is the closing of the month before it, exactly as in the
   Excel sheet, so each closed month rolls into the next one.
   ========================================================================== */

function month_key(?string $date = null): string
{
    $ts = $date ? (strtotime($date) ?: time()) : time();
    return date('Y-m', $ts);
}

function month_valid(string $key): bool
{
    return (bool)preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $key);
}

function month_label(string $key): string
{
    $ts = strtotime($key . '-01');
    return $ts ? date('F Y', $ts) : $key;
}

function month_short(string $key): string
{
    $ts = strtotime($key . '-01');
    return $ts ? date('M y', $ts) : $key;
}

/** @return array{0:string,1:string} first and last date of the month */
function month_bounds(string $key): array
{
    $start = $key . '-01';
    $end   = date('Y-m-t', strtotime($start));
    return [$start, $end];
}

function month_add(string $key, int $n): string
{
    return date('Y-m', strtotime($key . '-01 ' . ($n >= 0 ? '+' : '-') . abs($n) . ' month'));
}

/** Months worth offering in the picker: every stored month + the last 12. */
function month_options(PDO $pdo): array
{
    $months = $pdo->query('SELECT DISTINCT month_key FROM stock_month_summary
                           ORDER BY month_key DESC')->fetchAll(PDO::FETCH_COLUMN);
    $cur = month_key();
    for ($i = 0; $i < 13; $i++) {
        $months[] = month_add($cur, -$i);
    }
    $months = array_values(array_unique(array_filter($months, 'month_valid')));
    rsort($months);
    return $months;
}

/**
 * Figures for one month, per item.
 * Imported months are returned as they were imported; any other month is
 * calculated from the system's own purchase receipts and stock-out entries.
 *
 * @return array<int,array> item_id => row
 */
function month_figures(PDO $pdo, string $key): array
{
    $stored = [];
    $st = $pdo->prepare('SELECT * FROM stock_month_summary WHERE month_key = ?');
    $st->execute([$key]);
    foreach ($st->fetchAll() as $r) {
        $stored[(int)$r['item_id']] = $r;
    }

    $imported = false;
    foreach ($stored as $r) {
        if ($r['source'] === 'Imported') {
            $imported = true;
            break;
        }
    }
    if ($imported) {
        $out = [];
        foreach ($stored as $id => $r) {
            $out[$id] = [
                'op_qty'  => (float)$r['op_qty'],  'op_value'  => (float)$r['op_value'],
                'pur_qty' => (float)$r['pur_qty'], 'pur_value' => (float)$r['pur_value'],
                'iss_qty' => (float)$r['iss_qty'], 'iss_value' => (float)$r['iss_value'],
                'cl_qty'  => (float)$r['cl_qty'],  'cl_value'  => (float)$r['cl_value'],
                'reqd_qty'   => (float)$r['reqd_qty'],
                'reqd_rate'  => (float)$r['reqd_rate'],
                'reqd_value' => (float)$r['reqd_value'],
                'source'     => 'Imported',
            ];
        }
        return $out;
    }

    [$start, $end] = month_bounds($key);
    $rows = [];

    $blank = [
        'op_qty' => 0.0, 'op_value' => 0.0, 'pur_qty' => 0.0, 'pur_value' => 0.0,
        'iss_qty' => 0.0, 'iss_value' => 0.0, 'cl_qty' => 0.0, 'cl_value' => 0.0,
        'reqd_qty' => 0.0, 'reqd_rate' => 0.0, 'reqd_value' => 0.0, 'source' => 'System',
    ];

    /* opening = closing of the previous month */
    $st = $pdo->prepare('SELECT item_id, cl_qty, cl_value FROM stock_month_summary WHERE month_key = ?');
    $st->execute([month_add($key, -1)]);
    foreach ($st->fetchAll() as $r) {
        $id = (int)$r['item_id'];
        $rows[$id] = $blank;
        $rows[$id]['op_qty']   = (float)$r['cl_qty'];
        $rows[$id]['op_value'] = (float)$r['cl_value'];
    }

    /* purchases actually received in the month */
    $st = $pdo->prepare("SELECT r.item_id, SUM(r.quantity) q, SUM(r.total_amount) v
                         FROM po_receipts r
                         JOIN purchase_orders po ON po.id = r.po_id
                         WHERE po.status = 'Final'
                           AND COALESCE(po.receive_date, po.po_date) BETWEEN ? AND ?
                         GROUP BY r.item_id");
    $st->execute([$start, $end]);
    foreach ($st->fetchAll() as $r) {
        $id = (int)$r['item_id'];
        $rows[$id] = $rows[$id] ?? $blank;
        $rows[$id]['pur_qty']   = (float)$r['q'];
        $rows[$id]['pur_value'] = (float)$r['v'];
    }

    /* issues of the month */
    $st = $pdo->prepare('SELECT item_id, SUM(quantity) q, SUM(quantity * unit_price) v
                         FROM stock_out WHERE out_date BETWEEN ? AND ?
                         GROUP BY item_id');
    $st->execute([$start, $end]);
    foreach ($st->fetchAll() as $r) {
        $id = (int)$r['item_id'];
        $rows[$id] = $rows[$id] ?? $blank;
        $rows[$id]['iss_qty']   = (float)$r['q'];
        $rows[$id]['iss_value'] = (float)$r['v'];
    }

    /* closing = opening + purchases - issues, and keep any saved requirement */
    foreach ($rows as $id => $r) {
        $rows[$id]['cl_qty']   = round($r['op_qty'] + $r['pur_qty'] - $r['iss_qty'], 2);
        $rows[$id]['cl_value'] = round($r['op_value'] + $r['pur_value'] - $r['iss_value'], 2);
        if (isset($stored[$id])) {
            $rows[$id]['reqd_qty']   = (float)$stored[$id]['reqd_qty'];
            $rows[$id]['reqd_rate']  = (float)$stored[$id]['reqd_rate'];
            $rows[$id]['reqd_value'] = (float)$stored[$id]['reqd_value'];
        }
    }
    return $rows;
}

/**
 * Suggested requirement for the next month:
 * cover `coverage` months of this month's consumption, minus what is left.
 */
function suggest_reqd(float $issQty, float $clQty, float $coverage = 2.0): float
{
    $need = ($issQty * $coverage) - $clQty;
    return $need > 0 ? ceil($need * 100) / 100 : 0.0;
}

/** Write one item's month row (used by Save requirement / Close month). */
function save_month_row(PDO $pdo, string $key, int $itemId, array $f, ?string $reqdMonth = null,
                        string $source = 'System', ?int $userId = null): void
{
    $sql = 'INSERT INTO stock_month_summary
              (month_key, reqd_month, item_id, op_qty, op_value, pur_qty, pur_value,
               iss_qty, iss_value, cl_qty, cl_value, reqd_qty, reqd_rate, reqd_value,
               source, updated_by)
            VALUES (:mk,:rm,:item,:opq,:opv,:puq,:puv,:isq,:isv,:clq,:clv,:rqq,:rqr,:rqv,:src,:usr)
            ON DUPLICATE KEY UPDATE
               reqd_month = VALUES(reqd_month),
               op_qty  = VALUES(op_qty),  op_value  = VALUES(op_value),
               pur_qty = VALUES(pur_qty), pur_value = VALUES(pur_value),
               iss_qty = VALUES(iss_qty), iss_value = VALUES(iss_value),
               cl_qty  = VALUES(cl_qty),  cl_value  = VALUES(cl_value),
               reqd_qty = VALUES(reqd_qty), reqd_rate = VALUES(reqd_rate),
               reqd_value = VALUES(reqd_value), updated_by = VALUES(updated_by)';
    $pdo->prepare($sql)->execute([
        ':mk'   => $key,
        ':rm'   => $reqdMonth ?? month_add($key, 1),
        ':item' => $itemId,
        ':opq'  => $f['op_qty'],  ':opv' => $f['op_value'],
        ':puq'  => $f['pur_qty'], ':puv' => $f['pur_value'],
        ':isq'  => $f['iss_qty'], ':isv' => $f['iss_value'],
        ':clq'  => $f['cl_qty'],  ':clv' => $f['cl_value'],
        ':rqq'  => $f['reqd_qty'], ':rqr' => $f['reqd_rate'], ':rqv' => $f['reqd_value'],
        ':src'  => $source,
        ':usr'  => $userId > 0 ? $userId : null,
    ]);
}

/* ==========================================================================
   PURCHASE ORDER NUMBERING  (PO/2026-27/0001)
   ========================================================================== */

/** Indian financial year label for a given date: 2026-27 */
function fy_label(?string $date = null): string
{
    $ts    = $date ? (strtotime($date) ?: time()) : time();
    $year  = (int)date('Y', $ts);
    $month = (int)date('n', $ts);
    $start = ($month >= 4) ? $year : $year - 1;
    return $start . '-' . substr((string)($start + 1), -2);
}

function next_po_number(PDO $pdo, ?string $poDate = null): string
{
    $fy     = fy_label($poDate);
    $prefix = DOC_PREFIX . '/' . $fy . '/';
    $last   = scalar($pdo, 'SELECT po_number FROM purchase_orders
                            WHERE po_number LIKE ? ORDER BY id DESC LIMIT 1', [$prefix . '%']);
    $seq = 0;
    if ($last) {
        $seq = (int)substr((string)$last, strrpos((string)$last, '/') + 1);
    }
    do {
        $seq++;
        $candidate = $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
        $exists    = (int)scalar($pdo, 'SELECT COUNT(*) FROM purchase_orders WHERE po_number = ?', [$candidate]);
    } while ($exists > 0 && $seq < 100000);

    return $candidate;
}

/**
 * Next number for a DIRECT goods receive note - GRN/2026-27/0001.
 * Used when stock arrives without a purchase order behind it.
 */
function next_grn_number(PDO $pdo, ?string $date = null): string
{
    $fy     = fy_label($date);
    $prefix = GRN_PREFIX . '/' . $fy . '/';
    $last   = scalar($pdo, 'SELECT po_number FROM purchase_orders
                            WHERE po_number LIKE ? ORDER BY id DESC LIMIT 1', [$prefix . '%']);
    $seq = 0;
    if ($last) {
        $seq = (int)substr((string)$last, strrpos((string)$last, '/') + 1);
    }
    do {
        $seq++;
        $candidate = $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
        $exists    = (int)scalar($pdo, 'SELECT COUNT(*) FROM purchase_orders WHERE po_number = ?', [$candidate]);
    } while ($exists > 0 && $seq < 100000);

    return $candidate;
}

/**
 * Next issue voucher number - ISS/2026-27/0001.
 * Several stock_out rows share one voucher number: that is what makes a
 * voucher a single slip with many items on it.
 */
function next_voucher_no(PDO $pdo, ?string $date = null): string
{
    $fy     = fy_label($date);
    $prefix = ISSUE_PREFIX . '/' . $fy . '/';
    $last   = scalar($pdo, 'SELECT voucher_no FROM stock_out
                            WHERE voucher_no LIKE ? ORDER BY id DESC LIMIT 1', [$prefix . '%']);
    $seq = 0;
    if ($last) {
        $seq = (int)substr((string)$last, strrpos((string)$last, '/') + 1);
    }
    do {
        $seq++;
        $candidate = $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
        $exists    = (int)scalar($pdo, 'SELECT COUNT(*) FROM stock_out WHERE voucher_no = ?', [$candidate]);
    } while ($exists > 0 && $seq < 100000);

    return $candidate;
}

/** Re-sum po_items into purchase_orders.grand_total. */
function recalc_po_total(PDO $pdo, int $poId): float
{
    $total = (float)scalar($pdo, 'SELECT COALESCE(SUM(total_amount),0) FROM po_items WHERE po_id = ?', [$poId]);
    $pdo->prepare('UPDATE purchase_orders SET grand_total = ? WHERE id = ?')
        ->execute([$total, $poId]);
    return $total;
}

/** Re-sum po_receipts into purchase_orders.received_total. */
function recalc_received_total(PDO $pdo, int $poId): float
{
    $total = (float)scalar($pdo, 'SELECT COALESCE(SUM(total_amount),0) FROM po_receipts WHERE po_id = ?', [$poId]);
    $pdo->prepare('UPDATE purchase_orders SET received_total = ? WHERE id = ?')
        ->execute([$total, $poId]);
    return $total;
}

/**
 * Ordered vs received comparison for one PO.
 * @return array{ordered:float, received:float, diff:float, pct:float}
 */
function po_variance(array $po): array
{
    $ordered  = (float)($po['grand_total'] ?? 0);
    $received = (float)($po['received_total'] ?? 0);
    $diff     = $received - $ordered;
    return [
        'ordered'  => $ordered,
        'received' => $received,
        'diff'     => $diff,
        'pct'      => $ordered > 0 ? ($diff / $ordered) * 100 : 0.0,
    ];
}

/** Coloured +/- label for a difference. */
function diff_badge(float $diff, bool $money = true): string
{
    if (abs($diff) < 0.005) {
        return '<span class="badge badge-soft-success">as ordered</span>';
    }
    $css  = $diff > 0 ? 'badge-soft-danger' : 'badge-soft-warning';
    $sign = $diff > 0 ? '+' : '-';
    $val  = $money ? money(abs($diff), false) : qty_str(abs($diff));
    return '<span class="badge ' . $css . '">' . $sign . ' ' . $val . '</span>';
}

/* ==========================================================================
   *** CORE TRIGGER ***  DRAFT -> FINAL : push received stock into item_batches
   --------------------------------------------------------------------------
   Draft POs never touch live stock. Stock is created from po_receipts - the
   quantities, rates and lot numbers confirmed on the Receive screen - never
   from the ordered lines, so a short delivery or a hiked price is posted
   exactly as it arrived.
   This function is the single place where purchased quantity + rate becomes
   stock, so it cannot be bypassed. Safe against double submission: the status
   flip is guarded by "WHERE status = 'Draft'" + a row lock, so a second call
   can never add the same quantity twice.
   ========================================================================== */
function finalize_po(PDO $pdo, int $poId, ?int $userId = null): array
{
    $ownTx = !$pdo->inTransaction();
    if ($ownTx) {
        $pdo->beginTransaction();
    }

    try {
        /* lock the PO row */
        $st = $pdo->prepare('SELECT * FROM purchase_orders WHERE id = ? FOR UPDATE');
        $st->execute([$poId]);
        $po = $st->fetch();

        if (!$po) {
            throw new RuntimeException('Purchase Order not found.');
        }
        if ($po['status'] === 'Final') {
            throw new RuntimeException('This purchase order is already marked Final.');
        }

        /* CONFIRMED RECEIPT lines + item master flags */
        $st = $pdo->prepare('SELECT r.*, i.has_lot, i.item_name
                             FROM po_receipts r
                             JOIN items i ON i.id = r.item_id
                             WHERE r.po_id = ? AND r.quantity > 0
                             ORDER BY r.po_item_id, r.id');
        $st->execute([$poId]);
        $lines = $st->fetchAll();

        if (!$lines) {
            throw new RuntimeException('Nothing has been confirmed as received yet. '
                . 'Open Receive (GRN), enter the quantities actually delivered, then finalize.');
        }

        /* flip the status FIRST - guarded, so concurrent calls cannot double post */
        $upd = $pdo->prepare("UPDATE purchase_orders
                              SET status = 'Final', finalized_at = NOW(), finalized_by = ?
                              WHERE id = ? AND status = 'Draft'");
        $upd->execute([$userId > 0 ? $userId : null, $poId]);
        if ($upd->rowCount() !== 1) {
            throw new RuntimeException('This purchase order was already finalized by another user.');
        }

        $supplierId   = (int)$po['supplier_id'];
        $purchaseDate = $po['po_date'];
        $newBatches   = 0;
        $mergedBatches = 0;

        foreach ($lines as $ln) {
            $itemId = (int)$ln['item_id'];
            $qty    = (float)$ln['quantity'];
            $rate   = (float)$ln['unit_rate'];
            if ($qty <= 0) {
                continue;
            }

            $lot    = (int)$ln['has_lot'] === 1 ? lot_label($ln['lot_number']) : GENERAL_LOT;
            $expiry = (int)$ln['has_lot'] === 1 ? clean_date($ln['expiry_date']) : null;

            /* Merge into an existing batch only when item + supplier + lot +
               expiry + rate are all identical, so valuation stays exact. */
            $sql = 'SELECT id FROM item_batches
                    WHERE item_id = :item AND lot_number = :lot AND unit_price = :rate
                      AND ' . ($supplierId > 0 ? 'supplier_id = :sup' : 'supplier_id IS NULL') . '
                      AND ' . ($expiry === null ? 'expiry_date IS NULL' : 'expiry_date = :exp') . '
                    LIMIT 1 FOR UPDATE';
            $find = $pdo->prepare($sql);
            $find->bindValue(':item', $itemId, PDO::PARAM_INT);
            $find->bindValue(':lot', $lot);
            $find->bindValue(':rate', number_format($rate, 2, '.', ''));
            if ($supplierId > 0) {
                $find->bindValue(':sup', $supplierId, PDO::PARAM_INT);
            }
            if ($expiry !== null) {
                $find->bindValue(':exp', $expiry);
            }
            $find->execute();
            $batchId = $find->fetchColumn();

            if ($batchId) {
                $batchId = (int)$batchId;
                $pdo->prepare('UPDATE item_batches
                               SET quantity_available = quantity_available + ?,
                                   purchase_date = ?, po_id = ?
                               WHERE id = ?')
                    ->execute([$qty, $purchaseDate, $poId, $batchId]);
                $mergedBatches++;
            } else {
                $pdo->prepare('INSERT INTO item_batches
                                 (item_id, supplier_id, lot_number, expiry_date,
                                  unit_price, quantity_available, purchase_date, po_id)
                               VALUES (?,?,?,?,?,?,?,?)')
                    ->execute([
                        $itemId,
                        $supplierId > 0 ? $supplierId : null,
                        $lot,
                        $expiry,
                        $rate,
                        $qty,
                        $purchaseDate,
                        $poId,
                    ]);
                $batchId = (int)$pdo->lastInsertId();
                $newBatches++;
            }

            /* keep the receipt row pointing at the stock lot it created */
            $pdo->prepare('UPDATE po_receipts SET batch_id = ? WHERE id = ?')
                ->execute([$batchId, (int)$ln['id']]);
        }

        recalc_po_total($pdo, $poId);
        recalc_received_total($pdo, $poId);

        if ($ownTx) {
            $pdo->commit();
        }

        return [
            'ok'      => true,
            'message' => 'Purchase Order finalized. Stock updated ('
                       . $newBatches . ' new lot(s), ' . $mergedBatches . ' lot(s) topped up).',
        ];
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

/** A Draft PO can be deleted freely. A Final PO is a stock document - blocked. */
function delete_draft_po(PDO $pdo, int $poId): array
{
    $status = scalar($pdo, 'SELECT status FROM purchase_orders WHERE id = ?', [$poId]);
    if (!$status) {
        return ['ok' => false, 'message' => 'Purchase Order not found.'];
    }
    if ($status === 'Final') {
        return ['ok' => false, 'message' => 'A Final purchase order cannot be deleted because stock has already been posted against it.'];
    }
    $pdo->prepare('DELETE FROM purchase_orders WHERE id = ? AND status = \'Draft\'')->execute([$poId]);
    return ['ok' => true, 'message' => 'Draft purchase order deleted.'];
}

/* ==========================================================================
   STOCK OUT / CONSUMPTION ENGINE
   --------------------------------------------------------------------------
   mode = 'fifo'   : consume oldest-expiry batches first (NULL expiry last)
   mode = 'manual' : consume the chosen lot only
   ========================================================================== */
function issue_stock(
    PDO $pdo,
    int $itemId,
    float $quantity,
    string $mode = 'fifo',
    ?int $batchId = null,
    ?string $outDate = null,
    string $issuedTo = '',
    string $purpose = '',
    ?int $userId = null,
    ?int $deptId = null,
    string $voucherNo = '',
    string $receivedBy = ''
): array {
    $outDate = clean_date($outDate) ?? today();

    if ($itemId <= 0) {
        return ['ok' => false, 'message' => 'Please select an item.'];
    }
    if ($quantity <= 0) {
        return ['ok' => false, 'message' => 'Issue quantity must be greater than zero.'];
    }

    $ownTx = !$pdo->inTransaction();
    if ($ownTx) {
        $pdo->beginTransaction();
    }

    try {
        $item = null;
        $st = $pdo->prepare('SELECT id, item_name, unit FROM items WHERE id = ?');
        $st->execute([$itemId]);
        $item = $st->fetch();
        if (!$item) {
            throw new RuntimeException('Item not found.');
        }

        if ($mode === 'manual') {
            if (!$batchId) {
                throw new RuntimeException('Please select a lot / batch to issue from.');
            }
            $st = $pdo->prepare('SELECT * FROM item_batches
                                 WHERE id = ? AND item_id = ? FOR UPDATE');
            $st->execute([$batchId, $itemId]);
            $batches = array_filter([$st->fetch()]);
            if (!$batches) {
                throw new RuntimeException('Selected lot does not belong to this item.');
            }
        } else {
            $st = $pdo->prepare('SELECT * FROM item_batches
                                 WHERE item_id = ? AND quantity_available > 0
                                 ORDER BY (expiry_date IS NULL) ASC, expiry_date ASC,
                                          purchase_date ASC, id ASC
                                 FOR UPDATE');
            $st->execute([$itemId]);
            $batches = $st->fetchAll();
        }

        $available = 0.0;
        foreach ($batches as $b) {
            $available += (float)$b['quantity_available'];
        }
        if ($available + 0.00001 < $quantity) {
            throw new RuntimeException(
                'Not enough stock. Available ' . qty_str($available) . ' ' . $item['unit']
                . ', requested ' . qty_str($quantity) . ' ' . $item['unit'] . '.'
            );
        }

        $remaining = $quantity;
        $consumed  = [];
        $valueOut  = 0.0;

        $insOut = $pdo->prepare('INSERT INTO stock_out
                                   (voucher_no, item_id, batch_id, quantity, unit_price, out_date,
                                    department_id, issued_to, received_by, purpose, issued_by)
                                 VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $updBatch = $pdo->prepare('UPDATE item_batches
                                   SET quantity_available = quantity_available - ?
                                   WHERE id = ? AND quantity_available >= ?');

        foreach ($batches as $b) {
            if ($remaining <= 0.00001) {
                break;
            }
            $take = min($remaining, (float)$b['quantity_available']);
            if ($take <= 0) {
                continue;
            }

            $updBatch->execute([$take, (int)$b['id'], $take]);
            if ($updBatch->rowCount() !== 1) {
                throw new RuntimeException('Stock changed while saving. Please retry.');
            }

            $insOut->execute([
                nullify($voucherNo),
                $itemId,
                (int)$b['id'],
                $take,
                (float)$b['unit_price'],
                $outDate,
                $deptId > 0 ? $deptId : null,
                nullify($issuedTo),
                nullify($receivedBy),
                nullify($purpose),
                $userId > 0 ? $userId : null,
            ]);

            $valueOut  += $take * (float)$b['unit_price'];
            $remaining -= $take;
            $consumed[] = [
                'lot'   => lot_label($b['lot_number']),
                'qty'   => $take,
                'rate'  => (float)$b['unit_price'],
                'value' => $take * (float)$b['unit_price'],
            ];
        }

        if ($remaining > 0.00001) {
            throw new RuntimeException('Could not allocate the full quantity from the available lots.');
        }

        if ($ownTx) {
            $pdo->commit();
        }

        $parts = [];
        foreach ($consumed as $c) {
            $parts[] = 'Lot ' . h($c['lot']) . ' - ' . qty_str($c['qty']) . ' ' . h($item['unit']);
        }

        return [
            'ok'       => true,
            'message'  => 'Stock issued: ' . implode(', ', $parts)
                        . ' &nbsp;|&nbsp; Consumption value ' . money($valueOut),
            'consumed' => $consumed,
            'value'    => $valueOut,
        ];
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

/**
 * One issue voucher = one slip, one date, one department, many items.
 * Every line is issued inside a single transaction, so a voucher is either
 * recorded completely or not at all.
 *
 * @param array $h     out_date, department_id, department_name, voucher_no,
 *                     received_by, purpose
 * @param array $lines [ ['item_id'=>int, 'qty'=>float, 'batch_id'=>?int], ... ]
 * @return array{ok:bool, message:string, lines:int, value:float, voucher:string}
 */
function issue_voucher(PDO $pdo, array $h, array $lines, ?int $userId = null): array
{
    $date       = clean_date($h['out_date'] ?? null) ?? today();
    $deptId     = (int)($h['department_id'] ?? 0);
    $deptName   = trim((string)($h['department_name'] ?? ''));
    $voucherNo  = trim((string)($h['voucher_no'] ?? ''));
    $receivedBy = trim((string)($h['received_by'] ?? ''));
    $purpose    = trim((string)($h['purpose'] ?? ''));

    $clean = [];
    foreach ($lines as $l) {
        $itemId = (int)($l['item_id'] ?? 0);
        $qty    = (float)($l['qty'] ?? 0);
        if ($itemId <= 0 || $qty <= 0) {
            continue;
        }
        $clean[] = ['item_id' => $itemId, 'qty' => $qty, 'batch_id' => (int)($l['batch_id'] ?? 0) ?: null];
    }

    if (!$clean) {
        return ['ok' => false, 'message' => 'Add at least one item with a quantity.',
                'lines' => 0, 'value' => 0.0, 'voucher' => $voucherNo];
    }
    if ($voucherNo === '') {
        $voucherNo = next_voucher_no($pdo, $date);
    }

    $ownTx = !$pdo->inTransaction();
    if ($ownTx) {
        $pdo->beginTransaction();
    }

    try {
        $value = 0.0;
        $done  = 0;
        foreach ($clean as $l) {
            $res = issue_stock(
                $pdo,
                $l['item_id'],
                $l['qty'],
                $l['batch_id'] ? 'manual' : 'fifo',
                $l['batch_id'],
                $date,
                $deptName,
                $purpose,
                $userId,
                $deptId ?: null,
                $voucherNo,
                $receivedBy
            );
            if (!$res['ok']) {
                $name = (string)scalar($pdo, 'SELECT item_name FROM items WHERE id = ?', [$l['item_id']]);
                throw new RuntimeException($name . ': ' . $res['message']);
            }
            $value += (float)$res['value'];
            $done++;
        }

        if ($ownTx) {
            $pdo->commit();
        }
        return [
            'ok'      => true,
            'message' => 'Voucher <b>' . h($voucherNo) . '</b> saved - ' . $done . ' item(s) issued'
                       . ($deptName !== '' ? ' to ' . h($deptName) : '')
                       . ', total value ' . money($value) . '.',
            'lines'   => $done,
            'value'   => $value,
            'voucher' => $voucherNo,
        ];
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'message' => $e->getMessage() . ' Nothing was issued on this voucher.',
                'lines' => 0, 'value' => 0.0, 'voucher' => $voucherNo];
    }
}

/** Undo one stock-out line: quantity goes back to the same batch. */
function reverse_stock_out(PDO $pdo, int $outId): array
{
    $ownTx = !$pdo->inTransaction();
    if ($ownTx) {
        $pdo->beginTransaction();
    }
    try {
        $st = $pdo->prepare('SELECT * FROM stock_out WHERE id = ? FOR UPDATE');
        $st->execute([$outId]);
        $row = $st->fetch();
        if (!$row) {
            throw new RuntimeException('Stock-out entry not found.');
        }

        $pdo->prepare('UPDATE item_batches SET quantity_available = quantity_available + ?
                       WHERE id = ?')
            ->execute([(float)$row['quantity'], (int)$row['batch_id']]);
        $pdo->prepare('DELETE FROM stock_out WHERE id = ?')->execute([$outId]);

        if ($ownTx) {
            $pdo->commit();
        }
        return ['ok' => true, 'message' => 'Stock-out entry reversed. Quantity returned to the lot.'];
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

/* ==========================================================================
   RATE LOOKUP  (used by the PO form via AJAX)
   Priority: last Final PO rate for this item + supplier
          -> last Final PO rate for this item (any supplier)
          -> last batch rate for this item
   ========================================================================== */
function last_rate(PDO $pdo, int $itemId, int $supplierId = 0): array
{
    /* the rate actually RECEIVED is the useful one, not the rate ordered */
    if ($supplierId > 0) {
        $st = $pdo->prepare("SELECT r.unit_rate, po.po_date, po.po_number, s.supplier_name
                             FROM po_receipts r
                             JOIN purchase_orders po ON po.id = r.po_id
                             JOIN suppliers s ON s.id = po.supplier_id
                             WHERE r.item_id = ? AND po.supplier_id = ? AND po.status = 'Final'
                               AND r.quantity > 0
                             ORDER BY po.po_date DESC, po.id DESC, r.id DESC LIMIT 1");
        $st->execute([$itemId, $supplierId]);
        if ($r = $st->fetch()) {
            return [
                'rate'   => (float)$r['unit_rate'],
                'source' => 'Last received rate from this supplier (' . $r['po_number'] . ', ' . dmy($r['po_date']) . ')',
            ];
        }
    }

    $st = $pdo->prepare("SELECT r.unit_rate, po.po_date, po.po_number, s.supplier_name
                         FROM po_receipts r
                         JOIN purchase_orders po ON po.id = r.po_id
                         JOIN suppliers s ON s.id = po.supplier_id
                         WHERE r.item_id = ? AND po.status = 'Final' AND r.quantity > 0
                         ORDER BY po.po_date DESC, po.id DESC, r.id DESC LIMIT 1");
    $st->execute([$itemId]);
    if ($r = $st->fetch()) {
        return [
            'rate'   => (float)$r['unit_rate'],
            'source' => 'Last received rate (' . $r['supplier_name'] . ', ' . dmy($r['po_date']) . ')',
        ];
    }

    $st = $pdo->prepare('SELECT b.unit_price, b.purchase_date, s.supplier_name
                         FROM item_batches b
                         LEFT JOIN suppliers s ON s.id = b.supplier_id
                         WHERE b.item_id = ?
                         ORDER BY b.purchase_date DESC, b.id DESC LIMIT 1');
    $st->execute([$itemId]);
    if ($r = $st->fetch()) {
        return [
            'rate'   => (float)$r['unit_price'],
            'source' => 'Opening / last stock rate'
                      . ($r['supplier_name'] ? ' (' . $r['supplier_name'] . ')' : ''),
        ];
    }

    return ['rate' => 0.0, 'source' => 'No previous rate found - please enter the rate'];
}
