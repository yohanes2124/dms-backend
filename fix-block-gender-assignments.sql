-- Fix block gender assignments to match dormitory management standards
-- Female blocks: A, D, H, J, L, N, P (for female students and supervisors)
-- Male blocks: B, C, F, G, I, K, M, O (for male students and supervisors)

UPDATE blocks SET gender = 'female' WHERE name IN ('A', 'D', 'H', 'J', 'L', 'N', 'P');
UPDATE blocks SET gender = 'male' WHERE name IN ('B', 'C', 'F', 'G', 'I', 'K', 'M', 'O');

-- Show updated assignments
SELECT name, gender, status FROM blocks ORDER BY name;