-- Lead Management / API Integration verification queries
-- Replace PASTE_YOUR_BATCH_ID_HERE with the batch_id you want to inspect.

-- 1. Latest uploaded leads with batch and mapped region
SELECT id, batch_id, lead_id, name, state, region, source_file, created_at
FROM leads
ORDER BY id DESC
LIMIT 50;

-- 2. Recent batches with total leads
SELECT batch_id, COUNT(*) AS total_leads, MAX(created_at) AS latest_upload_time
FROM leads
GROUP BY batch_id
ORDER BY latest_upload_time DESC
LIMIT 10;

-- 3. Region-wise lead count for a specific batch
SELECT region, COUNT(*) AS total_leads
FROM leads
WHERE batch_id = 'PASTE_YOUR_BATCH_ID_HERE'
GROUP BY region
ORDER BY region;

-- 4. Latest push log snapshot
SELECT id, lead_id, region, college_id, api_status, created_at
FROM lead_push_logs
ORDER BY id DESC
LIMIT 100;

-- 5. Push logs for a specific batch
SELECT l.batch_id, p.lead_id, p.region, p.college_id, p.api_status, p.created_at
FROM lead_push_logs p
INNER JOIN leads l ON l.lead_id = p.lead_id
WHERE l.batch_id = 'PASTE_YOUR_BATCH_ID_HERE'
ORDER BY p.id DESC;

-- 6. Region-wise expected leads vs actual logged API calls for a batch
SELECT l.region, COUNT(DISTINCT l.lead_id) AS leads_in_region, COUNT(p.id) AS api_calls_logged
FROM leads l
LEFT JOIN lead_push_logs p
    ON p.lead_id = l.lead_id
    AND p.region = l.region
WHERE l.batch_id = 'PASTE_YOUR_BATCH_ID_HERE'
GROUP BY l.region
ORDER BY l.region;

-- 7. Region-wise per-college call count for a batch
SELECT l.batch_id, p.region, p.college_id, COUNT(*) AS total_calls
FROM lead_push_logs p
INNER JOIN leads l ON l.lead_id = p.lead_id
WHERE l.batch_id = 'PASTE_YOUR_BATCH_ID_HERE'
GROUP BY l.batch_id, p.region, p.college_id
ORDER BY p.region, p.college_id;

-- 8. Success vs failed status count for a batch
SELECT p.api_status, COUNT(*) AS total
FROM lead_push_logs p
INNER JOIN leads l ON l.lead_id = p.lead_id
WHERE l.batch_id = 'PASTE_YOUR_BATCH_ID_HERE'
GROUP BY p.api_status;

-- 9. Full response bodies for a batch
SELECT p.id, p.lead_id, p.region, p.college_id, p.api_status, p.response, p.created_at
FROM lead_push_logs p
INNER JOIN leads l ON l.lead_id = p.lead_id
WHERE l.batch_id = 'PASTE_YOUR_BATCH_ID_HERE'
ORDER BY p.id DESC;

-- 10. Compare all batches by region
SELECT batch_id, region, COUNT(*) AS total_leads
FROM leads
GROUP BY batch_id, region
ORDER BY batch_id DESC, region;

-- 11. Check for leads that were uploaded but have no push logs yet
SELECT l.batch_id, l.lead_id, l.name, l.region
FROM leads l
LEFT JOIN lead_push_logs p ON p.lead_id = l.lead_id
WHERE l.batch_id = 'PASTE_YOUR_BATCH_ID_HERE'
  AND p.id IS NULL
ORDER BY l.id DESC;

-- 12. Distinct colleges hit for a batch
SELECT l.batch_id, COUNT(DISTINCT p.college_id) AS colleges_hit
FROM lead_push_logs p
INNER JOIN leads l ON l.lead_id = p.lead_id
WHERE l.batch_id = 'PASTE_YOUR_BATCH_ID_HERE'
GROUP BY l.batch_id;
