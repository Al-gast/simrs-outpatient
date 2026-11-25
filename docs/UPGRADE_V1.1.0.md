# Upgrade MacBook: v1.0.0 → v1.1.0 release candidate

**Preserve existing dummy UAT volume, `.env`, local changes and project name.** Runtime v1.1.0 is not yet verified. This is a migration-only upgrade, not a fresh seed. All shell steps are manual gates: stop if a command fails; inspect logs before continuing. Never run `docker compose down -v`, `migrate/fresh`, `seed/index`, `DROP DATABASE`, or an automatic restore on the preserved project.

## A. Identify the running project and back it up

From the existing project root (usual Mac path `/Users/ata/Developer/simrs-outpatient`), verify that this is the stack that actually owns your UAT data. If UAT is in a retained QA stack use its **actual** directory and explicit project name instead throughout. Keep any original `-p` / `COMPOSE_PROJECT_NAME` settings. Record the existing invoice balances and stock in the browser before stopping.

```bash
pwd
docker compose ls
docker compose ps -a
docker compose config --quiet
# Read-only check: verify actual named volume and migration history.
docker inspect "$(docker compose ps -q db)" --format '{{range .Mounts}}{{println .Name .Destination}}{{end}}'
docker compose exec -T db psql -U simrs_owner -d simrs -Atc 'SELECT version FROM migration ORDER BY version'
```

Expected baseline: 000001, 000002, 000003 (000004 may already exist if partly upgraded). If database not running, start only db and wait until healthy. Never create a second volume to 'fix' missing data.

```bash
# Stop writers and browser UI. Keep DB running.
docker compose stop frontend node yii
umask 077
BACKUP="$HOME/simrs-uat-backups/before-v110-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP"
cp -p .env "$BACKUP/.env"
tar --exclude=.git --exclude=node_modules --exclude=vendor --exclude=dist --exclude=qa-results -czf "$BACKUP/source.tar.gz" .
docker compose exec -T db pg_dump -U simrs_owner -d simrs -Fc > "$BACKUP/database.dump"
test -s "$BACKUP/database.dump"
docker compose exec -T db pg_restore -l < "$BACKUP/database.dump" > "$BACKUP/restore-toc.txt"
test -s "$BACKUP/restore-toc.txt"
shasum -a 256 "$BACKUP/database.dump" > "$BACKUP/database.sha256"
ls -lh "$BACKUP"
```

Dump TOC and checksum are sanity checks, not proof of a successful restore; optionally rehearse restoration in an isolated DB before upgrading. Backups contain credentials and data: keep outside Git, private, never attach to submission. The source archive includes `.env`; do not share it. Preserve Git metadata separately if desired.

## B. Apply incremental patch

Download `simrs-v1.0.0-to-v1.1.0.patch`. It was verified against the original v1.0.0 ZIP, not against the Mac working directory. Local build fixes may differ.

```bash
PATCH="$HOME/Downloads/simrs-v1.0.0-to-v1.1.0.patch"
git apply --stat "$PATCH"
git apply --check "$PATCH"
# Only if check succeeds:
git apply "$PATCH"
```

If there is a conflict, DO NOT use `--reject` or force overwrite blindly. Compare the local file with the matching file from the final ZIP; preserve local Docker/build fixes. `scripts/qa-runtime.py` had a previous local port fix and now has a sequential build change: manually merge both (port listener detection + `--parallel 1 build`, then `up --no-build`). Recheck affected hunks; `git apply` works outside a repository too. Do not replace project folder or `.env` with fresh ZIP. If a file has already been patched, inspect `git apply --reverse --check` before attempting again.

## C. Fingerprint, build, migration only

```bash
# Compare all public business tables before and after migration; hashes only.
docker compose exec -T db psql -X -qAt -U simrs_owner -d simrs -v ON_ERROR_STOP=1 < scripts/upgrade-fingerprint.sql > "$BACKUP/before.txt"
test -s "$BACKUP/before.txt"
docker compose --parallel 1 build
# Explicitly override migrate service command: do not run its default seed.
docker compose run --rm --no-deps migrate php yii migrate --interactive=0
docker compose exec -T db psql -X -qAt -U simrs_owner -d simrs -v ON_ERROR_STOP=1 < scripts/upgrade-fingerprint.sql > "$BACKUP/after.txt"
test -s "$BACKUP/after.txt"
diff -u "$BACKUP/before.txt" "$BACKUP/after.txt"
docker compose exec -T db psql -U simrs_owner -d simrs -Atc 'SELECT version FROM migration ORDER BY version'
```

Expected: empty diff and migration 000004 applied, five new sequences. No new table/column changes in v1.0.2–v1.1.0. The fingerprint is a lightweight deterministic check for a small dummy database, not a cryptographic guarantee or restore test; it excludes migration history and login_attempts and does not verify sequences. If any discrepancy or migration/build failure, stop, inspect; do not restart writers or reset data.

```bash
docker compose up -d --no-build --no-deps yii
# Wait for healthy; verify before continuing.
docker compose ps -a
curl -fsS http://localhost:8080/api/v1/health
docker compose up -d --no-build --no-deps node frontend
docker compose ps -a
curl -fsS http://localhost:3001/api/v1/health
curl -fsS -o /dev/null -w '%{http_code}\n' http://localhost:3000/
```

Use localhost. `--no-deps` is intentional: avoids running the default migration/seed container again. Normal startup of a fresh clone still uses Compose's migration+seed. For later restarts of the preserved stack, start db, wait healthy, then start Yii, Node/frontend with `--no-deps` as above. Do not treat an old one-shot migrate exit code as proof of this upgrade.

## D. Targeted retest and release evidence

1. Open browser, sign in once, reload protected page, verify six roles and old data. With unchanged UAT fixtures: 24 Nov 2026 Budi INV-1 Rp25,000 unpaid; Siti INV-2 Rp350,000 paid in two receipts (Rp150,000 + Rp200,000); OB001 stock 90. Report 1–24 Nov: 2 visits, 1 completed, Rp375,000 billed, Rp350,000 paid/received, Rp25,000 outstanding. These are expected from user-reported previous UAT, not a new test result. If additional UAT rows exist reconcile actual values.
2. Run [targeted UAT](FINAL_UAT.md) and [full checklist](QA_MANUAL_CHECKLIST.md). Do not alter historical paid invoice or seed users merely for testing. Capture updated screenshots and PDFs; no secrets.
3. Stop ordinary stack **without `-v`** to free ports. Run `python3 scripts/qa-runtime.py --dry-run --outage`, then `python3 scripts/qa-runtime.py --outage --keep` to execute destructive/concurrency tests on a fresh isolated DB. Keep actual QA summary. Follow printed isolated workdir/project name for Postman. Do not run individual mutating smoke scripts on original data.
4. Run Postman End-to-End collection (51 ordered requests) on the isolated dummy stack; capture runner results and Node response. If a test fails, stop and reconcile idempotent transaction before retrying. See `postman/README.md`.
5. Clean up only the explicitly named isolated QA stack when done. Restart preserved ordinary project using step C startup, no seed. Verify data again. Complete [submission gate](SUBMISSION_CHECKLIST.md).

## Recovery

Prefer a forward fix. For code-only rollback, retain the backup and additive sequences (older code ignores them); reconcile local changes and invalidate new sessions. Never reverse migration 000004 after a sequence has issued a code; `safeDown` refuses. A database restore is a separate deliberate operation, ideally first tested into a new isolated volume; it would replace post-backup changes, and is not an automatic rollback step. Never run migration rollback on the original UAT database just to test it.
