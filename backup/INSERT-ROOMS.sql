-- Direct SQL to insert 180 rooms into the database
-- Run this in phpMyAdmin or MySQL client

-- Clear existing rooms first (optional - comment out if you want to keep existing rooms)
-- TRUNCATE TABLE rooms;

-- Insert rooms for Block A
INSERT INTO rooms (room_number, block, floor, capacity, current_occupancy, room_type, status, description, facilities, created_at, updated_at) VALUES
-- Block A, Floor 1 (4-bed rooms 1-15, 6-bed rooms 16-20)
('A101', 'A', 1, 4, 0, 'four', 'available', 'Block A, Floor 1, four occupancy room', '["Shared Bathroom","Study Desk","Wardrobe","Fan"]', NOW(), NOW()),
('A102', 'A', 1, 4, 0, 'four', 'available', 'Block A, Floor 1, four occupancy room', '["Shared Bathroom","Study Desk","Wardrobe","Fan"]', NOW(), NOW()),
('A103', 'A', 1, 4, 0, 'four', 'available', 'Block A, Floor 1, four occupancy room', '["Shared Bathroom","Study Desk","Wardrobe","Fan"]', NOW(), NOW()),
('A104', 'A', 1, 4, 0, 'four', 'available', 'Block A, Floor 1, four occupancy room', '["Shared Bathroom","Study Desk","Wardrobe","Fan"]', NOW(), NOW()),
('A105', 'A', 1, 4, 0, 'four', 'available', 'Block A, Floor 1, four occupancy room', '["Shared Bathroom","Study Desk","Wardrobe","Fan"]', NOW(), NOW()),
('A106', 'A', 1, 4, 0, 'four', 'available', 'Block A, Floor 1, four occupancy room', '["Shared Bathroom","Study Desk","Wardrobe","Fan"]', NOW(), NOW()),
('A107', 'A', 1, 4, 0, 'four', 'available', 'Block A, Floor 1, four occupancy room', '["Shared Bathroom","Study Desk","Wardrobe","Fan"]', NOW(), NOW()),
('A108', 'A', 1, 4, 0, 'four', 'available', 'Block A, Floor 1, four occupancy room', '["Shared Bathroom","Study Desk","Wardrobe","Fan"]', NOW(), NOW()),
('A109', 'A', 1, 4, 0, 'four', 'available', 'Block A, Floor 1, four occupancy room', '["Shared Bathroom","Study Desk","Wardrobe","Fan"]', NOW(), NOW()),
('A110', 'A', 1, 4, 0, 'four', 'available', 'Block A, Floor 1, four occupancy room', '["Shared Bathroom","Study Desk","Wardrobe","Fan"]', NOW(), NOW()),
('A111', 'A', 1, 4, 0, 'four', 'available', 'Block A, Floor 1, four occupancy room', '["Shared Bathroom","Study Desk","Wardrobe","Fan"]', NOW(), NOW()),
('A112', 'A', 1, 4, 0, 'four', 'available', 'Block A, Floor 1, four occupancy room', '["Shared Bathroom","Study Desk","Wardrobe","Fan"]', NOW(), NOW()),
('A113', 'A', 1, 4, 0, 'four', 'available', 'Block A, Floor 1, four occupancy room', '["Shared Bathroom","Study Desk","Wardrobe","Fan"]', NOW(), NOW()),
('A114', 'A', 1, 4, 0, 'four', 'available', 'Block A, Floor 1, four occupancy room', '["Shared Bathroom","Study Desk","Wardrobe","Fan"]', NOW(), NOW()),
('A115', 'A', 1, 4, 0, 'four', 'available', 'Block A, Floor 1, four occupancy room', '["Shared Bathroom","Study Desk","Wardrobe","Fan"]', NOW(), NOW()),
('A116', 'A', 1, 6, 0, 'six', 'available', 'Block A, Floor 1, six occupancy room', '["Shared Bathroom","Study Desk","Wardrobe"]', NOW(), NOW()),
('A117', 'A', 1, 6, 0, 'six', 'available', 'Block A, Floor 1, six occupancy room', '["Shared Bathroom","Study Desk","Wardrobe"]', NOW(), NOW()),
('A118', 'A', 1, 6, 0, 'six', 'available', 'Block A, Floor 1, six occupancy room', '["Shared Bathroom","Study Desk","Wardrobe"]', NOW(), NOW()),
('A119', 'A', 1, 6, 0, 'six', 'available', 'Block A, Floor 1, six occupancy room', '["Shared Bathroom","Study Desk","Wardrobe"]', NOW(), NOW()),
('A120', 'A', 1, 6, 0, 'six', 'available', 'Block A, Floor 1, six occupancy room', '["Shared Bathroom","Study Desk","Wardrobe"]', NOW(), NOW());

-- This is a partial example. For full 180 rooms, use the PHP script or generate via Laravel seeder.
-- The pattern repeats for:
-- - Block A, Floors 2-3
-- - Block B, Floors 1-3  
-- - Block C, Floors 1-3

-- To generate the full SQL, run this command in backend directory:
-- php artisan tinker
-- Then paste: Database\Seeders\RoomSeeder::class)->run();
