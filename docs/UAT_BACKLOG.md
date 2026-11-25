# Backlog UAT hingga v1.1.0

Temuan berdasarkan UAT v1.0.0. Implementasi source telah selesai; tujuh kelompok UAT utama final lulus, tetapi tidak semua kasus UI rinci memiliki bukti tersendiri. Lihat [UAT final](FINAL_UAT.md) untuk cakupan dan keterbatasan.

| ID/kelompok | Temuan | Penanganan |
|---|---|---|
| BUG-004 | Refresh kembali login | v1.0.1 sesi HttpOnly; final hardening tab lama |
| Master kode | Kode otomatis | v1.0.1 sequence dan kompatibilitas kode lama |
| UI-010 | Aktivasi terpisah dari hapus | v1.0.1 PATCH status, DELETE ketat |
| UI-024 | Alasan awal klinis wajib | v1.0.1 alasan opsional pertama; edit/clear/re-add wajib |
| UI-008/011/014 | Modal tengah, ikon tooltip, Master 7 anak | v1.0.2; akun/audit dilengkapi v1.0.4 |
| UI-001/005/006/007/015/016/017 | Teal, search, format, placeholder, alert dan link | v1.0.2 perbaikan presentasi |
| UI-009/013 | Konfirmasi hapus kontekstual, arah penyesuaian stok | v1.0.2; signed delta dan retry key tetap |
| UI-018–023 | Workspace klinis, tahapan, review, pesan tanpa layanan | v1.0.3; alur utama dibuktikan |
| UI-025/026/031 | Dosis/frekuensi/unit, nomor padded, total resep | v1.0.3; raw identifier tetap |
| UI-027–030 | Badge, placeholder lab, empty state, multiline | v1.0.2 dan v1.0.3 |
| UI-032–036 | Rupiah, urutan invoice, halaman billing, progress, print | v1.0.2/3; bukti PDF aktual, footer browser minor |
| UI-037–043 | Tren, donut, ringkasan, status, modal rincian | v1.0.4; tanggal kas dan tagihan terpisah |
| UI-044/045 | Monitoring read-only dan judul sesuai role | v1.0.2/4 |
| UI-046–050 | Nama staff, aksi akun terpisah, ikon, role eksplisit | v1.0.4; pengujian dikonfirmasi |
| UI-051–057 | Dropdown audit, terjemahan, modal detail, reset | v1.0.4; kode filter tetap eksak |
| BR-001 | Peringatan di luar jam praktik | Ditunda; bukan blocker, perlu definisi aturan waktu |
| BR-002 | Booking tanggal mendatang | Ditunda; perlu kontrak tanggal dan regression baru |
| Bukti Postman/Node | Sebelumnya tertunda | Lulus Newman 51/51, 133/133 |
| Bukti tambahan | Detail admin unpaid, histori khusus, semua drill-down, responsive | Sebagian tercakup API; bukti browser rinci belum lengkap |

Tidak ada perubahan scope produksi, real gateway atau offline durable queue. Pertahankan RBAC, histori dan transaksi. Tidak perlu menambah fitur sebelum submission. Hasil final: [indeks bukti](evidence/FINAL_RUNTIME_EVIDENCE.md).
