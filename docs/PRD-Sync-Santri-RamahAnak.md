# PRD — Endpoint Sinkron Santri RamahAnak (Smart = Master)

**Audiens:** developer/chat pemegang akses SSH ke server **RamahAnak** (Laravel, Hostinger).
**Tujuan:** RamahAnak menyediakan **1 endpoint penerima** `POST /api/v1/santri/sync` agar **An‑Nur Smart System** dapat menyinkronkan **identitas & kelas santri** secara otomatis. **Smart System = sumber kebenaran (master/override)** untuk identitas & kelas; RamahAnak menjadi cermin + tetap memegang data fiturnya sendiri.
**Sifat:** An‑Nur = *client* (outbound). RamahAnak = *server penerima*. Melengkapi PRD‑Integrasi (eksekusi) yang sudah ada — auth & pola sama.

> Sisi An‑Nur **sudah selesai** (client `syncSantri`, outbox `santri_sync`, trigger saat santri dibuat/diubah/pindah kelas, command backfill `ramahanak:sync-santri`). Begitu endpoint ini live, admin An‑Nur cukup nyalakan `RAMAHANAK_ENABLED=true` lalu `ramahanak:flush`.

---

## 1. Kunci sinkron

- **NISN = `santri_profiles.nisn` (RA) ⇄ `santri.nip` (Smart)** — satu-satunya kunci upsert.
- **Tidak** memakai NPSN (RamahAnak saat ini single‑school).
- Kelas dipetakan by **`kode_kelas` + `tahun_ajaran`** (string), mis. `"10A"` + `"2025/2026"`.

## 2. Autentikasi

- Sama seperti PRD‑Integrasi: header `Authorization: Bearer <TOKEN>` + `Accept: application/json`. Token `annur-smart-habbit`. Token tidak valid → **401**.

## 3. Kontrak `POST /api/v1/santri/sync`

**Request body (JSON):**
```json
{
  "nisn": "1023",                      // WAJIB — kunci upsert (= NIP/NIS Smart)
  "nama_lengkap": "Ahmad Zaki",
  "nama_panggilan": "Zaki",            // nullable
  "jenis_kelamin": "Laki-laki",        // enum: "Laki-laki" | "Perempuan"
  "tempat_lahir": "Sidoarjo",          // nullable
  "tanggal_lahir": "2010-05-14",       // nullable, Y-m-d
  "no_whatsapp": "0812xxxx",           // nullable (WA wali)
  "is_aktif": true,                    // status santri di Smart
  "kelas": {                           // kelas AKTIF sekarang (nullable bila belum berkelas)
    "kode_kelas": "10A",
    "tingkat": 10,
    "tahun_ajaran": "2025/2026"
  },
  "riwayat_kelas": [                    // seluruh histori kelas (jenis "sekolah")
    { "kode_kelas": "9A",  "tingkat": 9,  "tahun_ajaran": "2024/2025",
      "tanggal_masuk": "2024-07-15", "tanggal_keluar": "2025-06-30", "is_active": false },
    { "kode_kelas": "10A", "tingkat": 10, "tahun_ajaran": "2025/2026",
      "tanggal_masuk": "2025-07-14", "tanggal_keluar": null,         "is_active": true }
  ],
  "ref_id": "SANTRISYNC-1023-ab12cd34ef56",  // idempotency (hash per versi data)
  "app": "annur-smart-habbit"
}
```

**Respons sukses (`200`/`201`):**
```json
{ "status": "ok", "santri_id": 42, "action": "created" }   // action: created | updated
```
Replay `ref_id` sama → `200 { "status": "duplicate", "santri_id": 42 }`.

## 4. Yang HARUS dilakukan RamahAnak saat menerima

1. **Idempotency**: cek `ref_id` (unik per `app`). Sudah pernah → balas `duplicate`, **jangan** proses ulang.
2. **Upsert `santri_profiles` by `nisn`:**
   - Belum ada → **buat `users`** (role santri) + `santri_profiles`. Password awal bebas kebijakan RA (mis. acak / dari tanggal lahir) — An‑Nur tidak mengirim password.
   - Sudah ada → **update** field identitas (nama, jenis_kelamin, tempat/tanggal lahir, no_whatsapp). Field ini **milik Smart** → boleh ditimpa (override).
   - `nama_wali`, `alamat` **tidak** dikirim Smart → **jangan** dikosongkan; biarkan nilai RA yang ada.
3. **Map & set kelas:**
   - Cari/insert RA `kelas` by `kode_kelas` + `tahun_ajaran` (isi `tingkat`, `status='active'`). Set `santri_profiles.kelas_id` & `santri_profiles.kelas` (string) ke kelas aktif.
4. **Update `riwayat_kelas_santri`** sesuai `riwayat_kelas`:
   - Untuk tiap entri, upsert by `(user_id, tahun_ajaran)`: set `kelas_id`, `tanggal_masuk`, `tanggal_keluar`, `is_active`.
   - Baris tahun-ajaran yang **tidak** ada di payload → biarkan (jangan hapus histori).
5. **JANGAN sentuh** data fitur RA (expert system tracking, bimbingan, laporan pelanggaran/apresiasi/konselor).
6. `is_aktif=false` → tandai santri nonaktif di RA sesuai kebijakan (mis. flag), **tanpa** menghapus data.

## 5. Semantik error (menentukan retry client) — sama seperti PRD‑Integrasi

| Kondisi | HTTP | Perlakuan client |
|---|---|---|
| Sukses | `200`/`201` `{status:ok,...}` | **sent** |
| Replay ref_id | `200` `{status:duplicate,...}` | **duplicate** (sukses) |
| Body tak valid (nisn kosong, enum jk salah, dll) | **`422`** `{message}` | **failed** (tak retry) |
| Token salah | `401` | retry (sementara) |
| Rate limit / server error | `429`/`5xx` | retry backoff |

> Gunakan `422` hanya untuk kegagalan permanen agar client tak retry sia-sia.

## 6. Acceptance criteria

1. `POST /santri/sync` nisn baru → `201 {status:ok, action:"created"}`; `users`+`santri_profiles` terbuat; `kelas_id` terisi; `riwayat_kelas_santri` sesuai payload.
2. Kirim ulang (ref_id sama) → `200 {status:"duplicate"}`, tanpa perubahan/duplikat.
3. Payload dengan data berubah (ref_id baru, nisn sama) → identitas & kelas ter‑update; histori lama tetap ada.
4. `nama_wali`/`alamat` lama RA **tidak** hilang setelah sync.
5. Data fitur RA (expert tracking dll) tidak terpengaruh.

## 7. Langkah implementasi (SSH RamahAnak)

1. **Route** (`routes/api.php`, grup v1 + auth token):
   ```php
   Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
       Route::post('/santri/sync', [IntegrasiController::class, 'syncSantri']);
   });
   ```
2. **Controller** `IntegrasiController@syncSantri`: validasi → cek `ref_id` → upsert user+profile by nisn → map kelas → update riwayat → balas `{status, santri_id, action}`.
3. **Idempotency store**: pakai tabel `integrasi_inbox(app, ref_id, ...)` yang sama dengan eksekusi, atau kolom `ref_id` di tabel log sync.
4. **Deploy** (alur GitHub biasa) → `php artisan route:clear && config:clear`.
5. **Uji** `curl -X POST .../api/v1/santri/sync -H "Authorization: Bearer <TOKEN>" -H "Content-Type: application/json" -d '{...}'`.

## 8. Checklist serah-terima ke An‑Nur

- [ ] `POST /api/v1/santri/sync` live & lolos uji §6.
- [ ] Idempotency `ref_id` berfungsi (replay → duplicate).
- [ ] Konfirmasi mapping kelas by kode_kelas + tahun_ajaran sesuai penamaan kedua sistem.
- [ ] Setelah live: An‑Nur set `RAMAHANAK_ENABLED=true`, `php artisan ramahanak:sync-santri` (backfill) lalu `ramahanak:flush`.
