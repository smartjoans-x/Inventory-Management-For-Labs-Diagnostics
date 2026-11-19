/* ============================================================================
   SL Diagnostics - Inventory : UPGRADE v5 -> v6
   ----------------------------------------------------------------------------
   Run this ONLY on an install that already has data from the earlier schema.
   A fresh install needs database.sql alone (then sl_live_data.sql).

   It adds the DIRECT goods receive note - stock that arrives without a
   purchase order behind it:
     purchase_orders.is_direct   0 = received against a PO, 1 = direct GRN

   A direct GRN is stored exactly like any other receipt, so the stock report,
   the budget and the valuation treat it the same way. Its number comes from
   its own series, GRN/2026-27/0001, so it is easy to tell apart from an order.

   Run it once. MySQL reports "Duplicate column name" if run twice.
   ============================================================================ */

USE `sl_inventory`;

ALTER TABLE `purchase_orders`
  ADD COLUMN `is_direct` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`,
  ADD KEY `idx_po_direct` (`is_direct`);
