-- Simply update Block E's gender to female
-- This is MUCH easier than deleting and recreating!

UPDATE blocks SET gender = 'female' WHERE name = 'E';

-- Verify the update
SELECT name, gender, total_rooms, status FROM blocks WHERE name = 'E';

-- Show all female blocks
SELECT name, gender, total_rooms FROM blocks WHERE gender = 'female' ORDER BY name;