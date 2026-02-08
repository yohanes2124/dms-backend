-- Fix Block E gender assignment
-- Set Block E as a female block

UPDATE blocks SET gender = 'female' WHERE name = 'E';

-- Verify the update
SELECT name, gender, status, total_rooms FROM blocks ORDER BY name;

-- Check all female blocks
SELECT name, gender, total_rooms FROM blocks WHERE gender = 'female' ORDER BY name;