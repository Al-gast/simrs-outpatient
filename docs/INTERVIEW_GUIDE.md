# Persiapan wawancara teknis

Pahami implementasi yang benar-benar berjalan; jangan menghafal angka atau mengklaim pengukuran yang tidak dapat dijelaskan. Gunakan source dan bukti aktual untuk mendukung jawaban.

| Pertanyaan | Pokok jawaban dan trade-off |
|---|---|
| Mengapa dua backend? | Yii2 penulis tunggal; Node integrasi lab dan SELECT view. Tidak ada duplikasi transaksi, tetapi Node bergantung Core untuk autentikasi. |
| Mengapa bukan MAX(queue)+1? | Counter UPSERT atomik, constraint unik, rollback dalam VisitService. Satu kunjungan/poli/hari. |
| Bagaimana race stok ditangani? | Lock visit, ID obat terurut, UPDATE bersyarat, ledger atomik. Tidak ada reservasi stok saat resep. |
| Respons pembayaran hilang? | Retry UUID/payload sama, cek ledger sebelum saldo. Key berbeda bukan transaksi yang sama. |
| Bagaimana versi klinis bekerja? | Versi bersama untuk perubahan klinis; stale 409, muat ulang dan rekonsiliasi, tanpa auto-merge. |
| Edit setelah invoice? | Tindakan hanya sebelum pembayaran pertama, invoice revision; diagnosis saja tetap boleh dengan audit. Tidak ada refund. |
| Mengapa integer Rupiah? | Bigint dan string desimal menghindari floating point; validasi batas. |
| Jika Node mati? | Timeout, replay key sama dan fallback Core; tidak ada false ack atau durable queue. |
| Apa yang mencegah IDOR? | Otorisasi backend dan scope doctor_id/staff_id, 404 di luar scope. Proyeksi DTO terbatas. |
| Bagaimana logout? | token_version, Node introspeksi, cookie HttpOnly; tab lama tidak mencabut sesi akun lain. Revokasi semua sesi akun, request in-flight mungkin selesai. |
| Mengapa audit append-only? | Runtime INSERT/SELECT tanpa UPDATE/DELETE; owner tetap dapat mengubah, bukan bukti kriptografis. |
| Definisi pendapatan? | Pembayaran tercatat menurut paid_at WIB; tagihan menurut kelompok tanggal kunjungan. |
| Optimasi laporan? | View, indeks, agregat, range tanggal dan pagination; plan dataset kecil bukan SLA. |
| Bukti concurrency? | Request paralel dan invariant SQL, bukan Postman sequential. Tunjukkan log 844 HTTP checks. |
| Apa yang belum siap produksi? | TLS, backup restore, monitoring, lockfile/advisory, load test, keamanan independen, privasi dan integrasi nyata. |

## Strategi demo

Jelaskan diagram arsitektur/ERD, lalu satu kunjungan dummy hingga lunas, invoice dan kuitansi, Node lab dan filter laporan. Tunjukkan log QA dan Newman (51 request/133 assertion) beserta batasannya. Untuk debugging, jelaskan evidence → hipotesis → verifikasi → root cause → perbaikan, misalnya merge `JsonExpression` lokal yang dipertahankan saat upgrade. Jangan mengarang pengalaman, hasil pengukuran atau riwayat commit.
