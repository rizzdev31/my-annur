# PRD — Validasi Pembelajaran (Validator)

**Status:** DITUNDA — brainstorm 3 Okt 2026; **sinyal anomali sudah diukur di
produksi 9 Okt 2026 (lihat bagian akhir) dan hasilnya mengubah arah: mesin risiko
tidak punya bahan, yang tersisa sidak berbasis sampel acak.**
**Tujuan:** mengetahui kebenaran guru mengajar atau tidak
**Terkait:** [PRD-Override-Pembelajaran.md](PRD-Override-Pembelajaran.md), [PRD-Guru-Piket.md](PRD-Guru-Piket.md)

---

## Temuan yang mengubah bentuk fitur

Diukur dari 30 hari data produksi (3 Sep – 3 Okt 2026): **1.381 sesi**, rata-rata
**44,6 sesi/hari** dari 363 slot jadwal per minggu.

Dari sesi berstatus terlaksana/pengganti, "ada buktinya" menurut aturan yang
sudah dipakai kinerja:

| Tipe | Sesi | Berbukti | Bukti kuat | Foto |
|---|---|---|---|---|
| tahsin | 207 | 100,0% | 100,0% | 0% |
| tahfidz | 302 | 100,0% | 100,0% | 0% |
| reguler | 651 | 98,2% | 97,4% | 99,1% |
| **SEMUA** | **1.160** | **99,0%** | **98,5%** | 55,6% |

→ hanya **12 sesi dalam 30 hari (0,5/hari)** yang tanpa bukti.

**Artinya:** bila validasi dirancang sebagai *"validator memeriksa apakah data
sesi lengkap"*, 99% lolos otomatis dan fitur ini **tidak menjawab pertanyaannya** —
karena seluruh bukti itu dibuat sendiri oleh guru yang bersangkutan. Dia yang
menekan absen, menulis materi, dan mengisi roster santri. Tidak ada saksi
independen.

**Kesimpulan:** inti fitur bukan memeriksa kelengkapan data, melainkan
**mendatangkan bukti dari luar catatan guru**.

---

## Posisi terhadap yang sudah ada

| | Guru Piket | Pengawas (pimpinan) | **Validator** |
|---|---|---|---|
| Waktu | saat berjalan | kapan saja | setelah sesi |
| Sifat | operasional | memantau (baca) | **memutuskan** |
| Dampak | menandai sesi | setujui izin | kinerja / gaji |
| Cakupan | hari gilirannya | per guru | disetel per jenis & cakupan |

`Pengawas` adalah preseden terdekat untuk "kredensial yang disetel admin": satu
baris per orang, modul yang boleh (JSON), cakupan `semua`/`pilih` + pivot, satu
sakelar wewenang tinggi (`boleh_setujui_izin`), dan `ditunjuk_oleh` sebagai jejak.
Validator **meniru pola itu dengan tabel sendiri** — pengawas sifatnya membaca,
validator bertugas aktif dengan antrian, kuota, dan tenggat.

---

## Tiga sumber kebenaran yang mungkin

1. **Kunjungan langsung (sidak).** Satu-satunya bukti yang benar-benar
   independen. Mahal → harus berbasis sampel.
2. **Konfirmasi santri.** Murah dan independen, tetapi sensitif — perlu kebijakan.
3. **Anomali metadata.** Jejak yang **tidak dikuasai guru**: *kapan* catatan
   dibuat (`created_at`) dibanding jam jadwalnya. Sesi yang diabsen 3 jam setelah
   selesai, lima sesi yang diisi dalam satu menit (pengisian borongan), atau guru
   yang tercatat mengajar di dua kelas pada jam yang sama — semuanya terdeteksi
   tanpa manusia.

**Usul: nomor 3 memilih siapa yang diperiksa, nomor 1 memeriksanya.**

---

## Rancangan

### Antrian berbasis risiko, bukan seluruh populasi

44 sesi/hari mustahil divalidasi manual. Yang masuk antrian hanya:

- **sinyal anomali** (absen borongan, telat jauh dari jadwal, tanpa roster, jam
  bertabrakan, pengganti tanpa catatan);
- **sampel acak 10–15%** dari sesi yang bersih — ini yang membuat "aman karena
  tidak dicurigai" tidak bisa diandalkan;
- **aduan** (santri, wali, pimpinan, atau menu Saran & Masukan yang sudah ada);
- **wajib** untuk kelas/guru yang sedang dalam pembinaan.

Target **5–8 sesi/hari per validator**, diatur sebagai kuota.

### Siklus hidup

`antri` → `sah` / `tidak_sesuai` / `tidak_dapat_diperiksa`; bila jendela lewat →
`kedaluwarsa`.

**Pemicu:** scheduler yang sudah jalan tiap 5 menit, 15 menit setelah jam selesai.

**Jendela & kegagalan yang aman.** Keputusan paling lambat H+1 dan tidak melewati
tanggal 25 (batas periode penggajian). Lewat jendela → `kedaluwarsa` dan
**diperlakukan sebagai sah** untuk guru. Guru tidak boleh dirugikan karena
validatornya lupa; yang tercatat gagal adalah validatornya.

### Dampak keputusan

`tidak_sesuai` **tidak membuat jalur hukuman baru**, tetapi menumpang istilah yang
sudah ada: sesi diubah menjadi **tidak terlaksana** lewat `SesiMengajarService` —
JP hangus, kinerja turun, tanpa potongan gaji. Satu definisi "sesi tidak jalan"
untuk seluruh sistem.

---

## Model data usulan

**`validator`** — `tenaga_pendidik_id` (atau `user_id` untuk staf non-guru),
`jenis` JSON (`sesi_mengajar`, nanti `izin_guru`, `tugas_tambahan`), `cakupan`
(`semua`/`pilih`) + pivot kelas dan/atau guru, `kuota_harian`, `boleh_sidak`,
`is_aktif`, `ditunjuk_oleh`, `catatan`. **Inilah "menu validator" tempat
kredensial disetel.**

**`validasi`** — `jenis` + `subjek_type`/`subjek_id` (polimorfik), `status`,
`sumber_antrian` (anomali/sampel/aduan/wajib/manual), `skor_risiko`, `sinyal`
JSON (alasan masuk antrian — penting supaya validator tahu apa yang dicari),
`validator_id`, `metode` (sidak/periksa bukti/konfirmasi santri), `temuan`,
`catatan`, `bukti_foto`, `diputuskan_pada`, `jendela_sampai`.

**`validasi_riwayat`** — setiap perubahan keputusan dicatat lewat observer, pola
`RiwayatRekapKinerja`.

Satu sesi satu baris validasi (unique). **Jenis validasi didaftarkan lewat
registry kecil**: tiap jenis menyebutkan cara mengambil subjek, bukti apa yang
ditampilkan, siapa yang dilarang memvalidasinya, dan dampak penolakannya →
menambah `izin_guru` nanti = menambah satu kelas kecil, bukan merombak UI.

## Service

- **`ValidatorService`** — kembaran `PengawasService`: `untuk()`,
  `boleh($id,$jenis)`, `bolehValidasi($validator,$subjek)`, `ringkasan()`.
- **`ValidasiService`** — satu-satunya penulis tabel validasi: `antrikan()`,
  `putuskan()`, `statusSesi()`, `rekap()`.
- **`RisikoSesiService`** — skor + daftar sinyal, dipisah agar aturan risiko bisa
  dikalibrasi tanpa menyentuh alur keputusan.

**Aturan integritas di gerbang, bukan controller:** validator tidak boleh
memvalidasi sesinya sendiri, tidak boleh memvalidasi sesi yang ia gantikan sebagai
inval, dan tidak boleh di luar cakupannya. Kinerja & payroll **membaca lewat
service**.

## UI

**Superadmin — menu "Validasi"** (grup sendiri karena akan menampung jenis lain):
1. **Antrian** — lencana risiko, filter, aksi massal untuk sampel bersih.
2. **Detail** — jadwal vs aktual, materi/setoran, roster santri, foto, **waktu
   pencatatan**, sinyal yang memasukkannya ke antrian, riwayat guru 3 bulan.
3. **Validator** — penunjukan & kredensial + beban & ketepatan waktu.
4. **Laporan** — akurasi pelaporan per guru, per kelas, dan **kinerja validator**.

**PWA validator** — tiga bagian: **Sedang berjalan sekarang** (sidak — jantungnya),
**Perlu diperiksa** (antrian + alasannya), **Riwayat saya**. Keputusan dua ketukan.
Notifikasi **dikumpulkan per blok jam** (2× sehari), bukan per sesi.

**PWA guru** — melihat status validasi sesinya + **hak sanggah**. Tanpa ini fitur
jadi sumber konflik, bukan alat kebenaran.

---

## Tahapan

1. **Fase 1 — mencatat saja (1 bulan).** Mesin risiko + antrian + menu validator +
   keputusan, **tanpa dampak** ke kinerja & gaji. Tujuannya kalibrasi.
2. **Fase 2 — berdampak ke kinerja** lewat `SesiMengajarService`, dengan hak sanggah.
3. **Fase 3 — gerbang penggajian** + kinerja validator.
4. **Fase 4 — jenis lain** (izin, tugas tambahan) masuk antrian yang sama.

## Risiko

1. **Beban** — bila semua sesi wajib divalidasi, fitur mati. Harus risiko + sampel.
2. **Validasi teater** — memeriksa data buatan guru sendiri = 99% lolos.
3. **Konflik sejawat** — mitigasi: alasan wajib, audit penuh, hak sanggah,
   validator bukan rekan satu mapel/jenjang.
4. **Terlalu cepat menyentuh gaji** → sengketa. Fase 1 tanpa dampak.
5. **Durasi tidak terukur** — `jam_selesai_aktual` hanya terisi **6,9%**: guru
   praktis tidak pernah menutup sesi, jadi sistem tidak tahu guru mengajar penuh
   atau pulang lebih awal. Bila validasi ingin menilai itu, menutup sesi harus
   diwajibkan lebih dulu. Dan kelas tahfidz/tahsin **0% berfoto** (509 sesi).

   **Diukur ulang 9 Okt 2026: justru turun menjadi 4,7%** (72 dari 1.533 sesi
   30 hari terakhir). Jadi bukan kebiasaan yang sedang membaik — menutup sesi
   memang tidak dijalankan. Setiap rancangan validasi yang bergantung pada durasi
   aktual harus dianggap **tidak punya data** sampai penutupan sesi diwajibkan.

## Keputusan yang masih dibutuhkan

1. Cakupan: semua sesi, atau risiko + sampel? (usul: yang kedua, kuota 5–8/hari)
2. Cara kerja validator: sidak, periksa bukti, atau keduanya? Berapa orang dan
   pembagian cakupannya?
3. Dampak `tidak_sesuai`: hanya kinerja, atau ikut gaji? Mulai fase 1 tanpa dampak?
4. Jendela: sampai H+1? Lewat jendela dianggap sah (usul) atau menggantung?
5. Siapa validatornya: guru lewat PWA, atau staf khusus ber-akun admin?
6. Hak sanggah guru — ada? Pemutus akhirnya superadmin atau pimpinan?

## Langkah pertama yang disarankan

Sebelum membangun apa pun: **ukur sinyal anomalinya di data nyata** — berapa sesi
yang diabsen borongan di akhir hari, berapa yang jam mulainya jauh dari jadwal,
berapa guru tercatat di dua kelas bersamaan. Hasilnya menentukan apakah mesin
risiko punya bahan, atau yang dibutuhkan murni sidak acak.

---

# HASIL PENGUKURAN — 9 Oktober 2026

Dijalankan di produksi, hanya baca. Jendela **9 Sep – 9 Okt 2026**: 1.533 baris
`absensi_mengajar`, populasi uji **1.221 sesi** yang diakui mengajar
(`terlaksana`+`pengganti`, tanpa libur pembelajaran & tanpa sesi ujian) di
**27 hari aktif** → **45,2 sesi/hari**.

Catatan metode: selisih waktu dihitung di PHP, bukan SQL — MySQL kontainer
berjalan UTC sementara aplikasi Asia/Jakarta. Dan **4,6% baris dibuat sistem
lebih dulu** (inval/kegiatan) lalu diisi guru, sehingga `created_at`-nya bukan
saat guru menekan absen; untuk baris itu dipakai `updated_at`.

## Sinyal yang TIDAK dikuasai guru — praktis kosong

| Sinyal | Hasil | Tafsir |
|---|---|---|
| Pencatatan borongan (≥2 sesi berjadwal beda dalam ≤120 detik) | **6 sesi (0,5%)**, 3 gugus | tidak ada bahan |
| Melewati batas pencatatan (`jam_selesai` + 15 mnt) | **0 sesi (0,0%)** | **mustahil secara struktural** — `KebijakanMengajar::batasAbsenSesi()` sudah menutup pintunya. 97,6% dicatat sebelum sesi selesai, 2,4% dalam masa tenggang |
| Dicatat jauh sebelum jadwal (>30 mnt di muka) | **1 sesi (0,1%)** | tidak ada bahan |
| Dicatat pada tanggal berbeda | **0 sesi** | tidak ada bahan |
| Satu guru dua kelas pada jam bertabrakan | 42 sesi / 21 pasangan (3,4%) | **bukan kecurangan**: 100% `tahsin × tahsin`, 13 pasangan jadwal yang sama berulang 1,6×, didominasi satu guru → **cacat/kesengajaan JADWAL**, bukan temuan validasi |

**Kekeliruan yang hampir masuk laporan:** pengukuran pertama memakai jarak dari
`jam_mulai` dan memunculkan "3,5% telat 1–3 jam". Itu palsu — sesi 2–3 JP memang
panjang. Diukur terhadap batas yang benar (`jam_selesai` + 15 menit), angkanya
**nol**.

## Sinyal yang ada bahannya — tetapi semuanya buatan guru sendiri

| Sinyal | Hasil | Catatan |
|---|---|---|
| Tanpa roster santri | **75 sesi (6,1%)** | semuanya kelas **reguler** (11,5% dari 653 sesi reguler); tahfidz & tahsin **0%**. **Satu guru menyumbang 32 dari 75 (43%)** |
| Pengganti (inval) tanpa materi | **21 dari 73 (28,8%)** | |
| Tanpa materi | 618 (50,6%) | **568 di antaranya struktural** (tahfidz 334 + tahsin 234 tidak memakai materi). Sisa nyata: **50 sesi reguler** |
| Tanpa foto | 569 (46,6%) | reguler **99,8% berfoto**; tahfidz & tahsin **0%** — memang tidak diminta |

## Kesimpulan: mesin risiko tidak punya bahan

Gabungan seluruh sinyal: **168 sesi (13,8%)**, 6,2/hari aktif; 88,7% hanya
memiliki satu sinyal. Dengan sampel acak 12% sesi bersih → antrian ±**10,9
sesi/hari**, cukup untuk **2 validator** berkuota 6/hari. Jadi **bebannya
terjangkau** — tetapi isi antriannya salah jenis.

Tiga alasan:

1. **Sinyal independen habis.** Yang benar-benar di luar kuasa guru (borongan,
   telat catat, catat di muka) berjumlah **7 sesi dalam 30 hari**. Penyebabnya
   justru baik: jendela absen per sesi sudah ketat, jadi tidak ada ruang
   menyeleweng dari waktu pencatatan.
2. **Sisanya validasi teater.** `tanpa_roster`, `pengganti_tanpa_catatan`,
   `tanpa_materi` semuanya memeriksa **kelengkapan catatan guru**, bukan
   kebenaran mengajarnya — persis risiko yang sudah diprediksi dokumen ini.
3. **Sinyal tidak menunjuk orang.** 29 dari 34 guru (85,3%) punya ≥1 sinyal; 5
   teratas hanya 55,4%. Jadi antrian berbasis risiko tidak menyaring siapa pun —
   ia hanya mengantre hampir semua orang.

**Untuk kelas Quran, risiko bahkan buta total:** tahfidz + tahsin = 568 sesi
(47% populasi) dengan 0% foto, tanpa materi, dan 0% sesi tanpa roster. Tidak ada
satu pun sinyal yang bisa membedakan halaqoh yang berjalan dari yang tidak.

## Usul arah setelah pengukuran

1. **Jangan bangun mesin risiko.** Tidak ada bahannya. Kalau validasi tetap
   diinginkan, intinya **sidak berbasis sampel acak** — sederhana, dan justru itu
   satu-satunya bukti independen menurut temuan ini.
2. **Tiga temuan ditindaklanjuti tanpa fitur baru — hasilnya di bawah.**
3. **Bila durasi mengajar ingin dinilai, wajibkan menutup sesi lebih dulu.**
   `jam_selesai_aktual` hanya 5,9% pada jendela ini.

---

# TINDAK LANJUT TIGA TEMUAN — 9 Oktober 2026

Ditelusuri sampai akarnya, lalu diperbaiki. Teruji di produksi **25/25**.

## Temuan A — sesi mapel tanpa roster: akarnya di KINERJA, bukan satu guru

Pelakunya memang terpusat (satu guru: **32 dari 41 sesinya**, 78%), tetapi
sebabnya struktural. Untuk kelas sekolah/pesantren, satu-satunya bukti yang
diperiksa `buktiLaporanSesi()` adalah kolom **`materi`**; roster santri **tidak
diperiksa sama sekali**. Akibatnya timpang:

| Keadaan sesi mapel | Jumlah | Kata kinerja |
|---|---|---|
| materi ADA, roster KOSONG | 67 (10,3%) | **lolos 100%** |
| roster ADA, materi kosong | 42 (6,4%) | dihitung **belum dilaporkan** |
| keduanya ada | 536 (82,1%) | lolos |
| keduanya kosong | 8 (1,2%) | belum dilaporkan |

Jadi absensi santri — inti pembelajaran, dan sumber Laporan Kehadiran Santri —
tidak berbobot apa pun, sementara catatan materi yang sifatnya pelengkap
menentukan skor. Guru di atas berskor laporan sempurna dengan 0 roster.

**Keputusan pimpinan 9 Okt 2026: absensi santri menjadi bukti WAJIB, tetapi
TIDAK berlaku mundur.** `KinerjaCalculationService::WAJIB_ROSTER_MAPEL_SEJAK =
'2026-11-01'`. Sesi sebelum tanggal itu tetap dinilai dengan aturan lama
(materi saja), sehingga skor September–Oktober tidak berubah dan guru bisa
diberi tahu lebih dahulu — menjadikannya retroaktif akan menurunkan skor **23
guru** sekaligus (19 di antaranya hanya 1–4 sesi).

Dua penghitung sengaja dipisah: `belum_roster_mapel` (semua sesi tanpa roster,
untuk dipantau sejak sekarang) dan `gagal_roster_mapel` (yang benar-benar
mengurangi skor, hanya sesudah tanggal berlaku) — tanpa pemisahan itu,
penjelasan ke guru akan menuduh sesi lama yang saat itu belum diwajibkan.
Penjelasan di aplikasi guru kini berbunyi "N sesi pelajaran belum ada absensi
santrinya" beserta bulan mulai berlakunya.

**Sisa pekerjaan non-teknis:** beri tahu guru sebelum 1 November.

## Temuan B — "21 inval tanpa catatan": PALSU

Dibongkar per tipe: reguler **4 dari 56 (7%)** — sama dengan garis dasar sesi
reguler biasa (7,7%), jadi bukan gejala khusus inval. Sisanya **17 sesi Qur'an
yang memang tidak pernah memakai kolom `materi`**, dan ketika diperiksa dengan
bukti yang benar: **17 punya roster santri, 9 punya setoran tahfidz, 0 tanpa
bukti apa pun.**

Kekeliruan ada pada definisi sinyal saya, bukan pada datanya. **Tidak ada yang
perlu diperbaiki di produk.**

## Temuan C — dua halaqoh satu jam: SAH, dan penjaganya salah tempat

Dikonfirmasi user, dan datanya cocok: satu pengampu memegang **Tahsin Level 5
(1 santri)** bersama **Persiapan Tahfidz level 6 (3 santri)** pada jam yang
sama, 8 slot per pekan — kelompok kecil berbeda level dalam satu majelis. Hanya
terjadi pada tahsin; tahfidz dan reguler **0 slot ganda**.

Yang ditemukan saat memeriksa kodenya justru dua hal lain:

1. **Jalur pembuatannya memang tidak memeriksa tumpang tindih** (generator
   Tahfidz/Tahsin) — dan itu benar, jangan ditambahi penjaga. Hanya ditambahkan
   **laporan jumlah slot yang berbarengan** pada pesan hasil generate, supaya
   halaqoh ganda karena salah pilih kelas tetap terlihat.
2. **`JadwalMengajarController::update()` sama sekali tidak memeriksa bentrok**,
   padahal `store()` memeriksa. Penjaganya bisa dilewati hanya dengan membuat
   slot bersih lalu menggeser jamnya lewat edit. **Lubang nyata, sekarang
   ditutup**: satu `alasanBentrok()` dipakai kedua jalur, mengecualikan dirinya
   sendiri saat update, dan pesannya kini menyebut kelas serta jam penghalangnya
   (sebelumnya hanya "Jadwal bentrok!" tanpa petunjuk). Sesi berurutan
   (10:20–11:30 lalu 11:30–12:40) tetap diterima.
