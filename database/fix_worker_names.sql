-- Fix Worker Names to Match Corrected List
-- Red Rig: Atta (Driller), Isaac (Rig Driver / Spanner), Tawiah (Rodboy), Godwin (Rodboy), Asare (Rodboy)
-- Green Rig: Earnest (Driller), Owusua (Rig Driver), Rasta (Spanner boy / Table boy), Chief (Rodboy), Godwin (Rodboy), Kwesi (Rodboy)

-- Step 1: Remove workers not in the corrected list
DELETE FROM workers WHERE worker_name IN ('Peter', 'Jawich', 'Razak', 'Anthony Emma', 'Mtw', 'BOSS', 'new', 'Finest', 'Giet', 'Linest', 'linef', 'Internal', 'Castro');

-- Step 2: Ensure correct names and roles match the corrected list
-- Update roles to match exactly
UPDATE workers SET role = 'Driller' WHERE worker_name = 'Atta';
UPDATE workers SET role = 'Rig Driver / Spanner' WHERE worker_name = 'Isaac';
UPDATE workers SET role = 'Rodboy' WHERE worker_name = 'Tawiah';
UPDATE workers SET role = 'Rodboy' WHERE worker_name = 'Godwin';
UPDATE workers SET role = 'Rodboy' WHERE worker_name = 'Asare';
UPDATE workers SET role = 'Driller' WHERE worker_name = 'Earnest';
UPDATE workers SET role = 'Rig Driver' WHERE worker_name = 'Owusua';
UPDATE workers SET role = 'Spanner boy / Table boy' WHERE worker_name = 'Rasta';
UPDATE workers SET role = 'Rodboy' WHERE worker_name = 'Chief';
UPDATE workers SET role = 'Rodboy' WHERE worker_name = 'Kwesi';

-- Step 3: Remove any workers with incorrect roles (keep only those matching corrected list)
DELETE FROM workers WHERE worker_name = 'Atta' AND role NOT LIKE '%Driller%';
DELETE FROM workers WHERE worker_name = 'Isaac' AND role NOT LIKE '%Rig Driver%' AND role NOT LIKE '%Spanner%';
DELETE FROM workers WHERE worker_name = 'Tawiah' AND role NOT LIKE '%Rodboy%';
DELETE FROM workers WHERE worker_name = 'Godwin' AND role NOT LIKE '%Rodboy%';
DELETE FROM workers WHERE worker_name = 'Asare' AND role NOT LIKE '%Rodboy%';
DELETE FROM workers WHERE worker_name = 'Earnest' AND role NOT LIKE '%Driller%';
DELETE FROM workers WHERE worker_name = 'Owusua' AND role NOT LIKE '%Rig Driver%';
DELETE FROM workers WHERE worker_name = 'Rasta' AND role NOT LIKE '%Spanner%' AND role NOT LIKE '%Table%';
DELETE FROM workers WHERE worker_name = 'Chief' AND role NOT LIKE '%Rodboy%';
DELETE FROM workers WHERE worker_name = 'Kwesi' AND role NOT LIKE '%Rodboy%';

