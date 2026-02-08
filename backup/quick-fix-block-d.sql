-- Quick fix: Create a few Block D rooms for testing
INSERT INTO rooms (room_number, block, capacity, current_occupancy, room_type, status, description, facilities, created_at, updated_at) VALUES
('D101', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk"]', NOW(), NOW()),
('D102', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk"]', NOW(), NOW()),
('D103', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk"]', NOW(), NOW()),
('D104', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk"]', NOW(), NOW()),
('D105', 'D', 4, 0, 'four', 'available', 'Female dormitory room', '["Wi-Fi", "Study Desk"]', NOW(), NOW());

-- Verify
SELECT 'Block D rooms created!' as status;
SELECT block, COUNT(*) as room_count FROM rooms WHERE block = 'D';