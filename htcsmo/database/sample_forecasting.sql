-- HTC Supply Management System: SYNTHETIC forecasting presentation data.
-- MySQL 8 / MariaDB. Run all current Laravel migrations first.
-- Select your presentation database in Workbench/phpMyAdmin before running.
-- No USE statement is included, so this uses your selected database.
-- Requires an approved, active SMO or admin account; creates no accounts.
-- Run the ENTIRE file with stop-on-error enabled. On error, run ROLLBACK.
-- If an error stops the demo reset, also run SET SESSION SQL_SAFE_UPDATES = 1.
-- Rerunning resets ONLY the six reserved demo supplies and their tagged logs.
-- These are simulated quantities/prices, not actual institutional usage.
USE `defaultdb`;

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
-- Match the application's Asia/Manila date even on a UTC database server.
SET time_zone = '+08:00';
SET @demo_tag = 'HTC-FORECAST-DEMO-V1: Synthetic presentation data';
SET @demo_month = CAST(DATE_FORMAT(CURRENT_DATE, '%Y-%m-01') AS DATE);

DROP TEMPORARY TABLE IF EXISTS forecast_demo_context;
CREATE TEMPORARY TABLE forecast_demo_context (
    user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY
);
INSERT INTO forecast_demo_context (user_id)
SELECT id FROM users
WHERE account_status = 'active' AND role IN ('smo', 'admin')
ORDER BY CASE WHEN role = 'smo' THEN 0 ELSE 1 END, id
LIMIT 1;

SELECT CASE WHEN COUNT(*) = 1
    THEN 'READY: demo inventory and release history will be created.'
    ELSE 'NO DATA INSERTED: an approved, active SMO or admin is required.'
END AS preflight_result
FROM forecast_demo_context;

DROP TEMPORARY TABLE IF EXISTS forecast_demo_items;
CREATE TEMPORARY TABLE forecast_demo_items (
    item_name VARCHAR(255) NOT NULL PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    unit_name VARCHAR(50) NOT NULL,
    current_stock INT NOT NULL,
    min_stock INT NOT NULL,
    unit_price DECIMAL(15, 2) NOT NULL,
    base_usage INT NOT NULL,
    pattern VARCHAR(20) NOT NULL
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO forecast_demo_items VALUES
('[DEMO FORECAST] Bond Paper A4', 'Office Supplies', 'Ream', 8, 10, 285.00, 40, 'examination'),
('[DEMO FORECAST] Whiteboard Marker', 'Office Supplies', 'Piece', 0, 12, 65.00, 30, 'semester'),
('[DEMO FORECAST] Ballpoint Pen', 'Office Supplies', 'Piece', 30, 20, 12.00, 60, 'steady'),
('[DEMO FORECAST] Alcohol 500mL', 'Medical Supplies', 'Bottle', 10, 8, 85.00, 24, 'rising'),
('[DEMO FORECAST] Liquid Hand Soap', 'Cleaning Supplies', 'Bottle', 35, 10, 95.00, 32, 'steady'),
('[DEMO FORECAST] Brown Envelope', 'Office Supplies', 'Piece', 1000, 30, 10.00, 50, 'steady');

DROP TEMPORARY TABLE IF EXISTS forecast_demo_months;
CREATE TEMPORARY TABLE forecast_demo_months (
    month_offset INT NOT NULL PRIMARY KEY,
    usage_month DATE NOT NULL
);
-- Offsets 0 through 23: current month plus the previous 23 months.
INSERT INTO forecast_demo_months (month_offset, usage_month)
SELECT n, TIMESTAMPADD(MONTH, -n, @demo_month)
FROM (
    SELECT ones.n + tens.n * 10 AS n
    FROM (
        SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3
        UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7
        UNION ALL SELECT 8 UNION ALL SELECT 9
    ) ones
    CROSS JOIN (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2) tens
) offsets
WHERE n < 24;

START TRANSACTION;

INSERT INTO supply_categories (category_name, description, created_at, updated_at)
SELECT DISTINCT d.category_name, 'Supply category', NOW(), NOW()
FROM forecast_demo_items d
CROSS JOIN forecast_demo_context ctx
WHERE NOT EXISTS (
    SELECT 1 FROM supply_categories c WHERE c.category_name = d.category_name
);

INSERT INTO supply_units (unit_name, created_at, updated_at)
SELECT DISTINCT d.unit_name, NOW(), NOW()
FROM forecast_demo_items d
CROSS JOIN forecast_demo_context ctx
WHERE NOT EXISTS (SELECT 1 FROM supply_units u WHERE u.unit_name = d.unit_name);

-- Uses normalized category/unit columns from the October 19 migrations.
INSERT INTO supplies
    (supply_category_id, supply_unit_id, item_name, requisition_tier,
     specifications, quantity, unit_price, min_stock_level, created_at, updated_at)
SELECT c.id, u.id, d.item_name, 'minor', @demo_tag,
       d.current_stock, d.unit_price, d.min_stock,
       TIMESTAMPADD(MONTH, -23, @demo_month), NOW()
FROM forecast_demo_items d
INNER JOIN supply_categories c ON c.category_name = d.category_name
INNER JOIN supply_units u ON u.unit_name = d.unit_name
CROSS JOIN forecast_demo_context ctx
WHERE NOT EXISTS (SELECT 1 FROM supplies s WHERE s.item_name = d.item_name);

-- Workbench safe updates rejects these tag-based resets (the tag is not a key).
-- Change only this connection's setting, restoring it immediately after reset.
SET @forecast_demo_safe_updates = @@SESSION.SQL_SAFE_UPDATES;
SET SESSION SQL_SAFE_UPDATES = 0;

UPDATE supplies s
INNER JOIN forecast_demo_items d ON d.item_name = s.item_name
CROSS JOIN forecast_demo_context ctx
SET s.quantity = d.current_stock, s.min_stock_level = d.min_stock,
    s.unit_price = d.unit_price, s.deleted_at = NULL, s.updated_at = NOW()
WHERE s.specifications = @demo_tag;

DELETE l FROM supply_inventory_logs l
INNER JOIN supplies s ON s.id = l.supply_id
INNER JOIN forecast_demo_items d ON d.item_name = s.item_name
CROSS JOIN forecast_demo_context ctx
WHERE s.specifications = @demo_tag AND l.remarks = @demo_tag;

SET SESSION SQL_SAFE_UPDATES = @forecast_demo_safe_updates;

DROP TEMPORARY TABLE IF EXISTS forecast_demo_usage;
CREATE TEMPORARY TABLE forecast_demo_usage (
    supply_id BIGINT UNSIGNED NOT NULL,
    usage_month DATE NOT NULL,
    used_quantity INT NOT NULL,
    current_stock INT NOT NULL,
    PRIMARY KEY (supply_id, usage_month)
);

INSERT INTO forecast_demo_usage
SELECT s.id, m.usage_month,
       CAST(CEIL(d.base_usage * CASE
           WHEN d.pattern = 'examination' AND MONTH(m.usage_month) IN (3, 10, 11) THEN 2.5
           WHEN d.pattern = 'examination' AND MONTH(m.usage_month) = 6 THEN 1.8
           WHEN d.pattern = 'semester' AND MONTH(m.usage_month) IN (6, 11) THEN 2.4
           WHEN d.pattern = 'rising' AND m.month_offset <= 2 THEN 2.0
           WHEN d.pattern = 'rising' AND m.month_offset <= 5 THEN 1.5
           ELSE 1.0
       END) AS SIGNED), d.current_stock
FROM forecast_demo_items d
INNER JOIN supplies s ON s.item_name = d.item_name AND s.specifications = @demo_tag
CROSS JOIN forecast_demo_months m
CROSS JOIN forecast_demo_context ctx;

-- Opening balances followed by matched monthly restocks and releases.
-- Each release returns to the configured demo stock, so balances reconcile.
-- Historical restocks do not contribute to the forecasting algorithm.
INSERT INTO supply_inventory_logs
    (supply_id, user_id, requisition_id, transaction_type,
     quantity_change, balance_after, remarks, created_at, updated_at)
SELECT s.id, ctx.user_id, NULL, 'restock_delivery', d.current_stock,
       d.current_stock, @demo_tag,
       TIMESTAMPADD(SECOND, -1, TIMESTAMPADD(MONTH, -23, @demo_month)),
       TIMESTAMPADD(SECOND, -1, TIMESTAMPADD(MONTH, -23, @demo_month))
FROM forecast_demo_items d
INNER JOIN supplies s ON s.item_name = d.item_name AND s.specifications = @demo_tag
CROSS JOIN forecast_demo_context ctx
WHERE d.current_stock > 0;

INSERT INTO supply_inventory_logs
    (supply_id, user_id, requisition_id, transaction_type,
     quantity_change, balance_after, remarks, created_at, updated_at)
SELECT h.supply_id, ctx.user_id, NULL, 'restock_delivery', h.used_quantity,
       h.current_stock + h.used_quantity, @demo_tag, h.usage_month, h.usage_month
FROM forecast_demo_usage h
CROSS JOIN forecast_demo_context ctx
ORDER BY h.usage_month, h.supply_id;

-- The forecast reads ONLY release_deduction rows with negative quantities.
INSERT INTO supply_inventory_logs
    (supply_id, user_id, requisition_id, transaction_type,
     quantity_change, balance_after, remarks, created_at, updated_at)
SELECT h.supply_id, ctx.user_id, NULL, 'release_deduction', -h.used_quantity,
       h.current_stock, @demo_tag,
       TIMESTAMPADD(SECOND, 1, h.usage_month), TIMESTAMPADD(SECOND, 1, h.usage_month)
FROM forecast_demo_usage h
CROSS JOIN forecast_demo_context ctx
ORDER BY h.usage_month, h.supply_id;

COMMIT;

-- Expected: six rows, each with 24 months and 24 release records (144 total).
SELECT s.item_name, s.quantity AS current_stock, s.unit_price,
       COUNT(DISTINCT DATE_FORMAT(l.created_at, '%Y-%m')) AS months_of_history,
       COUNT(l.id) AS release_records, COALESCE(SUM(-l.quantity_change), 0) AS total_usage
FROM supplies s
INNER JOIN forecast_demo_items d ON d.item_name = s.item_name
LEFT JOIN supply_inventory_logs l ON l.supply_id = s.id
    AND l.transaction_type = 'release_deduction' AND l.remarks = @demo_tag
WHERE s.specifications = @demo_tag
GROUP BY s.id, s.item_name, s.quantity, s.unit_price
ORDER BY s.item_name;

DROP TEMPORARY TABLE IF EXISTS forecast_demo_usage;
DROP TEMPORARY TABLE IF EXISTS forecast_demo_months;
DROP TEMPORARY TABLE IF EXISTS forecast_demo_items;
DROP TEMPORARY TABLE IF EXISTS forecast_demo_context;

-- After importing, run: php artisan cache:clear
-- Then open Inventory > Forecast and select Monthly or Quarterly.
