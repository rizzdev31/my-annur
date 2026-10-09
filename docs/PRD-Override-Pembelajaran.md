# PRD — Override & Koreksi Pembelajaran

**Status:** Terpasang di produksi (`myannur.id`), 1–8 Oktober 2026
**Modul:** Smart Education + Smart Payroll
**Dokumen turunan:** [PRD-Validasi-Pembelajaran.md](PRD-Validasi-Pembelajaran.md) (ditunda)

Dokumen ini merangkum satu rangkaian pekerjaan: bagaimana pembelajaran yang
**tidak berjalan normal** dicatat dengan jujur tanpa merusak gaji, kinerja, dan
laporan. Empat fitur, satu alur cerita.

---

## 1. Kategori kelas `pesantren`

**Masalah.** Muhadharah SMA mengumpulkan santri kelas X, XI, XII menjadi satu
kelas putra dan satu kelas putri. Kedua kelas itu berjenis `sekolah`, sehingga
satu slot dengan kelas X/XI/XII — memasukkan santri ke sana justru
**mengeluarkannya** dari kelas sekolahnya (`Kelas::SLOT` = satu kelas aktif per
slot).

**Keputusan.** Jenis kelas keempat `pesantren` dengan **slot sendiri**, sehingga
keanggotaan sekolah + pesantren + Quran berjalan berdampingan. Selain slot,
perlakuannya **sama dengan sekolah**: Jadwal Mengajar umum, mapel tetap
`reguler`, jurnal & absensi biasa. Mapel **tidak** diberi tipe baru — routing
PWA `/{tipe}/{id}` akan patah.

**Jejak teknis.** `Kelas::SLOT` + `JENIS_REGULER` + `scopeReguler()`. Tiga tempat
yang memakai `scope sekolah()` wajib pindah ke `reguler()`: pilihan kelas di
Jadwal Mengajar, Jurnal Mengajar, dan Laporan Pembelajaran — kalau tidak, kelas
pesantren hilang dari daftar. Yang **tetap** `sekolah`: kelas utama ke RamahAnak
dan label kelas santri di PWA wali.

**Data:** Muhadoroh SMA Putra (#34) & Putri (#33) → `pesantren`, tingkat SMA,
46 santri SMA terdistribusi sesuai jenis kelamin (23 putra, 23 putri).

---

## 2. Libur pembelajaran (kegiatan)

**Masalah.** Saat ada kegiatan, pembelajaran diliburkan **tetapi orangnya tetap
masuk**. Tidak boleh memakai `hari_libur`: `HariLibur::isLibur()` dibaca ~20
tempat yang semuanya berarti "libur sehari penuh untuk semua" — termasuk gerbang
absen masuk guru dan **jumlah hari kerja penggajian**.

**Keputusan.** Tabel sendiri `libur_pembelajaran`, hanya dibaca sisi
pembelajaran. Cakupan `semua` | `kelas` | `sesi`, penyaring jenis kelas & jam
(kegiatan pagi tidak meliburkan halaqoh malam). Jurnal terisi nama kegiatan,
santri tercatat hadir kecuali yang izin/sakit — memakai `KehadiranSantriService`,
gerbang yang sama dengan roster guru.

**Pengaman.**
- `absensi_mengajar.libur_pembelajaran_id` + `status_sebelum` → pembatalan bersih.
- `absensi_santri.sumber` (`guru`|`kegiatan`) → laporan memisahkan kehadiran
  pembelajaran dari kehadiran kegiatan.
- Sesi yang sudah diajar (termasuk pengganti yang sudah mengisi) **tidak ditimpa**.
- Pengganti yang belum mengisi ikut libur agar vakasinya tidak terbayar.
- Pratinjau dampak **wajib** sebelum simpan.
- Periode penggajian terkunci **atau sudah berslip** ditolak.

**Koreksi roster kegiatan.** Auto-hadir hanya titik awal; guru pendamping
(pengampu sesi, pengganti, atau **guru piket hari itu**) boleh membetulkannya
berulang lewat endpoint terpisah `POST /absensi/mengajar/koreksi-kegiatan` —
bukan `absen-santri` yang sengaja sekali-simpan-lalu-terkunci.

---

## 3. Ujian sekolah (penjaga + inval)

**Masalah.** Pembelajaran diganti sesi ujian; penjaganya **boleh guru mana saja**,
bukan hanya yang berjadwal. Jam ujian lebih panjang dari jam pelajaran.

**Keputusan struktural.** Satu sesi ujian = **baris `jadwal_mengajar`
bermasa-berlaku SEHARI**, dengan guru = penjaga dan mapel = mata ujian. Dengan
begitu absensi, roster santri, laporan, papan piket, kinerja, dan **INVAL**
(`digantikan_oleh` + `PenggantiMengajarService`) ikut bekerja tanpa jalur baru.

Dua opsi lain ditolak: menumpangi sesi reguler memaksa jam ujian = jam pelajaran
dan merusak atribusi kinerja guru asli; tabel absensi sendiri memutus jalur
laporan `absensi_santri → absensi_mengajar → jadwal_mengajar`.

**Harga keputusan.** `jadwal_mengajar` tadinya mingguan tanpa masa berlaku.
Ditambah `berlaku_mulai`/`berlaku_selesai` + `scopeBerlakuPada()` dan
`bukanUjian()`/`ujian()`. **18 pembaca "jadwal pada tanggal X" wajib memakai
scope itu** — satu saja terlewat, jadwal ujian muncul lagi setiap pekan dan
memicu "sesi tidak terlaksana" palsu. Masa berlaku eksplisit **menang** atas
penjaga `created_at`.

**Keputusan kebijakan (8 Okt).** Satu penjaga satu kelas · vakasi **per sesi**,
dan saat di-inval **penggantinya dibayar dengan nominal penjaga yang sama** ·
inval **hanya oleh admin** · mata ujian diambil dari mapel yang ada.

**Pengaman.**
- Jadwal ujian **tidak boleh ikut diliburkan** (`bukanUjian()` di LiburMengajarService).
- Libur bentukan paket ujian dibuat dengan `isi_absensi_santri = false` →
  kehadiran santri tidak terhitung dua kali.
- Sesi ujian **dikecualikan** dari vakasi mengajar per-JP, daftar & JP jadwal
  mingguan admin, dan pemeriksaan bentrok jadwal manual.
- Sesi ujian **tetap ditagih** scheduler & papan piket → penjaga mangkir terdeteksi.
- Kandidat penjaga diperiksa **lintas dua dunia**: jadwal reguler yang masih
  berjalan (yang sudah diliburkan tidak dihitung bentrok) + sesi ujian lain + izin.

**Laporan.** `admin.smart-education.laporan.ujian` (modul `se_laporan_ujian`):
jendela per paket atau rentang tanggal, tiga bagian (per penjaga / per kelas /
per sesi), kop + tanda tangan + cetak. Dasar vakasi di laporan **identik** dengan
payroll: penjaga aktual + hanya sesi yang benar-benar dijaga.

Sesi ujian juga tercatat di **jurnal kelas masing-masing** dengan penanda UJIAN;
bila guru belum menulis materi, deskripsinya jatuh ke "Ujian: <paket>".

---

## 4. Koreksi pembelajaran (inval cepat, koreksi sesi & absensi)

Satu pintu: `KoreksiPembelajaranService`. Pengaman sama di semua jalur — periode
terkunci/berslip ditolak, setiap perubahan berjejak, WA wali **hanya** untuk
baris yang benar-benar berubah.

| Jalur | Siapa | Batas |
|---|---|---|
| Edit absensi santri | guru pengampu / inval | dalam jendela JP (`jam_selesai` + 15 menit) |
| Koreksi sesi (terlaksana/JP/jam/materi) | admin | alasan wajib |
| Koreksi absensi santri | admin | tanpa jendela |
| Inval cepat | admin | tanpa syarat izin guru |

**Catatan penting.** Jendela edit guru memakai `KebijakanMengajar::batasAbsenSesi()`
— **batas yang sama dengan batas JP**, supaya guru tidak menghafal dua aturan.
Berlaku untuk sekolah, pesantren, tahfidz, dan tahsin karena semuanya menyimpan
di `absensi_santri`.

**Inval cepat sengaja tidak mensyaratkan izin resmi** — berbeda dengan
`PenggantiMengajarService::tunjukPengganti()` yang menolak tanpa izin disetujui.
Itu benar untuk guru yang menunjuk penggantinya sendiri, tetapi menghalangi admin:
guru bisa berhalangan mendadak tanpa sempat mengajukan izin. Alasan admin dicatat
sebagai gantinya.

---

## Gotcha yang sudah memakan korban

1. **`koreksi_absensi.absensi_mengajar_id` ber-FK tanpa cascade.** Setiap jalur
   yang MENGHAPUS baris `absensi_mengajar` gagal di tengah transaksi bila sesi itu
   pernah dikoreksi — terjadi pada "batal inval". Pakai
   `KoreksiPembelajaranService::lepaskanJejakAbsensi()`: rujukan log di-null-kan
   (jejaknya tidak dihapus), baru barisnya dihapus.
2. **`periode_penggajian.dikunci_pada` kosong di semua periode produksi**, padahal
   September sudah menerbitkan 51 slip. Penjaga yang hanya melihat `dikunci_pada`
   praktis mati — harus **atau sudah punya baris penggajian**.
3. **`setting_vakasi.berlaku_mulai` & `dibuat_oleh` NOT NULL tanpa default** —
   skrip apa pun yang membuat baris vakasi wajib mengisinya.
4. **Sidebar grup Laporan** masih memakai kode `se_laporan` yang sudah dipecah
   granular → pemegang hak granular tidak pernah melihat menunya (sudah dibereskan).

---

## Yang BELUM dikerjakan (lanjutan)

| # | Item | Catatan |
|---|---|---|
| 1 | **Nominal vakasi "Jaga Ujian"** | Setting Vakasi masih kosong → vakasi laporan Rp 0. Harus diisi sebelum paket ujian pertama. |
| 2 | **Alpha ujian & WA wali ujian** | Ditunda atas keputusan 8 Okt. Saat ini roster ujian memakai alur absensi biasa. |
| 3 | **Kehadiran ujian dibedakan di laporan** | Pola sudah ada dari `absensi_santri.sumber` (kegiatan); belum diterapkan untuk ujian. |
| 4 | **Ruang gabungan / beberapa pengawas per ruang** | Model sekarang satu penjaga satu kelas (keputusan 8 Okt). |
| 5 | **Koreksi roster kegiatan massal** | 316 baris `tasmi_lulus` tanpa setoran tasmi — keputusan kebijakan belum diambil (lihat memori sinkron hafalan). |
| 6 | **Validasi pembelajaran (validator)** | Dibrainstorm, ditunda. Lihat PRD terpisah. |

## Uji produksi

| Lingkup | Hasil |
|---|---|
| Kategori pesantren | 23/23 |
| Libur pembelajaran | 43/43 |
| Koreksi roster kegiatan | 30/30 |
| Ujian — fondasi masa berlaku | 48/48 |
| Ujian — alur + inval + PWA | 49/49 |
| Laporan ujian + jurnal kelas | 39/39 |
| Koreksi pembelajaran | 47/47 |

Semua dijalankan di produksi dalam transaksi yang dibatalkan.
