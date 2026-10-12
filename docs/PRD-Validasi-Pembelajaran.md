# PRD — Validasi Pembelajaran (Validator)

**Status:** BELUM DIKERJAKAN — menunggu **tiga keputusan terakhir** (lihat
bagian *Arah 12 Okt*). Eksekusi ditahan user.

- 3 Okt 2026 — brainstorm awal.
- 9 Okt 2026 — sinyal anomali **diukur di produksi**; hasilnya mengubah arah:
  mesin risiko tidak punya bahan.
- 11 Okt 2026 — **Tahapan direvisi** (Langkah 0–6), validator diputuskan berupa
  **guru lewat PWA** dengan cakupan **per kategori pembelajaran**, dan
  ketergantungannya pada absensi santri diperiksa ke data.
- 12 Okt 2026 — arah berubah lagi: **validasi harian menyeluruh, bukan sampel**.
  Beban serentak diukur; dua koreksi wajib (jumlah validator & bentuk ACC).
  Bagian *Sampel* menjadi tidak berlaku.

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

### Antrian berbasis risiko — DIBATALKAN 9 Okt 2026

> **Bagian ini tidak berlaku.** Ditulis saat masih diduga ada sinyal anomali yang
> bisa menyaring. Pengukuran membuktikan tidak ada (7 sesi dalam 30 hari), jadi
> **`anomali` dihapus sebagai sumber antrian** dan `RisikoSesiService` tidak
> dibuat. Yang berlaku: bagian **Sampel** dan **Tahapan — Revisi 11 Okt 2026**
> di bawah. Disimpan di sini hanya sebagai jejak alasan.

44 sesi/hari mustahil divalidasi manual. Yang masuk antrian hanya:

- ~~**sinyal anomali** (absen borongan, telat jauh dari jadwal, tanpa roster, jam
  bertabrakan, pengganti tanpa catatan)~~ → dibatalkan;
- **sampel acak** dari sesi yang bersih — ini yang membuat "aman karena
  tidak dicurigai" tidak bisa diandalkan (kini menjadi sumber UTAMA);
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

**`validator`** — `tenaga_pendidik_id` (validator adalah GURU, lewat PWA —
keputusan 11 Okt 2026), `jenis` JSON (`sesi_mengajar`, nanti `izin_guru`,
`tugas_tambahan`), **`kategori` JSON** berisi `sekolah` / `pesantren` /
`quran`, `kuota_harian`, `is_aktif`, `ditunjuk_oleh`, `catatan`. **Inilah "menu
validator" tempat kredensial disetel.**

Cakupan ditentukan **per kategori pembelajaran**, bukan per kelas atau per guru
(keputusan 11 Okt 2026). Tiga kategori itu memetakan langsung ke data yang
sudah ada — `kelas.jenis` = `sekolah`, `pesantren`, dan `tahfidz`+`tahsin`
digabung menjadi satu kategori `quran` karena bentuk pembelajaran serta
bentuk buktinya sama (setoran/penilaian, tanpa materi & tanpa foto). Satu
validator boleh memegang lebih dari satu kategori.

**`validasi`** — `jenis` + `subjek_type`/`subjek_id` (polimorfik), `status`,
`sumber_antrian` (**sampel**/aduan/wajib/manual — `anomali` dibatalkan, lihat
hasil pengukuran), `validator_id`, `metode` (sidak/periksa bukti/konfirmasi
santri), `temuan`, `catatan`, `bukti_foto`, `diputuskan_pada`, `jendela_sampai`.
Kolom `skor_risiko` & `sinyal` **tidak dibuat** — tidak ada bahannya.

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

## Tahapan — REVISI 11 Oktober 2026

Tahapan lama menempatkan **mesin risiko** sebagai isi Fase 1. Itu **dibatalkan**
setelah pengukuran 9 Okt: sinyal yang tidak dikuasai guru hanya 7 sesi dalam 30
hari, jadi tidak ada yang bisa dikalibrasi. Intinya bergeser ke **sidak
berbasis sampel acak**, dan `RisikoSesiService` tidak dibuat.

### Langkah 0 — keputusan (lihat bagian Keputusan di bawah)

Sudah diputuskan 11 Okt: validator = **guru lewat PWA**, cakupan **per kategori
pembelajaran**. Yang masih terbuka: jumlah validator per kategori dan angka
kuota harian.

### Langkah 1 — fondasi kredensial (tanpa antrian, tanpa dampak)

Tabel `validator` + menu **Validasi → Validator** di superadmin (penunjukan,
kategori, kuota, aktif/nonaktif, jejak penunjuk) + `ValidatorService` sebagai
gerbang tunggal, meniru `PengawasService`. Modul RBAC `validasi` masuk
`config/modul.php`. Belum ada apa pun yang dinilai.

**Aturan integritas ditaruh di gerbang, bukan controller:** validator tidak
boleh memvalidasi sesinya sendiri, tidak boleh memvalidasi sesi yang ia
gantikan sebagai inval, dan tidak boleh di luar kategorinya.

### Langkah 2 — antrian & keputusan (masih tanpa dampak)

Tabel `validasi` + `validasi_riwayat` + `ValidasiService` (`antrikan`,
`putuskan`, `statusSesi`, `rekap`).

**Pengisi antrian (revisi 12 Okt):** **seluruh sesi hari itu** pada kategori
yang dipegang validator yang bertugas — bukan sampel. Ditambah dua sumber
pelengkap yang tetap berlaku:

1. **aduan** — menumpang menu Saran & Masukan yang sudah ada;
2. **wajib** — guru/kelas yang sedang dalam pembinaan, ditandai admin.

~~sampel acak harian ditimbang~~ → tidak dipakai; disimpan sebagai cadangan bila
beban menyeluruh ternyata tak tertanggung setelah Langkah 4.

Dijalankan scheduler yang sudah ada. Keputusan `sah` / `tidak_sesuai` /
`tidak_dapat_diperiksa`, lewat jendela → `kedaluwarsa` dan **diperlakukan sah**
bagi guru; yang tercatat gagal adalah validatornya.

Status validasi disimpan **terpisah** dari `absensi_mengajar.status` (lihat
Koreksi 2 pada *Arah 12 Okt*) — kecuali bila user memilih gerbang keras.

### Langkah 3 — PWA validator

Tiga bagian: **Sedang berjalan sekarang** (sidak — jantungnya), **Perlu
diperiksa**, **Riwayat saya**. Keputusan dua ketukan.

Bisa menumpang yang sudah ada, tidak perlu query baru:
`SesiMengajarService::jadwalTanggal($tanggal)` + `statusLive()` sudah memberi
"sesi apa yang berjalan sekarang beserta statusnya" — sumber yang sama dipakai
papan guru piket dan monitoring pimpinan, jadi angkanya tidak akan berbeda.
Notifikasi **dikumpulkan per blok jam** (2× sehari), bukan per sesi.

### Langkah 4 — jalan 1 bulan, lalu DIUKUR

Berapa yang benar-benar disidak, berapa kedaluwarsa, berapa persen
`tidak_sesuai`, dan apakah temuannya berbeda antar kategori. **Angka inilah
yang menentukan apakah Langkah 5 layak** — bukan asumsi. Bila ternyata
hampir semua `sah`, fitur ini cukup berhenti sebagai alat sidak dan tidak perlu
menyentuh kinerja sama sekali.

### Langkah 5 — berdampak ke kinerja (bekas Fase 2)

`tidak_sesuai` → sesi menjadi **tidak terlaksana** lewat `SesiMengajarService`
(JP hangus, kinerja turun, tanpa potongan gaji — satu definisi untuk seluruh
sistem). Disertai **hak sanggah** guru di PWA dan status validasi yang terlihat
oleh guru. **Bergantung pada:** kebijakan bukti absensi santri (lihat
Ketergantungan).

### Langkah 6 — bekas Fase 3 & 4

Kinerja validator sendiri, lalu jenis lain (izin, tugas tambahan) masuk antrian
yang sama lewat registry jenis.

---

## ARAH 12 OKTOBER 2026 — validasi harian menyeluruh, bukan sampel

**Keputusan user:** setiap hari ada validator bertugas, dibagi per kategori
pembelajaran, tugasnya **memastikan guru benar-benar mengajar**. Sesi **tidak
otomatis dihitung terlaksana** — menunggu validator meng-ACC.

Ini jalur yang semula ditandai Risiko #1 ("bila semua sesi wajib divalidasi,
fitur mati"), jadi kelayakannya diukur lebih dulu ke jadwal produksi. **Hasilnya:
tertanggung**, dengan dua koreksi wajib di bawah.

### Beban nyata (jadwal aktif, 12 Okt 2026)

| Kategori | Slot/pekan | **Puncak serentak** | Blok jam | Durasi sesi | ACC/hari |
|---|---|---|---|---|---|
| sekolah | 201 (35 guru, 9 kelas) | **9 kelas** | 7 blok: 07:30 · 08:40 · 10:20 · 11:30 · 20:00 | 69–75 mnt (malam 45) | 23,8 |
| quran | 160 (19 guru, 20 kelas) | **20 halaqoh** | 2 blok: 05:00 · 18:00 | 45 mnt | 19,8 |
| pesantren | 2 | 2 | 20:00 kamis | 45 mnt | 0,1 |

**Sekolah lapang:** 9 kelas dalam 70 menit = ±7,8 menit per kelas, cukup untuk
masuk dan mengamati sebentak. **Quran yang ketat:** 20 halaqoh dalam 45 menit.

### Koreksi 1 — quran WAJIB dua validator (putra & putri)

20 halaqoh jam 05:00 terbagi **12 putra + 8 putri, nol campur**; pengampunya pun
12 laki-laki dan 8 perempuan:

- putra: Tahfidz Putra 1–6, Tahsin Putra 1–5, Persiapan Tahfidz 1–2
- putri: Tahfidz Putri 1–5, Tahsin Putri 1, Tahsin Level 5, Persiapan Tahfidz Ustadzah

Satu orang tidak dapat memasuki area putra dan putri sekaligus — bukan soal
jarak, tetapi kepatutan. Dipisah, angkanya masuk: **12 ÷ 45 mnt = 3,8 menit**
per halaqoh (putra), **8 ÷ 45 = 5,6 menit** (putri). **Syarat yang belum
dikonfirmasi: halaqoh terkumpul di satu area.** Bila tersebar beberapa gedung,
hitungan ini batal.

Jadi kebutuhan harian **minimal 4 orang**, bukan 3 — jumlah validator mengikuti
**beban serentak**, bukan jumlah kategori:

| Giliran | Jam | Cakupan |
|---|---|---|
| 1 | 05:00 & 18:00 | quran **putra** (12 halaqoh) |
| 2 | 05:00 & 18:00 | quran **putri** (8 halaqoh) |
| 3 | 07:30–12:40 | sekolah pagi (4–5 blok × 9 kelas) |
| 4 | 20:00 | sekolah malam (9 kelas) + pesantren |

Bisa 3 orang bila giliran 3+4 digabung — konsekuensinya jam tugas 07:30–20:45.
Tenaganya tersedia: jam 05:00 **tidak ada guru yang mengajar** (51 guru luang),
jam 18:00 ada 32 luang dari 51.

### Koreksi 2 — ACC jangan dijadikan gerbang keras

Versi harfiah ("tanpa ACC = tidak terlaksana") memindahkan risiko ke orang yang
salah. Empat akibatnya:

1. **Satu orang lupa, puluhan guru kena.** Validator quran putri tidak datang
   sehari → 8 pengampu kehilangan JP dan kinerjanya turun padahal mengajar.
   Dengan 12 halaqoh dalam 45 menit, ada satu-dua terlewat itu **normal**.
2. **Jam 05:00 setiap hari tanpa libur.** Kesiangan sekali saja → seluruh
   tahfidz pagi gagal.
3. **`terlaksana` dibaca banyak tempat** — vakasi guru pengganti, potongan per
   sesi tidak mengajar, kinerja, laporan kehadiran santri, papan piket.
   Menggantungkannya pada pihak ketiga = **titik gagal baru di penggajian**.
4. **Bertabrakan dengan yang sudah jalan.** Scheduler tiap 5 menit sudah
   menandai `tidak_terlaksana` lewat batas jam (`SesiMengajarService`). Bila ACC
   jadi syarat, harus diputuskan mana yang menang.

**Usul: status validasi TERPISAH, tidak menggantikan `terlaksana`.**

| Kejadian | Status sesi | Status validasi |
|---|---|---|
| Guru absen sesi | `terlaksana` (seperti sekarang) | **`menunggu`** |
| Validator ACC | `terlaksana` | `sah` |
| Validator **menolak** | diubah → `tidak_terlaksana` (JP hangus) | `tidak_sesuai` |
| Jendela tutup tanpa ACC | `terlaksana` | `kedaluwarsa` — **dianggap sah bagi guru**, yang tercatat gagal validatornya |

Tuntutan user tetap terpenuhi: guru melihat sesinya **"menunggu validasi"**
(jadi tidak terasa otomatis beres), validator dapat membatalkan klaim mengajar,
dan akuntabilitasnya dua arah — kinerja validator diukur dari berapa yang ia
tuntaskan. Yang dihindari: guru dihukum karena validatornya lupa.

Bila versi harfiah tetap dipilih, **syarat minimum**: validator pengganti wajib
ada setiap hari (inval untuk validator), jendela ACC diperpanjang s.d. H+1 agar
yang terlewat bisa disusul, dan sanksinya **hanya kinerja — jangan menyentuh
gaji**.

### Tiga keputusan yang ditunggu sebelum Langkah 1

1. **4 validator/hari, atau tetap 3** (giliran sekolah pagi + malam digabung)?
2. **Halaqoh putra 12 kelompok terkumpul di satu tempat, atau tersebar?**
3. ACC sebagai **status terpisah** (usul di atas) atau **gerbang keras** (versi
   user)?

---

## Sampel — mengapa tidak semua sesi diperiksa  *(TIDAK BERLAKU sejak 12 Okt)*

> **Digantikan oleh *Arah 12 Okt: validasi harian menyeluruh*.** User memilih
> setiap sesi di-ACC validator harian, bukan sebagian yang diambil acak.
> Disimpan karena dua hal di bawah tetap relevan: alasan *mengapa* acak itu
> syarat (bila kelak beban tak tertanggung dan harus kembali ke sampel), dan
> **ketimpangan bukti antar kategori** yang jadi dasar penimbangan.

Ada **45,2 sesi per hari aktif** (1.221 sesi dalam 30 hari). Memeriksa semuanya
berarti ±8 orang bekerja penuh waktu hanya untuk memvalidasi — fitur akan mati
di bulan pertama. Karena itu yang diperiksa **sebagian, dipilih acak**, lalu
kesimpulannya dipakai untuk menilai keadaan keseluruhan — cara yang sama dipakai
pemeriksaan mutu di pabrik: tidak semua barang dibuka, tetapi contoh yang
diambil acak sudah cukup untuk tahu ada masalah atau tidak.

**Acak itu syarat, bukan hiasan.** Kalau validator memilih sendiri siapa yang
disidak, guru akan belajar polanya dan menyesuaikan diri — "aman karena tidak
dicurigai" kembali berlaku. Dengan acak, setiap sesi punya peluang didatangi,
sehingga efek jeranya menyeluruh walau yang benar-benar diperiksa sedikit.

**Ditimbang** berarti porsinya tidak dibagi rata, melainkan lebih besar pada
kategori yang buktinya paling tipis. Dasarnya pengukuran 9 Okt:

| Kategori | Sesi/30 hari | Bukti selain catatan guru | Porsi sampel |
|---|---|---|---|
| sekolah + pesantren | 653 | foto 99,8% + materi 92,3% | lebih kecil |
| quran (tahfidz+tahsin) | 568 | **tidak ada** — 0% foto, tanpa materi | **lebih besar** |

Di kelas sekolah masih ada foto dan materi sebagai bukti sekunder; di halaqoh
tidak ada apa pun, sehingga satu-satunya cara mengetahui halaqoh berjalan atau
tidak adalah mendatanginya.

~~Dengan kuota 5–8 sesi/hari per validator dan satu validator per kategori,
cakupannya ±25–35% sesi per bulan.~~ → Angka kuota tidak lagi dipakai; lihat
*Arah 12 Okt*, yang menargetkan **100% sesi hari itu**.

## Risiko

1. **Beban** — bila semua sesi wajib divalidasi, fitur mati bila orangnya tidak
   cukup. **User memilih jalur ini (12 Okt)**, dan setelah diukur ternyata
   tertanggung — dengan syarat jumlah validator menyesuaikan beban serentak,
   bukan jumlah kategori. Lihat *Arah 12 Okt*.
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

## Ketergantungan: absensi santri & kelengkapan sebelum penggajian

Dibahas 11 Okt 2026, lalu **diperiksa ke data produksi** — karena arah fitur ini
berubah bila absensi santri terisi otomatis.

**Yang diperiksa: apakah sistem pernah menandai "hadir semua" tanpa guru?**
Hasilnya **tidak, sama sekali belum**:

| Pemeriksaan | Hasil |
|---|---|
| Baris `absensi_santri` bersumber `guru` | **21.529 (100%)** |
| Bersumber `kegiatan` (auto-hadir) | **0** |
| Baris `libur_pembelajaran` | **0** — fiturnya ada tapi belum pernah dipakai |
| Sesi ber-`libur_pembelajaran_id` | **0 dari 1.863** |

Sebaran statusnya pun wajar, bukan cap massal: hadir 86,8% · izin 4,1% · telat
3,8% · sakit 3,2% · **alpha 2,1%**. Jadi jalur auto-hadir **hanya** ada pada
Libur Pembelajaran (kegiatan), dan itu belum pernah dijalankan.

**Konsekuensinya untuk rancangan.** Bila kelak sesi biasa dibuat
otomatis-hadir-semua supaya jurnal tidak kosong, tiga hal rusak sekaligus:

1. **Validator kehilangan objeknya.** Yang divalidasi menjadi data buatan
   sistem, bukan buatan guru — validasi teater satu tingkat lebih jauh.
2. **Kewajiban roster 1 Nov jadi hampa.** Roster tidak akan pernah kosong, jadi
   `gagal_roster_mapel` selalu 0 dan kebijakannya tidak pernah menagih siapa pun.
3. **Laporan Kehadiran Santri jadi fiksi.** Wali membaca "hadir" untuk
   pertemuan yang mungkin tidak terjadi — padahal angka alpha 2,1% hari ini
   membuktikan guru memang mencatat apa adanya.

**Usul:** jangan isi otomatis pada sesi biasa. Jurnal yang kosong karena sesi
memang tidak berjalan **sudah punya istilah sendiri** — `tidak_terlaksana`,
JP hangus, kinerja turun, tanpa potongan gaji. Itu jujur dan sudah berjalan.

**Fitur kelengkapan sebelum penggajian (rencana user, sejalan).** Bahan nyata
30 hari terakhir:

| Status sesi | Sesi | JP jadwal | JP tercatat | Selisih |
|---|---|---|---|---|
| terlaksana | 1.062 | 2.120 | 2.120 | 0 |
| pengganti | 74 | 147 | 147 | 0 |
| **tidak_terlaksana** | **252** | **502** | **0** | **−502** |
| **izin** | **53** | **105** | **0** | **−105** |

Jadi **607 JP per bulan** tercatat hilang, dan 252 sesi di antaranya belum tentu
sudah dipertanggungjawabkan gurunya. Bentuk fiturnya: **daftar periksa
pra-penggajian per guru** — sesi yang JP-nya hangus wajib diberi keterangan
(diinval, diganti hari lain, atau diakui tidak terlaksana) sebelum periode
dikunci. Ini **pekerjaan terpisah dari validator**, tetapi berpasangan:
validator memeriksa yang DIAKUI terlaksana, daftar periksa menagih yang
TIDAK terlaksana. Keduanya memakai `SesiMengajarService` yang sama.

Catatan data yang perlu ditelusuri saat fitur itu dikerjakan: **287 sesi
`tidak_terlaksana` justru memiliki 1.259 baris roster** — guru sempat mengisi
absensi lalu sesinya ditandai tidak terlaksana (atau sebaliknya). Pola ini sudah
pernah dikonfirmasi asli, jadi daftar periksa tidak boleh menganggapnya
anomali.

## Keputusan — status 11 Oktober 2026

| # | Pertanyaan | Keputusan |
|---|---|---|
| 1 | Cakupan: semua sesi, atau sampel? | **SEMUA sesi, harian** (keputusan 12 Okt; sampel acak dibatalkan, anomali sudah dibatalkan 9 Okt) |
| 2 | Siapa validatornya | **Guru, lewat PWA.** Ditunjuk per orang, dibedakan **per kategori pembelajaran**: sekolah · pesantren · quran (tahfidz+tahsin) — dengan **quran dipecah putra/putri**, lihat Koreksi 1 |
| 3 | Cara kerja | **Sidak** sebagai inti; "periksa bukti" hanya untuk aduan — memeriksa catatan buatan guru sendiri = 99% lolos |
| 4 | Jendela | Sidak = **saat sesi berjalan**; pemeriksaan aduan s.d. H+1. Lewat jendela → `kedaluwarsa`, **dianggap sah** bagi guru |
| 5 | Dampak `tidak_sesuai` | **Tanpa dampak** selama Langkah 1–4. Bila berdampak: kinerja saja (JP hangus), **bukan potongan gaji** |
| 6 | Hak sanggah guru | **Ada**, pemutus akhir pimpinan |

**Masih terbuka (3 butir, lihat akhir bagian *Arah 12 Okt*):** jumlah validator
harian (4 atau 3), apakah 12 halaqoh putra terkumpul satu tempat, dan bentuk ACC
(status terpisah atau gerbang keras). Angka kuota harian **tidak lagi relevan** —
targetnya seluruh sesi hari itu.

## Langkah pertama yang disarankan — SUDAH DIKERJAKAN 9 Okt 2026

Sebelum membangun apa pun: **ukur sinyal anomalinya di data nyata** — berapa sesi
yang diabsen borongan di akhir hari, berapa yang jam mulainya jauh dari jadwal,
berapa guru tercatat di dua kelas bersamaan. Hasilnya menentukan apakah mesin
risiko punya bahan, atau yang dibutuhkan murni sidak acak.

**Sudah dijalankan — hasilnya: murni sidak acak.** Rinciannya di bagian
**Hasil Pengukuran** di bawah.

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
