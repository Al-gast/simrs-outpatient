# Daftar periksa QA manual dan bukti

Gunakan data dummy; catat tanggal, browser/perangkat, harapan, observasi, status dan file bukti. Ini daftar skenario, bukan pernyataan bahwa semua baris telah dijalankan. Hasil aktual dan batas cakupan ada di [UAT final](FINAL_UAT.md). Gunakan QA terisolasi untuk mutasi negatif, race dan outage. Jangan mengubah invoice paid lama hanya untuk tes.

## Alur dasar

| ID | Aksi | Harapan |
|---|---|---|
| UI-01 | Login enam role | Landing/menu tepat |
| UI-02 | URL terlarang, logout, refresh/back | Login/penolakan tanpa cache terlindungi |
| UI-03 | CRUD/cari/filter/pagination pasien | Validasi dan data tersimpan |
| UI-04 | Adjustment, respons ambigu, retry | Satu movement; payload beda ditolak |
| UI-05 | Pendaftaran pasien/jadwal | Antrean unik dan dokter melihat |
| UI-06 | Rekam medis/tindakan/resep/lab, dua tab | Versi lama 409, draft tetap |
| UI-07 | Lab Node, outage dan fallback | Tidak ada hasil ganda/false ack |
| UI-08 | Dispense resep | Stok berkurang sekali |
| UI-09 | Rincian invoice empat kategori | Snapshot/total tepat |
| UI-10 | Nominal desimal/negatif/lebih saldo | Tidak dibulatkan diam-diam; ditolak |
| UI-11 | Pembayaran sebagian/lunas dan retry | Saldo benar, key stabil |
| UI-12 | Revisi unpaid dan bayar versi lama | Stale ditolak, biaya paid terkunci |
| UI-13 | Cetak invoice/kuitansi Chrome/Safari | A4 terbaca, waktu/nominal tepat |
| UI-14 | Periode/rincian laporan | Angka SQL sesuai, empty tidak error |
| UI-15 | Akun/reset/status dan token lama | Revokasi, self-edit ditolak |
| UI-16 | 1440/1024/375 dan keyboard | Responsive dan fokus |
| UI-17 | Network/Console/CSP | Tidak ada error tak terduga atau bocor rahasia |
| UI-18 | Form kotor, close/reload/navigasi | Konfirmasi sesuai scope, tidak kehilangan diam-diam |

## Sesi dan aturan bisnis v1.0.1

| ID | Aksi | Harapan |
|---|---|---|
| V101-UI-01 | Refresh/back tiap role | Role/rute pulih tanpa flash data |
| V101-UI-02 | Logout lalu reload/back | Login wajib, token lama ditolak |
| V101-UI-03 | Reset/nonaktif akun pada profil lain | Restore lama ditolak |
| V101-UI-04 | Core berhenti lalu pulih | Error/retry, bukan logout otomatis |
| V101-UI-05 | Storage dan Network | Bearer tidak persisten, cookie scoped Strict |
| V101-UI-06 | Kode otomatis dan API paralel | Unik, kode lama tetap |
| V101-UI-07 | Status dan DELETE ketat | Referensi menolak delete tanpa deaktivasi |
| V101-UI-08 | Simpan pertama dan edit section | Alasan awal opsional, edit wajib |
| V101-UI-09 | Kosongkan dan tambah lagi | Alasan tetap wajib dan audit |
| V101-UI-10 | Cek INV-1/2 dan OB001 sebelum transaksi baru | Data lama tetap |

## Presentasi v1.0.2

| ID | Aksi | Harapan |
|---|---|---|
| UI102-01 | Collapse/expand Master | 7 anak tanpa ikon, aktif teal |
| UI102-02 | Enam role dan menu mobile | Anak sesuai RBAC, keyboard |
| UI102-03 | CRUD 7 master dan akun | Modal tengah, desktop 2 kolom/mobile 1 |
| UI102-04 | Input invalid, cancel, pending | Validasi, mask tidak menutup, tidak double submit |
| UI102-05 | Modal daftar dan riwayat | Picker di atas, draft kembali, same-day |
| UI102-06 | Hover/focus/touch icon | Tooltip/label, aksi utama teks |
| UI102-07 | Ketik 150000, edit di tengah | 150.000 tampil, raw 150000 |
| UI102-08 | Paste 150.000 vs 1.5/1,5/-1000/abc/1.000,50/01.000 | Valid ribuan; invalid ditolak |
| UI102-09 | Tambah 50, kurangi 30 | Preview, server menolak stok kurang |
| UI102-10 | Retry adjustment tidak pasti | Payload/key frozen, tidak ganda |
| UI102-11 | Zona waktu perangkat | Tanggal sama, timestamp WIB, ISO tetap |
| UI102-12 | Cari/filter/empty/reload | Placeholder terbaca, hasil benar |
| UI102-13 | Badge dan link | Warna semantik dan teal konsisten |
| UI102-14 | Lab/dispense | Modal tengah, multiline, mutasi sekali |
| UI102-15 | 375/390/768/1024/1280/1440 dan zoom 200% | Scroll lokal, footer terjangkau |
| UI102-16 | Refresh dan data sebelum upgrade | Sesi/data terjaga |

## Klinis dan billing v1.0.3

| ID | Aksi | Harapan |
|---|---|---|
| WF103-01 | Buka/refresh workspace dokter, role lain | Rute khusus dan scope benar |
| WF103-02 | Rekam medis invalid lalu valid | Validasi, simpan menaikkan versi, belum ada finish |
| WF103-03 | Skip langkah opsional kosong | Tanpa mutasi tak perlu, estimasi pendaftaran |
| WF103-04 | Simpan tindakan/resep/lab | Versi naik dan review sesuai |
| WF103-05 | Draft kotor lalu navigasi/back/reload | Konfirmasi, cancel menjaga draft |
| WF103-06 | API gagal/stale/interupsi | Draft dipertahankan, tidak maju |
| WF103-07 | Buka histori lalu tutup | Stage/draft tetap; pasien lain dibatasi |
| WF103-08 | Cancel finish lalu konfirmasi | Satu transisi, pending vs ready |
| WF103-09 | Buka paid/completed | Tidak ada resep/lab baru, koreksi sesuai aturan |
| WF103-10 | Detail farmasi/lab sebelum/sesudah | Unit/total, stok sekali, retry lab, multiline |
| WF103-11 | Cari invoice lama/padded | Invoice sama, ID DB tidak berubah |
| WF103-12 | Kasir/admin URL unpaid | Kasir bayar; admin read-only; role lain ditolak |
| WF103-13 | 150000 dari 350000 lalu 200000 | 42,8% lalu 100%, dua kuitansi |
| WF103-14 | Navigasi saat pembayaran tidak pasti | Key/body sama, tidak double charge |
| WF103-15 | Cetak dengan header/footer off | A4, nominal panjang, multi-page, kuitansi individual |
| WF103-16 | Desktop/tablet/mobile, keyboard | Stepper, fokus, scroll dan tabel |

## Dasbor dan admin v1.0.4

| ID | Aksi | Harapan |
|---|---|---|
| ADM104-01 | Periode 1–24 Nov baseline | 2 kunjungan, 350k kas, 375k tagihan, 25k sisa |
| ADM104-02 | Bayar di luar periode visit | Kas menurut paid_at; cohort paid mencakup pembayaran berikut |
| ADM104-03 | 1 hari/kosong/sparse/366 hari | Tanggal/zero benar, tabel tersedia |
| ADM104-04 | Donut kosong/partial/paid/unpaid | Proporsi jumlah invoice |
| ADM104-05 | 6 rincian/per poli, halaman 2 | Modal tengah, periode/scope, privasi, posisi terjaga |
| ADM104-06 | Node mati lalu retry | Error bukan nol, retry |
| ADM104-07 | Cari/filter akun, staff nonaktif | Nama staff/fallback benar, role eksplisit |
| ADM104-08 | Edit tanpa password | Nama berubah, status/hash tetap, revokasi |
| ADM104-09 | Reset saja | Token/password lama ditolak, role/staff/status tetap |
| ADM104-10 | Status, stale, self | Nonaktif ditolak, aktif kembali, 409 stale, self diblokir |
| ADM104-11 | Cari petugas, filter eksak/reset/page | Sesuai query, reset bulan ini |
| ADM104-12 | Detail audit kode dikenal/lama | Label/raw fallback, ID/request ID, tanpa rahasia |
| ADM104-13 | Monitoring dan rute non-admin | Read-only, non-admin ditolak |
| ADM104-14 | 375/mobile/tablet/desktop/keyboard | Grafik/modal/dropdown/fokus dapat digunakan |

## Verifikasi v1.1.0

Login dua akun berbeda secara berurutan dalam dua tab; tab lama kehilangan state lokal, sesi baru tidak dicabut oleh logout bearer lama. Uji HTTP stale bearer vs cookie baru, restore 401 dan refresh/logout normal. Gunakan profil terpisah untuk role bersamaan. Lihat [FINAL_UAT](FINAL_UAT.md).

Kumpulkan screenshot asli, PDF, ringkasan Newman dan QA yang disaring; raw logs, `.env`, data medis asli, token dan backup tetap privat. Pengujian browser rinci, mobile, zoom, Safari, accessibility dan perilaku tab belum seluruhnya terbukti; laporkan apa adanya.
