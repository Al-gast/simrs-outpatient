#!/bin/sh
# Read-only assertions against the disposable dummy database.
set -eu
cd "$(dirname "$0")/.."
docker compose exec -T db psql -U simrs_owner -d simrs -v ON_ERROR_STOP=1 <<'SQL'
DO $$
BEGIN
 IF EXISTS (SELECT 1 FROM medicines WHERE stock<0) THEN RAISE EXCEPTION 'Negative stock'; END IF;
 IF EXISTS (SELECT 1 FROM medicines m LEFT JOIN LATERAL (SELECT sum(quantity_delta) qty FROM stock_movements WHERE medicine_id=m.id) s ON true WHERE m.stock<>coalesce(s.qty,0)) THEN RAISE EXCEPTION 'Stock ledger mismatch'; END IF;
 IF EXISTS (SELECT 1 FROM stock_adjustment_requests r JOIN stock_movements s ON s.id=r.movement_id WHERE s.kind<>'adjustment' OR s.medicine_id<>r.medicine_id OR s.actor_id<>r.actor_id) THEN RAISE EXCEPTION 'Adjustment key ledger mismatch'; END IF;
 IF EXISTS (SELECT 1 FROM invoices i LEFT JOIN LATERAL (SELECT sum(amount) paid FROM payments WHERE invoice_id=i.id) p ON true WHERE coalesce(p.paid,0)>i.total_amount) THEN RAISE EXCEPTION 'Overpayment'; END IF;
 IF EXISTS (SELECT 1 FROM invoices i LEFT JOIN LATERAL (SELECT sum(unit_price::numeric*quantity) total FROM invoice_items WHERE invoice_id=i.id) l ON true WHERE i.total_amount<>coalesce(l.total,0)) THEN RAISE EXCEPTION 'Invoice total mismatch'; END IF;
 IF EXISTS (SELECT 1 FROM visits v LEFT JOIN invoices i ON i.visit_id=v.id LEFT JOIN LATERAL (SELECT sum(amount) paid FROM payments WHERE invoice_id=i.id) p ON true WHERE v.status='completed' AND (i.id IS NULL OR coalesce(p.paid,0)<>i.total_amount)) THEN RAISE EXCEPTION 'Completed visit without paid invoice'; END IF;
 IF EXISTS (SELECT 1 FROM visits v JOIN invoices i ON i.visit_id=v.id LEFT JOIN LATERAL (SELECT sum(amount) paid FROM payments WHERE invoice_id=i.id) p ON true WHERE v.status='ready_for_billing' AND coalesce(p.paid,0)=i.total_amount) THEN RAISE EXCEPTION 'Paid invoice with incomplete visit'; END IF;
 IF EXISTS (SELECT 1 FROM prescriptions p JOIN prescription_items i ON i.prescription_id=p.id LEFT JOIN stock_movements m ON m.prescription_item_id=i.id WHERE (p.status='dispensed' AND (m.id IS NULL OR m.quantity_delta<>-i.quantity OR m.medicine_id<>i.medicine_id)) OR (p.status='pending' AND m.id IS NOT NULL)) THEN RAISE EXCEPTION 'Dispense ledger mismatch'; END IF;
 IF EXISTS (SELECT 1 FROM lab_orders o JOIN lab_order_items i ON i.lab_order_id=o.id WHERE o.status='completed' AND i.result_text IS NULL) THEN RAISE EXCEPTION 'Incomplete lab results'; END IF;
 IF EXISTS (SELECT 1 FROM visits v LEFT JOIN queue_counters q ON q.clinic_id=v.clinic_id AND q.visit_date=v.visit_date WHERE q.last_number IS NULL OR v.queue_number>q.last_number) THEN RAISE EXCEPTION 'Queue counter mismatch'; END IF;
END $$;
SQL
echo 'PASS: 10 database invariant queries'
