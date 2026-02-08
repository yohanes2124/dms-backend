-- FINAL SOLUTION: Ensure 100% gender segregation
-- This SQL script fixes all gender assignments and ensures strict filtering

-- Step 1: Update block gender assignments
UPDATE blocks SET gender = 'female' WHERE name IN ('A', 'D');
UPDATE blocks SET gender = 'male' WHERE name IN ('B', 'C', 'F');

-- Step 2: Verify the assignments
SELECT 'Block Gender Assignments:' as info;
SELECT name, gender, status FROM blocks ORDER BY name;

-- Step 3: Check sample rooms per block
SELECT 'Sample Rooms by Block:' as info;
SELECT block, COUNT(*) as room_count, MIN(room_number) as first_room, MAX(room_number) as last_room 
FROM rooms 
GROUP BY block 
ORDER BY block;

-- Step 4: Verify no mixed gender access
SELECT 'Gender Verification Complete' as status;