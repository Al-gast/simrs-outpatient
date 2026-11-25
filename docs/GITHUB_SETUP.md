# Panduan publikasi Git dan GitHub

Gunakan visibility sesuai izin rekrutmen. Jika publikasi materi penugasan tidak diizinkan, buat repository privat dan berikan akses evaluator. Jangan unggah PDF soal asli tanpa izin. Semua pasien/transaksi dummy. Commit awal telah dibuat lokal; gunakan `git status`, jangan inisialisasi ulang jika `.git` sudah ada.

```bash
git status --short
git diff --cached --check
git diff --cached --stat
git ls-files
```

Periksa file dan isi sebelum commit/push: `.env`, backup, dump, token, prefix, raw report Newman, node_modules/vendor, qa-results, data asli tidak boleh masuk. Hanya template env dan bukti yang sudah disaring. Scan regex dan nilai rahasia lokal membantu, bukan jaminan menyeluruh; tinjau juga gambar/PDF. Lockfile aktual perlu dibuat dan ditinjau ketika memungkinkan.

Buat repository kosong (tanpa README/gitignore/license tambahan). Sesuaikan URL nyata; jika origin sudah ada, periksa `git remote -v` dan jangan menimpanya sembarang.

```bash
git remote add origin https://github.com/<AKUN>/<REPOSITORY>.git
git push -u origin main
```

Setelah publikasi terverifikasi, tag opsional pada commit yang benar-benar diuji (perubahan dokumentasi tidak mengubah source).

```bash
git tag -a v1.1.0 -m "Rilis SIMRS Rawat Jalan v1.1.0"
git push origin v1.1.0
```

Periksa melalui clean clone atau akun yang berwenang; pastikan semua link README, schema, migration, koleksi dan bukti terbuka. Jangan force-push. Jika privat, pastikan evaluator dapat menerima undangan. Kirim URL dan bila perlu commit hash lewat email. Deployment tidak diwajibkan.
