-- FINAL FIX: Add Block D rooms to database
-- First select the correct database
USE dms_db;

-- Check if Block D rooms already exist
SELECT 'Checking existing Block D rooms...' as status;
SELECT COUNT(*) as existing_block_d_rooms FROM rooms WHERE block = 'D';

-- Create Block D rooms (Female dormitory)
INSERT INTO rooms (room_number, block, capacity, current_occupancy, room_type, status, description, facilities, created_at, updated_at) VALUES
('D101', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe"]', NOW(), NOW()),
('D102', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe"]', NOW(), NOW()),
('D103', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe"]', NOW(), NOW()),
('D104', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe"]', NOW(), NOW()),
('D105', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe"]', NOW(), NOW()),
('D106', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe"]', NOW(), NOW()),
('D107', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe"]', NOW(), NOW()),
('D108', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe"]', NOW(), NOW()),
('D109', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe"]', NOW(), NOW()),
('D110', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe"]', NOW(), NOW()),
('D201', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe"]', NOW(), NOW()),
('D202', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe"]', NOW(), NOW()),
('D203', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe"]', NOW(), NOW()),
('D204', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe"]', NOW(), NOW()),
('D205', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe"]', NOW(), NOW());

-- Verify the creation
SELECT 'Block D rooms created successfully!' as status;
SELECT block, COUNT(*) as room_count FROM rooms WHERE block = 'D' GROUP BY block;

-- Show sample rooms
SELECT 'Sample Block D rooms:' as info;
SELECT room_number, block, status FROM rooms WHERE block = 'D' LIMIT 5;

-- Verify gender filtering will work
SELECT 'Gender verification:' as info;
SELECT b.name as block_name, b.gender, COUNT(r.id) as room_count 
FROM blocks b 
LEFT JOIN rooms r ON b.name = r.block 
WHERE b.gender = 'female' 
GROUP BY b.name, b.gender 
ORDER BY b.name;