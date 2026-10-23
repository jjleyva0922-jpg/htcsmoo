-- HTC Supply Management System
-- Legacy bootstrap import for databases that have not yet run migration
-- 2026_10_19_021000_normalize_supply_options. Do not rerun this file after
-- that migration; manage the normalized catalog through the application.
-- Paste this entire script into MySQL Workbench while connected to the Aiven `defaultdb` database.
-- Safe to run again: item names are unique and existing stock quantities are preserved.

USE `defaultdb`;

START TRANSACTION;

INSERT INTO supply_categories (category_name, description, created_at, updated_at) VALUES
('Cleaning Supplies', 'Sanitation, hygiene chemicals, soaps, and janitorial consumables.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('Construction Supplies', 'Campus repair, masonry, lumber, and building materials.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('Drugs and Medicines', 'Clinic medicines and first-aid medications.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('Maintenance Supplies', 'Electrical, hardware, lighting, fixtures, and campus upkeep items.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('Medical Supplies', 'Clinical diagnostic tools, masks, alcohol, and health essentials.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('Non-Medical Supplies', 'General utility materials and institutional equipment.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('Office Supplies', 'Paper, writing tools, desktop consumables, and office equipment.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE
    description = VALUES(description),
    updated_at = CURRENT_TIMESTAMP;

SET @cleaning = (SELECT id FROM supply_categories WHERE category_name = 'Cleaning Supplies' LIMIT 1);
SET @construction = (SELECT id FROM supply_categories WHERE category_name = 'Construction Supplies' LIMIT 1);
SET @medicine = (SELECT id FROM supply_categories WHERE category_name = 'Drugs and Medicines' LIMIT 1);
SET @maintenance = (SELECT id FROM supply_categories WHERE category_name = 'Maintenance Supplies' LIMIT 1);
SET @medical = (SELECT id FROM supply_categories WHERE category_name = 'Medical Supplies' LIMIT 1);
SET @nonmedical = (SELECT id FROM supply_categories WHERE category_name = 'Non-Medical Supplies' LIMIT 1);
SET @office = (SELECT id FROM supply_categories WHERE category_name = 'Office Supplies' LIMIT 1);

-- MINOR REQUISITION CATALOG
INSERT INTO supplies
    (supply_category_id, item_name, brand, model_number, requisition_tier,
     specifications, configurable_options, quantity, unit, unit_price, min_stock_level, created_at, updated_at)
VALUES
(@office, 'Bond Paper', 'PaperOne, Double A, Hard Copy, Copy One', NULL, 'minor', '80 gsm multipurpose copy paper, 500 sheets per ream', JSON_OBJECT('Size', JSON_ARRAY('A4', 'Long', 'Short'), 'Color', JSON_ARRAY('White')), 120, 'Ream', 285.00, 30, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Colored Copy Paper', 'PaperOne, Hard Copy, Advance', NULL, 'minor', '80 gsm, 100 sheets per pack', JSON_OBJECT('Size', JSON_ARRAY('A4', 'Short'), 'Color', JSON_ARRAY('Blue', 'Green', 'Pink', 'Yellow')), 50, 'Pack', 145.00, 12, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Cartolina', 'Joy, Advance, Sterling', NULL, 'minor', 'Standard classroom display board', JSON_OBJECT('Color', JSON_ARRAY('White', 'Black', 'Blue', 'Green', 'Red', 'Yellow')), 100, 'Sheet', 18.00, 25, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Manila Paper', 'Joy, Advance, Sterling', NULL, 'minor', 'Standard full-size sheet', JSON_OBJECT('Type', JSON_ARRAY('Plain', 'Grid')), 150, 'Sheet', 12.00, 30, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Ballpoint Pen', 'Panda, Pilot, HBW, Faber-Castell', NULL, 'minor', 'Retractable writing pen', JSON_OBJECT('Ink Color', JSON_ARRAY('Black', 'Blue', 'Red'), 'Tip Size', JSON_ARRAY('0.5 mm', '0.7 mm')), 300, 'Piece', 12.00, 60, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Gel Pen', 'Pilot, Dong-A, M&G', NULL, 'minor', 'Smooth gel ink pen', JSON_OBJECT('Ink Color', JSON_ARRAY('Black', 'Blue', 'Red'), 'Tip Size', JSON_ARRAY('0.5 mm', '0.7 mm')), 120, 'Piece', 58.00, 25, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Wooden Pencil', 'Mongol, Faber-Castell, Staedtler', 'No. 2', 'minor', 'Graphite writing pencil with eraser', JSON_OBJECT('Grade', JSON_ARRAY('HB', '2B')), 240, 'Piece', 15.00, 50, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Permanent Marker', 'Pilot, Artline, Sharpie', NULL, 'minor', 'Quick-drying permanent ink', JSON_OBJECT('Ink Color', JSON_ARRAY('Black', 'Blue', 'Red'), 'Tip', JSON_ARRAY('Fine', 'Broad')), 90, 'Piece', 42.00, 20, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Whiteboard Marker', 'Pilot, Artline, Dong-A', NULL, 'minor', 'Refillable dry-erase marker', JSON_OBJECT('Ink Color', JSON_ARRAY('Black', 'Blue', 'Green', 'Red')), 100, 'Piece', 65.00, 24, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Highlighter Pen', 'Stabilo, Faber-Castell, Sharpie', NULL, 'minor', 'Chisel-tip fluorescent highlighter', JSON_OBJECT('Color', JSON_ARRAY('Yellow', 'Green', 'Orange', 'Pink', 'Blue')), 80, 'Piece', 48.00, 20, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Correction Tape', 'Tombow', NULL, 'minor', '5 mm x 8 m correction tape', NULL, 75, 'Piece', 55.00, 15, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Pencil Eraser', 'Mongol', NULL, 'minor', 'Soft non-abrasive rubber eraser', NULL, 100, 'Piece', 12.00, 20, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Pencil Sharpener', 'Maped', NULL, 'minor', 'Manual sharpener with shavings container', JSON_OBJECT('Type', JSON_ARRAY('Single Hole', 'Double Hole')), 50, 'Piece', 35.00, 10, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Desktop Stapler', 'Max', 'HD-10D', 'minor', 'Compact metal stapler for No. 10 wire', NULL, 35, 'Piece', 165.00, 8, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Staple Wire', 'Max', NULL, 'minor', 'Staple wire, 1000 staples per box', JSON_OBJECT('Size', JSON_ARRAY('No. 10', 'No. 35', 'No. 3')), 100, 'Box', 38.00, 20, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Paper Clips', 'Joy', NULL, 'minor', 'Vinyl-coated paper clips, 100 pieces per box', JSON_OBJECT('Size', JSON_ARRAY('28 mm', '33 mm', '50 mm')), 80, 'Box', 35.00, 15, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Binder Clips', 'Joy', NULL, 'minor', 'Black metal binder clips, 12 pieces per box', JSON_OBJECT('Size', JSON_ARRAY('19 mm', '25 mm', '32 mm', '41 mm')), 75, 'Box', 58.00, 15, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Expanding File Folder', 'Deli', NULL, 'minor', 'Document organizer with multiple pockets', JSON_OBJECT('Size', JSON_ARRAY('A4', 'Long'), 'Color', JSON_ARRAY('Black', 'Blue', 'Green', 'Red')), 40, 'Piece', 185.00, 10, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Plastic Envelope Folder', 'Joy', NULL, 'minor', 'Reusable snap-button document envelope', JSON_OBJECT('Size', JSON_ARRAY('A4', 'Long'), 'Color', JSON_ARRAY('Clear', 'Blue', 'Green', 'Red', 'Yellow')), 100, 'Piece', 28.00, 20, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Brown Mailing Envelope', 'Star', NULL, 'minor', 'Kraft document envelope', JSON_OBJECT('Size', JSON_ARRAY('A4', 'Long', 'Legal')), 120, 'Piece', 10.00, 25, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Index Card', 'Advance', NULL, 'minor', 'Ruled index cards, 100 sheets per pack', JSON_OBJECT('Size', JSON_ARRAY('3 x 5 in', '4 x 6 in', '5 x 8 in')), 70, 'Pack', 75.00, 15, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Sticky Notes', 'Post-it', NULL, 'minor', 'Self-adhesive note pad, 100 sheets', JSON_OBJECT('Size', JSON_ARRAY('1.5 x 2 in', '3 x 3 in'), 'Color', JSON_ARRAY('Yellow', 'Assorted Neon', 'Assorted Pastel')), 90, 'Pad', 65.00, 20, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Record Book', 'Advance', NULL, 'minor', 'Hardbound ruled record book', JSON_OBJECT('Pages', JSON_ARRAY('300 Pages', '500 Pages')), 60, 'Piece', 155.00, 12, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Legal Pad', 'Victory', NULL, 'minor', 'Ruled writing pad, 50 sheets', JSON_OBJECT('Size', JSON_ARRAY('A4', 'Legal'), 'Color', JSON_ARRAY('White', 'Yellow')), 75, 'Pad', 58.00, 15, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Transparent Tape', '3M', NULL, 'minor', 'Clear adhesive tape', JSON_OBJECT('Width', JSON_ARRAY('12 mm', '18 mm', '24 mm')), 100, 'Roll', 45.00, 20, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Masking Tape', '3M', NULL, 'minor', 'General-purpose masking tape', JSON_OBJECT('Width', JSON_ARRAY('12 mm', '24 mm', '48 mm')), 80, 'Roll', 72.00, 15, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Glue Stick', 'Elmers', NULL, 'minor', 'Washable solid adhesive', JSON_OBJECT('Size', JSON_ARRAY('8 g', '21 g', '40 g')), 90, 'Piece', 48.00, 18, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Multipurpose Scissors', 'Maped', NULL, 'minor', 'Stainless steel scissors', JSON_OBJECT('Size', JSON_ARRAY('6 in', '8 in')), 45, 'Piece', 125.00, 10, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Utility Cutter', 'Deli', NULL, 'minor', 'Retractable utility knife', JSON_OBJECT('Size', JSON_ARRAY('Small 9 mm', 'Large 18 mm')), 45, 'Piece', 85.00, 10, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Rubber Bands', 'Joy', NULL, 'minor', 'Assorted rubber bands, 100 g per pack', NULL, 60, 'Pack', 55.00, 12, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Stamp Pad', 'Trodat', NULL, 'minor', 'Felt stamp pad in plastic case', JSON_OBJECT('Ink Color', JSON_ARRAY('Black', 'Blue', 'Red')), 35, 'Piece', 95.00, 8, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Stamp Pad Ink', 'Trodat', NULL, 'minor', '28 ml refill ink', JSON_OBJECT('Ink Color', JSON_ARRAY('Black', 'Blue', 'Red')), 40, 'Bottle', 110.00, 8, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@cleaning, 'Isopropyl Alcohol', 'Green Cross', NULL, 'minor', '70 percent isopropyl alcohol', JSON_OBJECT('Size', JSON_ARRAY('500 ml', '1 Liter')), 90, 'Bottle', 145.00, 20, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@cleaning, 'Liquid Hand Soap', 'Safeguard', NULL, 'minor', 'Antibacterial liquid hand soap', JSON_OBJECT('Size', JSON_ARRAY('500 ml', '1 Liter', '1 Gallon')), 50, 'Bottle', 185.00, 12, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@cleaning, 'Disinfectant Bleach', 'Zonrox', NULL, 'minor', 'Multipurpose disinfecting bleach', JSON_OBJECT('Size', JSON_ARRAY('1 Liter', '1 Gallon')), 55, 'Bottle', 98.00, 12, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@cleaning, 'Powder Detergent', 'Champion', NULL, 'minor', 'Heavy-duty cleaning detergent', JSON_OBJECT('Size', JSON_ARRAY('1 kg', '3 kg')), 45, 'Pack', 165.00, 10, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@cleaning, 'Garbage Bag', 'Clean Up', NULL, 'minor', 'Heavy-duty garbage bags, 10 pieces per roll', JSON_OBJECT('Size', JSON_ARRAY('Small', 'Medium', 'Large', 'Extra Large'), 'Color', JSON_ARRAY('Black', 'Clear')), 100, 'Roll', 95.00, 25, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@cleaning, 'Facial Tissue', 'Sanicare', NULL, 'minor', 'Two-ply facial tissue, 100 pulls', NULL, 100, 'Box', 78.00, 25, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@cleaning, 'Toilet Tissue', 'Sanicare', NULL, 'minor', 'Two-ply bathroom tissue, 12 rolls per pack', NULL, 70, 'Pack', 225.00, 18, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@cleaning, 'Microfiber Cleaning Cloth', 'Scotch-Brite', NULL, 'minor', 'Reusable absorbent cleaning cloth', JSON_OBJECT('Color', JSON_ARRAY('Blue', 'Green', 'Red', 'Yellow')), 70, 'Piece', 75.00, 15, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@cleaning, 'Soft Broom', 'Home Gallery', NULL, 'minor', 'Indoor soft-bristle broom', NULL, 35, 'Piece', 165.00, 8, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@cleaning, 'Floor Mop', 'Scotch-Brite', NULL, 'minor', 'Absorbent mop with handle', JSON_OBJECT('Type', JSON_ARRAY('Cotton Mop', 'Microfiber Mop')), 30, 'Piece', 325.00, 8, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@medical, 'Disposable Face Mask', 'Indoplas', NULL, 'minor', 'Three-ply surgical mask, 50 pieces per box', JSON_OBJECT('Color', JSON_ARRAY('Blue', 'Black', 'White')), 80, 'Box', 145.00, 20, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@medical, 'Examination Gloves', 'Indoplas', NULL, 'minor', 'Powder-free disposable gloves, 100 pieces per box', JSON_OBJECT('Material', JSON_ARRAY('Nitrile', 'Latex'), 'Size', JSON_ARRAY('Small', 'Medium', 'Large')), 50, 'Box', 385.00, 12, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@medical, 'Sterile Gauze Pad', 'Mediplast', NULL, 'minor', 'Sterile eight-ply gauze, 100 pieces per pack', JSON_OBJECT('Size', JSON_ARRAY('2 x 2 in', '4 x 4 in')), 45, 'Pack', 185.00, 10, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@medical, 'Elastic Bandage', 'Mediplast', NULL, 'minor', 'Reusable elastic support bandage', JSON_OBJECT('Width', JSON_ARRAY('2 in', '3 in', '4 in')), 50, 'Roll', 95.00, 10, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@medical, 'Digital Thermometer', 'Omron', 'MC-246', 'minor', 'Digital oral and underarm thermometer', NULL, 20, 'Piece', 295.00, 5, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@maintenance, 'LED Light Bulb', 'Philips', NULL, 'minor', 'Energy-efficient daylight LED bulb', JSON_OBJECT('Wattage', JSON_ARRAY('7W', '9W', '12W', '15W')), 60, 'Piece', 145.00, 15, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@maintenance, 'Electrical Tape', '3M', NULL, 'minor', 'Flame-retardant PVC electrical tape', JSON_OBJECT('Color', JSON_ARRAY('Black', 'Blue', 'Red', 'White', 'Yellow')), 60, 'Roll', 85.00, 12, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@maintenance, 'Alkaline Battery', 'Energizer', NULL, 'minor', 'Long-lasting alkaline battery, two pieces per pack', JSON_OBJECT('Size', JSON_ARRAY('AA', 'AAA', 'C', 'D', '9V')), 90, 'Pack', 135.00, 20, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@maintenance, 'Extension Cord', 'Omni', NULL, 'minor', 'Grounded extension outlet with overload protection', JSON_OBJECT('Length', JSON_ARRAY('3 m', '5 m', '10 m'), 'Outlets', JSON_ARRAY('3 Outlets', '4 Outlets', '6 Outlets')), 30, 'Piece', 495.00, 8, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE
    supply_category_id = VALUES(supply_category_id), brand = VALUES(brand), model_number = VALUES(model_number),
    requisition_tier = VALUES(requisition_tier), specifications = VALUES(specifications),
    configurable_options = VALUES(configurable_options), unit = VALUES(unit), unit_price = VALUES(unit_price),
    min_stock_level = VALUES(min_stock_level), updated_at = CURRENT_TIMESTAMP;

-- MAJOR REQUISITION CATALOG
INSERT INTO supplies
    (supply_category_id, item_name, brand, model_number, requisition_tier,
     specifications, configurable_options, quantity, unit, unit_price, min_stock_level, created_at, updated_at)
VALUES
(@office, 'Laser Printer', 'Brother', 'HL-L2375DW', 'major', 'Monochrome duplex network laser printer', JSON_OBJECT('Connectivity', JSON_ARRAY('USB', 'Wi-Fi and LAN')), 8, 'Unit', 12995.00, 2, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Multifunction Ink Tank Printer', 'Epson', 'EcoTank L5290', 'major', 'Print, scan, copy, fax, Wi-Fi and automatic document feeder', NULL, 6, 'Unit', 15995.00, 2, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Photocopier Machine', 'Canon', 'imageRUNNER 2425', 'major', 'A3 monochrome multifunction copier with network printing', NULL, 2, 'Unit', 118000.00, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Document Scanner', 'Epson', 'WorkForce DS-530 II', 'major', 'Duplex sheet-fed scanner with automatic document feeder', NULL, 4, 'Unit', 23500.00, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Laminating Machine', 'Fellowes', 'Spectra A3', 'major', 'A3 thermal laminator for office documents', JSON_OBJECT('Maximum Size', JSON_ARRAY('A4', 'A3')), 5, 'Unit', 6950.00, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@office, 'Paper Shredder', 'Fellowes', 'Powershred 12C', 'major', 'Cross-cut shredder with twelve-sheet capacity', NULL, 4, 'Unit', 8950.00, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@nonmedical, 'Laptop Computer', 'Lenovo', 'ThinkPad E14', 'major', '14-inch business laptop, Windows 11 Pro', JSON_OBJECT('Processor', JSON_ARRAY('Intel Core i5', 'Intel Core i7'), 'Memory', JSON_ARRAY('8 GB', '16 GB'), 'Storage', JSON_ARRAY('512 GB SSD', '1 TB SSD')), 10, 'Unit', 48995.00, 2, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@nonmedical, 'Desktop Computer Set', 'Dell', 'OptiPlex 7010', 'major', 'Business desktop with keyboard, mouse, and licensed operating system', JSON_OBJECT('Processor', JSON_ARRAY('Intel Core i5', 'Intel Core i7'), 'Memory', JSON_ARRAY('8 GB', '16 GB')), 8, 'Set', 45995.00, 2, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@nonmedical, 'LED Monitor', 'LG', '24MP400-B', 'major', 'Full HD IPS computer display', JSON_OBJECT('Screen Size', JSON_ARRAY('24 inch', '27 inch')), 12, 'Unit', 7895.00, 3, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@nonmedical, 'Multimedia Projector', 'Epson', 'EB-E01', 'major', 'Classroom projector with at least 3300 lumens', JSON_OBJECT('Resolution', JSON_ARRAY('XGA', 'Full HD')), 5, 'Unit', 32995.00, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@nonmedical, 'Interactive Smart Display', 'ViewSonic', 'ViewBoard IFP6552', 'major', '65-inch 4K interactive classroom display', JSON_OBJECT('Screen Size', JSON_ARRAY('65 inch', '75 inch')), 2, 'Unit', 165000.00, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@nonmedical, 'Uninterruptible Power Supply', 'APC', 'BX1100LI-MS', 'major', 'Line-interactive UPS with automatic voltage regulation', JSON_OBJECT('Capacity', JSON_ARRAY('650 VA', '1100 VA', '1500 VA')), 12, 'Unit', 6295.00, 3, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@nonmedical, 'Wireless Router', 'TP-Link', 'Archer AX55', 'major', 'Dual-band Wi-Fi 6 gigabit router', NULL, 8, 'Unit', 4995.00, 2, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@nonmedical, 'Wireless Access Point', 'Ubiquiti', 'UniFi U6 Plus', 'major', 'Ceiling-mounted dual-band Wi-Fi 6 access point', NULL, 10, 'Unit', 7995.00, 2, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@nonmedical, 'External Hard Drive', 'Western Digital', 'My Passport', 'major', 'Portable USB 3 storage drive', JSON_OBJECT('Capacity', JSON_ARRAY('1 TB', '2 TB', '4 TB')), 10, 'Unit', 4295.00, 2, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@nonmedical, 'CCTV Security Camera', 'Hikvision', NULL, 'major', 'IP security camera with night vision', JSON_OBJECT('Type', JSON_ARRAY('Indoor Dome', 'Outdoor Bullet'), 'Resolution', JSON_ARRAY('2 MP', '4 MP')), 16, 'Unit', 5850.00, 4, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@nonmedical, 'Office Chair', 'Ofix', NULL, 'major', 'Ergonomic chair with adjustable height and lumbar support', JSON_OBJECT('Type', JSON_ARRAY('Mesh Task Chair', 'Executive Chair'), 'Color', JSON_ARRAY('Black', 'Gray')), 20, 'Piece', 4850.00, 5, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@nonmedical, 'Office Table', 'Ofix', NULL, 'major', 'Laminated office desk with steel frame', JSON_OBJECT('Size', JSON_ARRAY('120 cm', '150 cm', '180 cm'), 'Color', JSON_ARRAY('Beech', 'Mahogany', 'White')), 12, 'Piece', 6850.00, 3, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@nonmedical, 'Steel Filing Cabinet', 'Ofix', NULL, 'major', 'Lockable powder-coated filing cabinet', JSON_OBJECT('Configuration', JSON_ARRAY('2 Drawer', '3 Drawer', '4 Drawer'), 'Color', JSON_ARRAY('Beige', 'Gray')), 10, 'Piece', 8950.00, 2, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@nonmedical, 'Water Dispenser', 'Hanabishi', 'HFSWD-1900', 'major', 'Top-loading hot and cold water dispenser', JSON_OBJECT('Type', JSON_ARRAY('Tabletop', 'Free Standing')), 6, 'Unit', 6495.00, 2, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@maintenance, 'Air Conditioning Unit', 'Carrier', 'Aura Inverter', 'major', 'Split-type inverter air conditioner with installation kit', JSON_OBJECT('Capacity', JSON_ARRAY('1.0 HP', '1.5 HP', '2.0 HP', '2.5 HP')), 5, 'Unit', 38995.00, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@maintenance, 'Portable Generator', 'Honda', 'EU22i', 'major', 'Quiet inverter generator for emergency power', NULL, 2, 'Unit', 74995.00, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@maintenance, 'Pressure Washer', 'Bosch', 'EasyAquatak 120', 'major', 'Compact high-pressure campus cleaning equipment', NULL, 3, 'Unit', 8995.00, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@maintenance, 'Fire Extinguisher', 'Flameguard', NULL, 'major', 'Rechargeable ABC dry chemical fire extinguisher', JSON_OBJECT('Capacity', JSON_ARRAY('5 lb', '10 lb', '20 lb')), 20, 'Unit', 2850.00, 5, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@medical, 'Automatic Blood Pressure Monitor', 'Omron', 'HEM-7120', 'major', 'Digital upper-arm blood pressure monitor', NULL, 4, 'Unit', 4250.00, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@medical, 'Wheelchair', 'Indoplas', NULL, 'major', 'Foldable wheelchair with safety brakes and footrests', JSON_OBJECT('Type', JSON_ARRAY('Standard', 'Reclining')), 3, 'Unit', 8950.00, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@construction, 'Portland Cement', 'Holcim', NULL, 'major', 'Type 1 general-purpose Portland cement, 40 kg', NULL, 80, 'Bag', 285.00, 20, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@construction, 'Marine Plywood', 'Santa Clara', NULL, 'major', '4 x 8 ft moisture-resistant plywood sheet', JSON_OBJECT('Thickness', JSON_ARRAY('1/4 in', '1/2 in', '3/4 in')), 45, 'Sheet', 1250.00, 10, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@construction, 'Steel Reinforcing Bar', 'SteelAsia', NULL, 'major', 'Grade 40 deformed steel bar, 6 m length', JSON_OBJECT('Diameter', JSON_ARRAY('10 mm', '12 mm', '16 mm')), 100, 'Piece', 385.00, 25, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(@construction, 'Interior Latex Paint', 'Boysen', 'Permacoat', 'major', 'Water-based interior latex paint', JSON_OBJECT('Size', JSON_ARRAY('1 Gallon', '4 Liters', '16 Liters'), 'Color', JSON_ARRAY('White', 'Beige', 'Light Gray')), 30, 'Can', 895.00, 8, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE
    supply_category_id = VALUES(supply_category_id), brand = VALUES(brand), model_number = VALUES(model_number),
    requisition_tier = VALUES(requisition_tier), specifications = VALUES(specifications),
    configurable_options = VALUES(configurable_options), unit = VALUES(unit), unit_price = VALUES(unit_price),
    min_stock_level = VALUES(min_stock_level), updated_at = CURRENT_TIMESTAMP;

COMMIT;

-- Verification results
SELECT requisition_tier, COUNT(*) AS item_count, SUM(quantity) AS total_stock
FROM supplies
GROUP BY requisition_tier
ORDER BY requisition_tier;

SELECT sc.category_name AS category, COUNT(*) AS item_count
FROM supplies s
LEFT JOIN supply_categories sc ON sc.id = s.supply_category_id
GROUP BY sc.category_name
ORDER BY sc.category_name;
