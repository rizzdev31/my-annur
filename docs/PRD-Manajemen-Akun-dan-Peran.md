# PRD — Manajemen Akun & Peran (RBAC Web Admin)

**Status:** Draft (disetujui garis besar) · **Tanggal:** 2026-07-14 · **Modul:** Pengaturan → Akun & Peran
**Cakupan:** **HANYA panel WEB (Laravel/Inertia).** Aplikasi **Flutter (guru/santri) tidak berubah sama sekali.**
**Tujuan utama:** Superadmin dapat **membuat peran sendiri (fleksibel, bisa nambah kapan saja)**, menentukan **fitur/modul apa** yang boleh diakses tiap peran, lalu **memberikan peran itu ke akun yang dituju** — semuanya lewat UI, **tanpa mengganggu / meng-error-kan fitur yang sudah berjalan.**

---

## 1. Latar Belakang & Sasaran

Saat ini seluruh panel web dikunci `role: super_admin` (peran tunggal), satu akun mengelola semua. Pesantren ingin **membagi tugas** ke beberapa petugas (tak harus guru): Administrasi, Kurikulum, Kesiswaan, dsb.

**Sasaran:**
- Akun admin **berdiri sendiri** (tak perlu terhubung data guru).
- **Peran bisa ditambah/diubah superadmin** (tidak dikunci 4 saja) — nama peran fleksibel.
- Superadmin memilih **modul mana** yang termasuk tiap peran, dan **memberikan peran ke akun** — bisa diubah kapan saja.
- **Superadmin tetap akses penuh** dan tak pernah terkunci.

**Non-Sasaran:**
- **Tidak menyentuh Flutter / API guru-santri sama sekali.**
- Bukan izin per-tombol (granular ekstrem). Cukup **per-modul** (satu tingkat di atas menu).
- Tidak mengubah `petugas_peran` (delegasi guru untuk app Flutter) — lapisan berbeda (lihat §9).

---

## 2. Konsep Inti (3 lapisan)

```
AKUN  ──punya──►  PERAN (fleksibel, dibuat superadmin)  ──berisi──►  MODUL (tetap, terikat kode)
user  user_peran        peran (tabel DB)                peran_modul     config/modul.php
```

- **MODUL** = daftar **tetap** unit fitur nyata yang ada di aplikasi (terikat route). Superadmin **tidak** membuat modul (karena kodenya harus ada). Contoh: `smart_education`, `smart_health`, `penggajian`, `inventaris`.
- **PERAN** = **bundel modul yang diberi nama**, disimpan di **tabel DB** → superadmin bebas **menambah peran baru** (mis. "Keuangan") dan mencentang modul mana saja isinya.
- **AKUN** diberi satu/lebih **peran**.

> Inilah kunci fleksibilitas: modul (yang butuh kode) tetap; **peran (yang cuma pengelompokan) bisa ditambah sepuasnya lewat UI** tanpa ubah kode.

### 2.1 Prinsip Akses = PENUH per modul (bukan read-only, bukan per-tombol)

Akun yang diberi sebuah **modul** memiliki **AKSES PENUH** ke modul itu — bisa **melihat, menambah, mengubah, menghapus, dan mengelola/menyetujui** (semua aksi yang tersedia di modul tersebut), supaya benar-benar dapat **membantu pengelolaan & penyesuaian** di bagiannya. Di **luar** modul yang diberikan → **tidak ada akses sama sekali** (menu tak muncul & route ditolak server).

- **Granularitas = per MODUL**, bukan per aksi/tombol. Tidak ada mode "hanya lihat" di fase ini.
- Contoh: akun **Kesiswaan** bisa melakukan **semua** aksi di Smart Controlling, Smart Health, Perizinan Santri, Smart Eksekusi (scan, validasi, setujui, hapus, dll), **tetapi tak bisa membuka** Penggajian/Absensi/Master sama sekali.

---

## 3. Daftar MODUL (tetap — sumber: `config/modul.php`)

Setiap modul memetakan ke prefix nama route (Ziggy). Route di luar daftar ini → **superadmin only** (fail-safe).

| Kode modul | Nama tampil | Prefix route |
|---|---|---|
| `absensi` | Absensi & Kehadiran | `admin.smart-payroll.absensi`, `admin.smart-payroll.monitoring` |
| `kinerja` | Kinerja | `admin.smart-payroll.kinerja` |
| `tugas` | Tugas & Lembur | `admin.smart-payroll.tugas-jabatan`, `…tugas-tambahan`, `…absensi-kegiatan`, `…lembur` |
| `pengajuan_izin` | Pengajuan Izin Guru | `admin.smart-payroll.pengajuan-izin` |
| `penggajian` | Penggajian & Laporan | `admin.smart-payroll.periode`, `…penggajian`, `…laporan`, `…hari-libur`, `…libur-tendik` |
| `whatsapp` | WhatsApp | `admin.smart-payroll.setting-wa`, `…wa-outbox`, `…wa-inbox` |
| `smart_education` | Smart Education | `admin.smart-education.` |
| `piket` | Guru Piket | `admin.piket.` |
| `smart_habbit` | Smart Controlling & Eksekusi | `admin.smart-habbit.` |
| `perizinan_santri` | Perizinan Santri | `admin.perizinan.` |
| `smart_health` | Smart Health | `admin.smart-health.` |
| `inventaris` | Inventaris | `admin.inventaris.` |

**Superadmin-only (tak masuk modul):** Master Data (`admin.master.*`), Setting Gaji/Kinerja/Lokasi/Potongan/Pengajuan, dan **Kelola Akun & Peran** itu sendiri.

---

## 4. PERAN Bawaan (seed awal — bisa ditambah/ubah)

4 peran default di-*seed* (ditandai `bawaan`), superadmin bebas menambah lagi:

| Peran (bawaan) | Modul default |
|---|---|
| **Administrasi** | absensi, kinerja, tugas, pengajuan_izin, penggajian, whatsapp |
| **Kurikulum** | smart_education, piket |
| **Kesiswaan** | smart_habbit, perizinan_santri, smart_health |
| **Sarana** | inventaris |

Contoh peran **baru** yang bisa dibuat superadmin nanti: "Keuangan" = {penggajian} saja, "Operator WA" = {whatsapp}, dll.

---

## 5. Model Data (ADDITIF — tanpa hapus/ubah data lama)

**M1. Perluas enum role:**
```
users.role : enum('super_admin','tenaga_pendidik','santri','admin')
```

**M2. Tabel `peran`** (master peran — bisa ditambah):
| kolom | tipe | ket |
|---|---|---|
| id | PK | |
| kode | string(40) unique | slug, mis. `administrasi` |
| nama | string(60) | tampilan |
| deskripsi | string nullable | |
| is_bawaan | boolean default false | peran seed → tak boleh dihapus (boleh diedit modul) |
| is_aktif | boolean default true | |
| timestamps | | |

**M3. Tabel `peran_modul`** (peran ↔ modul):
| kolom | ket |
|---|---|
| id, peran_id (FK cascade), **modul** (string kode dari config), timestamps |
| **unique** (peran_id, modul) |

**M4. Tabel `user_peran`** (akun ↔ peran):
| kolom | ket |
|---|---|
| id, user_id (FK cascade), peran_id (FK cascade), ditetapkan_oleh (FK nullable), timestamps |
| **unique** (user_id, peran_id) |

**Config `config/modul.php`** = daftar modul tetap + prefix (tabel §3).

**Model:**
- `Peran` — `modul()` (hasMany peran_modul) / `daftarModul(): array`, `users()`.
- `User` — `peran()` (belongsToMany via user_peran), helper `modulSaya(): array` (gabungan modul dari semua peran aktif), `bolehModul(string): bool`, `bolehRoute(string $namaRoute): bool` (**super_admin → selalu true**).

---

## 6. Penegakan Akses (Enforcement)

### 6.1 Middleware `akses`
- Alias `akses`. Dipasang di **grup route admin** (satu kali).
- Logika tiap request:
  1. `super_admin` → **lolos** (bypass).
  2. Bukan `admin` (guru/santri) → tetap ditolak panel web (seperti sekarang).
  3. `admin`: petakan **nama route saat ini → modul** (via prefix di `config/modul.php`).
     - Modul ada di `modulSaya()` → lolos.
     - Tidak → **redirect ke halaman "Akses Ditolak" ramah** (bukan 500).
     - Route **tak terpetakan modul** (master/setting) → **hanya super_admin** (fail-safe).

### 6.2 Sidebar (AdminLayout)
- Bagikan `auth.modul` (array kode) + `auth.is_superadmin` via `HandleInertiaRequests`.
- Tiap section/menu dibungkus `v-if="boleh('smart_health')"` (helper). Superadmin lihat semua. Menu tak diizinkan **tidak dirender**.

### 6.3 Login & Landing
- `super_admin`/`admin` → panel admin.
- `admin` diarahkan ke **menu pertama yang ia punya**. Tanpa peran/modul → halaman "Belum ada akses, hubungi superadmin" (bukan error).

---

## 7. Fitur Superadmin (menu baru, superadmin-only)

### 7.1 Kelola Peran — `admin.peran.*`
- Daftar peran + jumlah modul + jumlah akun pemakai + aktif/nonaktif.
- **+ Tambah Peran**: nama, deskripsi, **centang modul** (dari `config/modul.php`).
- Edit peran (ubah nama/modul), aktif/nonaktif.
- Peran **bawaan**: boleh diedit modulnya, **tak boleh dihapus** (proteksi).

### 7.2 Kelola Akun — `admin.akun.*`
- Daftar akun admin: nama, email/username, **peran (chip)**, status, aksi.
- **+ Tambah Akun**: nama, email, username, password, aktif.
- **Centang peran** (dari tabel `peran` aktif) — bisa >1.
- Reset password, aktif/nonaktif, hapus (konfirmasi + toast). **Akun `super_admin` tak dapat diubah/dihapus dari sini.**

---

## 8. Aturan & Edge Case (kunci "tanpa error")

1. **Superadmin bypass total** — tak pernah terkunci apa pun konfigurasinya.
2. **Migrasi additif** — enum hanya ditambah; akun `super_admin` lama tetap penuh; `tenaga_pendidik`/`santri` & **Flutter** tak tersentuh.
3. **Akun admin tanpa peran/modul** → tak crash; halaman "belum ada akses".
4. **Modul di DB tapi tak ada di config** (mis. dihapus dari config) → di-filter aman terhadap `config('modul')`.
5. **Route tak terpetakan** → default **superadmin-only** (fail-safe lebih ketat).
6. **403 ramah** — halaman "Akses Ditolak" ber-desain, bukan error mentah.
7. **Anti self-lockout** — superadmin tak bisa menurunkan/menghapus dirinya sendiri.
8. **Pertahanan berlapis** — **middleware server = sumber kebenaran**; sidebar hanya kenyamanan (route tetap dijaga server walau URL diketik manual).
9. **Peran nonaktif / dihapus** → otomatis tak memberi akses; akun pemakainya kehilangan modul itu dengan aman (bukan error).

---

## 9. Hubungan dengan `petugas_peran` (Flutter) — DIPISAH

- `petugas_peran` (perizinan/kesehatan, terhubung `tenaga_pendidik`) = **delegasi GURU untuk aksi di app Flutter** (mis. validasi Smart Health).
- PRD ini = **peran AKUN WEB ADMIN**. **Dibiarkan terpisah** (dua kebutuhan beda). Penyatuan = enhancement opsional kelak.

---

## 10. Tahapan Implementasi (bertahap, tiap tahap diverifikasi)

**T1 — Fondasi & Penegakan (backend):**
- Migrasi M1–M4 + `config/modul.php` + Seeder 4 peran bawaan.
- Model `Peran`/`User` helper + relasi.
- Middleware `akses` + halaman "Akses Ditolak".
- *Verifikasi:* migrate, `route:list`, tinker (akun kesiswaan → lolos smart-habbit/perizinan/health, ditolak smart-payroll; superadmin lolos semua; tanpa peran → ditolak ramah).

**T2 — Sidebar & Landing:**
- Bagikan modul ke Inertia; filter section sidebar; redirect landing per modul pertama.
- *Verifikasi:* build; menu hanya yang diizinkan (uji akun contoh).

**T3 — UI Kelola Peran & Akun:**
- Controller + halaman Vue (Kelola Peran: CRUD + centang modul; Kelola Akun: CRUD + assign peran) + route superadmin-only + entri sidebar.
- *Verifikasi:* build, tinker/manual (buat peran baru → beri modul → tetapkan ke akun → login akun itu → akses sesuai).

---

## 11. Definition of Done

- [ ] Superadmin bisa **buat peran baru** + centang modul, dan **memberi peran ke akun** — lewat UI + toast.
- [ ] Akun admin hanya melihat & mengakses modul peran-nya; lainnya → "Akses Ditolak" (bukan error), termasuk saat URL diketik manual.
- [ ] Superadmin akses penuh; akun lama, data, & **Flutter** tak terganggu.
- [ ] `php -l`, `route:list`, `npm run build`, tinker skenario akses — semua hijau.
- [ ] Regresi: seluruh menu existing tetap berfungsi untuk superadmin.

---

## 12. Risiko & Mitigasi

| Risiko | Mitigasi |
|---|---|
| Menu disembunyikan tapi route diakses langsung | Middleware server sumber kebenaran. |
| Salah petakan modul → akun terkunci | Superadmin bypass + config satu tempat + fail-safe superadmin-only. |
| Superadmin mengunci diri | Proteksi anti self-demote/self-delete. |
| Enum `admin` memengaruhi query lama | Additif; cek `super_admin` lama tetap valid; tak ada nilai dihapus. |
| Peran fleksibel → modul tak dikenal | Selalu di-filter terhadap `config('modul')`. |

---

*Setelah PRD disetujui, implementasi dari **T1**, diverifikasi tiap tahap ("berjalan tanpa error"). Modul tetap (kode), peran & pemetaan fleksibel (DB/UI).*
