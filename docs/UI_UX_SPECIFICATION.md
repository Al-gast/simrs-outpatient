# Spesifikasi UI/UX SIMRS Rawat Jalan

Arah visual: Premium Healthcare SaaS, sidebar putih, aksen teal, layout luas, mode terang. Bahasa Indonesia, Inter atau system sans-serif, ikon outline Lucide, Ant Design. Hindari gradien berlebihan, animasi dekoratif dan efek kaca. Desktop-first dengan responsivitas; target aksesibilitas WCAG 2.2 AA belum diaudit secara menyeluruh. `UI_TOKENS.json` adalah konfigurasi desain yang tidak diubah dalam lokalisasi dokumentasi.

| Token | Nilai |
|---|---|
| Primer/hover/soft | #0F766E / #115E59 / #F0FDFA |
| Latar/surface/border | #F8FAFC / #FFFFFF / #E2E8F0 |
| Judul/teks/muted | #0F172A / #475569 / #64748B |
| Sukses/latar | #15803D / #DCFCE7 |
| Peringatan/latar | #B45309 / #FEF3C7 |
| Informasi/latar | #1D4ED8 / #DBEAFE |
| Bahaya/latar | #B91C1C / #FEE2E2 |
| Fokus | Outline teal 2px, offset 2px |
| Spasi | 4,8,12,16,20,24,32,40 px |
| Sidebar | 264/80 px |
| Header | 72 px |
| Padding desktop/card | 32/24 px |
| Radius card/input/button | 16/10/10 px |
| Tinggi kontrol utama | 44 px |
| Baris tabel minimum | 56 px |
| Judul/subjudul/body/caption | 28/18/14/12 px |
| Berat font | 400/500/600/700 |
| Bayangan | 0 1px 3px rgba(15,23,42,.06) |

Nilai token merupakan spesifikasi desain; CSS aktual dapat menyesuaikan kebutuhan komponen. Angka tabular, nominal rata kanan jika sesuai, status memakai teks bukan warna saja. Kontrol fokus jelas; icon-only punya accessible name/tooltip. CTA utama teal, sekunder outline, merah untuk destruktif.

## Shell dan responsivitas

Sidebar putih dengan divider, brand simrs., menu sesuai role, item aktif teal muda; Master Data berisi tujuh anak tanpa ikon bagi admin. Header breadcrumb, menu/collapse, profil dan logout. Halaman punya judul/deskripsi, tindakan utama, filter dan area kerja. Tidak ada pencarian pasien global untuk role tidak berizin. Target ≥1280 sidebar penuh, 1024–1279 dapat collapse, <1024 navigasi drawer; <640 form satu kolom dan tabel scroll lokal. Modal sederhana di tengah dengan footer terjangkau. Uji 1440/1280/1024/768/375–390, zoom 200% dan keyboard; hasil rinci belum terbukti penuh.

## Ruang kerja per peran

| Peran | Halaman | Interaksi utama |
|---|---|---|
| admin | `/dashboard` | Empat KPI, grafik kunjungan/kas, ringkasan tagihan, donut, enam modal rincian, monitoring read-only, master, akun dan audit. |
| registration | `/registration` | Modal pilih pasien/poli/jadwal aktif hari ini, tarif preview, antrean, filter dan histori administratif. |
| doctor | `/doctor`, `/doctor/:visitId` | Antrean sendiri, lima tahap rekam medis/tindakan/resep/lab/review, simpan dan lanjutkan, skip opsional, histori dan draft guard. |
| pharmacy | `/pharmacy` | Daftar resep, modal rincian, qty/unit/subtotal, konfirmasi stok dan penyerahan. |
| laboratory | `/laboratory` | Permintaan, hasil multiline, simpan Node, retry/fallback eksplisit. |
| cashier | `/billing`, `/billing/:visitId` | Invoice terurut, saldo, progress, pembayaran dan bukti cetak. |

Login username/password, show-password, loading, error generik, landing sesuai role; sesi dipulihkan dengan HttpOnly sesuai arsitektur. Tidak ada signup publik. Admin monitor `/billing-monitor/:visitId` tidak mempunyai form bayar. Master form sederhana dua kolom desktop/satu mobile, nomor otomatis, aksi lihat/ubah/status/delete dengan tooltip, konfirmasi kontekstual. Stok adjustment pilih tambah/kurang dan preview saldo; retry key dipertahankan. Akun role eksplisit, edit/reset/status terpisah, self-edit ditolak. Audit filter petugas searchable, action/entity eksak dan detail metadata. Dashboard membedakan basis tanggal kas/tagihan; error tidak ditampilkan sebagai nol.

## Perilaku bersama

Gunakan format id-ID, WIB, Rupiah integer dan pemisah ribuan. Input nominal tidak boleh menormalisasi desimal, negatif atau input tidak valid secara diam-diam. Format nomor RX/LAB/INV/PAY enam digit untuk tampilan, raw ID tetap. Status waiting = Menunggu, in_exam = Diperiksa, services_pending = Pelayanan penunjang, ready_for_billing = Siap pembayaran, completed = Selesai. unpaid/partial/paid = Belum dibayar/Dibayar sebagian/Lunas. Label diterjemahkan, payload tetap canonical.

401 login/restore; 403 terlarang; 404 tidak ditemukan/scope; 409 reload atau rekonsiliasi, 422 error field, 503 baca retryable atau transaksi belum pasti; 500 error generik dengan Request ID. Jangan auto-retry mutasi tanpa key. State loading/empty/error sesuai filter; draft klinis belum disimpan memberi konfirmasi, navigasi bersih tidak perlu prompt. Print A4 memakai text node, tanpa shell, instruksi mematikan header/footer Chrome. Kuitansi mewakili transaksi individual.

## Verifikasi

Tangkapan layar v1.1.0 membuktikan layout desktop alur utama, bukan semua ukuran dan aksesibilitas. Lihat [QA manual](QA_MANUAL_CHECKLIST.md), [bukti aktual](evidence/FINAL_RUNTIME_EVIDENCE.md). Backend, API dan RBAC tetap otoritatif.
