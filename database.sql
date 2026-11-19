/* ============================================================================
   SL DIAGNOSTICS - LABORATORY INVENTORY & STOCK MANAGEMENT SYSTEM
   ----------------------------------------------------------------------------
   Engine : MySQL 5.7+ / MariaDB 10.3+   (InnoDB, utf8mb4)
   Usage  : phpMyAdmin -> Import -> database.sql
            or  mysql -u root -p < database.sql

   CLEAN INSTALL - no sample suppliers, items or stock.
   The only row created is the default administrator login:

        Username : admin
        Password : admin123      <-- change it after the first login

   Everything else (suppliers, items, purchase orders, stock) is entered
   manually through the application.
   ============================================================================ */

CREATE DATABASE IF NOT EXISTS `sl_inventory`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sl_inventory`;

SET FOREIGN_KEY_CHECKS = 0;
DROP VIEW  IF EXISTS `v_item_stock`;
DROP TABLE IF EXISTS `stock_month_summary`;
DROP TABLE IF EXISTS `stock_out`;
DROP TABLE IF EXISTS `po_receipts`;
DROP TABLE IF EXISTS `po_items`;
DROP TABLE IF EXISTS `purchase_orders`;
DROP TABLE IF EXISTS `item_batches`;
DROP TABLE IF EXISTS `items`;
DROP TABLE IF EXISTS `equipments`;
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `suppliers`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

/* ---------------------------------------------------------------------------
   0. USERS  (login + role)
      role Admin : everything, including creating and managing users
      role User  : everything except User Management
   --------------------------------------------------------------------------- */
CREATE TABLE `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name`     VARCHAR(120) NOT NULL,
  `username`      VARCHAR(60)  NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role`          ENUM('Admin','User') NOT NULL DEFAULT 'User',
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `last_login`    DATETIME     DEFAULT NULL,
  `created_by`    INT UNSIGNED DEFAULT NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  KEY `idx_user_role`   (`role`),
  KEY `idx_user_active` (`is_active`),
  KEY `idx_user_author` (`created_by`),
  CONSTRAINT `fk_user_creator` FOREIGN KEY (`created_by`)
      REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ---------------------------------------------------------------------------
   1. SUPPLIERS
   --------------------------------------------------------------------------- */
CREATE TABLE `suppliers` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `supplier_name`  VARCHAR(150)  NOT NULL,
  `contact_person` VARCHAR(100)  DEFAULT NULL,
  `phone`          VARCHAR(40)   DEFAULT NULL,
  `email`          VARCHAR(120)  DEFAULT NULL,
  `gst_no`         VARCHAR(20)   DEFAULT NULL,
  `address`        TEXT          NULL,
  `is_active`      TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_supplier_name` (`supplier_name`),
  KEY `idx_sup_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ---------------------------------------------------------------------------
   1b. DEPARTMENTS  (Biochemistry, Haematology, Radiology, GCC ...)
       Items belong to a department, and every stock issue records the
       department that consumed it - that is what the monthly budget is
       grouped by.
   --------------------------------------------------------------------------- */
CREATE TABLE `departments` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dept_name`  VARCHAR(80)  NOT NULL,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dept_name` (`dept_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ---------------------------------------------------------------------------
   1c. EQUIPMENTS  (AU480, Beckman Unicel Dxc, Mini Vidas, Abbott, Mispa i3 ...)
       An analyser inside a department. Reagent items are mapped to the machine
       they run on, so the budget can be read machine wise.
   --------------------------------------------------------------------------- */
CREATE TABLE `equipments` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `equipment_name` VARCHAR(100) NOT NULL,
  `department_id`  INT UNSIGNED DEFAULT NULL,
  `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_equipment` (`equipment_name`, `department_id`),
  KEY `idx_equip_dept` (`department_id`),
  CONSTRAINT `fk_equip_dept` FOREIGN KEY (`department_id`)
      REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ---------------------------------------------------------------------------
   2. ITEMS  (Item Master)
      has_lot = 1 -> item is tracked lot/batch wise (reagents, kits, controls)
      has_lot = 0 -> item is tracked as a single 'GENERAL' batch (gloves, tubes)
   --------------------------------------------------------------------------- */
CREATE TABLE `items` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `item_code`       VARCHAR(40)   NOT NULL,
  `item_name`       VARCHAR(190)  NOT NULL,
  `category`        VARCHAR(80)   DEFAULT NULL,
  `unit`            VARCHAR(20)   NOT NULL DEFAULT 'Nos',
  `has_lot`         TINYINT(1)    NOT NULL DEFAULT 1,
  `min_stock_alert` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `department_id`       INT UNSIGNED DEFAULT NULL,
  `equipment_id`        INT UNSIGNED DEFAULT NULL,
  `default_supplier_id` INT UNSIGNED DEFAULT NULL,
  `is_active`       TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_item_code` (`item_code`),
  KEY `idx_item_name`   (`item_name`),
  KEY `idx_item_cat`    (`category`),
  KEY `idx_item_active` (`is_active`),
  KEY `idx_item_dept`   (`department_id`),
  KEY `idx_item_equip`  (`equipment_id`),
  KEY `idx_item_sup`    (`default_supplier_id`),
  CONSTRAINT `fk_item_dept` FOREIGN KEY (`department_id`)
      REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_item_equip` FOREIGN KEY (`equipment_id`)
      REFERENCES `equipments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_item_supplier` FOREIGN KEY (`default_supplier_id`)
      REFERENCES `suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ---------------------------------------------------------------------------
   3. ITEM_BATCHES  (Multi-Lot + Stock Valuation engine)
      One row = one lot of one item from one supplier at one purchase rate.
      Live stock value = SUM(quantity_available * unit_price)
   --------------------------------------------------------------------------- */
CREATE TABLE `item_batches` (
  `id`                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `item_id`            INT UNSIGNED  NOT NULL,
  `supplier_id`        INT UNSIGNED  DEFAULT NULL,
  `lot_number`         VARCHAR(80)   DEFAULT NULL,
  `expiry_date`        DATE          DEFAULT NULL,
  `unit_price`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `quantity_available` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `purchase_date`      DATE          DEFAULT NULL,
  `po_id`              INT UNSIGNED  DEFAULT NULL,
  `created_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_batch_item`   (`item_id`, `quantity_available`),
  KEY `idx_batch_sup`    (`supplier_id`),
  KEY `idx_batch_expiry` (`expiry_date`),
  KEY `idx_batch_lot`    (`lot_number`),
  KEY `idx_batch_po`     (`po_id`),
  CONSTRAINT `fk_batch_item` FOREIGN KEY (`item_id`)
      REFERENCES `items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_batch_supplier` FOREIGN KEY (`supplier_id`)
      REFERENCES `suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ---------------------------------------------------------------------------
   4. PURCHASE_ORDERS
      status Draft -> what we ORDERED. Record only, does NOT touch live stock.
      status Final -> what the supplier actually DELIVERED, confirmed on the
                      Receive screen, then pushed into item_batches.
      grand_total    = ordered value  (what we asked)
      received_total = received value (what they gave)
      receive_date   = date the goods were actually received
   --------------------------------------------------------------------------- */
CREATE TABLE `purchase_orders` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `po_number`      VARCHAR(40)   NOT NULL,
  `supplier_id`    INT UNSIGNED  NOT NULL,
  `po_date`        DATE          NOT NULL,
  `status`         ENUM('Draft','Final') NOT NULL DEFAULT 'Draft',
  `is_direct`      TINYINT(1)    NOT NULL DEFAULT 0,  -- 1 = direct GRN, no order was raised
  `grand_total`    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `received_total` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `receive_date`   DATE          DEFAULT NULL,
  `invoice_no`     VARCHAR(60)   DEFAULT NULL,
  `receive_notes`  TEXT          NULL,
  `notes`          TEXT          NULL,
  `created_by`     INT UNSIGNED  DEFAULT NULL,
  `finalized_by`   INT UNSIGNED  DEFAULT NULL,
  `finalized_at`   DATETIME      DEFAULT NULL,
  `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_po_number` (`po_number`),
  KEY `idx_po_supplier` (`supplier_id`),
  KEY `idx_po_status`   (`status`),
  KEY `idx_po_date`     (`po_date`),
  KEY `idx_po_author`   (`created_by`),
  CONSTRAINT `fk_po_supplier` FOREIGN KEY (`supplier_id`)
      REFERENCES `suppliers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_po_creator` FOREIGN KEY (`created_by`)
      REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_po_finalizer` FOREIGN KEY (`finalized_by`)
      REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ---------------------------------------------------------------------------
   5. PO_ITEMS  -  what was ORDERED (one row per item on the purchase order)
      lot_number / expiry_date stay nullable here: the real lot details are
      captured on the Receive screen, into po_receipts.
   --------------------------------------------------------------------------- */
CREATE TABLE `po_items` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `po_id`        INT UNSIGNED  NOT NULL,
  `item_id`      INT UNSIGNED  NOT NULL,
  `lot_number`   VARCHAR(80)   DEFAULT NULL,
  `expiry_date`  DATE          DEFAULT NULL,
  `quantity`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `unit_rate`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_poitem_po`   (`po_id`),
  KEY `idx_poitem_item` (`item_id`),
  CONSTRAINT `fk_poitem_po` FOREIGN KEY (`po_id`)
      REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_poitem_item` FOREIGN KEY (`item_id`)
      REFERENCES `items` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ---------------------------------------------------------------------------
   5b. PO_RECEIPTS  -  what was actually RECEIVED against each ordered line
      One ordered line can become SEVERAL receipt rows, so 10 kits delivered as
      5 of lot AS123 and 5 of lot AR123 are two rows against the same po_item.
      Quantity and rate are confirmed here, so a short delivery or a hiked
      price is recorded as it happened, without touching the ordered line.
      batch_id is filled when the PO is finalized, linking the receipt row to
      the stock lot it created.
   --------------------------------------------------------------------------- */
CREATE TABLE `po_receipts` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `po_id`        INT UNSIGNED  NOT NULL,
  `po_item_id`   INT UNSIGNED  NOT NULL,
  `item_id`      INT UNSIGNED  NOT NULL,
  `lot_number`   VARCHAR(80)   DEFAULT NULL,
  `expiry_date`  DATE          DEFAULT NULL,
  `quantity`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `unit_rate`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `batch_id`     INT UNSIGNED  DEFAULT NULL,
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rcpt_po`    (`po_id`),
  KEY `idx_rcpt_line`  (`po_item_id`),
  KEY `idx_rcpt_item`  (`item_id`),
  KEY `idx_rcpt_batch` (`batch_id`),
  CONSTRAINT `fk_rcpt_po` FOREIGN KEY (`po_id`)
      REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rcpt_poitem` FOREIGN KEY (`po_item_id`)
      REFERENCES `po_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rcpt_item` FOREIGN KEY (`item_id`)
      REFERENCES `items` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_rcpt_batch` FOREIGN KEY (`batch_id`)
      REFERENCES `item_batches` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ---------------------------------------------------------------------------
   6. STOCK_OUT  (daily consumption / issue register)
      unit_price is copied from the batch at the moment of issue so that the
      consumption value stays correct even if the batch is later re-rated.
      voucher_no groups the rows that were issued together on one slip, so one
      voucher can carry many items for one department on one date.
      received_by is the person who collected them, typed in by hand.
   --------------------------------------------------------------------------- */
CREATE TABLE `stock_out` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `voucher_no` VARCHAR(40)   DEFAULT NULL,
  `item_id`    INT UNSIGNED  NOT NULL,
  `batch_id`   INT UNSIGNED  NOT NULL,
  `quantity`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `out_date`      DATE          NOT NULL,
  `department_id` INT UNSIGNED  DEFAULT NULL,
  `issued_to`   VARCHAR(120) DEFAULT NULL,
  `received_by` VARCHAR(120) DEFAULT NULL,
  `purpose`    VARCHAR(190)  DEFAULT NULL,
  `issued_by`  INT UNSIGNED  DEFAULT NULL,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_out_voucher` (`voucher_no`),
  KEY `idx_out_item`   (`item_id`),
  KEY `idx_out_batch`  (`batch_id`),
  KEY `idx_out_date`   (`out_date`),
  KEY `idx_out_dept`   (`department_id`),
  KEY `idx_out_author` (`issued_by`),
  CONSTRAINT `fk_out_dept` FOREIGN KEY (`department_id`)
      REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_out_item` FOREIGN KEY (`item_id`)
      REFERENCES `items` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_out_batch` FOREIGN KEY (`batch_id`)
      REFERENCES `item_batches` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_out_user` FOREIGN KEY (`issued_by`)
      REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ---------------------------------------------------------------------------
   6b. STOCK_MONTH_SUMMARY  (the monthly budget sheet, per item)
       One row per item per month:
         opening -> purchase -> issues -> closing, and the quantity required
         for the FOLLOWING month (reqd_month).
       source = 'Imported' : figures brought in from the Excel budget
       source = 'System'   : figures the application calculated from its own
                             purchase orders and stock-out entries
   --------------------------------------------------------------------------- */
CREATE TABLE `stock_month_summary` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `month_key`  CHAR(7)       NOT NULL,              -- 'YYYY-MM' of the movement
  `reqd_month` CHAR(7)       DEFAULT NULL,          -- 'YYYY-MM' the requirement is for
  `item_id`    INT UNSIGNED  NOT NULL,
  `op_qty`     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `op_value`   DECIMAL(16,2) NOT NULL DEFAULT 0.00,
  `pur_qty`    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `pur_value`  DECIMAL(16,2) NOT NULL DEFAULT 0.00,
  `iss_qty`    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `iss_value`  DECIMAL(16,2) NOT NULL DEFAULT 0.00,
  `cl_qty`     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `cl_value`   DECIMAL(16,2) NOT NULL DEFAULT 0.00,
  `reqd_qty`   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `reqd_rate`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `reqd_value` DECIMAL(16,2) NOT NULL DEFAULT 0.00,
  `source`     ENUM('Imported','System') NOT NULL DEFAULT 'System',
  `note`       VARCHAR(190)  DEFAULT NULL,
  `updated_by` INT UNSIGNED  DEFAULT NULL,
  `updated_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_month_item` (`month_key`, `item_id`),
  KEY `idx_sms_item`  (`item_id`),
  KEY `idx_sms_month` (`month_key`),
  KEY `idx_sms_user`  (`updated_by`),
  CONSTRAINT `fk_sms_item` FOREIGN KEY (`item_id`)
      REFERENCES `items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sms_user` FOREIGN KEY (`updated_by`)
      REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ---------------------------------------------------------------------------
   7. VIEW : item wise live stock + valuation
   --------------------------------------------------------------------------- */
CREATE VIEW `v_item_stock` AS
SELECT
    i.id                AS item_id,
    i.item_code,
    i.item_name,
    i.category,
    i.unit,
    i.has_lot,
    i.min_stock_alert,
    i.is_active,
    i.department_id,
    i.equipment_id,
    i.default_supplier_id,
    d.dept_name,
    e.equipment_name,
    sp.supplier_name    AS default_supplier_name,
    COALESCE(SUM(b.quantity_available), 0)                  AS total_qty,
    COALESCE(SUM(b.quantity_available * b.unit_price), 0)   AS stock_value,
    COUNT(b.id)                                             AS batch_count,
    MIN(b.expiry_date)                                      AS next_expiry,
    MAX(b.unit_price)                                       AS max_rate,
    MIN(b.unit_price)                                       AS min_rate
FROM `items` i
LEFT JOIN `item_batches` b
       ON b.item_id = i.id AND b.quantity_available > 0
LEFT JOIN `departments` d  ON d.id  = i.department_id
LEFT JOIN `equipments`  e  ON e.id  = i.equipment_id
LEFT JOIN `suppliers`   sp ON sp.id = i.default_supplier_id
/* the three joined names are listed as well, so the view also builds on a
   server running with ONLY_FULL_GROUP_BY - they are 1:1 with i.id anyway */
GROUP BY i.id, d.dept_name, e.equipment_name, sp.supplier_name;

/* ---------------------------------------------------------------------------
   8. DEFAULT ADMINISTRATOR
       username : admin
       password : admin123
       The hash below is a bcrypt hash of "admin123".
       Log in, open  My Account  and change the password immediately.
   --------------------------------------------------------------------------- */
INSERT INTO `users` (`full_name`, `username`, `password_hash`, `role`, `is_active`) VALUES
 ('System Administrator', 'admin',
  '$2y$10$/IUih0D6o5KVvu36sFugdeFNr1UjhcXChkcwV/uhz.FFTclg3ih4q',
  'Admin', 1);

/* ============================================================================
   NOTHING ELSE IS INSERTED - the masters start empty.
   To load the real SL Diagnostics masters, opening stock and the October 2026
   budget figures from the Excel sheet, run  sl_live_data.sql  after this file.

   First run order:
     login -> Suppliers -> Item Master -> Create PO (always saved as Draft)
           -> when the goods arrive: Receive & Confirm (edit quantity / rate,
              enter lot numbers, split a line across lots) -> Finalize
           -> stock and valuation update -> Stock Out for daily consumption
   ============================================================================ */
