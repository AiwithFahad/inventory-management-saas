# Inventory Management SaaS — StockPro

Production-oriented, multi-tenant Inventory & Warehouse Management SaaS built exactly to the specified stack:
**HTML5, CSS3, Bootstrap 5, JavaScript (AJAX/Fetch), Core PHP 8+ (no Laravel/React/Vue/Node), MySQL via PDO, Chart.js, Bootstrap Icons.**

## Core design
- **Stock is derived from `stock_movements`** (`in / out / adjustment / transfer / damage / expiry / return`, signed quantities). A cached `product_warehouse.quantity` per warehouse and `products.stock_qty` total are updated **inside the same DB transaction** by one engine function (`apply_movement()` in `includes/functions.php`).
- **Negative stock prevention** is a per-tenant setting (`prevent_negative_stock`, on by default, toggle in Settings). Stock-outs, sale confirmations and transfers that would go negative are blocked and rolled back.
- **Tenant isolation:** every query is scoped by `tenant_id` from the session; stock is additionally scoped per warehouse. Roles are enforced with `require_role()` on every page.

## Features
- **Auth:** register (creates business tenant + **Business Owner** + 14-day trial subscription on the Trial plan + default **Main Warehouse** + starter categories/settings), login/logout, remember-me (hashed token cookie), forgot/reset password (hashed, expiring tokens), suspended user/tenant checks, `password_hash`, secure sessions, CSRF on every POST, XSS escaping via `e()`.
- **Roles:** Business Owner, Warehouse Manager, Stock Manager, Sales Manager, Purchase Manager, Accountant, Employee — plus platform Super Admin.
- **Dashboard:** total products, total stock quantity, stock value (qty × cost), low-stock & out-of-stock counts, suppliers, open PO/SO counts, monthly sales revenue chart, recent movements, alert banner.
- **Products:** CRUD with SKU unique per tenant, barcode (auto-generated if blank), category & brand management, unit, cost/sale prices, min/max levels, expiry-tracking flag, search + category filter, **CSV export** and **CSV import that actually parses** the uploaded file (upsert by SKU), plan product-limit enforcement.
- **Stock:** stock-in, stock-out, signed adjustment, damaged and expired recording — each writes a movement + updates the warehouse cache transactionally; current stock by warehouse; per-product movement history; batches with expiry dates.
- **Warehouses:** CRUD (location, address, racks / shelves / bins), per-warehouse inventory & valuation view, **stock transfers between warehouses** (one transaction: out of source + into destination, two movements linked by one reference), plan warehouse-limit enforcement.
- **Suppliers:** CRUD, balances derived as purchases − payments, purchase history, supplier payment recording.
- **Purchases:** multi-item purchase orders, approve action, **goods-received with partial receiving** (`received_qty` per item, batch + expiry captured, stock added transactionally, PO status → partial/received), purchase returns (stock removed if received), supplier payments.
- **Sales:** customers CRUD with derived balances & credit limits, multi-item sales orders (draft → **confirm takes stock out in one transaction** and blocks negative stock; cancel restores stock), printable **invoice view**, payment recording with unpaid/partial/paid status, sales returns (stock restored).
- **Alerts:** low stock, out of stock, batches **expiring within 30 days**, and reorder suggestions (restore to 2× minimum or 14 days of average usage, whichever is larger).
- **Finance:** sales income vs collected, receivables, purchase expense vs paid, payables, COGS from sales stock-out movements, gross profit, inventory valuation by warehouse, outstanding supplier/customer balances.
- **Reports:** stock, movements, purchases, sales, expiry and per-product profit reports with date filters and CSV export.
- **Employees:** add users with any tenant role (plan employee-limit enforced), role changes, suspend/reactivate.
- **Settings:** business settings, negative-stock toggle, low-stock threshold, profile, password change, support tickets, platform announcements.
- **Subscriptions:** Free Trial / Basic / Professional / Business / Enterprise, monthly & yearly, plan change writes subscription + payment + invoice **in one transaction**, coupon validation + discount application (demo coupon `WELCOME10`, 10% off), invoice history. Warehouse / product / employee limits are enforced from the active plan.
- **Super Admin (`super-admin/`):** platform dashboard & revenue, tenants with suspend/reactivate, plans CRUD (product/warehouse/user limits), subscriptions + payments, coupons CRUD, support tickets, announcements, platform settings, audit-log view.
- **AJAX:** global product search in the header hits `api/product_search.php` via Fetch and returns tenant-scoped JSON.
- **Audit & activity logs, notifications** on key actions.

## Installation
1. Requirements: PHP 8.1+, MySQL 8+ (or MariaDB 10.4+), a web server (Apache/Nginx) or the PHP built-in server.
2. Create the database and import the schema + demo data:
   ```bash
   mysql -u root -p < database.sql
   ```
   This creates database `stock_saas` with all tables, foreign keys, indexes and demo seed data.
3. Configure the DB connection via environment variables (or edit `config/config.php` defaults):
   `DB_HOST`, `DB_NAME` (default `stock_saas`), `DB_USER`, `DB_PASS`.
4. Serve the folder:
   ```bash
   php -S localhost:8000 -t .
   ```
5. Open `http://localhost:8000/auth/login.php`.

## Demo logins (password for all: `password`)
| Role | Email | Lands on |
|---|---|---|
| Super Admin | `super@stockpro.test` | `super-admin/index.php` |
| Business Owner (Demo Wholesale Co) | `admin@demo.com` | `dashboard.php` |
| Warehouse Manager | `warehouse@demo.com` | `dashboard.php` |
| Stock Manager | `stock@demo.com` | `dashboard.php` |
| Sales Manager | `sales@demo.com` | `dashboard.php` |
| Purchase Manager | `purchase@demo.com` | `dashboard.php` |
| Accountant | `accountant@demo.com` | `dashboard.php` |
| Employee | `employee@demo.com` | `dashboard.php` |

Demo coupon: `WELCOME10` (10% off).

Demo seed: 2 warehouses (Main + North Branch), 4 categories, 3 brands, 10 products with stock across warehouses (Protein Bars low-stock, Webcam HD out-of-stock), 2 batches expiring within 30 days, 3 suppliers, PO #1 fully received + PO #2 pending, 3 customers, 3 sales orders (2 confirmed, 1 draft), and a movement history whose per-product/per-warehouse sums exactly match the cached stock levels.

## Project structure
```
config/        DB + app config (PDO, env-based)
includes/      functions (CSRF, escaping, audit, tenant + stock engine helpers), roles, auth guards, header/footer/sidebar
auth/          login, register, logout, forgot/reset password
api/           AJAX endpoints (product search)
super-admin/   platform dashboard, tenants, plans, subscriptions, coupons, tickets, announcements, settings
assets/        CSS + JS (theme, charts, AJAX search, dynamic item rows)
database.sql   complete schema + demo seed data
vercel-demo/   static in-browser demo (see below)
```

## Deployment notes
- **PHP/MySQL hosting (production app):** deploy to any PHP 8 + MySQL host / VPS (Apache/Nginx + PHP-FPM). Set env vars, import `database.sql`, point the web root at this folder, enable HTTPS (the session cookie Secure flag activates automatically), and disable display_errors in production.
- **Payment integration:** subscription payments/invoices are recorded in the DB with a gateway-ready architecture — plug Stripe/PayPal credentials into the subscription change flow in `subscription.php` where the payment row is created, and mark payments `paid` only after gateway confirmation/webhook.
- **Vercel:** Vercel cannot run Core PHP/MySQL, so `vercel-demo/index.html` is a single self-contained static demo (Bootstrap 5 + Chart.js via CDN, data in localStorage) that mirrors StockPro — dashboard stats/charts with stock alerts, products CRUD with SKU/barcode, stock in/out/adjust actions, warehouses with transfers, suppliers, purchase orders with receiving, sales orders that take stock out, reports and plans, light/dark mode. Deploy that folder to Vercel for a live demo link; keep the full PHP app on PHP hosting / GitHub.

## Security notes
PDO prepared statements everywhere, `password_hash`/`password_verify`, CSRF tokens, output escaping, secure + HttpOnly + SameSite session cookies, tenant + warehouse-scoped queries, transactional stock updates, audit logging. Change demo passwords immediately in production and remove/rotate seeded accounts.
