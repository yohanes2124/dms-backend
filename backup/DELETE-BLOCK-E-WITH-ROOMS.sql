-- Delete Block E and all its rooms
-- Use this if you want to completely remove Block E

-- First, delete all rooms in Block E
DELETE FROM rooms WHERE block = 'E';

-- Then, delete Block E itself
DELETE FROM blocks WHERE name = 'E';

-- Verify deletion
SELECT 'Block E and its rooms have been deleted' as status;
SELECT name FROM blocks ORDER BY name;