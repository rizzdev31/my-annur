# PRD — Validasi Pembelajaran (Validator)

**Status:** DITUNDA — hasil brainstorm 3 Oktober 2026, belum dikerjakan
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
