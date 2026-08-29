# PRD — API Penerima RamahAnak untuk Integrasi An‑Nur Smart System

**Audiens:** developer/chat yang memegang akses SSH ke server **RamahAnak** (Laravel, di Hostinger).
**Tujuan:** RamahAnak menyediakan **API penerima** agar aplikasi **An‑Nur Smart System** (pengirim) dapat mengirim laporan **pelanggaran, apresiasi, konselor, dan absensi (telat/alpha) santri** secara otomatis.
**Sifat:** An‑Nur = *client* (outbound saja). RamahAnak = *server penerima*. Tidak ada koneksi balik ke An‑Nur.

> Catatan: dokumen ini hanya menjelaskan **apa yang harus disediakan RamahAnak**. Sisi An‑Nur sudah selesai (pola outbox + retry + idempotent) dan akan langsung memakai kontrak di bawah begitu RamahAnak siap.

---

## 1. Ringkasan arsitektur

```
An-Nur Smart System (client)                 RamahAnak (server, hosted)
  event (pelanggaran/apresiasi/                POST /api/v1/eksekusi/pelanggaran
  konselor/absensi telat)                      POST /api/v1/eksekusi/apresiasi
        │ simpan ke outbox (idempotent)        POST /api/v1/eksekusi/konselor
        │ queue worker kirim HTTP  ─────────►  GET  /api/v1/ping
        │ Bearer token + JSON                  (semua butuh Bearer token)
        ▼
  tandai sent / duplicate / failed  ◄────────  balas JSON {status, laporan_*_id}
```

- **Base URL** yang dipakai client: `https://ramahanak.ppmannursidoarjo.com/api/v1`
- **Autentikasi:** header `Authorization: Bearer <TOKEN>` + `Accept: application/json`.
- **Timeout client:** 30 dtk; **retry** otomatis untuk error sementara (lihat §5).

---

## 2. Autentikasi & token

1. RamahAnak menerbitkan **1 token integrasi** (mis. Laravel Sanctum personal access token) untuk aplikasi pengirim bernama **`annur-smart-habbit`**.
2. Token dikirim ke admin An‑Nur lewat **kanal aman** (bukan chat publik/repo). Di An‑Nur token hanya disimpan di `.env` (`RAMAHANAK_API_TOKEN`).
3. Semua endpoint di bawah **wajib** dilindungi middleware token (`auth:sanctum` atau setara). Token tidak valid → **HTTP 401**.

---

## 3. Kontrak endpoint (WAJIB disediakan RamahAnak)

Semua endpoint diawali prefix `/api/v1`. Semua request `Content-Type: application/json`.

### 3.1 `GET /ping` — health check
- **Respons sukses:** `200` `{ "status": "ok" }` (body bebas, yang penting `200`).
- Dipakai An‑Nur untuk tes koneksi awal.

### 3.2 `POST /eksekusi/pelanggaran`
Mencatat pelanggaran santri. **Juga dipakai untuk absensi telat/alpha** (lihat §4).

**Request body:**
```json
{
  "nisn_pelaku": "1023",            // NISN/NIP santri pelaku (wajib)
  "nisn_korban": "1050",            // opsional (boleh tidak ada)
  "kode": "P002",                   // kode pelanggaran (lihat §6)
  "tanggal": "2026-06-28",          // Y-m-d
  "catatan": "Terlambat KBM (07:15)",// opsional
  "ref_id": "PELANGGARAN-uuid…",    // kunci idempotency (lihat §5) — WAJIB diproses
  "app": "annur-smart-habbit",      // identitas pengirim
  "actor": "Ust. Fulan (NIP 123)"   // opsional, untuk jejak audit
}
```

**Respons sukses (`200`/`201`):**
```json
{ "status": "ok", "laporan_pelanggaran_id": 9876 }
```

### 3.3 `POST /eksekusi/apresiasi`
**Request body:**
```json
{ "nisn_pelaku": "1023", "kode": "A001", "tanggal": "2026-06-28",
  "catatan": "…", "ref_id": "APRESIASI-…", "app": "annur-smart-habbit", "actor": "…" }
```
**Respons sukses:** `{ "status": "ok", "laporan_apresiasi_id": 555 }`

### 3.4 `POST /eksekusi/konselor`
**Request body:**
```json
{ "nisn_korban": "1050", "kode": "K001", "tanggal": "2026-06-28",
  "catatan": "…", "ref_id": "KONSELOR-…", "app": "annur-smart-habbit", "actor": "…" }
```
**Respons sukses:** `{ "status": "ok", "laporan_konselor_id": 321 }`

### 3.5 `POST /absensi/telat` — *opsional*
Endpoint disiapkan client, tapi **saat ini** absensi telat/alpha dikirim lewat `POST /eksekusi/pelanggaran` (jenis `absensi`, dengan `kode` dari konfigurasi). Boleh disediakan untuk kebutuhan mendatang dengan kontrak sama seperti §3.2.

### 3.6 Utility (opsional, kalau ada): `GET /variabel/{jenis}`, `GET /santri/{nisn}`
- `GET /santri/{nisn}` → cek santri ada/tidak: `200 {santri…}` atau `404`.
- `GET /variabel/{jenis}` → daftar kode/master (mis. `pelanggaran`, `apresiasi`).

---

## 4. Pemetaan "absensi" → pelanggaran

Santri **telat / alpha** dari fitur absensi (KBM/Tahfidz/Tahsin/Controlling/Guru Piket/Pengganti) dikirim sebagai **`POST /eksekusi/pelanggaran`** dengan:
- `nisn_pelaku` = NISN santri,
- `kode` = kode pelanggaran absensi (mis. **`P002`** untuk telat *dan* alpha — ditetapkan di konfigurasi An‑Nur),
- `catatan` = mis. `"Absensi telat: KBM Fiqih (07:15)"`.

RamahAnak **tidak perlu** membedakan; cukup memproses sebagai pelanggaran biasa berdasarkan `kode`.

---

## 5. Idempotency & semantik error (PENTING)

### 5.1 Idempotency via `ref_id`
- Setiap request membawa **`ref_id`** unik & stabil. An‑Nur bisa mengirim ulang request yang sama (retry/flush).
- RamahAnak **wajib** menyimpan `ref_id` (disarankan unik per `app`) dan:
  - Jika `ref_id` **baru** → proses + simpan, balas `{ "status": "ok", "laporan_*_id": … }`.
  - Jika `ref_id` **sudah pernah** → **jangan buat duplikat**, balas `{ "status": "duplicate", "laporan_*_id": <id lama> }` dengan HTTP `200`.
- Saran skema: kolom `ref_id` (string, indexed, unique per app) di tabel laporan, atau tabel `integrasi_inbox`.

### 5.2 Kode status yang HARUS dipatuhi (menentukan retry di client)
| Kondisi | HTTP | Body | Perlakuan client |
|---|---|---|---|
| Sukses | `200`/`201` | `{status:"ok", laporan_*_id}` | tandai **sent** |
| Replay `ref_id` sama | `200` | `{status:"duplicate", laporan_*_id}` | tandai **duplicate** (sukses) |
| Data tak valid (kode/santri salah, validasi) | **`422`** | `{message:"…"}` | tandai **failed** (TIDAK retry) |
| Santri/kode tidak ditemukan | **`404`** | `{message:"…"}` | tandai **failed** (TIDAK retry) |
| Token salah | `401` | — | **retry** (anggap sementara) |
| Rate limit / server error | `429`/`5xx` | — | **retry** dgn backoff |

> Implikasi: gunakan `404`/`422` **hanya** untuk kegagalan permanen (jangan untuk error sementara), agar client tidak retry sia‑sia.

---

## 6. Master kode (`kode`)

`kode` dikirim apa adanya oleh client (mis. `P002`, `A001`, `K001`). RamahAnak yang menentukan daftar kode valid & artinya. Yang perlu disepakati:
- Kode untuk **absensi telat/alpha** (default An‑Nur: **`P002`**) harus terdaftar valid di RamahAnak.
- Kode pelanggaran/apresiasi/konselor lain mengikuti master RamahAnak; bagikan daftarnya agar admin An‑Nur memilih kode yang benar di UI.

---

## 7. Acceptance criteria (uji terima)

1. `GET /api/v1/ping` dgn token valid → `200 {status:"ok"}`; tanpa/ salah token → `401`.
2. `POST /api/v1/eksekusi/pelanggaran` payload §3.2 (ref_id baru) → `200/201 {status:"ok", laporan_pelanggaran_id}` dan **tercatat** di RamahAnak.
3. Kirim ulang **payload yang sama persis** (ref_id sama) → `200 {status:"duplicate"}`, **tidak** ada baris ganda.
4. `kode` tidak dikenal / `nisn_pelaku` tak ada → `422` atau `404` dgn `message` jelas.
5. Apresiasi & konselor (§3.3/§3.4) berfungsi serupa.

---

## 8. Langkah implementasi di server RamahAnak (via SSH)

> Asumsi RamahAnak = Laravel. Sesuaikan dengan struktur yang ada.

1. **Routes** — `routes/api.php`, grup `prefix('v1')` + middleware token:
   ```php
   Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
       Route::get('/ping', fn() => response()->json(['status' => 'ok']));
       Route::post('/eksekusi/pelanggaran', [IntegrasiController::class, 'pelanggaran']);
       Route::post('/eksekusi/apresiasi',   [IntegrasiController::class, 'apresiasi']);
       Route::post('/eksekusi/konselor',    [IntegrasiController::class, 'konselor']);
       // opsional:
       Route::get('/santri/{nisn}', [IntegrasiController::class, 'cekSantri']);
   });
   ```
2. **Controller** `IntegrasiController` — tiap method:
   - validasi body (`nisn_*`, `kode`, `tanggal`, `ref_id`, `app`),
   - cek `ref_id` (idempotency): kalau sudah ada → balas `duplicate`,
   - resolve santri by `nisn`, validasi `kode` → kalau gagal balas `422`/`404`,
   - simpan laporan + `ref_id`, balas `{status:"ok", laporan_*_id}`.
3. **Idempotency store** — migration tambah kolom `ref_id` (string, index unik per app) di tabel laporan terkait, atau tabel `integrasi_inbox(app, ref_id, laporan_id, jenis, created_at)`.
4. **Token** — terbitkan token Sanctum untuk user/aplikasi `annur-smart-habbit`:
   ```bash
   php artisan tinker --execute="echo \App\Models\User::find(<ID_ADMIN>)->createToken('annur-smart-habbit')->plainTextToken;"
   ```
   Salin token → kirim ke admin An‑Nur via kanal aman.
5. **CORS/HTTPS** — pastikan domain aktif HTTPS (sudah) & endpoint `/api/v1/*` dapat diakses publik dgn token.
6. **Deploy** — commit → push → pull di server → `php artisan route:clear && php artisan config:clear` (atau alur deploy GitHub yang biasa).
7. **Verifikasi** dengan `curl`:
   ```bash
   curl -s https://ramahanak.ppmannursidoarjo.com/api/v1/ping \
     -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json"
   # harus: {"status":"ok"}
   ```

---

## 9. Checklist serah‑terima ke An‑Nur

- [ ] Endpoint §3.1–§3.4 live & lolos uji §7.
- [ ] Idempotency `ref_id` berfungsi (replay → `duplicate`, tanpa baris ganda).
- [ ] Daftar **kode** valid dibagikan (khususnya kode absensi telat/alpha = `P002`).
- [ ] **Token** dikirim ke admin An‑Nur via kanal aman.
- [ ] Konfirmasi base URL final: `https://ramahanak.ppmannursidoarjo.com/api/v1`.

Setelah semua ✔, admin An‑Nur tinggal: isi `RAMAHANAK_API_TOKEN` + `RAMAHANAK_ENABLED=true` di `.env`, `php artisan config:clear`, jalankan `queue:work`, lalu `php artisan ramahanak:flush` untuk mengirim backlog.
