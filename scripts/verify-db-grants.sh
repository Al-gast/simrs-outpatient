#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
# Explicit credentials via container environment, never echo secrets.
# Use TCP so password auth is actually exercised.
docker compose exec -T db sh -ec 'PGPASSWORD="$REPORT_DB_PASSWORD" psql -h 127.0.0.1 -U simrs_report -d simrs -v ON_ERROR_STOP=1 -At' <<'SQL'
SELECT count(*) FROM reporting.visits;
SELECT count(*) FROM reporting.procedures;
SELECT count(*) FROM reporting.revenue;
SELECT count(*) FROM reporting.medicine_usage;
SELECT count(*) FROM reporting.invoice_status;
SQL
for query in 'SELECT * FROM public.users LIMIT 1' 'UPDATE public.medicines SET stock=stock WHERE false' 'CREATE TABLE reporting.forbidden_probe(id int)'; do
  if docker compose exec -T db sh -ec 'PGPASSWORD="$REPORT_DB_PASSWORD" psql -h 127.0.0.1 -U simrs_report -d simrs -v ON_ERROR_STOP=1 -c "$1"' sh "$query"; then
    echo "FAIL: reporting role unexpectedly permitted: $query" >&2; exit 1
  fi
done
docker compose exec -T db sh -ec 'PGPASSWORD="$CORE_DB_PASSWORD" psql -h 127.0.0.1 -U simrs_core -d simrs -v ON_ERROR_STOP=1 -Atc "SELECT count(*) FROM users"'
echo 'PASS: reporting views readable, direct core read/write and DDL denied; core account can read users.'
# Explicit privilege inspection avoids false positive denial due to unrelated SQL errors.
docker compose exec -T db psql -U simrs_owner -d simrs -v ON_ERROR_STOP=1 <<'SQL'
DO $$
DECLARE t text; p text;
BEGIN
 FOREACH t IN ARRAY ARRAY['audit_logs','payments','stock_movements','lab_result_events','stock_adjustment_requests'] LOOP
  IF NOT has_table_privilege('simrs_core',t,'INSERT') OR NOT has_table_privilege('simrs_core',t,'SELECT') THEN RAISE EXCEPTION 'Missing append/read permission: %',t; END IF;
  FOREACH p IN ARRAY ARRAY['UPDATE','DELETE','TRUNCATE'] LOOP
   IF has_table_privilege('simrs_core',t,p) THEN RAISE EXCEPTION 'Ledger mutation permitted: % %',t,p; END IF;
  END LOOP;
 END LOOP;
 FOREACH t IN ARRAY ARRAY['staff_code_seq','clinic_code_seq','procedure_code_seq','medicine_code_seq','lab_test_code_seq'] LOOP
  IF NOT has_sequence_privilege('simrs_core',t,'USAGE') OR has_sequence_privilege('simrs_report',t,'USAGE') OR has_sequence_privilege('simrs_report',t,'SELECT') THEN RAISE EXCEPTION 'Incorrect code sequence privileges: %',t; END IF;
 END LOOP;
 IF has_table_privilege('simrs_report','public.users','SELECT') OR has_table_privilege('simrs_report','public.medical_records','SELECT') OR has_schema_privilege('simrs_report','reporting','CREATE') THEN RAISE EXCEPTION 'Report account excess permissions'; END IF;
END $$;
SQL
echo 'PASS: ledger append-only permission assertions'
