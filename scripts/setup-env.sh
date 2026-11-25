#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
if [ -e .env ]; then echo '.env already exists; not overwritten.'; exit 0; fi
umask 077
{ printf 'POSTGRES_PASSWORD=%s\n' "$(openssl rand -hex 24)"; printf 'CORE_DB_PASSWORD=%s\n' "$(openssl rand -hex 24)"; printf 'REPORT_DB_PASSWORD=%s\n' "$(openssl rand -hex 24)"; printf 'JWT_SECRET=%s\n' "$(openssl rand -hex 32)"; printf 'DEMO_PASSWORD_PREFIX=%s\n' "$(openssl rand -hex 8)"; printf 'FRONTEND_ORIGIN=http://localhost:3000\n'; } > .env
printf 'Created .env with random local secrets. Do not commit it.\n'
