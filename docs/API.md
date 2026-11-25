# Referensi REST API v1.1.0

Core Yii2: `http://localhost:8080/api/v1`; Node: `http://localhost:3001/api/v1`. JSON UTF-8. Bearer JWT wajib kecuali health, login dan endpoint sesi browser khusus. ID dan nominal Rupiah dikirim sebagai string desimal agar aman untuk bigint; jumlah dan versi integer. Tanggal `YYYY-MM-DD` WIB; timestamp ISO-8601. Field DTO snake_case; field tulis yang tidak dikenal ditolak. Aktor, status dan harga tidak boleh ditentukan klien.

```json
{"data":{"id":"1"},"meta":{"request_id":"req-123"}}
```
```json
{"error":{"code":"INSUFFICIENT_STOCK","message":"Stok obat tidak mencukupi","details":{}},"meta":{"request_id":"req-123"}}
```

Daftar memakai `data: []`, `meta: {page,per_page,total,request_id}`. Default 1/20, maksimal 100 per halaman. Urutan stabil dengan ID sebagai pembeda. Status: 200 baca/ubah/replay, 201 buat, 204 penghapusan fisik/logout, 400 JSON rusak, 401 autentikasi, 403 izin, 404 objek tidak ditemukan/di luar scope, 409 konflik, 422 validasi, 429 pembatasan login, 503 tidak tersedia, 500 kesalahan tak terduga. Detail error tidak berisi SQL/rahasia.

## Endpoint Yii2

| Metode dan path | Akses | Permintaan/perilaku |
|---|---|---|
| `GET /health` | Publik | Kesiapan layanan dan DB |
| `POST /auth/login` | Publik | `username,password` → token, expiry dan user |
| `GET /auth/me` | Terautentikasi | Identitas/role/staff_id; introspeksi Node |
| `POST /auth/logout` | Terautentikasi | Pencabutan via token_version |
| `POST /auth/browser/login` | Origin diizinkan | Login dan cookie HttpOnly |
| `POST /auth/browser/restore` | Origin diizinkan | `{}`; pulihkan sesi belum kedaluwarsa |
| `POST /auth/browser/logout` | Origin diizinkan | `{}`; logout cookie/bearer |
| `GET/POST /admin/users` | admin | Daftar/buat akun |
| `PATCH /admin/users/{id}` | admin | Ubah akun dengan expected_version |
| `GET/POST /master/{resource}` | RBAC | Cari/daftar/buat master yang diizinkan |
| `GET/PATCH/DELETE /master/{resource}/{id}` | RBAC | Baca, ubah, hapus/deaktivasi |
| `POST /master/medicines/{id}/adjust-stock` | admin | delta, reason dan Idempotency-Key UUID |
| `GET /visits` | admin,registration,doctor | Filter kunjungan; scope dan proyeksi role |
| `POST /visits` | registration | patient_id, schedule_id |
| `GET /visits/{id}` | admin,registration,doctor | Detail administratif sesuai scope |
| `GET /patients/{id}/visits` | registration,doctor | Riwayat kunjungan sesuai RBAC |
| `POST /visits/{id}/start` | doctor | Mulai pemeriksaan, dokter ditugaskan |
| `GET /visits/{id}/clinical` | doctor | Rekam medis dan snapshot layanan |
| `GET /visits/{id}/clinical-history` | doctor | Metadata riwayat dengan pagination |
| `GET /visits/{id}/clinical-history/{target}` | doctor | Snapshot historis sesuai izin, akses diaudit |
| `PUT /visits/{id}/medical-record` | doctor | Rekam medis, expected_version, reason jika revisi |
| `PUT /visits/{id}/procedures` | doctor | items, expected_version, reason jika diperlukan |
| `PUT /visits/{id}/prescription` | doctor | items resep, expected_version, reason |
| `PUT /visits/{id}/lab-order` | doctor | items lab, expected_version, reason |
| `POST /visits/{id}/finish-exam` | doctor | expected_version dan rekam medis lengkap |
| `GET /pharmacy/orders` | pharmacy | Daftar resep |
| `GET /pharmacy/orders/{id}` | pharmacy | Detail dan stok |
| `POST /pharmacy/orders/{id}/dispense` | pharmacy | Penyerahan atomik seluruh resep |
| `GET /laboratory/orders` | laboratory | Daftar permintaan |
| `GET /laboratory/orders/{id}` | laboratory | Item dan status hasil |
| `POST /laboratory/items/{item_id}/result` | laboratory | result_text, Idempotency-Key UUID |
| `GET /billing/visits` | cashier,admin | Daftar tagihan dan filter |
| `GET /billing/visits/{id}` | cashier,admin | Invoice, item, pembayaran, saldo; ID kunjungan |
| `POST /billing/visits/{id}/invoice` | cashier | Buat invoice lama yang belum ada, replay aman |
| `POST /billing/visits/{id}/payments` | cashier | amount, method, expected_revision, Idempotency-Key |
| `GET /admin/audit` | admin | Metadata aktivitas |
| `GET /system/access/{role}` | Peran yang cocok | Probe otorisasi dasar |

## Endpoint Node.js

| Metode dan path | Akses | Perilaku |
|---|---|---|
| `GET /health` | Publik | Kesiapan DB pelaporan dan Core |
| `GET /laboratory/orders/{id}` | laboratory | Proxy terautentikasi ke Core |
| `POST /laboratory/items/{item_id}/result` | laboratory | Validasi UUID/teks, teruskan bearer/key asli |
| `GET /reports/summary?from=...&to=...` | admin | Agregat view pelaporan |
| `GET /reports/details?metric=...&from=...&to=...` | admin | Rincian dengan pagination dan allowlist metric |
| `GET /system/access/{role}` | Peran yang cocok | Probe peran |

## Autentikasi dan sesi

Login bearer biasa tetap kompatibel. Browser login mengatur `simrs_browser_session` (host-only, HttpOnly, SameSite=Strict, Path=/api/v1/auth/browser/, Max-Age=7200, Secure untuk HTTPS). Restore mengembalikan bearer asli dan sisa expiry; 401 tanpa menghapus cookie. Logout memprioritaskan bearer eksplisit jika ada; cookie akun berbeda tidak ikut dicabut. Ketiga endpoint khusus browser mewajibkan Origin yang dikonfigurasi (HTTP lokal hanya `http://localhost:3000`) dan JSON; cookie saja tidak mengotorisasi API bisnis. Login menerima 1–72 byte UTF-8 tanpa NUL; password baru/reset 12–72 byte. Rate limit login memakai bucket IP dan username/IP selama 15 menit; 415 non-JSON, 413 body terlalu besar.

## Master data

| Resource | Field tulis |
|---|---|
| `patients` | `identity_no` nullable, `name,birth_date,sex,phone,address`; nomor RM otomatis |
| `medical-staff` | `code,name,profession` (doctor/nurse/other) |
| `clinics` | `code,name,registration_fee` |
| `doctor-schedules` | `doctor_id,clinic_id,weekday` ISO 1–7, `start_time,end_time` |
| `procedures` | `code,name,price` |
| `medicines` | `code,name,unit,price`; stok awal 0 |
| `lab-tests` | `code,name,unit,price` |

`active` tersedia pada master; PATCH perubahan status tetap mengikuti invariant. `code` opsional untuk lima resource berkode; prefix D/N/S, POL-, T, OB, L dan minimal enam digit sequence. Kode manual tetap valid, kode lama tidak diubah. Pencarian contains literal tanpa memperhatikan huruf besar/kecil; filter sesuai resource (`q,active,profession,weekday,clinic_id,date`); halaman hingga 100000. Harga 0–999999999999, penyesuaian stok delta nonzero ±1000000. Penyesuaian wajib `reason` dan header UUID; hasil mencakup saldo sekarang, `movement_id,replayed`. Key sama dengan payload/aktor/obat berbeda → 409 `IDEMPOTENCY_CONFLICT`. DELETE `?mode=permanent` mengembalikan 204 atau 409 jika berreferensi/stok masih ada; tidak melakukan deaktivasi terselubung. DELETE lama tetap dapat fallback ke deaktivasi (200).

## Pendaftaran dan rekam medis

`GET /visits` default tanggal hari ini server Asia/Jakarta, metadata `today`; filter `page,per_page,q,date,clinic_id,doctor_id,status`. `q` nama/RM/identitas untuk admin/registration, nama/RM untuk doctor. Scope dokter selalu berdasarkan staff_id. Riwayat pasien default semua tanggal. DTO kunjungan: `id,patient_id,doctor_id,clinic_id,schedule_id,visit_date,queue_number,queue_label,registration_fee,status,created_at,updated_at,patient_name,medical_record_no,clinic_name,clinic_code,doctor_name,start_time,end_time`. Tarif pendaftaran/nomor antrean merupakan snapshot, nama/kode jadwal adalah display master kini. Tidak ada identitas lengkap/isi klinis.

```json
{"patient_id":"1","schedule_id":"2"}
```

POST kunjungan hanya menerima dua field tersebut; dokter, poli, tanggal, tarif, nomor antrean dan status ditentukan server. 409 `DUPLICATE_VISIT`, `QUEUE_CONFLICT`, `PATIENT_INACTIVE`, `SCHEDULE_UNAVAILABLE`; 404 pasien hilang, 422 input salah. Mulai pemeriksaan `{}` mengembalikan 200 untuk waiting→in_exam atau replay in_exam, 409 `INVALID_VISIT_STATUS` lainnya. Retry deadlock/serialization maksimal dua kali lalu 503 `RETRY_LATER`.

Response klinis `data: {visit,record,procedures,procedure_total,prescription,lab_orders,can_revise_procedures,reason_required}`; record null sebelum dibuat. Riwayat klinis berupa metadata paginated dan snapshot target. Riwayat lintas dokter read-only memerlukan anchor kunjungan yang sedang diperiksa hari ini untuk pasien sama. Akses rinci diaudit.

Rekam medis wajib `complaint,anamnesis,physical_exam,primary_diagnosis`, `secondary_diagnoses` array maksimal 10, `vital_signs` numerik dengan `systolic,diastolic,pulse,temperature,respiratory_rate,weight`. `expected_version=0` saat pertama membuat, versi terbaru untuk semua perubahan. Simpan awal tidak wajib alasan; perubahan record wajib alasan. Tindakan `items:[{procedure_id,quantity}]` maksimal 30 unik, jumlah 1–100. Harga snapshot lama dipertahankan sampai item dihapus, harga baru dari master aktif. `reason_required` berisi boolean procedures/prescription/lab_order. Setelah section pernah tersimpan, termasuk kosong, perubahan berikut wajib alasan; revisi invoice selalu wajib. Alasan non-null maksimal 500 byte dan tidak boleh kosong. 409 `STALE_RECORD`: muat ulang dan rekonsiliasi; `RECORD_REQUIRED`, `INVALID_VISIT_STATUS`, `BILLING_LOCKED`, `PROCEDURE_UNAVAILABLE` sesuai kondisi.

## Farmasi dan laboratorium

Resep menerima `items:[{medicine_id,dosage,frequency,quantity}]`; lab `items:[{lab_test_id}]`; 0–20 item unik, `expected_version>0`, reason sesuai riwayat. Kuantitas obat 1–10000; dosis/frekuensi maksimal 200 byte UTF-8. Daftar kosong menghapus order pending yang belum diproses. Semua edit menaikkan versi klinis bersama; snapshot lama dipertahankan, ID baris draft dapat berubah. Satu order lab berisi beberapa item.

Daftar order menerima `q,status=pending|done|all,page,per_page`. Farmasi melihat kunjungan setelah pemeriksaan selesai; lab juga dapat melihat pemeriksaan aktif. Detail hanya identitas minimal dan item modul. Dispense `{}` idempotent berdasarkan resep; seluruh perubahan stok dan movement satu transaksi.

```http
POST /api/v1/laboratory/items/42/result
Authorization: Bearer <token>
Idempotency-Key: 9c670c94-a31f-4d24-9eb4-67c7bdc69de1
Content-Type: application/json

{"result_text":"Hasil dummy"}
```

Teks hasil maksimal 2000 byte; satuan dimasukkan manual. Node meneruskan bearer, Request ID dan key. Yii menyimpan hasil, event, audit dan status secara atomik. Key dan payload/aktor/item sama → snapshot order terkini tanpa mutasi; berbeda → `IDEMPOTENCY_CONFLICT`; key baru pada item selesai → 409 `RESULT_EXISTS` atau `ORDER_LOCKED`. Timeout Node 503: hasil mungkin sudah commit; ulang dengan key/payload sama atau fallback Core eksplisit. Tidak ada amend hasil atau antrean durable Node.

## Tagihan dan pembayaran

Daftar `GET /billing/visits?q=&status=all|unpaid|partial|paid|unissued&page=1&per_page=20`; detail memuat identitas minimal, tanggal/poli, invoice/revision, `total_amount,paid_amount,balance,payment_status,items,payments`. Tidak ada diagnosis/key pembayaran mentah. `unissued` untuk kunjungan lama siap billing tanpa invoice. POST invoice `{}` hanya kasir; kunjungan baru otomatis memiliki invoice saat `ready_for_billing`. Total nol langsung selesai tanpa baris pembayaran.

```http
POST /api/v1/billing/visits/42/payments
Authorization: Bearer <token>
Idempotency-Key: 9c670c94-a31f-4d24-9eb4-67c7bdc69de1
Content-Type: application/json

{"amount":"250000","method":"cash","expected_revision":1}
```

`method=cash|non_cash`; amount string integer positif 1–18 digit. Response 200 `data:{invoice,payment_id,replayed}`, termasuk kuitansi individual di `invoice.payments`. Replay membandingkan invoice, aktor dan hash canonical amount/method/revision/visit, mengembalikan ID pembayaran semula dan invoice terkini. 409 `IDEMPOTENCY_CONFLICT`, `STALE_INVOICE`, `OVERPAYMENT`, `INVOICE_CLOSED`, `INVOICE_REQUIRED`; 422 input/key invalid, 404 di luar status billing. Tindakan dapat direvisi dokter yang ditugaskan hanya sebelum pembayaran pertama; versi klinis, alasan dan revisi invoice atomik. Baris identik tidak menaikkan revisi invoice. Diagnosis saja dapat dikoreksi setelah pembayaran, biaya tidak berubah. Nomor RX/LAB/INV/PAY enam digit hanya tampilan; pencarian invoice berpadded dinormalisasi ke `INV-n`.

## Pelaporan, akun dan audit

Periode inklusif WIB maksimal 366 hari. Summary Node memuat kunjungan/selesai, 10 poli, kuantitas tindakan, penerimaan kas/transaksi per hari, jenis dan kuantitas obat, status invoice dan tren. `payments[]` menambah `total_amount,paid_amount` string desimal; outstanding = total - paid. Semua pembayaran invoice kelompok kunjungan dipertimbangkan, berbeda dari `revenue.amount` berdasarkan `paid_at`. Summary satu snapshot, detail snapshot independen.

Detail `metric=visits|clinics|procedures|revenue|medicines|payments`, `clinic_id` opsional untuk visits/clinics, halaman 1–10000, per_page 1–100; `data:{metric,rows,total,page,per_page}`. Tidak ada identitas pasien/isi klinis.

`GET /admin/users` filter `q,role,active,page,per_page`; DTO `id,username,name,role,staff_id,active,version,staff_name` (nullable). POST `{username,name,role,staff_id,active,password}`, username lowercase, staff_id string desimal khusus dokter, selain itu null. 201 atau 409 duplikat username/staff. PATCH `{name,role,staff_id,active,expected_version,password?}`; password tidak dikirim berarti hash tidak berubah. Tidak bisa edit sendiri/pindah identitas dokter. Update berhasil menaikkan versi token; versi lama 409. Aksi edit/reset/status UI terpisah memakai kontrak yang sama.

`GET /admin/audit?from=...&to=...` dengan `actor_id,action,entity_type,page,per_page` opsional; filter eksak, maksimal 366 hari. DTO `id,actor_id,actor_username,action,entity_type,entity_id,request_id,created_at`. Tidak ada before/after, alasan, password atau isi klinis; modul klinis/farmasi/lab/billing memakai entitas `visits`, dibedakan melalui action.

## Verifikasi

Koleksi JSON merupakan contoh request aktual. `python3 scripts/check-release.py` memeriksa pemetaan metode/path dan sintaks JavaScript, bukan runtime. Hasil Newman aktual: [51 request, 133 assertion](evidence/postman/newman-summary.txt). Pengujian HTTP terisolasi: [log QA](evidence/automated-qa/runtime-excerpts.txt). Tidak ada perubahan kontrak tulis pada v1.1.0.
