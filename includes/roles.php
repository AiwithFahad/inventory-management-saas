<?php
// Role sets for StockPro tenant pages.
define('ROLE_ALL', ['owner','warehouse_manager','stock_manager','sales_manager','purchase_manager','accountant','employee']);
define('ROLE_OWNER', ['owner']);
define('ROLE_STOCK', ['owner','warehouse_manager','stock_manager']);
define('ROLE_SALES', ['owner','sales_manager','accountant']);
define('ROLE_PURCHASE', ['owner','purchase_manager','warehouse_manager','accountant']);
define('ROLE_MONEY', ['owner','accountant']);
define('ROLE_MANAGE', ['owner','warehouse_manager']);
