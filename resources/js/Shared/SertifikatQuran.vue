<script setup>
/**
 * SERTIFIKAT TASMI' / TASNIF — DUA HALAMAN.
 *
 * Satu komponen dipakai tiga bundel (PWA guru, portal santri/wali, admin
 * Inertia) lewat alias `@`. Sebelumnya tata letaknya disalin di tiga tempat,
 * sehingga perubahan rancangan harus dikerjakan tiga kali dan mudah melenceng.
 *
 * Halaman 1 (DEPAN) — pengakuan: identitas lembaga, nama santri, pernyataan
 * lulus, predikat, dan tanda tangan. Inilah yang dipajang/diserahkan.
 * Halaman 2 (BELAKANG) — nilai: rincian rubrik, nilai akhir, ambang lulus,
 * catatan penguji, dan masukan pimpinan.
 *
 * Alasan dipisah: halaman depan dulu memuat tabel rubrik sehingga nama santri
 * harus mengecil dan angka penilaian ikut terpampang saat sertifikat dipajang.
 * Nomor sertifikat dicetak ulang di halaman nilai supaya keduanya tetap
 * terhubung bila lembarnya terlepas.
 */
defineProps({
    /** Payload dari endpoint sertifikat (tasmi' atau tasnif). */
    data: { type: Object, required: true },
    /** 'tasmi' | 'tasnif' */
    jenis: { type: String, default: 'tasmi' },
})
</script>

<template>
    <div class="cert-wrap">
        <!-- ══════════ HALAMAN 1 — DEPAN (nama & pengakuan) ══════════ -->
        <div class="cert cert-depan">
            <div class="cert-border">
                <span class="corner tl"></span><span class="corner tr"></span>
                <span class="corner bl"></span><span class="corner br"></span>

                <div class="cert-inner">
                    <p class="bismillah">﷽</p>
                    <h2 class="lembaga">{{ data.lembaga }}</h2>
                    <p class="program">Program {{ data.program }}</p>
                    <p class="alamat">{{ data.alamat }}</p>

                    <div class="rule"><span></span><i>❁</i><span></span></div>

                    <h1 class="judul">SERTIFIKAT</h1>
                    <p class="sub">
                        {{ jenis === 'tasmi' ? "Kelulusan Tasmi' Al-Qur'an" : 'Kelulusan Ujian Kenaikan Tingkat (Tasnif)' }}
                    </p>
                    <p class="nomor">Nomor: {{ data.nomor }}</p>

                    <p class="diberikan">Dengan bangga diberikan kepada</p>
                    <p class="nama">{{ data.santri.nama }}</p>
                    <p v-if="data.santri.nip" class="nip">NIS. {{ data.santri.nip }}</p>

                    <p class="narasi">
                        Telah dinyatakan <b>LULUS</b>
                        <template v-if="jenis === 'tasmi'">
                            dalam Tasmi' (uji hafalan) <b>Juz {{ data.juz }}</b> Al-Qur'anul Karim
                        </template>
                        <template v-else>
                            dalam Ujian Kenaikan Tingkat <b>{{ data.level_label }}</b> Tahsinul Qur'an
                        </template>
                        dengan predikat <b class="predikat">{{ data.predikat }}</b>.
                    </p>

                    <p class="rujuk-nilai">Rincian penilaian tercantum pada halaman berikut.</p>

                    <div class="ttd">
                        <div class="ttd-col">
                            <p class="ttd-role">Pengampu</p>
                            <div class="ttd-space"></div>
                            <p class="ttd-nama">{{ data.pengampu }}</p>
                        </div>
                        <div class="ttd-seal">
                            <div class="seal">
                                <span class="seal-in">لُلُوْس<br><small>{{ jenis === 'tasmi' ? "TASMI'" : 'TASNIF' }}</small></span>
                            </div>
                            <p class="ttd-tanggal">Sidoarjo, {{ data.tanggal }}</p>
                        </div>
                        <div class="ttd-col">
                            <p class="ttd-role">Penguji</p>
                            <div class="ttd-space"></div>
                            <p class="ttd-nama">{{ data.penguji }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════ HALAMAN 2 — BELAKANG (nilai) ══════════ -->
        <div class="cert cert-belakang">
            <div class="cert-border">
                <span class="corner tl"></span><span class="corner tr"></span>
                <span class="corner bl"></span><span class="corner br"></span>

                <div class="cert-inner nilai-inner">
                    <!-- Pengait ke halaman depan bila lembarnya terlepas -->
                    <div class="pengait">
                        <div>
                            <p class="pengait-nama">{{ data.santri.nama }}</p>
                            <p class="pengait-sub">
                                {{ jenis === 'tasmi' ? `Juz ${data.juz}` : data.level_label }}
                                <span v-if="data.santri.nip"> · NIS. {{ data.santri.nip }}</span>
                            </p>
                        </div>
                        <p class="pengait-nomor">{{ data.nomor }}</p>
                    </div>

                    <h1 class="judul-nilai">DAFTAR NILAI</h1>
                    <p class="sub-nilai">
                        {{ jenis === 'tasmi' ? "Tasmi' Al-Qur'an" : 'Ujian Kenaikan Tingkat Tahsin' }}
                    </p>

                    <div class="rule"><span></span><i>❁</i><span></span></div>

                    <!-- Tabel rubrik: tempatnya lapang di halaman sendiri, jadi
                         bisa menampilkan nama komponen penuh tanpa disingkat. -->
                    <table v-if="data.rubrik_ada !== false && data.rubrik?.length" class="tabel-nilai">
                        <thead>
                            <tr><th class="kiri">Komponen Penilaian</th><th>Nilai</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in data.rubrik" :key="r.label">
                                <td class="kiri">{{ r.label }}</td>
                                <td class="angka">{{ r.nilai ?? '—' }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr><td class="kiri">Nilai Akhir (rata-rata)</td><td class="angka">{{ data.nilai }}</td></tr>
                        </tfoot>
                    </table>
                    <p v-else class="rubrik-none">Nilai akhir: <b>{{ data.nilai }}</b></p>

                    <div class="predikat-box">
                        <div>
                            <p class="pk-label">Predikat</p>
                            <p class="pk-nilai">{{ data.predikat }}</p>
                        </div>
                        <div class="pk-sep"></div>
                        <div>
                            <p class="pk-label">Nilai Akhir</p>
                            <p class="pk-angka">{{ data.nilai }}</p>
                        </div>
                        <div v-if="data.ambang" class="pk-sep"></div>
                        <div v-if="data.ambang">
                            <p class="pk-label">Ambang Lulus</p>
                            <p class="pk-nilai">{{ data.ambang }}</p>
                        </div>
                    </div>

                    <!-- Berdampingan bila keduanya ada; ditumpuk membuat
                         halaman nilai luber keluar bingkai. -->
                    <div v-if="data.catatan || data.masukan"
                        :class="['catatan-grid', (data.catatan && data.masukan) ? 'dua' : '']">
                        <div v-if="data.catatan" class="kotak-catatan">
                            <p class="kc-label">Catatan Penguji</p>
                            <p class="kc-isi">{{ data.catatan }}</p>
                            <p class="kc-oleh">— {{ data.penguji }}</p>
                        </div>

                        <div v-if="data.masukan" class="kotak-catatan kotak-masukan">
                            <p class="kc-label">Masukan Pimpinan</p>
                            <p class="kc-isi">{{ data.masukan }}</p>
                            <p v-if="data.masukan_oleh" class="kc-oleh">— {{ data.masukan_oleh }}</p>
                        </div>
                    </div>

                    <div class="ttd ttd-nilai">
                        <div class="ttd-col ttd-kosong"></div>
                        <div class="ttd-col">
                            <p class="ttd-role">Sidoarjo, {{ data.tanggal }}</p>
                            <p class="ttd-role">Pimpinan Program {{ data.program }}</p>
                            <div class="ttd-space"></div>
                            <p class="ttd-nama">(………………………………)</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/*
 * SATUAN: semuanya cqh (1% TINGGI kotak sertifikat), bukan cqw atau persen.
 *
 * Versi sebelumnya memakai cqw untuk font dan PERSEN untuk margin. Persen
 * selalu dihitung dari LEBAR, jadi pada A4 landscape (960x679) tiap
 * `margin-top: 2.6%` menjadi 25px dan delapan margin saja sudah memakan 200px
 * dari 679px yang tersedia. Begitu satu teks membungkus (nama panjang, nama
 * pengampu panjang), isinya tumpah keluar bingkai. Terukur: butuh 1.368px pada
 * ruang 679px -- hampir dua kali.
 *
 * Dengan `container-type: size` tinggi kotak menjadi acuan (cqh), sehingga
 * seluruh anggaran ruang bisa dijumlahkan dan dijaga di bawah 100cqh.
 * `overflow: hidden` jadi jaring terakhir: bila ada data yang di luar dugaan
 * panjang, hasilnya terpotong rapi, bukan berantakan menimpa bingkai.
 */
.cert-wrap { --gold: #b8912f; --emer: #0f766e; --ink: #23303a;
    display: flex; flex-direction: column; align-items: center; gap: 20px; }

.cert {
    width: 100%;
    max-width: 960px;
    aspect-ratio: 1.414 / 1;              /* A4 landscape */
    background: radial-gradient(circle at 15% 10%, #fff 0%, #fbf7ec 55%, #f6efdd 100%);
    box-shadow: 0 18px 50px rgba(30, 40, 60, .18);
    color: var(--ink);
    font-family: Georgia, 'Times New Roman', serif;
    container-type: size;                 /* membuka satuan cqh */
    overflow: hidden;                     /* jaring terakhir */
}
.cert-border {
    height: 100%; margin: 1.6cqh 1.1cqh;
    border: 2px solid var(--gold);
    outline: 5px solid var(--gold); outline-offset: 4px;
    position: relative; display: flex; overflow: hidden;
}
.corner { position: absolute; width: 3.4cqh; height: 3.4cqh; border: 2.5px solid var(--emer); }
.corner.tl { top: -2px; left: -2px; border-right: 0; border-bottom: 0; }
.corner.tr { top: -2px; right: -2px; border-left: 0; border-bottom: 0; }
.corner.bl { bottom: -2px; left: -2px; border-right: 0; border-top: 0; }
.corner.br { bottom: -2px; right: -2px; border-left: 0; border-top: 0; }

.cert-inner { flex: 1; min-height: 0; padding: 3cqh 5cqh 2.5cqh; text-align: center;
    display: flex; flex-direction: column; align-items: center; }
/* Margin bawaan peramban pada p/h1/h2/table dinolkan: seluruh jarak diatur
   eksplisit dalam cqh, kalau tidak anggaran ruang tidak pernah akurat. */
.cert p, .cert h1, .cert h2, .cert table { margin: 0; }

/* -- Halaman depan -------------------------------------------------------- */
.bismillah { font-size: 5cqh; color: var(--gold); line-height: 1; }
.lembaga { font-size: 3.6cqh; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; line-height: 1.2; margin-top: 1cqh; }
.program { font-size: 2.4cqh; color: var(--emer); font-weight: 600; }
.alamat { font-size: 1.8cqh; color: #8a7f6a; font-family: system-ui, sans-serif; line-height: 1.3; }

.rule { display: flex; align-items: center; gap: 1cqh; width: 50%; margin: 1.2cqh 0; }
.rule span { flex: 1; height: 2px; background: linear-gradient(90deg, transparent, var(--gold)); }
.rule span:last-child { background: linear-gradient(90deg, var(--gold), transparent); }
.rule i { color: var(--gold); font-style: normal; font-size: 2.2cqh; }

.judul { font-size: 6.4cqh; font-weight: 700; letter-spacing: .2em; color: var(--emer); text-indent: .2em; line-height: 1.05; }
.sub { font-size: 2.4cqh; color: var(--gold); font-style: italic; }
.nomor { font-size: 1.8cqh; color: #8a7f6a; font-family: system-ui, sans-serif; margin-top: .4cqh; }

.diberikan { font-size: 2.2cqh; color: #6b6151; margin-top: 2cqh; }
/* Nama: dua baris maksimal, dipotong rapi bila lebih panjang dari dugaan. */
.nama { font-size: 5.6cqh; font-weight: 700; line-height: 1.15; margin-top: .6cqh;
    border-bottom: 2px dotted var(--gold); padding: 0 3cqh 1cqh; max-width: 92%;
    max-height: 14cqh; overflow: hidden; }
.nip { font-size: 1.9cqh; color: #8a7f6a; font-family: system-ui, sans-serif; margin-top: .8cqh; }

.narasi { font-size: 2.4cqh; line-height: 1.45; max-width: 88%; margin-top: 1.8cqh;
    max-height: 11cqh; overflow: hidden; }
.narasi .predikat { color: var(--emer); }
.rujuk-nilai { font-size: 1.7cqh; color: #8a7f6a; font-style: italic; margin-top: 1.2cqh; font-family: system-ui, sans-serif; }

.ttd { margin-top: auto; display: flex; align-items: flex-end; justify-content: space-between; width: 100%; }
.ttd-col { width: 31%; }
.ttd-role { font-size: 1.9cqh; color: #6b6151; font-family: system-ui, sans-serif; line-height: 1.3; }
.ttd-space { height: 3.5cqh; }
/* Nama penanda tangan juga dibatasi dua baris supaya tinggi blok tetap. */
.ttd-nama { font-size: 2.1cqh; font-weight: 700; border-top: 1.5px solid var(--ink); padding-top: .9cqh;
    line-height: 1.2; max-height: 6.2cqh; overflow: hidden; }
.ttd-seal { display: flex; flex-direction: column; align-items: center; gap: .6cqh; }
.seal { width: 10cqh; height: 10cqh; border-radius: 50%; border: 3px double var(--gold); color: var(--emer);
    display: grid; place-items: center; text-align: center; background: radial-gradient(circle, #fff, #fbf3dd); transform: rotate(-8deg); }
.seal-in { font-size: 2.2cqh; font-weight: 700; line-height: 1; }
.seal-in small { font-size: 1.5cqh; letter-spacing: .1em; color: var(--gold); font-family: system-ui, sans-serif; }
.ttd-tanggal { font-size: 1.8cqh; color: #6b6151; font-family: system-ui, sans-serif; }

/* -- Halaman nilai -------------------------------------------------------- */
.nilai-inner { padding: 3cqh 5cqh 2.5cqh; }
.pengait { width: 100%; display: flex; align-items: flex-start; justify-content: space-between; gap: 2cqh;
    border-bottom: 1.5px solid #e7dcc0; padding-bottom: 1.4cqh; text-align: left; }
.pengait-nama { font-size: 2.6cqh; font-weight: 700; line-height: 1.2;
    max-height: 6.5cqh; overflow: hidden; }
.pengait-sub { font-size: 1.8cqh; color: #8a7f6a; font-family: system-ui, sans-serif; }
.pengait-nomor { font-size: 1.7cqh; color: #8a7f6a; font-family: system-ui, sans-serif; white-space: nowrap; }

.judul-nilai { font-size: 4.6cqh; font-weight: 700; letter-spacing: .16em; color: var(--emer); text-indent: .16em; margin-top: 2cqh; line-height: 1.1; }
.sub-nilai { font-size: 2.1cqh; color: var(--gold); font-style: italic; }

.tabel-nilai { width: 74%; border-collapse: collapse; margin-top: 1cqh; font-family: system-ui, sans-serif; }
.tabel-nilai th, .tabel-nilai td { border: 1.2px solid #e0d3b0; padding: .8cqh 2cqh; font-size: 2cqh; text-align: center; line-height: 1.2; }
.tabel-nilai th { background: #faf4e6; color: #6b6151; font-size: 1.7cqh; text-transform: uppercase; letter-spacing: .06em; }
.tabel-nilai .kiri { text-align: left; }
.tabel-nilai .angka { font-weight: 700; font-size: 2.2cqh; }
.tabel-nilai tfoot td { background: var(--emer); color: #fff; font-weight: 700; border-color: var(--emer); }
.rubrik-none { margin-top: 2cqh; font-size: 2.8cqh; }

.predikat-box { display: flex; align-items: center; gap: 3cqh; margin-top: 1.8cqh;
    background: #fff; border: 1.5px solid #e7dcc0; border-radius: 1.4cqh; padding: 1cqh 3cqh; }
.pk-sep { width: 1.5px; height: 5cqh; background: #e7dcc0; }
.pk-label { font-size: 1.5cqh; color: #8a7f6a; font-family: system-ui, sans-serif; text-transform: uppercase; letter-spacing: .06em; }
.pk-nilai { font-size: 2.2cqh; font-weight: 700; color: var(--emer); line-height: 1.2; white-space: nowrap; }
.pk-angka { font-size: 3cqh; font-weight: 700; font-family: system-ui, sans-serif; line-height: 1.2; }

/* Dua kotak catatan BERDAMPINGAN -- ditumpuk membuat halaman nilai luber. */
.catatan-grid { width: 88%; margin-top: 1.6cqh; display: grid; gap: 1.6cqh; grid-template-columns: 1fr; }
.catatan-grid.dua { grid-template-columns: 1fr 1fr; }
.kotak-catatan { text-align: left; background: #fff; border: 1.5px solid #e7dcc0;
    border-left: 4px solid var(--gold); border-radius: 1cqh; padding: 1.2cqh 1.8cqh; }
.kotak-masukan { border-left-color: var(--emer); }
.kc-label { font-size: 1.5cqh; color: #8a7f6a; font-family: system-ui, sans-serif; text-transform: uppercase; letter-spacing: .06em; }
.kc-isi { font-size: 1.9cqh; line-height: 1.4; margin-top: .3cqh; font-style: italic;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.kc-oleh { font-size: 1.5cqh; color: #8a7f6a; margin-top: .4cqh; font-family: system-ui, sans-serif; text-align: right; }

.ttd-nilai { justify-content: flex-end; }
.ttd-nilai .ttd-col { width: 34%; }
.ttd-kosong { width: 0 !important; }

@media print {
    .cert-wrap { gap: 0; }
    .cert { box-shadow: none; max-width: 100%; }
    /* Dua lembar: depan lalu nilai. */
    .cert-depan { break-after: page; page-break-after: always; }
    @page { size: A4 landscape; margin: 8mm; }
}
</style>
