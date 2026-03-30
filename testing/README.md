# Testing Guide

This folder contains sample files and SQL queries to verify that the lead upload, region mapping, API push, and logging flow is working correctly.

## Files in this folder

- `sample_region_leads.csv`
- `sample_region_leads_batch_2.csv`
- `sql_verification_queries.sql`

## Goal of the test

Verify the following flow:

1. Upload a CSV file
2. Confirm leads are parsed and stored in the database
3. Confirm state-wise region mapping is correct
4. Push the uploaded batch to region-wise colleges
5. Confirm push logs are written correctly

## Recommended test order

### 1. Upload first sample file

Upload:

- `sample_region_leads.csv`

Expected regional lead split:

- North: 2
- South: 2
- East: 2
- West / Others: 2

### 2. Find the latest batch ID

Open `sql_verification_queries.sql` and run query `2. Recent batches with total leads`.

Copy the latest `batch_id`.

### 3. Verify stored leads

Run these queries from `sql_verification_queries.sql`:

- `1. Latest uploaded leads with batch and mapped region`
- `3. Region-wise lead count for a specific batch`

Replace `PASTE_YOUR_BATCH_ID_HERE` with the copied batch ID.

### 4. Run the API push flow

In the application:

1. Go to `Leads`
2. Upload the file
3. Continue through:
   - Preview
   - Region Grouping
   - Region API Coverage
4. Click `Push Region Leads`

### 5. Verify push logs

Run these queries:

- `4. Latest push log snapshot`
- `5. Push logs for a specific batch`
- `7. Region-wise per-college call count for a batch`
- `8. Success vs failed status count for a batch`
- `9. Full response bodies for a batch`

### 6. Validate expected API call counts

Current configured college counts in code:

- North: 5 colleges
- South: 5 colleges
- East: 0 colleges
- West / Others: 5 colleges

For `sample_region_leads.csv`, expected API calls:

- North: `2 x 5 = 10`
- South: `2 x 5 = 10`
- East: `2 x 0 = 0`
- West / Others: `2 x 5 = 10`

Expected total log rows: `30`

### 7. Upload the second sample file

Upload:

- `sample_region_leads_batch_2.csv`

Expected regional lead split:

- North: 2
- South: 2
- East: 2
- West / Others: 2

Repeat the same verification flow using the new batch ID.

### 8. Compare both batches

Run these queries:

- `10. Compare all batches by region`
- `12. Distinct colleges hit for a batch`

### 9. Check for missing logs

Run:

- `11. Check for leads that were uploaded but have no push logs yet`

If rows are returned, those leads were uploaded but not logged in `lead_push_logs`.

## Notes

- The application currently uses configured region-wise college mappings from code.
- East region currently has no colleges configured, so East leads should not create push log rows.
- API logs are also visible in the dashboard at `Lead Push Logs`.

## Safe testing reminder

The project uses real API endpoints. Use test data only.
