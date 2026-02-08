-- ============================================
-- DIRECT DATABASE CLEANUP - Run this in phpMyAdmin
-- ============================================

-- Step 1: Check what invalid room types exist
SELECT DISTINCT room_type, COUNT(*) as count FROM rooms GROUP BY room_type;

-- Step 2: See the invalid rooms before deletion
SELECT id, room_number, block, room_type, capacity FROM rooms WHERE room_type NOT IN ('four', 'six');

-- Step 3: DELETE all rooms with invalid room types (like 'double-bed')
DELETE FROM rooms WHERE room_type NOT IN ('four', 'six');

-- Step 4: Verify cleanup - should only show 'four' and 'six'
SELECT DISTINCT room_type, COUNT(*) as count FROM rooms GROUP BY room_type;

-- Step 5: Show all remaining rooms
SELECT id, room_number, block, room_type, capacity FROM rooms ORDER BY block, room_number;
