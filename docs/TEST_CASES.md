# Skenario pengujian teknis

Daftar ini menyatakan rancangan tes dan hasil yang diharapkan, bukan bukti setiap kasus dijalankan terpisah. Hasil aktual suite tersedia di [TESTING](TESTING.md) dan [indeks bukti](evidence/FINAL_RUNTIME_EVIDENCE.md). Gunakan DB dummy baru, koneksi independen untuk race, dan rekam waktu, perintah, harapan, observasi dan status.

| ID | Skenario | Hasil yang diharapkan |
|---|---|---|
| T01 | Login enam role, password salah, expiry dan logout | Token valid hanya akun benar; revoked 401 |
| T02 | Registration membayar, doctor akses pasien dokter lain, header role palsu | 403/404 sesuai scope, tanpa mutasi |
| T03 | CRUD master, duplikat kode/identitas, harga invalid, delete berreferensi | Valid tersimpan, konflik/422, histori terlindungi |
| T04 | Daftar pasien aktif dengan jadwal valid/salah | Snapshot tarif dan antrean benar; invalid ditolak |
| T05 (D1) | Dua request bersamaan pasien/poli/hari sama | Satu insert, satu 409, counter sekali |
| T06 (D1) | Pasien berbeda, poli/hari sama | Nomor berbeda, kedua visit ada |
| T07 | Mulai, simpan record dan vital, versi lama | Histori/audit; stale 409 |
| T08 | Dua tindakan, harga master berubah | Snapshot tetap, total benar |
| T09 | Resep dua obat | Stok belum berubah saat resep, dispense sekali |
| T10 (D2) | Stok 5, dua resep masing-masing 5 bersamaan | Satu sukses, satu 409, stok 0, satu movement |
| T11 (D2) | Item kedua tidak cukup setelah item pertama | Semua perubahan rollback, tetap pending |
| T12 | Dua item lab melalui Node, histori dokter | Pending sampai lengkap, Yii menyimpan hasil |
| T13 (D5) | Event lab UUID/payload sama, lalu beda | Replay aman, beda 409, satu ledger |
| T14 | Finish record belum lengkap atau layanan pending | Ditolak jika record belum lengkap; invoice belum terbit saat pending |
| T15 | 25k pendaftaran +150k tindakan +150k lab +75k obat | Rp400.000, empat kategori, satu invoice |
| T16 | Bayar 250k tunai lalu 150k non_cash, coba overpay | Partial→paid; saldo benar, overpay ditolak |
| T17 (D3) | Respons commit hilang, retry key/body sama | Satu pembayaran/kuitansi; beda payload 409 |
| T18 (D3) | Key sama paralel; key berbeda berebut saldo | Replay satu; tidak overpay |
| T19 (D4) | Revisi sebelum bayar, kasir memakai revisi lama | Revisi/total/audit atomik, stale 409 |
| T20 (D4) | Pembayaran pertama vs revisi bersamaan | Serial; salah satu ditolak berdasarkan status/revisi |
| T21 (D4) | Diagnosis setelah lunas dan coba ubah tindakan | Diagnosis diaudit, invoice tetap, tindakan ditolak |
| T22 (D5) | Node berhenti, lab fallback Core, dashboard | Core tersedia; dashboard error, bukan nol |
| T23 (D5) | Core berhenti, retry event setelah pulih/timeout ambigu | 503 tanpa false ack, satu hasil setelah retry |
| T24 (D6) | Dataset dummy, bandingkan SQL, EXPLAIN | Agregat dan tanggal WIB tepat, plan tercatat |
| T25 | Akun Node SELECT/UPDATE tabel inti | Permission denied, view SELECT berhasil |
| T26 | Cetak invoice dan dua kuitansi | Nominal/nomor tepat, tanpa isi klinis lain |
| T27 | Total invoice nol | Paid/completed tanpa pembayaran |
| T28 | Semua metric dan rincian, role lain | Data konsisten per snapshot, tanpa PHI, 403 non-admin |
| T29 | Master nonaktif dengan histori, adjustment stok | Histori tetap, pilihan baru dibatasi, ledger benar |
| T30 | UI enam role dan alur lengkap | Menu sesuai izin, data tersimpan dan lunas |

## Skenario tambahan per modul

Klinis: create bersamaan; record vs tindakan, finish stale, akses lintas dokter, harga/nama snapshot, master nonaktif, alasan setelah section dikosongkan, histori dan audit. `clinical-smoke.py --db-check`.

Farmasi/lab: qty invalid/master inactive 422/409, order tidak parsial; dua tab stale; stock race dan rollback multi-item; dispense replay; hasil pertama saat in_exam mengunci order tetapi tidak mengakhiri pemeriksaan; same-key concurrent satu event; key beda pada item final 409; lab selesai dengan obat pending tetap services_pending; layanan terakhir memicu invoice; Node mati/fallback; role tidak sah 403. `ancillary-smoke.py --db-check --outage`.

Billing: invoice setelah semua layanan; issue replay; revisi unpaid dan audit; stale invoice; same-key parallel dan mismatch body/invoice/actor; overpay; partial/full; dua kasir; pembayaran vs revisi; edit biaya setelah bayar ditolak, diagnosis saja diizinkan; total nol; admin dilarang bayar; cetak; retry setelah refresh memakai key/body sama. `billing-smoke.py --db-check`.

Laporan/admin: enam role dan no-token; range invalid/kabisat/366 hari, metric/paging, agregat tanpa fan-out, SQL langsung, batas WIB dan beda tanggal pembayaran, filter poli; create akun, duplikat username/staff, reset/role/status, revokasi, stale version, self-edit/binding dokter; audit metadata dan filter eksak; role admin berubah ketika mutasi; EXPLAIN periode sempit/luas. `report-admin-smoke.py`.

Keamanan/QA: replay adjustment dan key salah/hilang, concurrency key berbeda; dua dokter scope terpisah; dua kasir dan key aktor berbeda; JSON rusak, content-type salah, body besar, bearer salah, Origin invalid, password NUL/panjang, ID bigint/query/SQL-like filter; grant ledger tanpa UPDATE/DELETE/TRUNCATE; Node tidak boleh baca tabel inti atau CREATE schema; invariant stok, adjustment, pembayaran, invoice, status, dispense, lab, antrean; migration round-trip hanya DB QA; sesi browser lintas tab. `security-smoke.py`, `verify-db-grants.sh`, `verify-invariants.sh`, `qa-runtime.py`.

## Metode bukti

Concurrency menggunakan request paralel/koneksi DB independen, bukan hanya Newman sequential. Gunakan PHP policy tests, Vitest helper, smoke HTTP/SQL, dan browser/print. Jangan menyebut PHPUnit bila tidak benar-benar dijalankan; suite PHP aktual adalah skrip policy. Catat bukti per kasus, data dummy, tanpa token atau rahasia. Rencana browser: [QA manual](QA_MANUAL_CHECKLIST.md).
