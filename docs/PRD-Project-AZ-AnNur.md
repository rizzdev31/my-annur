# PRD — Project AZ (Sisi An-Nur Smart System)

> Status: DRAFT · Disusun 2026-06-30 · Pemilik: tim An-Nur Smart System
> Lingkup: 3 fitur baru — (1) Inventaris & Peminjaman, (2) Validasi Laporan oleh Wali (sinkron NIP dengan RamahAnak), (3) Bot WhatsApp (Fonnte).
> Dokumen pendamping: `docs/PRD-Project-AZ-RamahAnak.md` (logic yang harus disiapkan di sisi RamahAnak).

---

## 0. Ringkasan Eksekutif

Project AZ menambah tiga kapabilitas:

1. **Inventaris** — guru mengajukan peminjaman aset (lab, alat, dll) yang dikelola admin; pemakaian terdata real-time; ada rekap bulanan untuk evaluasi. **Murni internal An-Nur**, tidak menyentuh RamahAnak.
2. **Validasi Laporan oleh Wali** — laporan pelanggaran/apresiasi/konselor yang dikirim ke RamahAnak divalidasi oleh **wali kelas/wali asrama** langsung dari aplikasi mobile guru. **Kunci sinkron = NIP** (NIP tenaga pendidik An-Nur = NIP wali di RamahAnak).
3. **Bot WhatsApp (Fonnte)** — begitu sebuah laporan (pelanggaran/apresiasi/konselor) atau kejadian absensi (telat/alfa) **final**, sistem otomatis mengirim WhatsApp ke nomor wali/ortu santri (`santri.no_whatsapp`) via Fonnte.

### Prinsip teknis (berlaku semua fitur)
- **Migrasi ADITIF** — tidak mengubah/menghapus kolom lama; semua tabel/kolom baru ditambah.
- **Kunci sinkron lintas sistem = NIP/NISN** — `santri.nip` (= NISN RamahAnak), `tenaga_pendidik.nip` (= NIP wali RamahAnak). Normalisasi: `trim`, samakan tipe string, hati-hati leading zero.
- **Pola Outbox + Queue** untuk semua kiriman keluar (RamahAnak & Fonnte) → idempotent, retry, tahan gangguan jaringan. Reuse arsitektur `outbox_laporan` + `KirimLaporanJob`.
- **Kredensial hanya di `.env`** (Fonnte token, webhook secret) — TIDAK pernah di repo, Vue, atau Flutter.
- **Prasyarat ops**: `queue:work` (Supervisor) + `schedule:run` (cron) WAJIB hidup di produksi. Lihat `deploy/supervisor/`.

---

## 1. Fitur 1 — Inventaris & Peminjaman

### 1.1 Tujuan
- Admin mengelola master inventaris (aset/ruang/alat).
- Guru mengajukan peminjaman dari aplikasi mobile.
- Ketersediaan stok terhitung **real-time** (total − yang sedang dipinjam).
- **Rekap bulanan** pemakaian untuk evaluasi.

### 1.2 Aktor
- **Guru** (tenaga pendidik) — mengajukan, melihat status, mengembalikan.
- **Admin/Petugas Inventaris** — kelola master, setujui/tolak, tandai kembali, monitor + rekap.

### 1.3 Skema data (aditif)

**`inventaris`** (master barang/ruang)
| kolom | tipe | catatan |
|---|---|---|
| id | bigint PK | |
| kode | string unique | mis. LAB-IPA-01 |
| nama | string | |
| kategori | enum(`ruang`,`alat`,`elektronik`,`kendaraan`,`lainnya`) | |
| lokasi | string nullable | |
| jumlah_total | int default 1 | unit yang dimiliki |
| satuan | string default 'unit' | |
| kondisi | enum(`baik`,`perlu_perbaikan`,`rusak`) default baik | |
| perlu_persetujuan | bool default true | jika false → auto-approve |
| is_aktif | bool default true | |
| keterangan | text nullable | |
| timestamps | | |

**`peminjaman_inventaris`** (transaksi)
| kolom | tipe | catatan |
|---|---|---|
| id | bigint PK | |
| inventaris_id | FK inventaris | |
| tenaga_pendidik_id | FK tenaga_pendidik | peminjam |
| jumlah | int default 1 | |
| keperluan | string | wajib |
| tanggal_pinjam | datetime | rencana mulai |
| tanggal_rencana_kembali | datetime | |
| tanggal_kembali | datetime nullable | aktual |
| status | enum(`diajukan`,`disetujui`,`ditolak`,`dipinjam`,`dikembalikan`,`terlambat`,`dibatalkan`) | |
| disetujui_oleh | FK users nullable | |
| catatan_admin | string nullable | alasan tolak / catatan |
| kondisi_kembali | enum(`baik`,`perlu_perbaikan`,`rusak`) nullable | |
| timestamps | | |

> Indeks: `(inventaris_id, status)`, `(tenaga_pendidik_id, status)`, `tanggal_pinjam`.

### 1.4 State machine peminjaman
```
diajukan ──(admin setuju)──► disetujui ──(diambil/checkout)──► dipinjam ──(kembali)──► dikembalikan
   │                                                              │
   ├──(admin tolak)──► ditolak                                    └──(lewat tgl_rencana_kembali)──► terlambat ──(kembali)──► dikembalikan
   └──(guru batalkan, selagi diajukan)──► dibatalkan
```
- `perlu_persetujuan=false` → `diajukan` langsung jadi `disetujui` (atau `dipinjam`).
- **Stok tersedia** = `jumlah_total − Σ jumlah peminjaman berstatus {disetujui, dipinjam, terlambat}`. Validasi saat pengajuan: `jumlah ≤ tersedia`.
- **Auto-terlambat**: command `inventaris:auto-terlambat` (scheduler tiap jam) menandai `dipinjam`/`disetujui` yang lewat `tanggal_rencana_kembali` jadi `terlambat`.

### 1.5 Endpoint

**API Mobile (guru, prefix `/inventaris`, auth Sanctum)**
- `GET /inventaris` — daftar inventaris + stok tersedia real-time (+ filter kategori/cari).
- `GET /inventaris/peminjaman` — riwayat & status peminjaman milik guru ini.
- `POST /inventaris/peminjaman` — ajukan `{inventaris_id, jumlah, keperluan, tanggal_pinjam, tanggal_rencana_kembali}`.
- `POST /inventaris/peminjaman/{id}/batal` — batalkan (hanya saat `diajukan`).

**Web Admin (`admin.inventaris.*`)**
- CRUD master `inventaris`.
- Daftar pengajuan + aksi `setujui`/`tolak`/`tandai-dipinjam`/`tandai-kembali` (dengan `kondisi_kembali`).
- **Monitor real-time**: tabel apa yang sedang dipinjam siapa, jatuh tempo, terlambat.
- **Rekap bulanan**: per inventaris (frekuensi pinjam, total hari dipakai, % utilisasi) & per guru; filter periode; export.

### 1.6 Flutter
- Menu **Inventaris** (drawer): daftar barang + badge stok, tombol "Ajukan Peminjaman" (sheet form), tab "Peminjaman Saya" (status berwarna: diajukan/disetujui/dipinjam/terlambat/selesai), tombol batalkan.

### 1.7 Sinkronisasi gaji/kinerja
- **TIDAK** memengaruhi payroll/kinerja (fitur netral). Hanya data operasional + evaluasi.

---

## 2. Fitur 2 — Validasi Laporan oleh Wali (sinkron NIP)

### 2.1 Tujuan
Laporan pelanggaran/apresiasi/konselor yang dibuat guru (Smart Eksekusi) **tidak langsung final**. Harus divalidasi **wali kelas / wali asrama** santri terkait, dari aplikasi mobile, sebelum diteruskan ke Guru BK. Kunci pencocokan wali = **NIP**.

### 2.2 Konsep sinkron NIP
- An-Nur: `tenaga_pendidik.nip`. RamahAnak: setiap santri punya **wali** dengan **NIP** yang sama persis.
- Saat guru mengirim laporan → RamahAnak menempatkannya pada gerbang `pending_tenaga_pendidik` dan menetapkan **wali NIP** (dari relasi santri→wali di RamahAnak).
- Aplikasi mobile menampilkan ke seorang guru hanya laporan yang **wali NIP-nya = NIP guru yang login**.

> Catatan data: pastikan NIP wali di RamahAnak diisi identik dengan NIP di An-Nur (string, tanpa spasi). Sediakan util normalisasi di kedua sisi.

### 2.3 Alur (flow)
```
Guru A (pelapor)         An-Nur backend        RamahAnak                 Wali (guru B) di Flutter
   │ buat laporan ──────────► outbox ─────────► simpan, status=
   │                                            pending_tenaga_pendidik
   │                                            (wali_nip = NIP wali santri)
   │
   │                                   ◄──── Wali B buka "Validasi Laporan"
   │                         GET /validasi/pending  (kirim nip=NIP B) ──► filter where wali_nip=NIP B
   │                         ◄───────────────── daftar laporan pending
   │                         POST /validasi/{id}/approve|reject (oleh B) ─► ubah status:
   │                                            approve → pending_bk
   │                                            reject  → ditolak (+alasan)
   │                         ◄───────────────── hasil
   │                                            (status final di BK menyusul)
```

### 2.4 Endpoint (An-Nur sebagai proxy aman)
Flutter **tidak** memegang token RamahAnak. Flutter memanggil An-Nur; An-Nur memanggil RamahAnak dengan token server.

**API Mobile (`/smart-habbit/validasi`, auth Sanctum)** — NIP diturunkan dari user login (anti-spoof):
- `GET /smart-habbit/validasi/pending` → An-Nur teruskan ke RamahAnak `GET /validasi/pending?nip={nip_user}`.
- `GET /smart-habbit/validasi/{id}` → detail laporan.
- `POST /smart-habbit/validasi/{id}/approve` `{catatan?}`.
- `POST /smart-habbit/validasi/{id}/reject` `{alasan}` (wajib).

**Dependensi RamahAnak** (lihat PRD RamahAnak §A): endpoint list pending by NIP, approve, reject; semua memvalidasi bahwa NIP pemvalidasi == wali santri.

### 2.5 Flutter
- Menu **Validasi Laporan** (badge jumlah pending). List kartu: nama santri, jenis (pelanggaran/apresiasi/konselor), kode + poin, pelapor, tanggal, deskripsi → tombol **Setujui** / **Tolak (alasan wajib)**. Setelah aksi → refresh.
- Hanya muncul untuk guru yang menjadi wali (punya laporan pending). Jika kosong → empty state.

### 2.6 Exception handling
- NIP user tidak cocok wali manapun → list kosong (bukan error).
- Laporan sudah divalidasi orang lain / status berubah → tampilkan "sudah diproses", refresh.
- RamahAnak down → pesan ramah + retry; aksi approve/reject **idempotent** (ref by laporan id).

---

## 3. Fitur 3 — Bot WhatsApp (Fonnte)

### 3.1 Tujuan
Mengirim WhatsApp otomatis ke nomor wali/ortu santri (`santri.no_whatsapp`) saat ada kejadian **final**:
- **Pelanggaran / Apresiasi / Konselor** — setelah laporan disetujui penuh (lulus gerbang BK di RamahAnak).
- **Absensi** — saat santri **telat/alfa** tercatat (Smart Controlling / absensi kegiatan / education).

### 3.2 Keputusan arsitektur — An-Nur sebagai HUB WA (rekomendasi)
Alasan:
- `santri.no_whatsapp` dan data absensi santri **ada di An-Nur** (sumber tunggal nomor WA).
- Cukup **satu** integrasi Fonnte (satu token, satu service) → lebih mudah dirawat.
- RamahAnak cukup menambah **webhook keluar** saat status laporan final (lihat PRD RamahAnak §B), tidak perlu tahu nomor WA atau Fonnte.

```
[Absensi telat/alfa di An-Nur] ─────────────┐
                                            ▼
[RamahAnak laporan final] ──webhook──► [An-Nur: WaService.enqueue] ──► wa_outbox ──► KirimWaJob ──► Fonnte API ──► WhatsApp wali santri
                                            ▲
                                   (lookup santri.no_whatsapp by NISN/nip)
```

> Alternatif: RamahAnak mengirim WA sendiri. Ditolak karena memecah sumber nomor WA & menggandakan integrasi Fonnte.

### 3.3 Skema data (aditif)
**`wa_outbox`** (pola sama `outbox_laporan`)
| kolom | tipe | catatan |
|---|---|---|
| id | PK | |
| ref_id | string unique | idempotency (mis. `WA-PEL-{laporan_id}` / `WA-ABS-{absensi_id}`) |
| tujuan | string | nomor WA tujuan (E.164 / 08xx dinormalisasi) |
| santri_id | FK nullable | konteks |
| jenis | enum(`pelanggaran`,`apresiasi`,`konselor`,`absensi`) | |
| pesan | text | template terisi |
| status | enum(`pending`,`sent`,`failed`,`skipped`) | skipped = no_whatsapp kosong |
| provider_response | json nullable | respon Fonnte |
| attempts | int default 0 | |
| error | string nullable | |
| sent_at | datetime nullable | |
| timestamps | | |

> Tambah `config/fonnte.php` (token, device, enabled) + key `.env`: `FONNTE_TOKEN`, `FONNTE_ENABLED`, `WA_WEBHOOK_SECRET`.

### 3.4 Komponen
- **`FonnteClient`** — `kirim($tujuan, $pesan): Response` (POST ke `https://api.fonnte.com/send`, header `Authorization: {token}`). Token dari config.
- **`WaService::enqueue($jenis, $santri, $pesan, $refId)`** — `firstOrCreate` by `ref_id`; jika `santri.no_whatsapp` kosong → status `skipped` (tidak dispatch); else dispatch `KirimWaJob`.
- **`KirimWaJob`** — kirim via Fonnte; 200 ok → `sent`; gagal sementara → retry (backoff); gagal permanen → `failed`. (Mirror `KirimLaporanJob`.)
- **Webhook receiver** `POST /api/webhook/ramahanak/laporan-final` — diproteksi `WA_WEBHOOK_SECRET` (header). Payload dari RamahAnak: `{ref_id, nisn, jenis, kode, poin, judul, tanggal, status}`. An-Nur → cari santri by `nip=nisn` → render template → `WaService::enqueue`.

### 3.5 Trigger
| Sumber | Pemicu | refId |
|---|---|---|
| Absensi santri (Controlling/kegiatan/education) | status tercatat `telat`/`alfa` | `WA-ABS-{absensi_id}` |
| RamahAnak (webhook) | laporan `selesai`/disetujui | `WA-{JENIS}-{laporan_id}` |

> Idempotency via `ref_id` mencegah WA dobel walau webhook/absensi terkirim ulang.

### 3.6 Template pesan (contoh)
- Pelanggaran: `*Laporan Pelanggaran*\nAnanda {nama} ({kelas})\nKejadian: {judul} (poin {poin})\nTanggal: {tgl}\nMohon perhatian & bimbingannya. — PPM An-Nur`
- Apresiasi: `*Apresiasi* 🎉\nAnanda {nama} memperoleh apresiasi: {judul} (+{poin}).\nTerus semangat! — PPM An-Nur`
- Konselor: `*Info Konseling*\nAnanda {nama} dijadwalkan/ditangani konselor terkait {judul}. — PPM An-Nur`
- Absensi: `*Absensi*\nAnanda {nama} tercatat {STATUS} pada kegiatan {kegiatan} ({tgl} {jam}). — PPM An-Nur`

### 3.7 Keamanan & kepatuhan
- `FONNTE_TOKEN` & `WA_WEBHOOK_SECRET` hanya di `.env`.
- Validasi nomor (normalisasi 08→62), rate limit, dan **opt-out** (kolom `santri.wa_nonaktif` opsional) untuk menghormati privasi.
- Hanya kejadian final yang dikirim (hindari spam laporan yang masih divalidasi).

---

## 4. Matriks Sinkronisasi & Dependensi

| Fitur | An-Nur | RamahAnak | Kunci sinkron |
|---|---|---|---|
| Inventaris | DB + API + Web + Flutter | — | — |
| Validasi Wali | Proxy API + Flutter | Endpoint pending/approve/reject by NIP | **NIP** (tenaga_pendidik ↔ wali) |
| Bot WA | Fonnte + wa_outbox + webhook receiver + trigger absensi | Webhook keluar saat laporan final | **NISN/NIP** (santri) |

Urutan ketergantungan: Validasi (butuh RamahAnak §A) dan WA-laporan (butuh RamahAnak §B webhook) bergantung pada PRD RamahAnak. WA-absensi & Inventaris bisa jalan mandiri lebih dulu.

---

## 5. Tahapan Implementasi (disarankan)

1. **Inventaris** (mandiri, paling cepat berdampak): migrasi → model → API guru → Web admin (CRUD + approve + monitor) → rekap bulanan → Flutter → scheduler auto-terlambat.
2. **WA — jalur absensi** (mandiri): config Fonnte + FonnteClient + wa_outbox + WaService + KirimWaJob → pasang trigger di pencatatan absensi santri (telat/alfa). Uji kirim ke 1 nomor.
3. **Validasi Wali**: setelah RamahAnak §A siap → proxy endpoint An-Nur → Flutter "Validasi Laporan".
4. **WA — jalur laporan**: setelah RamahAnak §B (webhook) siap → webhook receiver di An-Nur → render template → enqueue.

Tiap tahap: migrasi aditif, lint (`php -l`), build (`npm run build`), `flutter analyze`, uji tinker/e2e, dengan konfirmasi bertahap.

---

## 6. Risiko & Mitigasi
- **NIP tidak konsisten** antar sistem → util normalisasi + laporan "wali tidak ditemukan" + tooling pencocokan.
- **Worker/scheduler mati** → outbox `pending`, WA tidak terkirim, auto-terlambat tak jalan → pastikan Supervisor + cron (sudah didokumentasikan).
- **Spam/privasi WA** → hanya kejadian final, opt-out, rate limit, idempotency.
- **Fonnte limit/biaya** → batasi jenis & frekuensi; log provider_response; alert saat gagal beruntun.
- **Webhook palsu** → secret header + (opsional) IP allowlist.

---

## 7. Definisi Selesai (per fitur)
- Inventaris: guru bisa ajukan→admin setujui→pinjam→kembali; stok real-time benar; rekap bulanan tampil & export; auto-terlambat jalan.
- Validasi: wali melihat & memutuskan pending miliknya; status berubah benar di RamahAnak; idempotent.
- WA: kejadian final memicu WA ke nomor wali santri; idempotent; gagal ter-retry; skip bila nomor kosong.
