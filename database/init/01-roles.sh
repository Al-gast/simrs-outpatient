#!/bin/sh
set -eu
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" -v core_password="$CORE_DB_PASSWORD" -v report_password="$REPORT_DB_PASSWORD" <<'SQL'
CREATE ROLE simrs_core LOGIN PASSWORD :'core_password';
CREATE ROLE simrs_report LOGIN PASSWORD :'report_password';
REVOKE ALL ON DATABASE simrs FROM PUBLIC;
GRANT CONNECT ON DATABASE simrs TO simrs_core, simrs_report;
REVOKE ALL ON SCHEMA public FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO simrs_core;
SQL
