-- Check current room types in database
SELECT DISTINCT room_type, COUNT(*) as count FROM rooms GROUP BY room_type;

-- Delete rooms with invalid room types (not 'four' or 'six')
DELETE FROM rooms WHERE room_type NOT IN ('four', 'six');

-- Verify cleanup
SELECT DISTINCT room_type, COUNT(*) as count FROM rooms GROUP BY room_type;

-- Show remaining rooms
SELECT id, room_number, block, room_type, capacity FROM rooms ORDER BY block, room_number;
