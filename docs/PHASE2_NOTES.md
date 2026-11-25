# Catatan fase 2 — Fondasi v0.2.0

Ringkasan historis checkpoint source. Kondisi pada saat fase ini berbeda dari status pengujian v1.1.0 saat ini.

## Implementasi

Core Yii2 menyediakan envelope JSON, Request ID, allowlist route, CORS, JWT HS256 firebase/php-jwt, bcrypt, status akun/versi token dan throttle login. Node Express/Zod melakukan introspeksi live, timeout 503 dan health view; React Ant Design dengan sidebar putih, teal, login, profil dan role landing. Compose memisahkan owner/Core/report, migration menambah login_attempts, seed enam role serta master dummy. Probe role bukan izin domain.

## Verifikasi saat fase ini

17 file PHP lint, 42 assertion PHP, 90 assertion TS, 16 file TS/TSX sintaks, shell/JSON/Python/YAML, kesetaraan token desain. Bukti asli: [log statis fase 2](evidence/phase2-static-checks.txt).

## Keterbatasan historis

Docker/Composer/PostgreSQL dan dependency belum tersedia saat fase ini; registry EAI_AGAIN. Belum ada runtime, full build, Vitest, JWT integration, Postman, screenshot atau domain CRUD saat itu.

Status terbaru dan kontrak yang menggantikan keputusan awal: [README](../README.md), [API](API.md), [alur transaksi](WORKFLOW.md), [bukti aktual](evidence/FINAL_RUNTIME_EVIDENCE.md).
