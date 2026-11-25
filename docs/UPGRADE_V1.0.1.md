# Panduan upgrade historis v1.0.1

Panduan ini berlaku untuk baseline v1.0.0 sebelum rilis kumulatif. Untuk upgrade final gunakan [v1.1.0](UPGRADE_V1.1.0.md). Jangan menerapkan patch lama ke v1.1.0. Gunakan direktori dan nama Compose yang memiliki UAT, pertahankan env/volume, periksa kondisi layanan. Stop writer dan backup sebelum patch.

```bash
# Jalankan dari root proyek existing; simpan backup di luar repo.
docker compose stop frontend node yii
mkdir -p "$HOME/simrs-uat-backups"
BACKUP="$HOME/simrs-uat-backups/simrs-before-v101-$(date +%Y%m%d-%H%M%S).dump"
umask 077
docker compose exec -T db pg_dump -U simrs_owner -d simrs -Fc > "$BACKUP"
docker compose exec -T db pg_restore -l < "$BACKUP" > /dev/null
ls -lh "$BACKUP"
```

Jangan lanjut jika dump/TOC gagal. Backup privat. Jika DB berhenti, mulai DB saja hingga healthy. Validasi patch; jika konflik, bandingkan dan gabungkan lokal, jangan force overwrite. Perbaikan lokal runner port dapat dipertahankan setelah hunk lain diperiksa.

```bash
git apply --check "$HOME/Downloads/simrs-v1.0.0-to-v1.0.1.patch"
git apply "$HOME/Downloads/simrs-v1.0.0-to-v1.0.1.patch"
```

Build sequential dan migration-only, tanpa seed ulang:

```bash
docker compose --parallel 1 build
docker compose run --rm --no-deps migrate php yii migrate --interactive=0
docker compose up -d --no-build --no-deps yii
# Pastikan Yii healthy dan periksa /health dahulu.
docker compose ps -a
curl -fsS http://localhost:8080/api/v1/health
docker compose up -d --no-build --no-deps node frontend
docker compose ps -a
```

Periksa data historis, health, sesi setelah refresh, auto-code, aktivasi/deaktivasi, alasan klinis dan regression API. Jangan jalankan `down -v`. Status historis fase ini sebelum runtime: lihat [catatan](V1.0.1_NOTES.md); hasil final di [bukti aktual](evidence/FINAL_RUNTIME_EVIDENCE.md).
