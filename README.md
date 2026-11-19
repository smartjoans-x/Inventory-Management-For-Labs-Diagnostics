<div align="center">

  <!-- Animated Typing Header -->
  <a href="https://www.smartjoans.space">
    <img src="https://readme-typing-svg.demolab.com?font=Fira+Code&weight=700&size=26&duration=3500&pause=1000&color=0D6EFD¢er=true&vCenter=true&width=850&lines=SL+Diagnostics+%E2%80%94+Laboratory+Inventory+System;Two-Stage+Order+%E2%86%92+Receive+(GRN)+Workflow;FIFO+Stock+%26+Live+Monthly+Budget+Engine;Built+for+Zero-Downtime+Medical+Lab+Operations" alt="Typing SVG" />
  </a>

  <p align="center">
    <strong>Core PHP (PDO) + MySQL + Bootstrap 5 + Vanilla JS</strong><br/>
    <em>No framework, no Composer, no build step — runs smoothly on XAMPP locally and cPanel/LiteSpeed shared hosting as-is.</em>
  </p>

  <!-- Tech Stack & Creator Badges -->
  <p align="center">
    <img src="https://img.shields.io/badge/PHP-7.4%20%7C%208.3-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP" />
    <img src="https://img.shields.io/badge/MySQL-Database-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL" />
    <img src="https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white" alt="Bootstrap" />
    <img src="https://img.shields.io/badge/JavaScript-Vanilla-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black" alt="JS" />
  </p>

  <p align="center">
    <a href="https://www.smartjoans.space" target="_blank">
      <img src="https://img.shields.io/badge/Creator-Smart%20Joans-00C853?style=for-the-badge&logo=googlechrome&logoColor=white" alt="Website" />
    </a>
    <a href="https://instagram.com/smart_joans" target="_blank">
      <img src="https://img.shields.io/badge/Instagram-@smart__joans-E4405F?style=for-the-badge&logo=instagram&logoColor=white" alt="Instagram" />
    </a>
  </p>

</div>

---

## ✨ Highlighted Build Features

> **This build includes:** Clean relational database schema, secure login with **Admin / User** role-based access, custom organization logo on printed A4 Purchase Orders, and a strict two-stage **Order → Receive (GRN)** purchase workflow.

* 🛡️ **Audit-Proof Inventory:** Separate tracking for *Ordered* vs. *Received* quantities and rates.
* 📦 **Multi-Lot & Expiry Tracking:** Automatic **FIFO** (First-In, First-Out) dispensing and exact lot-level valuation (no distorted average costing).
* 📊 **Live Excel-Style Monthly Budget:** Tracks `Opening → Purchase → Issues → Closing → Next Month Requirement` with one-click supplier-wise Draft PO generation.
* ⚡ **Direct GRN Support:** Instant stock entry for urgent supplies, samples, or over-the-counter purchases without a prior PO.

---

## 📁 1. Folder Structure

```text
sl-inventory/
├── database.sql              # Clean MySQL schema + ONE default admin login
├── 
├── upgrade_v6.sql            # Upgrade script: direct GRN flag
├── logo.png                  # ← PUT YOUR LOGO HERE (see section 6)
├── db.php                    # App constants, session settings, PDO (EDIT THIS)
├── login.php                 # Sign in (split-panel on desktop, single column on mobile)
├── logout.php                # Sign out
├── index.php                 # Dashboard — stock value, low stock, expiry, POs to receive
├── suppliers.php             # Supplier master (CRUD)
├── items.php                 # Item master (CRUD): lot switch, dept, analyser, supplier
├── departments.php           # Departments + equipment / analysers inside them
├── budget.php                # Monthly budget — live Excel sheet inside the system
├── create_po.php             # Raise / edit a Draft PO (AJAX rate auto-fill)
├── receive_po.php            # Receive (GRN) — qty, rate, lot numbers, then finalize
├── direct_grn.php            # Direct GRN — goods received with no purchase order
├── po_list.php               # PO register — ordered vs received, variance
├── view_po.php               # PO view + A4 print with logo + goods-received comparison
├── stock_report.php          # Stock & valuation (lot-wise / item-wise) + CSV export
├── stock_out.php             # Issue voucher — multi-item slip + issue register
├── users.php                 # User Management ← ADMIN ONLY
├── profile.php               # My Account — own name + own password (both roles)
├── includes/
│   ├── functions.php         # Helpers + ALL core stock business logic
│   ├── auth.php              # Login session, roles, server-side page guards
│   ├── header.php            # <head>, sidebar, topbar (set $page_title,$active)
│   └── footer.php            # Closing markup + scripts
├── ajax/
│   ├── get_last_rate.php     # ?item_id=&supplier_id= → last purchase rate + item info
│   └── get_batches.php       # ?item_id= → available lots in FIFO order
└── assets/
    ├── css/style.css         # Theme, login page, responsive sidebar, print stylesheet
    └── js/app.js             # Shared JS (number formatting, fetch wrapper, confirms)
