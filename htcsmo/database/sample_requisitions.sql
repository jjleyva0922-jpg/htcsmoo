-- HTC Supply Management System
-- DEMO REQUISITIONS FOR MYSQL WORKBENCH / AIVEN MYSQL
--
-- Prerequisites:
--   1. Run the Laravel migrations.
--   2. Import database/inventory_catalog.sql first.
--   3. Have at least one approved user in the users table.
--
-- Safe to rerun: only the fixed DEMO-* requisitions listed below are replaced.
-- In this application, a delivered/completed request uses the status `released`.

USE `defaultdb`;

-- Match Laravel's table collation. This prevents MySQL Workbench/Aiven from
-- comparing utf8mb4_0900_ai_ci temporary strings with utf8mb4_unicode_ci columns.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

SET @requestor_id = (
    SELECT id
    FROM users
    WHERE is_approved = 1
    ORDER BY
        CASE WHEN role = 'employee' THEN 0 WHEN role = 'dept_head' THEN 1 ELSE 2 END,
        id
    LIMIT 1
);

SET @minor_item_1 = COALESCE(
    (SELECT id FROM supplies WHERE item_name = 'Bond Paper' AND requisition_tier = 'minor' LIMIT 1),
    (SELECT id FROM supplies WHERE requisition_tier = 'minor' ORDER BY id LIMIT 1)
);
SET @minor_item_2 = COALESCE(
    (SELECT id FROM supplies WHERE item_name = 'Ballpoint Pen' AND requisition_tier = 'minor' LIMIT 1),
    (SELECT id FROM supplies WHERE requisition_tier = 'minor' AND id <> @minor_item_1 ORDER BY id LIMIT 1),
    @minor_item_1
);
SET @major_item_1 = COALESCE(
    (SELECT id FROM supplies WHERE item_name = 'Laptop Computer' AND requisition_tier = 'major' LIMIT 1),
    (SELECT id FROM supplies WHERE requisition_tier = 'major' ORDER BY id LIMIT 1)
);
SET @major_item_2 = COALESCE(
    (SELECT id FROM supplies WHERE item_name = 'Multimedia Projector' AND requisition_tier = 'major' LIMIT 1),
    (SELECT id FROM supplies WHERE requisition_tier = 'major' AND id <> @major_item_1 ORDER BY id LIMIT 1),
    @major_item_1
);

-- Preflight result: all four values must be non-NULL before the inserts can succeed.
SELECT
    @requestor_id AS requestor_user_id,
    @minor_item_1 AS minor_supply_1,
    @minor_item_2 AS minor_supply_2,
    @major_item_1 AS major_supply_1,
    @major_item_2 AS major_supply_2;

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS demo_request_seed;
CREATE TEMPORARY TABLE demo_request_seed (
    tracking_number VARCHAR(50) PRIMARY KEY,
    request_type VARCHAR(10) NOT NULL,
    status VARCHAR(40) NOT NULL,
    purpose TEXT NOT NULL,
    remarks TEXT NULL,
    created_days_ago INT NOT NULL,
    created_hours_ago INT NOT NULL DEFAULT 0,
    updated_days_ago INT NOT NULL,
    updated_hours_ago INT NOT NULL DEFAULT 0,
    supply_id_1 BIGINT UNSIGNED NOT NULL,
    quantity_1 INT NOT NULL,
    supply_id_2 BIGINT UNSIGNED NULL,
    quantity_2 INT NULL
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO demo_request_seed
    (tracking_number, request_type, status, purpose, remarks,
     created_days_ago, created_hours_ago, updated_days_ago, updated_hours_ago,
     supply_id_1, quantity_1, supply_id_2, quantity_2)
VALUES
    ('DEMO-MIN-PENDING-01', 'minor', 'pending',
     'For printing weekly classroom exercises and student assessment sheets.', NULL,
     0, 6, 0, 6, @minor_item_1, 2, @minor_item_2, 8),

    ('DEMO-MAJ-PENDING-02', 'major', 'pending',
     'For the replacement of an aging faculty workstation used for academic records.', NULL,
     1, 3, 1, 3, @major_item_1, 1, NULL, NULL),

    ('DEMO-MIN-DEPT-03', 'minor', 'approved_dept',
     'For administrative forms required during the upcoming enrollment period.',
     'Endorsed by the department and awaiting VP Finance review.',
     2, 4, 1, 10, @minor_item_1, 2, @minor_item_2, 5),

    ('DEMO-MAJ-DEPT-04', 'major', 'approved_dept',
     'For classroom presentation and multimedia instruction requirements.',
     'Department endorsement completed; awaiting VP Administration review.',
     3, 0, 2, 4, @major_item_2, 1, NULL, NULL),

    ('DEMO-MAJ-VP-05', 'major', 'approved_vp',
     'For upgrading the equipment used in faculty research presentations.',
     'VP Administration endorsed the request; awaiting Provost review.',
     4, 0, 2, 0, @major_item_1, 1, @major_item_2, 1),

    ('DEMO-MAJ-PROVOST-06', 'major', 'approved_provost',
     'For the replacement of obsolete instructional technology equipment.',
     'Provost endorsement completed; awaiting Presidential approval.',
     5, 0, 1, 6, @major_item_2, 1, NULL, NULL),

    ('DEMO-MAJ-READY-07', 'major', 'approved_president',
     'For a laboratory workstation approved under the institutional equipment plan.',
     'Fully approved and ready for SMO release.',
     6, 0, 0, 10, @major_item_1, 1, NULL, NULL),

    -- This record is fully approved and has waited 2 days: near-deadline warning.
    ('DEMO-MIN-NEAR-08', 'minor', 'approved_vp',
     'For examination printing supplies needed before the scheduled testing week.',
     'Fully approved two days ago and nearing the three-day release deadline.',
     5, 0, 2, 2, @minor_item_1, 2, @minor_item_2, 4),

    -- This record is fully approved and has waited 4 days: overdue SLA warning.
    ('DEMO-MIN-OVERDUE-09', 'minor', 'approved_vp',
     'For urgent printing of accreditation evidence and compliance documents.',
     'Fully approved four days ago but not yet released by SMO.',
     8, 0, 4, 1, @minor_item_1, 2, @minor_item_2, 6),

    -- Major request exceeding the same three-day release deadline.
    ('DEMO-MAJ-OVERDUE-10', 'major', 'approved_president',
     'For replacement equipment required by the institutional computer laboratory.',
     'Presidential approval was completed five days ago; SMO release is overdue.',
     12, 0, 5, 0, @major_item_1, 1, NULL, NULL),

    ('DEMO-MIN-INQUIRY-11', 'minor', 'for_clarification',
     'For additional office materials requested outside the normal monthly allocation.',
     'Please clarify the unusually high requested quantity with the Department Head.',
     2, 0, 1, 5, @minor_item_2, 12, NULL, NULL),

    ('DEMO-MAJ-REJECTED-12', 'major', 'rejected',
     'For equipment that was proposed without an approved procurement plan.',
     'Rejected because supporting justification and budget authority were incomplete.',
     9, 0, 7, 0, @major_item_2, 1, NULL, NULL),

    ('DEMO-MIN-RELEASED-13', 'minor', 'released',
     'For routine office printing and records preparation.',
     'Delivered to the requestor and acknowledged by the receiving office.',
     10, 0, 1, 0, @minor_item_1, 2, @minor_item_2, 5),

    ('DEMO-MAJ-RELEASED-14', 'major', 'released',
     'For the faculty development room presentation system.',
     'Equipment released and delivered after completing the full approval chain.',
     35, 0, 25, 0, @major_item_2, 1, NULL, NULL);

-- Remove only this script's fixed sample records. Related items and logs cascade-delete.
DELETE r
FROM requisitions r
INNER JOIN demo_request_seed d ON d.tracking_number = r.tracking_number;

INSERT INTO requisitions
    (tracking_number, user_id, request_type, status, purpose, remarks, grand_total, created_at, updated_at)
SELECT
    d.tracking_number,
    @requestor_id,
    d.request_type,
    d.status,
    d.purpose,
    d.remarks,
    ROUND((s1.unit_price * d.quantity_1) + COALESCE(s2.unit_price * d.quantity_2, 0), 2),
    TIMESTAMPADD(HOUR, -d.created_hours_ago, TIMESTAMPADD(DAY, -d.created_days_ago, NOW())),
    TIMESTAMPADD(HOUR, -d.updated_hours_ago, TIMESTAMPADD(DAY, -d.updated_days_ago, NOW()))
FROM demo_request_seed d
INNER JOIN supplies s1 ON s1.id = d.supply_id_1
LEFT JOIN supplies s2 ON s2.id = d.supply_id_2;

-- First requested item for every sample requisition.
INSERT INTO requisition_items
    (requisition_id, supply_id, item_name, specifications, quantity, unit, unit_price, subtotal, created_at, updated_at)
SELECT
    r.id,
    s.id,
    s.item_name,
    CONCAT_WS(' | ', s.specifications, CONCAT('Approved brands: ', COALESCE(s.brand, 'Standard'))),
    d.quantity_1,
    s.unit,
    s.unit_price,
    ROUND(s.unit_price * d.quantity_1, 2),
    r.created_at,
    r.updated_at
FROM demo_request_seed d
INNER JOIN requisitions r ON r.tracking_number = d.tracking_number
INNER JOIN supplies s ON s.id = d.supply_id_1;

-- Optional second requested item.
INSERT INTO requisition_items
    (requisition_id, supply_id, item_name, specifications, quantity, unit, unit_price, subtotal, created_at, updated_at)
SELECT
    r.id,
    s.id,
    s.item_name,
    CONCAT_WS(' | ', s.specifications, CONCAT('Approved brands: ', COALESCE(s.brand, 'Standard'))),
    d.quantity_2,
    s.unit,
    s.unit_price,
    ROUND(s.unit_price * d.quantity_2, 2),
    r.created_at,
    r.updated_at
FROM demo_request_seed d
INNER JOIN requisitions r ON r.tracking_number = d.tracking_number
INNER JOIN supplies s ON s.id = d.supply_id_2
WHERE d.supply_id_2 IS NOT NULL AND d.quantity_2 IS NOT NULL;

-- Department Head approvals for every request that progressed beyond the first stage.
INSERT INTO approval_logs (requisition_id, action, role, remarks, created_at, updated_at)
SELECT r.id, 'Approved by Department Head', 'dept_head',
       'Department need and requested quantities were verified.',
       TIMESTAMPADD(HOUR, 8, r.created_at), TIMESTAMPADD(HOUR, 8, r.created_at)
FROM requisitions r
INNER JOIN demo_request_seed d ON d.tracking_number = r.tracking_number
WHERE r.status IN ('approved_dept', 'approved_vp', 'approved_provost', 'approved_president', 'released');

-- VP Finance approvals for progressed minor requests.
INSERT INTO approval_logs (requisition_id, action, role, remarks, created_at, updated_at)
SELECT r.id, 'Approved by VP for Finance', 'vp_finance',
       'Budget availability and minor requisition value were verified.',
       TIMESTAMPADD(HOUR, 20, r.created_at), TIMESTAMPADD(HOUR, 20, r.created_at)
FROM requisitions r
INNER JOIN demo_request_seed d ON d.tracking_number = r.tracking_number
WHERE r.request_type = 'minor' AND r.status IN ('approved_vp', 'released');

-- VP Administration approvals for progressed major requests.
INSERT INTO approval_logs (requisition_id, action, role, remarks, created_at, updated_at)
SELECT r.id, 'Approved by VP for Administration', 'vp_admin',
       'Administrative need and equipment request were endorsed.',
       TIMESTAMPADD(DAY, 1, r.created_at), TIMESTAMPADD(DAY, 1, r.created_at)
FROM requisitions r
INNER JOIN demo_request_seed d ON d.tracking_number = r.tracking_number
WHERE r.request_type = 'major' AND r.status IN ('approved_vp', 'approved_provost', 'approved_president', 'released');

-- Provost approvals for major requests that reached the executive stages.
INSERT INTO approval_logs (requisition_id, action, role, remarks, created_at, updated_at)
SELECT r.id, 'Approved by School Provost', 'provost',
       'Academic and institutional justification were confirmed.',
       TIMESTAMPADD(DAY, 2, r.created_at), TIMESTAMPADD(DAY, 2, r.created_at)
FROM requisitions r
INNER JOIN demo_request_seed d ON d.tracking_number = r.tracking_number
WHERE r.request_type = 'major' AND r.status IN ('approved_provost', 'approved_president', 'released');

-- Presidential approvals for fully approved major requests.
INSERT INTO approval_logs (requisition_id, action, role, remarks, created_at, updated_at)
SELECT r.id, 'Approved by School President', 'president',
       'Final executive approval granted for institutional procurement.',
       TIMESTAMPADD(DAY, 3, r.created_at), TIMESTAMPADD(DAY, 3, r.created_at)
FROM requisitions r
INNER JOIN demo_request_seed d ON d.tracking_number = r.tracking_number
WHERE r.request_type = 'major' AND r.status IN ('approved_president', 'released');

-- Clarification entry. The role is important because it controls who can resolve it.
INSERT INTO approval_logs (requisition_id, action, role, remarks, created_at, updated_at)
SELECT r.id, 'Flagged for Office Clarification by Department Head', 'dept_head', r.remarks, r.updated_at, r.updated_at
FROM requisitions r
INNER JOIN demo_request_seed d ON d.tracking_number = r.tracking_number
WHERE r.status = 'for_clarification';

-- Rejection entry.
INSERT INTO approval_logs (requisition_id, action, role, remarks, created_at, updated_at)
SELECT r.id, 'Rejected by Department Head', 'dept_head', r.remarks, r.updated_at, r.updated_at
FROM requisitions r
INNER JOIN demo_request_seed d ON d.tracking_number = r.tracking_number
WHERE r.status = 'rejected';

-- Delivery/release entry.
INSERT INTO approval_logs (requisition_id, action, role, remarks, created_at, updated_at)
SELECT r.id, 'Released and Fulfilled by SMO In-Charge', 'smo', r.remarks, r.updated_at, r.updated_at
FROM requisitions r
INNER JOIN demo_request_seed d ON d.tracking_number = r.tracking_number
WHERE r.status = 'released';

COMMIT;

-- Verification: expected result is 14 demo requisitions.
SELECT
    r.tracking_number,
    r.request_type,
    r.status,
    u.name AS requestor,
    d.dept_code AS department,
    r.grand_total,
    r.created_at,
    r.updated_at,
    TIMESTAMPDIFF(DAY, r.updated_at, NOW()) AS days_waiting_after_last_update,
    COUNT(DISTINCT ri.id) AS item_count,
    COUNT(DISTINCT al.id) AS audit_log_count
FROM requisitions r
INNER JOIN users u ON u.id = r.user_id
LEFT JOIN departments d ON d.id = u.department_id
LEFT JOIN requisition_items ri ON ri.requisition_id = r.id
LEFT JOIN approval_logs al ON al.requisition_id = r.id
WHERE r.tracking_number LIKE 'DEMO-%'
GROUP BY r.id, r.tracking_number, r.request_type, r.status, u.name, d.dept_code,
         r.grand_total, r.created_at, r.updated_at
ORDER BY r.created_at DESC;

SELECT status, COUNT(*) AS demo_count
FROM requisitions
WHERE tracking_number LIKE 'DEMO-%'
GROUP BY status
ORDER BY status;

DROP TEMPORARY TABLE IF EXISTS demo_request_seed;
