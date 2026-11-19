<div align="center">

<img src="https://readme-typing-svg.demolab.com?font=Inter&weight=700&size=27&pause=1100&color=2563EB&center=true&vCenter=true&width=760&lines=SL+Diagnostics;Laboratory+Inventory+%26+Stock+Management" alt="SL Diagnostics animated title" />

**A clean, practical inventory and stock workflow for diagnostic laboratories**

[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![JavaScript](https://img.shields.io/badge/Vanilla-JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=222)](https://developer.mozilla.org/en-US/docs/Web/JavaScript)

[Website](https://services.smartjoans.space) · [Instagram @smart_joans](https://www.instagram.com/smart_joans/)

</div>

---

## ✨ Overview

SL Diagnostics is a laboratory inventory and stock management system built with **Core PHP (PDO), MySQL, Bootstrap 5 and vanilla JavaScript**.

- No framework, Composer dependency or build step.
- Runs locally on **XAMPP** and on **cPanel / LiteSpeed** shared hosting.
- Admin and User login roles.
- Purchase Order → Goods Receive Note (GRN) two-stage workflow.
- Stock valuation by actual received lot rate.
- Department-wise stock issue and monthly budget tracking.
- Your logo appears in the application and on printed purchase documents.

> **Current build:** clean database setup, role-based login, logo-enabled PO printing, and the two-stage Order → Receive (GRN) workflow.

## 🧰 Technology

| Component | Details |
|---|---|
| Backend | PHP 7.4+ (tested on PHP 8.3) |
| Database | MySQL using PDO |
| Frontend | Bootstrap 5.3 + vanilla JavaScript |
| Database objects | 11 tables + `v_item_stock` view |
| Hosting | XAMPP (Windows), cPanel / LiteSpeed |
| Required PHP extension | `pdo_mysql` |

---

## 📁 1. Folder structure

```text
sl-inventory/
├── database.sql              Clean MySQL schema + ONE default admin login
├── upgrade_v3.sql            Older install upgrade: receipt tables
├── upgrade_v4.sql            Older install upgrade: dept / equipment / budget
├── upgrade_v5.sql            Older install upgrade: issue voucher columns
├── upgrade_v6.sql            Older install upgrade: direct GRN flag
├── logo.png                  Your logo (see section 7)
├── db.php                    App constants, session settings, PDO (EDIT THIS)
├── login.php                 Sign in
├── logout.php                Sign out
├── index.php                 Dashboard
├── suppliers.php             Supplier master (CRUD)
├── items.php                 Item master (CRUD)
├── departments.php           Departments + equipment / analysers
├── budget.php                Monthly budget
├── create_po.php             Create / edit Draft PO
├── receive_po.php            Receive (GRN) against a PO
├── direct_grn.php            Direct GRN without a PO
├── po_list.php               Purchase Order register
├── view_po.php               PO view + A4 print
├── stock_report.php          Stock valuation + CSV export
├── stock_out.php             Issue voucher + issue register
├── users.php                 User management (ADMIN ONLY)
├── profile.php               My Account
├── includes/
│   ├── functions.php         Helpers + stock business logic
│   ├── auth.php              Login session, roles, page guards
│   ├── header.php            Head, sidebar, topbar
│   └── footer.php            Closing markup + scripts
├── ajax/
│   ├── get_last_rate.php     Last purchase rate + item info
│   └── get_batches.php       Available lots in FIFO order
└── assets/
    ├── css/style.css         Theme, login, responsive sidebar, print styles
    └── js/app.js             Number formatting, fetch wrapper, confirms
```

---

## 🚀 2. Installation

### Step 1 — Copy the project

Copy `sl-inventory` into:

- XAMPP: `htdocs`
- cPanel: `public_html`

### Step 2 — Import the database

Import `database.sql` through phpMyAdmin, or run:

```bash
mysql -u root -p < database.sql
```

This creates the `sl_inventory` database, all eleven tables, the `v_item_stock` view and one default administrator account.

**No suppliers, items or stock are inserted by default.** Create your masters from the application.

### Step 3 — Configure `db.php`

Edit the database settings near the top of `db.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'sl_inventory');
define('DB_USER', 'root'); // cPanel: your database user
define('DB_PASS', '');     // cPanel: your database password
```

Other application constants:

| Constant | Purpose |
|---|---|
| `APP_ADDRESS` / `APP_GST` | Printed on the purchase order |
| `SESSION_IDLE_MINUTES` | Idle timeout; default `240` |
| `LOGIN_MAX_ATTEMPTS` | Brute-force protection; default `5` |
| `LOGIN_LOCK_MINUTES` | Lockout duration; default `5` |
| `DOC_PREFIX` / `GRN_PREFIX` / `ISSUE_PREFIX` | Three document number series |
| `APP_LOGO` | Logo filename; default `logo.png` |

### Step 4 — Sign in

Open `login.php` and use the initial credentials:

| Login field | Default |
|---|---|
| Username | `admin` |
| Password | `admin123` |

<details>
<summary><strong>⚠️ Important security step</strong></summary>

Change the default password immediately after the first login:

1. Sign in as `admin`.
2. Open **My Account**.
3. Set a new password.

</details>

### Step 5 — First-run workflow

```text
Login
  ↓
Suppliers
  ↓
Item Master
  ↓
Create Purchase Order (Draft)
  ↓
Goods arrive
  ↓
Receive (GRN)
  ↓
Finalize
  ↓
Stock becomes available
  ↓
Stock Out
```

The dashboard displays a Getting Started checklist until the masters contain data and lists purchase orders that are still waiting to be received.

### Already using an older build?

Do **not** re-import `database.sql` if you need to retain existing data. Run these upgrade scripts in order:

1. `upgrade_v3.sql`
2. `upgrade_v4.sql`
3. `upgrade_v5.sql`
4. `upgrade_v6.sql`

Together, they add receipt tables, the department / equipment layer, the budget table, issue voucher columns and the direct GRN flag while keeping existing data.

A fresh installation does not need these upgrade scripts.

---

## 🧾 3. Three documents, three number series

All three prefixes are defined at the top of `db.php`. Changing a prefix there updates the series throughout the application.

| Document | Example number | Purpose |
|---|---|---|
| Purchase Order (`DOC_PREFIX`) | `PO/2026-27/0001` | What was ordered; remains Draft until goods arrive |
| Goods Receive Note (`GRN_PREFIX`) | `GRN/2026-27/0001` | Goods received without a purchase order (`direct_grn.php`) |
| Issue Voucher (`ISSUE_PREFIX`) | `ISS/2026-27/0001` | Stock issued to a department |

Receiving against a purchase order retains that order's own `PO/...` number. The received copy is treated as its goods receive note. Therefore, the printed sheet is headed **Purchase Order** while it is a Draft and **Goods Receive Note** after it has been received.

---

## 🔐 4. Roles & security

### Role access

| Page / feature | Admin | User |
|---|:---:|:---:|
| Dashboard | ✔ | ✔ |
| Suppliers | ✔ | ✔ |
| Item Master | ✔ | ✔ |
| Departments | ✔ | ✔ |
| Monthly Budget, requirement, Draft POs from budget | ✔ | ✔ |
| Create PO, Purchase Orders, final conversion, print | ✔ | ✔ |
| Stock & Valuation, CSV export, Stock Out, Reverse | ✔ | ✔ |
| My Account (own name / password) | ✔ | ✔ |
| User Management | ✔ | ✖ |

A User can access every page except `users.php`; only an Admin can create and manage logins.

Access checks are enforced server-side:

- `require_login()` protects authenticated pages.
- `require_admin()` protects `users.php`.
- The User Management menu item is hidden from Users.
- AJAX endpoints return `401` for an expired or missing session instead of exposing data.

Admins can create, edit, deactivate and delete logins, reset passwords, and change a login between Admin and User.

### Account-protection rules

- You cannot delete or deactivate your own login.
- The last active Admin cannot be deleted, deactivated or demoted. This prevents the system from being locked out.

### Security measures

- Passwords are stored as bcrypt hashes using `password_hash()`, never as plain text.
- CSRF token on every form.
- Prepared statements throughout.
- `h()` used for output escaping.
- HttpOnly + SameSite=Lax session cookie (Secure automatically over HTTPS).
- Session ID regenerated on login.
- Idle timeout and a five-attempt login lockout.
- Whitelist on the post-login redirect.

### Audit trail

Every purchase order stores who created it and who finalized it. Every stock-out line stores who issued it.

These names appear in the PO register, PO view, printed **Prepared By** field and issue register. They remain available even if the associated login is later deleted.

---

## 📦 5. How the stock logic works

### Multi-lot items

When `items.has_lot = 1`, every purchase creates or updates a row in `item_batches`. Each lot carries its own:

- `lot_number`
- `expiry_date`
- `supplier_id`
- `unit_price`

When `has_lot = 0` (for example, gloves, tubes and stationery), stock is stored under the reserved lot `GENERAL` with a `NULL` expiry. The PO form automatically hides the lot and expiry fields for these items.

### Stock valuation — no average costing

Each lot retains the rate at which it was actually purchased.

```text
Current Total Stock Value
= SUM(item_batches.quantity_available * item_batches.unit_price)
```

This remains accurate when the same item is purchased from multiple suppliers at different rates.

### Purchase Order workflow — order first, receive second

A purchase order cannot be finalized directly from the order screen. It is first saved as a Draft. Stock is created only after the delivery has been physically checked and received.

| Stage | Page | What happens |
|---|---|---|
| 1. Order | `create_po.php` | Select item, quantity and expected rate. Saves as Draft. `item_batches` is untouched; no stock valuation is affected. Drafts can be edited or deleted. Lot numbers are not requested at this stage. |
| 2. Receive (GRN) | `receive_po.php` | Enter what actually arrived. Correct the quantity or rate, add lot numbers and expiry dates, and split one ordered line across multiple lots. A partial receipt can be saved without finalizing; the PO remains Draft. |
| 3. Finalize | `receive_po.php` | `finalize_po()` posts confirmed receipt rows into `item_batches`. The PO becomes read-only and can be printed as a Goods Receive Note. |

### Goods received without an order — Direct GRN

Use `direct_grn.php` for urgent supplies, free replacements, samples or items bought over the counter.

The screen captures the GRN number (its own `GRN/...` series), supplier, date, invoice number and item lines with lot, expiry, quantity and rate.

Saving a Direct GRN posts stock immediately — there is no Draft or separate receive step.

Behind the scenes, a Direct GRN is stored like a received purchase order (`purchase_orders.is_direct = 1`), with ordered lines mirroring the received lines. As a result:

- It shows no order-versus-received variance.
- Stock report, valuation, last-rate lookup and monthly budget count it like any other receipt.
- The register tags it as **Direct GRN** and can filter it.
- The printed sheet identifies that goods arrived without a purchase order.
- The same item can be entered on two rows with different lot numbers to receive it into two lots.

### Ordered vs received — stored separately

| Data | Stored in | Meaning |
|---|---|---|
| Ordered | `po_items` + `purchase_orders.grand_total` | What was requested |
| Received | `po_receipts` + `purchase_orders.received_total` | What was actually delivered |

For example, a PO ordered at ₹1,000 but delivered as 8 of 10 units at a revised rate of ₹110 keeps the original ₹1,000 order on record and posts ₹880 of stock. The difference appears in the PO register, PO view and printed sheet.

Stock is always valued at the actual received rate, not the ordered rate.

### Splitting one ordered line across multiple lots

On the Receive screen, use **Add another lot for this item**.

For example, ten kits delivered as five units of lot `AS123` and five units of lot `AR123` become two receipt rows and two separate stock lots. Each lot has its own expiry and rate.

The screen compares received quantity and value with the ordered figures line by line, making short deliveries and rate changes visible before finalization.

### One controlled entry point into stock

`finalize_po()` in `includes/functions.php` is the only place where stock is created.

- It is wrapped in a database transaction.
- It changes status using `UPDATE ... WHERE id = ? AND status = 'Draft'`.
- A double-click or two users finalizing at the same time cannot post the same quantity twice.
- Finalizing without confirmed receipt rows is rejected.
- A received line merges into an existing lot only when item, supplier, lot, expiry and rate all match.
- Any difference creates a new lot row, preserving accurate valuation.
- Every receipt row stores `batch_id`, so each stock lot can be traced to its exact PO line.

### Purchase-rate auto-fill

When an item is selected in the PO form, `ajax/get_last_rate.php` looks up the rate in this order:

1. Last received rate for the selected item and supplier.
2. Last received rate for that item from any supplier.
3. Opening-stock rate.

The rate field remains editable. Once a user types a value, changing the supplier will not overwrite that manual value.

### Stock Out — issue voucher

One voucher represents one issue slip. It contains a date, department, voucher number and the person who collected the goods, followed by as many item lines as required.

- Voucher number is auto-filled, for example `ISS/2026-27/0001`, but remains editable to match the physical register.
- **Received by** is entered manually.
- Each line is issued FIFO (earliest expiry first; lots without expiry last), or from one selected lot.
- FIFO can span multiple lots when required.
- Each issued slice creates a `stock_out` row with that lot's own rate, preserving historical consumption value.
- All lines in a voucher share one voucher number.

Saving is all-or-nothing. `issue_voucher()` wraps the complete voucher in one transaction. If the third item has insufficient stock, the first two lines are rolled back as well. Short quantities are rejected in full, never partially issued.

After saving, the voucher can be printed with the logo, lot numbers, values and signature lines for **Issued By** and **Received By**.

In the register, each voucher appears as a block. It can be reversed line by line or as a whole; reversed quantities return to the same lot.

---

## 📊 6. The Budget page

`budget.php` is the live monthly stock budget sheet. Select a month to see the following figures per item:

```text
Opening → Purchase → Issues → Closing → Required for next month
```

The sheet can be grouped by Department / Equipment or Supplier, with subtotals for each group and a grand total.

The system calculates figures from its own records:

- Purchases come from PO receipts.
- Issues come from the Stock Out register.
- Closing = Opening + Purchase − Issues.

### Budget tools

- **Required quantity:** Editable per item.
- **Fill suggested:** Writes `issues × cover − closing` into blank rows. The cover period defaults to two months and can be changed.
- **Rate:** Editable.
- **Save Requirement:** Stores the requirement against the selected month.
- **Close month:** Freezes that month's figures so its closing stock becomes the next month's opening stock.
- **Create Draft POs (supplier wise):** Creates one Draft PO per supplier from the saved requirement, using each item's default supplier. Items without a supplier are skipped and listed. Stock is not affected until each PO is received and finalized.
- **Export:** Downloads the same table as CSV.

Departments and analysers are maintained in `departments.php`. Every Stock Out entry selects a department from this list, enabling accurate department-wise consumption in the budget.

---

## 🖼️ 7. Your logo

Place `logo.png` in the application root, next to `db.php`.

The logo is automatically used in:

- Printed purchase order header (beside the address / GSTIN).
- Login page (on a white panel, so dark or coloured logos remain readable).
- Sidebar brand area.
- Browser tab icon.

If the file is missing, the application falls back to the text wordmark **SL Diagnostics** in Times New Roman Bold.

A wide logo containing the full name works best. Maximum display heights:

| Location | Maximum height |
|---|---:|
| Printed PO | 64px |
| Login | 66px |
| Sidebar | 46px |

The width scales automatically. Replacing the file is enough: the `?v=` URL value uses the file's modified time so the updated logo appears without a stale browser cache.

To use a different filename, change `APP_LOGO` in `db.php`.

---

## 📝 8. Notes

- **CDN-free option:** Bootstrap 5.3 and Bootstrap Icons load from jsDelivr. For offline use, download `bootstrap.min.css`, `bootstrap.bundle.min.js` and the Bootstrap Icons font into `assets/`, then update the tags in `includes/header.php`, `includes/footer.php` and `login.php`.
- **Opening stock:** Either create one PO per supplier using current rates and receive it with the real lot numbers (recommended for audit history), or insert rows directly into `item_batches`.
- Rows entered without a real lot number can use a placeholder such as `OPENING` and be corrected later:

```sql
UPDATE item_batches
SET lot_number = 'AS123',
    expiry_date = '2027-06-30'
WHERE item_id = 12
  AND lot_number = 'OPENING';
```

- **Draft PO edits:** Editing a Draft PO after a receipt has been saved against it clears that saved receipt, because the ordered lines it referred to are replaced.
- **Deletion safeguards:** A supplier with POs, an item used in a PO or still holding stock, and any Final PO cannot be deleted. Deactivate these records instead.
- **Additional schema fields:** `is_active` on suppliers / items / users; `created_by`, `finalized_by`, `finalized_at`, `received_total`, `receive_date`, `invoice_no`, `receive_notes` on `purchase_orders`; `po_id` on `item_batches` for PO-to-lot traceability; `issued_by` on `stock_out`; plus the `users`, `po_receipts` and `stock_out` tables and the `v_item_stock` view. `po_items.lot_number` / `expiry_date` remain in the schema but are no longer filled by the order screen — lot details live in `po_receipts`.

---

## 💬 9. Help & Contact

Need help with installation, configuration, customization or technical support?

<div align="center">

### **SmartJOANS — Support & Services**

[![Visit Services](https://img.shields.io/badge/Support%20%26%20Services-services.smartjoans.space-2563EB?style=for-the-badge&logo=googlechrome&logoColor=white)](https://services.smartjoans.space)

[![Instagram](https://img.shields.io/badge/Instagram-@smart__joans-E4405F?style=for-the-badge&logo=instagram&logoColor=white)](https://www.instagram.com/smart_joans/)

**Website:** https://services.smartjoans.space  
**Instagram:** [@smart_joans](https://www.instagram.com/smart_joans/)

</div>

For support, share the issue details, the page where it occurs, and any relevant error message or screenshot.

---

## 🏷️ 10. Credits

Designed & developed by **SmartJOANS** — full-stack web development and digital solutions.

- PHP / MySQL systems
- ASP.NET applications
- Node.js services
- Digital tools

| Project | Link |
|---|---|
| SmartJOANS Services | https://services.smartjoans.space |
| SmartJOANS Website | https://www.smartjoans.space |
| SmartLib | https://books.smartjoans.space |
| Sharable | https://sharable.smartjoans.space |
| Digital CV | https://myprofile.cc |
| Instagram | [@smart_joans](https://www.instagram.com/smart_joans/) |
| Also built | https://artintentions.in · https://writerpraba.co.in |

---

<div align="center">

**SL Diagnostics — Laboratory Inventory & Stock Management System**

Core PHP · MySQL · Bootstrap 5 · No build step

**Built by [SmartJOANS](https://services.smartjoans.space) · [@smart_joans](https://www.instagram.com/smart_joans/)**

</div>
