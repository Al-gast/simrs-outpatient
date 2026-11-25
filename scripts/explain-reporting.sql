-- Run against dummy DB only, as simrs_owner, after representative test data are populated.
-- Capture actual plans in Phase 9; no performance claim until measured.
EXPLAIN (ANALYZE, BUFFERS) SELECT clinic_id,count(*) FROM reporting.visits
WHERE visit_date >= (now() AT TIME ZONE 'Asia/Jakarta')::date - 30
  AND visit_date < (now() AT TIME ZONE 'Asia/Jakarta')::date + 1 GROUP BY clinic_id;
EXPLAIN (ANALYZE, BUFFERS) SELECT status,count(*) FROM reporting.invoice_status
WHERE visit_date >= (now() AT TIME ZONE 'Asia/Jakarta')::date - 30
  AND visit_date < (now() AT TIME ZONE 'Asia/Jakarta')::date + 1 GROUP BY status;
EXPLAIN (ANALYZE, BUFFERS) SELECT sum(amount) FROM reporting.revenue
WHERE paid_at >= (((now() AT TIME ZONE 'Asia/Jakarta')::date-30)::timestamp AT TIME ZONE 'Asia/Jakarta')
  AND paid_at < (((now() AT TIME ZONE 'Asia/Jakarta')::date+1)::timestamp AT TIME ZONE 'Asia/Jakarta');
EXPLAIN (ANALYZE, BUFFERS) SELECT medicine_id,sum(quantity) FROM reporting.medicine_usage
WHERE created_at >= (((now() AT TIME ZONE 'Asia/Jakarta')::date-30)::timestamp AT TIME ZONE 'Asia/Jakarta')
  AND created_at < (((now() AT TIME ZONE 'Asia/Jakarta')::date+1)::timestamp AT TIME ZONE 'Asia/Jakarta') GROUP BY medicine_id;
EXPLAIN (ANALYZE, BUFFERS) SELECT id, visit_date FROM visits
WHERE status IN ('ready_for_billing','completed') ORDER BY visit_date DESC,id DESC LIMIT 20;
EXPLAIN (ANALYZE, BUFFERS) SELECT invoice_id,sum(amount) FROM payments
WHERE invoice_id IN (SELECT id FROM invoices WHERE visit_id=(SELECT min(visit_id) FROM invoices)) GROUP BY invoice_id;
