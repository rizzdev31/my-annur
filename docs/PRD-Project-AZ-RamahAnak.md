# PRD — Project AZ (Logic yang Diperlukan di Sisi RamahAnak)

> Status: DRAFT · Disusun 2026-06-30 · Untuk: tim/sesi yang memegang SSH server RamahAnak.
> Dokumen pendamping: `docs/PRD-Project-AZ-AnNur.md`.
> Konteks: RamahAnak (`https://ramahanak.ppmannursidoarjo.com/api/v1`) sudah LIVE menerima laporan pelanggaran/apresiasi/konselor & absensi telat dari An-Nur (lihat `PRD-Integrasi-RamahAnak-API.md`). Project AZ menambah **2 kemampuan baru** di RamahAnak.

---

## 0. Ringkasan
RamahAnak perlu menambah:
- **§A. Endpoint Validasi oleh Wali (berbasis NIP)** — agar wali kelas/asrama bisa menyetujui/menolak laporan dari aplikasi mobile An-Nur.
- **§B. Webhook keluar saat laporan FINAL** — agar An-Nur bisa mengirim notifikasi WhatsApp (Fonnte) ke wali/ortu santri.

Tidak ada perubahan pada kontrak lama (kirim laporan & absensi tetap seperti sekarang). Semua tambahan **aditif & backward-compatible**.

### Prinsip
- **NIP = kunci sinkron** orang. Setiap santri punya **wali** dengan `nip` yang **identik** dengan `tenaga_pendidik.nip` di An-Nur. Normalisasi `trim` + string.
- **NISN = `nip` santri An-Nur** (sudah disepakati di PRD integrasi).
- Auth tetap **token statis** (`INTEGRASI_API_TOKEN`) seperti sekarang.
- Semua aksi **idempotent** & ber-audit (siapa, kapan).

---

## §A. Endpoint Validasi oleh Wali (berbasis NIP)

### A.1 Latar
Saat ini alur approval: laporan masuk → `pending_tenaga_pendidik` (wali validasi) → `pending_bk` (Guru BK) → selesai. Yang belum ada: **cara wali memvalidasi via API** dari aplikasi mobile An-Nur. RamahAnak perlu mengekspos 3 endpoint.

> Prasyarat data: setiap laporan pada tahap `pending_tenaga_pendidik` harus menyimpan **`wali_nip`** (NIP wali dari santri terkait). Jika relasi santri→wali belum menyimpan NIP yang sama dengan An-Nur, tambahkan/isi kolom `nip` pada master guru/wali RamahAnak dan backfill agar identik.

### A.2 Endpoint

**1) GET `/validasi/pending`** — daftar laporan menunggu validasi wali tertentu.
- Query: `nip` (wajib), `jenis?` (`pelanggaran|apresiasi|konselor`), `page?`.
- Logika: kembalikan laporan `status = pending_tenaga_pendidik` **dan** `wali_nip = nip`.
- Response 200:
```json
{
  "status": "ok",
  "data": [
    {
      "id": 123,
      "jenis": "pelanggaran",
      "nisn": "0098765432",
      "nama_santri": "Ahmad Santri",
      "kelas": "VII-A",
      "kode": "P002",
      "judul": "Disiplin Waktu",
      "poin": 2,
      "deskripsi": "Terlambat masuk asrama",
      "pelapor": "Ust. Fulan (NIP 1990...)",
      "tanggal": "2026-06-30",
      "status": "pending_tenaga_pendidik",
      "wali_nip": "199204xxxxxx"
    }
  ]
}
```

**2) POST `/validasi/{id}/approve`** — wali menyetujui → status pindah ke `pending_bk`.
- Body: `{ "nip": "199204xxxxxx", "catatan": "opsional" }`.
- Validasi: laporan id ada **dan** `wali_nip == nip` **dan** `status == pending_tenaga_pendidik`. Selain itu → 409/422.
- Efek: `status = pending_bk`, simpan `validated_by_nip`, `validated_at`, `catatan_wali`.
- Idempotent: jika sudah `pending_bk`/lebih lanjut oleh NIP yang sama → balas 200 `already`.
- Response: `{ "status": "ok", "id": 123, "status_baru": "pending_bk" }`.

**3) POST `/validasi/{id}/reject`** — wali menolak → status `ditolak`.
- Body: `{ "nip": "...", "alasan": "wajib" }`.
- Validasi sama seperti approve.
- Efek: `status = ditolak`, simpan alasan + audit. (Laporan ditolak tidak lanjut ke BK & tidak memicu WA.)
- Response: `{ "status": "ok", "id": 123, "status_baru": "ditolak" }`.

### A.3 Aturan & error
| Kondisi | HTTP | body.status |
|---|---|---|
| sukses | 200 | `ok` / `already` |
| nip ≠ wali laporan | 403 | `bukan_wali` |
| laporan tak ada | 404 | `laporan_not_found` |
| status bukan pending_tenaga_pendidik | 409 | `status_tidak_valid` |
| validasi input gagal | 422 | `validation_error` |
| token salah | 401 | `unauthorized` |

> An-Nur memanggil endpoint ini sebagai **proxy** (token server An-Nur). `nip` selalu diturunkan dari user login An-Nur (anti-spoof), jadi RamahAnak cukup mempercayai `nip` pada body + memverifikasi kepemilikan laporan.

---

## §B. Webhook Keluar saat Laporan FINAL (untuk Bot WA)

### B.1 Latar
Saat laporan **lolos seluruh gerbang** (BK menyetujui → `selesai`/`disetujui`), RamahAnak harus **memberi tahu An-Nur** agar An-Nur mengirim WhatsApp ke wali/ortu santri. RamahAnak **tidak** perlu tahu nomor WA atau Fonnte — cukup kirim event.

### B.2 Spesifikasi webhook
- **Trigger**: status laporan berubah menjadi final (mis. `selesai`/`approved_bk`). (Opsional juga saat `ditolak` jika ingin notifikasi—default: hanya final disetujui.)
- **Target URL**: dari konfigurasi RamahAnak (`.env`: `ANNUR_WEBHOOK_URL`, mis. `https://<app-annur>/api/webhook/ramahanak/laporan-final`).
- **Auth**: header `X-Webhook-Secret: {ANNUR_WEBHOOK_SECRET}` (shared secret, sama dengan `WA_WEBHOOK_SECRET` di An-Nur).
- **Method**: `POST` JSON.
- **Payload**:
```json
{
  "ref_id": "PEL-123",
  "laporan_id": 123,
  "jenis": "pelanggaran",
  "nisn": "0098765432",
  "nama_santri": "Ahmad Santri",
  "kelas": "VII-A",
  "kode": "P002",
  "judul": "Disiplin Waktu",
  "poin": 2,
  "tanggal": "2026-06-30",
  "status": "selesai"
}
```
- **Idempotency**: sertakan `ref_id` stabil (mis. `{JENIS}-{laporan_id}`) → An-Nur memakai ini sebagai kunci anti-WA-dobel.
- **Keandalan**: kirim via queue/job dengan **retry** (mis. 5x backoff) jika An-Nur membalas non-2xx. An-Nur membalas `200 {"status":"ok"}` saat diterima.

### B.3 Keamanan
- Secret header wajib; tolak tanpa/secret salah.
- (Opsional) tandatangani payload (HMAC) untuk anti-tamper.

---

## §C. Penyelarasan Data NIP (wajib agar §A & WA jalan)
1. Pastikan master wali RamahAnak punya field `nip` (string) dan **diisi identik** dengan `tenaga_pendidik.nip` An-Nur.
2. Relasi `santri → wali` harus resolvable sehingga setiap laporan baru bisa diisi `wali_nip`.
3. Sediakan util normalisasi NIP (`trim`, buang spasi, pertahankan leading zero sebagai string).
4. Fallback: bila santri tak punya wali ber-NIP valid → laporan tetap bisa lanjut ke `pending_bk` (lewati gerbang wali) agar tidak macet, sambil log peringatan.

---

## §D. (Opsional, fase lanjut) Endpoint Riwayat Aktivitas Santri
Untuk fitur rekap/WA ringkasan periodik:
- `GET /santri/{nisn}/aktivitas?dari=&sampai=` → daftar pelanggaran/apresiasi/konselor santri pada rentang (untuk laporan bulanan / WA rekap). Tidak wajib untuk MVP Project AZ.

---

## §E. Checklist Implementasi RamahAnak
- [ ] Tambah/isi `nip` pada master wali + backfill identik dengan An-Nur.
- [ ] Pastikan laporan `pending_tenaga_pendidik` menyimpan `wali_nip`.
- [ ] Endpoint `GET /validasi/pending` (filter by nip + status).
- [ ] Endpoint `POST /validasi/{id}/approve` (→ pending_bk, idempotent, audit).
- [ ] Endpoint `POST /validasi/{id}/reject` (→ ditolak, alasan wajib).
- [ ] Error/HTTP sesuai tabel A.3.
- [ ] Webhook keluar saat laporan final (queue + retry + secret).
- [ ] Konfigurasi `.env`: `ANNUR_WEBHOOK_URL`, `ANNUR_WEBHOOK_SECRET`.
- [ ] Uji terima: list pending by NIP, approve→pending_bk, reject→ditolak, webhook diterima An-Nur (200).

---

## §F. Kontrak ringkas untuk tim An-Nur
An-Nur hanya butuh dari RamahAnak:
1. 3 endpoint validasi (§A) — dipanggil via proxy server An-Nur dengan `nip` user.
2. 1 webhook keluar (§B) — diterima `POST /api/webhook/ramahanak/laporan-final` di An-Nur dengan secret.
Semua kunci orang/santri memakai **NIP/NISN**. Tidak ada perubahan pada endpoint kirim laporan/absensi yang sudah ada.
