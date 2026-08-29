# PRD DRAFT (Brainstorming) — Perizinan Santri & Smart Health

> Status: **BRAINSTORM / DRAFT** · 2026-06-30 · Belum untuk dikoding.
> Tujuan dokumen: menyamakan pemahaman alur sebelum implementasi. Berisi analisis
> flow, usulan pengembangan, sketsa data, titik integrasi, dan pertanyaan terbuka.
> Dua fitur dibahas bersama karena saling terhubung ("izin pulang" dari Smart Health).

---

## 0. Ringkasan

Dua fitur baru untuk domain **santri** (bukan tendik):

1. **Perizinan Santri** (dibuat lebih dulu) — santri mengajukan izin (syar'i / non-syar'i), disetujui oleh **guru yang ditunjuk** (bukan superadmin; superadmin hanya **mendelegasikan akses** persetujuan ke guru tertentu).
2. **Smart Health** — pelaporan & pemantauan kesehatan santri. Disetujui **Bagian Kesehatan** (beberapa guru ditunjuk; **salah satu** menyetujui → lanjut). Ada kondisi **Sembuh / Pengecekan Hari 1–3 / Darurat**, tiap keputusan mengirim **notifikasi WhatsApp otomatis** ke wali santri (deskripsi penyakit + foto + status).

Keduanya **reuse fondasi yang sudah ada**: `santri` + `santri.no_whatsapp`, **Fonnte/`wa_outbox`/`WaService`/`WaTemplate`**, `tenaga_pendidik`, pola delegasi peran, pola persetujuan (seperti gerbang wali RamahAnak).

---

## 1. Konsep Kunci: Delegasi Akses ke Guru (dipakai kedua fitur)

Superadmin menunjuk guru sebagai **petugas** untuk fitur tertentu, tanpa menjadikannya admin:

- **Petugas Perizinan** — boleh menyetujui izin santri.
- **Petugas Kesehatan (Bagian Kesehatan)** — boleh memvalidasi laporan Smart Health.

Usulan model: tabel pivot `petugas_peran` (tenaga_pendidik_id, peran[`perizinan`|`kesehatan`], is_aktif, ditunjuk_oleh) **atau** kolom boolean di `tenaga_pendidik` (`is_petugas_izin`, `is_petugas_kesehatan`). Pivot lebih fleksibel (audit + multi-peran).

**Aturan persetujuan "salah satu":** bila ada ≥2 petugas, **siapa pun yang pertama menyetujui → sah**, laporan lanjut ke tahap berikutnya (OR-approval, bukan semua wajib setuju). Sederhana & sesuai permintaan.

> ❓ **Pertanyaan:** apakah "Petugas Perizinan" dan "Petugas Kesehatan" bisa orang yang sama? (Usulan: boleh, peran independen.)

---

## 2. Fitur 1 — Perizinan Santri

### 2.1 Aktor & alur
```
Santri ── ajukan izin ──► [status: diajukan]
                               │
             Guru (Petugas Perizinan) meninjau
                               │
              ┌────────────────┴────────────────┐
        Setujui                              Tolak
   [status: disetujui]                 [status: ditolak]
        │                                     │
   WA ke wali (info izin disetujui)     WA ke wali (opsional)
```

### 2.2 Jenis izin
- **Izin Syar'i** — alasan sah menurut syariat (mis. sakit, wafat keluarga, hajat penting). Umumnya tidak menambah "poin pelanggaran".
- **Izin Non-Syar'i** — izin biasa/pribadi (mis. keperluan, jenuh). Bisa dibatasi kuota / dicatat berbeda.

> ❓ **Pertanyaan:** apakah jenis izin memengaruhi skor/kedisiplinan santri (mis. non-syar'i mengurangi poin, atau ada kuota per bulan)? Atau murni pencatatan?

### 2.3 Cara santri mengajukan (⚠️ keputusan penting)
Di sistem ini **santri = data murni tanpa login**. Jadi "santri mengajukan" perlu mekanisme:
- **Opsi A — Kiosk/perangkat pesantren**: santri pilih namanya (scan barcode NIP seperti Smart Controlling) → isi izin. Reuse pola device.
- **Opsi B — Diinput petugas/wali kamar** atas nama santri.
- **Opsi C — Beri santri akses terbatas** (butuh akun santri — perubahan besar; tidak disarankan sekarang).

> ❓ **Keputusan:** pilih A/B/C. (Rekomendasi: **A** — konsisten dengan barcode Smart Controlling yang sudah ada.)

### 2.4 Sketsa data
`izin_santri`:
| kolom | catatan |
|---|---|
| id | |
| santri_id | FK |
| jenis | enum(`syari`,`non_syari`) |
| alasan | text |
| tanggal_mulai / tanggal_selesai | rentang izin |
| lampiran | foto/surat (opsional) |
| status | enum(`diajukan`,`disetujui`,`ditolak`,`selesai`,`dibatalkan`) |
| disetujui_oleh | FK tenaga_pendidik (petugas) |
| catatan_petugas | alasan tolak / catatan |
| diputuskan_pada | |
| timestamps | |

### 2.5 Integrasi
- **WA wali** saat disetujui/ditolak (reuse `WaService` + template baru "Izin Santri").
- **Hook ke Smart Health**: kondisi "diizinkan pulang" (Pengecekan Hari 3 / Darurat) bisa **otomatis membuat `izin_santri` jenis syar'i (sakit)** → satu sumber data izin.

---

## 3. Fitur 2 — Smart Health

### 3.1 Analisis flow (dari diagram)
```
Guru / Santri ── lapor ──► [Menunggu persetujuan Bagian Kesehatan]
                                     │
                    ┌────────────────┴───────────────┐
                 TIDAK                              IYA
           (langsung dihapus)                (laporan TERCATAT)
                                                    │
                              ┌─────────────────────┴─────────────────────┐
                    Pemberitahuan Otomatis (WA)              Masuk Database Pengecekan
                                                                          │
                                                                    [Pengecekan]
                                              ┌───────────────┬───────────┴──────────┐
                                          Sembuh        Pengecekan Hari 1→2→3      Darurat
                                              │                   │                   │
                                              └───────────────────┴───────────────────┘
                                                                  │
                                                    Pemberitahuan Otomatis (WA ke wali)
```

### 3.2 Aktor
- **Pelapor**: guru atau santri (melaporkan santri yang sakit) + **deskripsi penyakit** + **foto**.
- **Bagian Kesehatan**: beberapa guru ditunjuk; **salah satu** menyetujui → laporan tercatat & masuk database pengecekan. Bila tidak disetujui → **dihapus** (laporan gugur).

### 3.3 State machine (usulan rapi)
```
menunggu ──(petugas tolak)──► ditolak/dihapus
   │
   (petugas setuju)
   ▼
tercatat ──► dalam_pengecekan
                 │  (petugas mengisi keputusan tiap pantau)
                 ├─ Sembuh         → status: selesai (SEMBUH)
                 ├─ Pengecekan H1  → status: pengecekan (hari 1)
                 ├─ Pengecekan H2  → status: pengecekan (hari 2)
                 ├─ Pengecekan H3  → status: selesai (IZIN PULANG)
                 └─ Darurat        → status: selesai (IZIN PULANG / rujuk)
```
- **Hari 1–3** = pemantauan bertingkat. Tiap keputusan pantau dicatat (log) + memicu WA.
- **Sembuh** kapan saja → tutup kasus.
- **Darurat** kapan saja → izin pulang / rujukan.

> ❓ **Pertanyaan:** apakah Hari 1→2→3 **maju otomatis** (scheduler harian) atau **manual** (petugas menekan "Pantau hari ini")? (Rekomendasi: **manual** — petugas mengecek langsung tiap hari; lebih akurat.)

### 3.4 Kondisi → Pesan WhatsApp (inti permintaan)
| Kondisi (keputusan petugas) | Status kasus | Isi pesan WA ke wali |
|---|---|---|
| **Sembuh** | selesai | "Ananda **{nama}** telah **SEMBUH** dari {penyakit}." |
| **Pengecekan Hari 1** | berjalan | "Ananda **{nama}** masih dalam **pengecekan** ({penyakit}) — hari ke-1." |
| **Pengecekan Hari 2** | berjalan | "Ananda **{nama}** masih dalam **pengecekan** ({penyakit}) — hari ke-2." |
| **Pengecekan Hari 3** | selesai | "Ananda **{nama}** ({penyakit}) **diizinkan pulang** untuk pemulihan." |
| **Darurat** | selesai | "⚠️ Ananda **{nama}** kondisi **DARURAT** ({penyakit}) — **diizinkan pulang**/segera dijemput." |

Semua pesan menyertakan **deskripsi penyakit** + (opsional) **foto** (Fonnte mendukung kirim gambar + caption). Header/branding pakai `WaTemplate` yang sudah ada.

### 3.5 Sketsa data
`smart_health_laporan`:
| kolom | catatan |
|---|---|
| id | |
| santri_id | FK |
| pelapor_tenaga_pendidik_id | nullable (bila guru) |
| pelapor_tipe | enum(`guru`,`santri`,`petugas`) |
| deskripsi_penyakit | text |
| foto | path |
| status | enum(`menunggu`,`ditolak`,`tercatat`,`dalam_pengecekan`,`selesai`) |
| kondisi_akhir | enum(`sembuh`,`izin_pulang`,`darurat`) nullable |
| disetujui_oleh | FK tenaga_pendidik (petugas kesehatan) |
| disetujui_pada | |
| timestamps | |

`smart_health_pengecekan` (log pemantauan per hari):
| kolom | catatan |
|---|---|
| id | |
| laporan_id | FK |
| hari_ke | 1..3 (null utk sembuh/darurat) |
| keputusan | enum(`sembuh`,`pengecekan`,`darurat`) |
| catatan | text |
| oleh_tenaga_pendidik_id | petugas |
| tanggal | |
| timestamps | |

### 3.6 "Pemberitahuan Otomatis"
= kirim WA via **`WaService::enqueue`** (jenis baru `kesehatan`) → `wa_outbox` → Fonnte. Idempotent per keputusan (`WA-HEALTH-{pengecekan_id}`). Terkirim saat: (a) laporan disetujui, (b) tiap keputusan pantau, (c) sembuh/darurat.

### 3.7 Integrasi
- **→ Perizinan**: kondisi "izin pulang" (H3/darurat) **otomatis membuat `izin_santri`** jenis syar'i (sakit) → data izin & kesehatan sinkron.
- **→ Absensi santri**: santri yang sakit/izin pulang bisa otomatis tercatat status `izin`/`sakit` di absensi Smart Controlling/pembelajaran (hindari dianggap alfa). *(perlu keputusan)*
- **→ Monitoring admin**: dashboard kasus kesehatan (menunggu / dalam pengecekan / selesai), rekap bulanan penyakit.

---

## 4. Reuse Infrastruktur yang Sudah Ada
| Kebutuhan | Sudah ada? | Reuse |
|---|---|---|
| Nomor WA wali | ✅ `santri.no_whatsapp` | langsung |
| Kirim WA + retry + template | ✅ Fonnte, `wa_outbox`, `WaService`, `WaTemplate`, Monitor WA | tambah jenis `kesehatan`/`izin` + template |
| Data santri + barcode NIP | ✅ Smart Controlling | untuk input santri via kiosk (Opsi A) |
| Pola persetujuan bertingkat | ✅ (RamahAnak gerbang, izin tendik) | referensi |
| Delegasi peran | ⚠️ belum | tabel `petugas_peran` baru |
| Kirim WA + **gambar** | ⚠️ `FonnteClient` baru kirim teks | tambah dukungan `url` gambar |

---

## 5. Pertanyaan Terbuka (perlu keputusan sebelum PRD final)
1. **Cara santri mengajukan** (Perizinan & lapor Health): kiosk barcode / diinput petugas / akun santri? *(rekomendasi: kiosk barcode)*
2. **Jenis izin** memengaruhi skor/kuota santri atau murni catatan?
3. **Hari 1→2→3** maju otomatis (scheduler) atau manual per pantau? *(rekomendasi: manual)*
4. **Foto WA**: perlu kirim gambar via Fonnte (butuh upgrade `FonnteClient` + hosting foto publik)? Atau cukup teks?
5. **Delegasi peran**: pivot `petugas_peran` (fleksibel) vs boolean di `tenaga_pendidik` (sederhana)?
6. **Integrasi absensi**: santri izin pulang/sakit otomatis tercatat di absensi santri?
7. **Notifikasi ke Petugas**: apakah petugas kesehatan/perizinan dapat notifikasi (WA/in-app) saat ada laporan/izin baru menunggu?
8. **Akses guru**: apakah guru petugas mengelola via aplikasi Flutter (menu baru) atau via web admin (akses terbatas)?

---

## 6. Usulan Tahapan Implementasi (setelah keputusan)
1. **Fondasi delegasi peran** (`petugas_peran`) + UI superadmin menunjuk petugas.
2. **Perizinan Santri** (dibuat lebih dulu, sesuai permintaan): tabel `izin_santri`, input (kiosk/Flutter), persetujuan petugas, WA wali, monitor.
3. **Upgrade WA gambar** (bila foto diperlukan).
4. **Smart Health**: tabel laporan + pengecekan, alur validasi "salah satu petugas", state machine, keputusan → WA, integrasi izin pulang.
5. **Monitoring & rekap** admin (kasus kesehatan + izin), sinkron absensi.

---

## 7. Catatan Sinkronisasi
- Satu **sumber nomor WA** = `santri.no_whatsapp` (hub WA di An-Nur).
- Satu **mesin WA** = Fonnte + `wa_outbox` (idempotent, retry, monitor, kotak masuk).
- **Izin pulang (Health) ⇄ Izin Santri** = satu data izin (hindari duplikasi).
- **Prasyarat ops**: `queue:work` (kirim WA) + scheduler (bila Hari 1–3 otomatis).
