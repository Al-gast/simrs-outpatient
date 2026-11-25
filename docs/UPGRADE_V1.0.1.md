# Safe update to v1.0.1 (existing Mac UAT)

Use the incremental `simrs-v1.0.0-to-v1.0.1.patch` distributed alongside cumulative ZIP. It applies only changed files; it does not include `.env`, volume contents or lockfiles. The cumulative ZIP is for a fresh checkout or submission after merging any Mac-only fixes. The previous Mac build may contain changes not present in the archived source. Never blindly overwrite those files.

1. In the **same project directory** and with the same Compose project name that owns UAT data, confirm `docker compose ps -a`, `docker compose ls`, and verify current data in the UI. Keep `.env` unchanged. If using a retained QA project, use its actual directory and project name. Do not switch to a new volume.
2. Back up database before upgrade. Stop writers to avoid business changes during backup/upgrade. Example for ordinary project:

```bash
# Run from existing project root. Keep backup outside the repo.
docker compose stop frontend node yii
mkdir -p "$HOME/simrs-uat-backups"
BACKUP="$HOME/simrs-uat-backups/simrs-before-v101-$(date +%Y%m%d-%H%M%S).dump"
umask 077
docker compose exec -T db pg_dump -U simrs_owner -d simrs -Fc > "$BACKUP"
docker compose exec -T db pg_restore -l < "$BACKUP" > /dev/null
ls -lh "$BACKUP"
```

Do not proceed if dump or validation fails. If DB is stopped, start it first with `docker compose up -d db` and wait for healthy. Keep the dump private (dummy data only).

3. Apply incremental patch after checking it. Adjust the patch path to the actual download location. `git apply` works even without an initialized repository. If a local edit causes conflict, do not force apply or overwrite; reconcile first. If the only conflict is `scripts/qa-runtime.py` because the earlier port fix was made locally, it is safe to preserve that local runner via `--exclude=scripts/qa-runtime.py` (all expanded tests are in separate existing stage scripts). Recheck other hunks first.

```bash
git apply --check "$HOME/Downloads/simrs-v1.0.0-to-v1.0.1.patch"
git apply "$HOME/Downloads/simrs-v1.0.0-to-v1.0.1.patch"
```

4. Build sequentially to conserve memory. Apply migrations **without reseeding** the existing UAT DB. Start only the updated services after migration passes:

```bash
docker compose --parallel 1 build
docker compose run --rm --no-deps migrate php yii migrate --interactive=0
docker compose up -d --no-build --no-deps yii
# Wait until Yii reports healthy; verify /health before proceeding.
docker compose ps -a
curl -fsS http://localhost:8080/api/v1/health
docker compose up -d --no-build --no-deps node frontend
docker compose ps -a
```

Expected: fourth migration adds five code sequences; prior migrations already applied. No table reset or reseed. If build or migration fails, stop and inspect actual log; do not run `down -v`, `migrate/fresh` or drop DB. Old `migrate` one-shot container may still show its previous successful exit; the one-off command above is the actual upgrade migration. Preserve original project name and volume.

5. Test v1.0.1 against the existing dummy DB manually: login (one-time new cookie), reload, confirm same protected route, test logout/back; check master and clinical cases in `QA_MANUAL_CHECKLIST.md`; compare prior invoices and stock (INV-2 paid Rp350,000, INV-1 unpaid Rp25,000 and OB001 stock 90 if no later changes). Browser refresh now restores session but does not preserve unsaved clinical drafts.
6. For full regression use the separate isolated `scripts/qa-runtime.py --outage` runner. Stop ordinary stack with `docker compose down` **without -v** to free ports; it retains data. Do not run the mutating smoke scripts directly against the existing UAT DB. After isolated QA completes (without `--keep`), start ordinary services with original project/volume; if using the updated migration command, avoid unnecessary reseed. Save actual logs/screenshots only after execution.

Rollback: prefer forward fixes. Code-only rollback to earlier source leaves additive sequences harmless, but new cookies must be invalidated via logout. Do not downgrade migration if a generated sequence was used; its `safeDown` explicitly refuses. Restoring the dump would replace post-backup changes and must be a deliberate recovery action, never an automatic update step.
