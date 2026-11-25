# Panduan upgrade MacBook v1.0.0 → v1.1.0 tanpa menghapus data

Panduan historis untuk upgrade existing, bukan instalasi baru. Upgrade aktual berhasil pada 25 November 2026 dengan merge lokal ClinicalService.php, migration 000004 dan fingerprint 23 tabel bisnis identik. Jangan mengulang upgrade pada proyek yang sudah v1.1.0. Pertahankan `.env`, nama Compose, volume dan perubahan lokal. Jangan menjalankan `down -v`, `migrate/fresh`, `seed/index`, DROP DATABASE atau restore otomatis. Hentikan jika perintah gagal. Gunakan root proyek dan nama `-p` aktual bila memakai QA yang dipertahankan.

## A. Preflight dan backup

Periksa stack yang benar, catat saldo invoice dan stok. Jika DB berhenti, jalankan hanya DB lalu tunggu healthy. Jangan membuat volume baru untuk memperbaiki data hilang.

```bash
pwd
docker compose ls
docker compose ps -a
docker compose config --quiet
# Periksa volume dan riwayat migration tanpa menulis data.
docker inspect "$(docker compose ps -q db)" --format '{{range .Mounts}}{{println .Name .Destination}}{{end}}'
docker compose exec -T db psql -U simrs_owner -d simrs -Atc 'SELECT version FROM migration ORDER BY version'
```

Baseline migration 000001–000003; 000004 mungkin sudah ada bila upgrade sebagian. Hentikan penulis sebelum backup.

```bash
# Hentikan penulis data dan UI; DB tetap berjalan.
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

TOC dan checksum hanya sanity check, bukan bukti restore. Arsip source berisi `.env`; backup harus privat di luar Git dan jangan ikut submission. Uji restore pada DB terisolasi bila memungkinkan.

## B. Patch kumulatif

Patch `simrs-v1.0.0-to-v1.1.0.patch` diverifikasi pada ZIP original, bukan semua perubahan MacBook. Periksa konflik terlebih dahulu.

```bash
PATCH="$HOME/Downloads/simrs-v1.0.0-to-v1.1.0.patch"
git apply --stat "$PATCH"
git apply --check "$PATCH"
# Hanya jika pemeriksaan berhasil:
git apply "$PATCH"
```

Jika konflik, jangan force apply. Bandingkan versi lokal dengan ZIP final, pertahankan perbaikan lokal. Pada upgrade aktual, `ClinicalService.php` menggunakan `new \yii\db\JsonExpression(..., 'jsonb')` untuk before/after audit, vital_signs dan secondary_diagnoses; gabungkan ke source final bersama `hasSavedSection()`, `reason_required` dan validasi alasan. Patch file lain lulus. Jika runner QA telah memiliki perbaikan port lokal, pertahankan guard port dan build sequential. `git apply` dapat bekerja tanpa repo Git. Periksa reverse check bila sudah terpasang. Jangan menimpa seluruh direktori atau env.

## C. Fingerprint, build dan migration-only

Gunakan variabel BACKUP dari tahap A dalam shell yang sama.

```bash
# Bandingkan fingerprint tabel bisnis sebelum dan sesudah migration.
docker compose exec -T db psql -X -qAt -U simrs_owner -d simrs -v ON_ERROR_STOP=1 < scripts/upgrade-fingerprint.sql > "$BACKUP/before.txt"
test -s "$BACKUP/before.txt"
docker compose --parallel 1 build
# Jalankan migration saja, jangan seed ulang.
docker compose run --rm --no-deps migrate php yii migrate --interactive=0
docker compose exec -T db psql -X -qAt -U simrs_owner -d simrs -v ON_ERROR_STOP=1 < scripts/upgrade-fingerprint.sql > "$BACKUP/after.txt"
test -s "$BACKUP/after.txt"
diff -u "$BACKUP/before.txt" "$BACKUP/after.txt"
docker compose exec -T db psql -U simrs_owner -d simrs -Atc 'SELECT version FROM migration ORDER BY version'
```

Diff harus kosong; migration 000004 membuat lima sequence. Fingerprint hanya tabel bisnis dan hash deterministik, tidak mencakup metadata migration, login_attempts atau sequence; bukan pengganti backup/restore. Jika berbeda, jangan jalankan writer. Jalankan layanan setelah verifikasi berhasil:

```bash
docker compose up -d --no-build --no-deps yii
# Pastikan healthy sebelum melanjutkan.
docker compose ps -a
curl -fsS http://localhost:8080/api/v1/health
docker compose up -d --no-build --no-deps node frontend
docker compose ps -a
curl -fsS http://localhost:3001/api/v1/health
curl -fsS -o /dev/null -w '%{http_code}\n' http://localhost:3000/
```

`--no-deps` mencegah default migration+seed kembali. Restart stack existing: mulai db, tunggu healthy, lalu yii, node dan frontend dengan no-deps. Health HTTP dan status container harus diperiksa. Pada clone baru gunakan panduan instalasi README.

## D. Retest dan bukti

- Verifikasi data awal 24 November: Budi INV-1 Rp25.000 belum dibayar, Siti INV-2 Rp350.000 lunas, OB001 stok 90 sebelum transaksi baru. Periode 1–24 November: 2 kunjungan, 1 selesai, Rp375.000 tagihan, Rp350.000 dibayar, Rp25.000 sisa. Ini baseline sebelum UAT baru, bukan nilai hari ini.
- Jalankan [UAT terarah](FINAL_UAT.md), jangan ubah invoice lama atau akun seed untuk sekadar tes.
- Hentikan stack utama tanpa `-v`, jalankan `python3 scripts/qa-runtime.py --dry-run --outage` dan `python3 scripts/qa-runtime.py --outage --keep` pada QA terisolasi; simpan log.
- Jalankan [Newman](../postman/README.md) pada QA, lalu stop QA dengan nama proyek tepat dan kembalikan stack utama. Jangan hapus volume utama.
- Hasil aktual tercatat di [indeks bukti](evidence/FINAL_RUNTIME_EVIDENCE.md).

## Pemulihan

Utamakan perbaikan maju. Rollback kode saja dapat mempertahankan sequence tambahan yang diabaikan kode lama. Rekonsiliasi perubahan lokal dan cabut sesi baru bila diperlukan. `safeDown` 000004 menolak rollback jika sequence sudah mengeluarkan kode. Restore database adalah operasi terpisah yang akan mengganti perubahan pasca-backup; uji pada volume terisolasi dahulu. Jangan menguji rollback pada DB UAT utama.
