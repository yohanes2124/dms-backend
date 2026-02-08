-- COMPLETE BLOCK D ROOM CREATION
-- This script ensures Block D has the same number of rooms as other blocks (60 rooms)

-- Check current status
SELECT 'Current Block D status:' as info;
SELECT COUNT(*) as current_block_d_rooms FROM rooms WHERE block = 'D';

-- Check other blocks for comparison
SELECT 'Room counts by block:' as info;
SELECT block, COUNT(*) as room_count FROM rooms GROUP BY block ORDER BY block;

-- Create all Block D rooms (D101 to D320 = 60 rooms total, 3 floors, 20 rooms per floor)
-- Only insert if they don't already exist

-- Floor 1: D101 to D120
INSERT IGNORE INTO rooms (room_number, block, capacity, current_occupancy, room_type, status, description, facilities, created_at, updated_at) VALUES
('D101', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D102', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D103', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D104', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D105', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D106', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D107', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D108', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D109', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D110', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D111', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D112', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D113', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D114', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D115', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D116', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D117', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D118', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D119', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D120', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),

-- Floor 2: D201 to D220
('D201', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D202', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D203', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D204', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D205', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D206', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D207', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D208', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D209', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D210', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D211', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D212', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D213', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D214', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D215', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D216', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D217', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D218', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D219', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D220', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),

-- Floor 3: D301 to D320
('D301', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D302', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D303', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D304', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D305', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D306', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D307', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D308', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D309', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D310', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D311', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D312', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D313', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D314', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D315', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D316', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D317', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D318', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D319', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW()),
('D320', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk", "Wardrobe", "Shared Bathroom"]', NOW(), NOW());

-- Verify the final result
SELECT 'FINAL RESULT:' as status;
SELECT block, COUNT(*) as room_count FROM rooms GROUP BY block ORDER BY block;

SELECT 'Block D now has enough rooms for female students!' as success_message;
SELECT 'Sample Block D rooms:' as info;
SELECT room_number, status FROM rooms WHERE block = 'D' ORDER BY room_number LIMIT 10;