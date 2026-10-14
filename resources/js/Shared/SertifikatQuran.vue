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

                    <div v-if="data.catatan" class="kotak-catatan">
                        <p class="kc-label">Catatan Penguji</p>
                        <p class="kc-isi">{{ data.catatan }}</p>
                        <p class="kc-oleh">— {{ data.penguji }}</p>
                    </div>

                    <!-- Masukan pimpinan: ruang yang sebelumnya tidak ada sama
                         sekali, padahal superadmin yang memantau mutu penilaian. -->
                    <div v-if="data.masukan" class="kotak-catatan kotak-masukan">
                        <p class="kc-label">Masukan Pimpinan</p>
                        <p class="kc-isi">{{ data.masukan }}</p>
                        <p v-if="data.masukan_oleh" class="kc-oleh">— {{ data.masukan_oleh }}</p>
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
.cert-wrap { --gold: #b8912f; --emer: #0f766e; --ink: #23303a; display: flex; flex-direction: column; align-items: center; gap: 20px; }

.cert {
    width: 100%;
    max-width: 960px;
    aspect-ratio: 1.414 / 1;              /* A4 landscape */
    background: radial-gradient(circle at 15% 10%, #fff 0%, #fbf7ec 55%, #f6efdd 100%);
    box-shadow: 0 18px 50px rgba(30, 40, 60, .18);
    color: var(--ink);
    font-family: Georgia, 'Times New Roman', serif;
    container-type: inline-size;
}
.cert-border {
    height: 100%; margin: 2.2%;
    border: 2.5px solid var(--gold);
    outline: 6px solid var(--gold); outline-offset: 5px;
    position: relative; display: flex;
}
.corner { position: absolute; width: 26px; height: 26px; border: 3px solid var(--emer); }
.corner.tl { top: -3px; left: -3px; border-right: 0; border-bottom: 0; }
.corner.tr { top: -3px; right: -3px; border-left: 0; border-bottom: 0; }
.corner.bl { bottom: -3px; left: -3px; border-right: 0; border-top: 0; }
.corner.br { bottom: -3px; right: -3px; border-left: 0; border-top: 0; }

.cert-inner { flex: 1; padding: 4.5% 6% 3.5%; text-align: center; display: flex; flex-direction: column; align-items: center; }

/* ── Halaman depan ───────────────────────────────────────────────────────── */
.bismillah { font-size: 6cqw; color: var(--gold); line-height: 1; margin-bottom: 1.5%; }
.lembaga { font-size: 3.4cqw; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
.program { font-size: 2.1cqw; color: var(--emer); font-weight: 600; margin-top: .3%; }
.alamat { font-size: 1.5cqw; color: #8a7f6a; margin-top: .4%; font-family: system-ui, sans-serif; }

.rule { display: flex; align-items: center; gap: 10px; width: 55%; margin: 2.5% 0 1.5%; }
.rule span { flex: 1; height: 2px; background: linear-gradient(90deg, transparent, var(--gold)); }
.rule span:last-child { background: linear-gradient(90deg, var(--gold), transparent); }
.rule i { color: var(--gold); font-style: normal; font-size: 2.4cqw; }

.judul { font-size: 6.6cqw; font-weight: 700; letter-spacing: .22em; color: var(--emer); text-indent: .22em; }
.sub { font-size: 2.2cqw; color: var(--gold); font-style: italic; margin-top: -.4%; }
.nomor { font-size: 1.5cqw; color: #8a7f6a; margin-top: .8%; font-family: system-ui, sans-serif; }

.diberikan { font-size: 1.9cqw; color: #6b6151; margin-top: 3.2%; }
/* Nama bisa lebih besar sekarang karena tabel nilai pindah ke halaman 2. */
.nama { font-size: 6cqw; font-weight: 700; margin-top: .4%; border-bottom: 2px dotted var(--gold); padding: 0 4% 1%; line-height: 1.1; }
.nip { font-size: 1.6cqw; color: #8a7f6a; margin-top: 1.2%; font-family: system-ui, sans-serif; }

.narasi { font-size: 2.1cqw; line-height: 1.6; max-width: 82%; margin-top: 3%; }
.narasi .predikat { color: var(--emer); }
.rujuk-nilai { font-size: 1.4cqw; color: #8a7f6a; font-style: italic; margin-top: 1.4%; font-family: system-ui, sans-serif; }

.ttd { margin-top: auto; padding-top: 3%; display: flex; align-items: flex-end; justify-content: space-between; width: 100%; }
.ttd-col { width: 30%; }
.ttd-role { font-size: 1.6cqw; color: #6b6151; font-family: system-ui, sans-serif; }
.ttd-space { height: 4cqw; }
.ttd-nama { font-size: 1.9cqw; font-weight: 700; border-top: 1.5px solid var(--ink); padding-top: 4%; }
.ttd-seal { display: flex; flex-direction: column; align-items: center; gap: 6px; }
.seal { width: 11cqw; height: 11cqw; border-radius: 50%; border: 3px double var(--gold); color: var(--emer);
    display: grid; place-items: center; text-align: center; background: radial-gradient(circle, #fff, #fbf3dd); transform: rotate(-8deg); }
.seal-in { font-size: 2.4cqw; font-weight: 700; line-height: 1; }
.seal-in small { font-size: 1.1cqw; letter-spacing: .1em; color: var(--gold); font-family: system-ui, sans-serif; }
.ttd-tanggal { font-size: 1.5cqw; color: #6b6151; font-family: system-ui, sans-serif; }

/* ── Halaman nilai ───────────────────────────────────────────────────────── */
.nilai-inner { padding-top: 3.5%; }
.pengait { width: 100%; display: flex; align-items: flex-start; justify-content: space-between;
    border-bottom: 1.5px solid #e7dcc0; padding-bottom: 1.4%; text-align: left; }
.pengait-nama { font-size: 2.4cqw; font-weight: 700; }
.pengait-sub { font-size: 1.5cqw; color: #8a7f6a; font-family: system-ui, sans-serif; }
.pengait-nomor { font-size: 1.4cqw; color: #8a7f6a; font-family: system-ui, sans-serif; }

.judul-nilai { font-size: 4.4cqw; font-weight: 700; letter-spacing: .18em; color: var(--emer); text-indent: .18em; margin-top: 2.4%; }
.sub-nilai { font-size: 1.9cqw; color: var(--gold); font-style: italic; }

.tabel-nilai { width: 78%; border-collapse: collapse; margin-top: 1.5%; font-family: system-ui, sans-serif; }
.tabel-nilai th, .tabel-nilai td { border: 1.2px solid #e0d3b0; padding: 1.3% 2%; font-size: 1.7cqw; text-align: center; }
.tabel-nilai th { background: #faf4e6; color: #6b6151; font-size: 1.5cqw; text-transform: uppercase; letter-spacing: .06em; }
.tabel-nilai .kiri { text-align: left; }
.tabel-nilai .angka { font-weight: 700; font-size: 2cqw; }
.tabel-nilai tfoot td { background: var(--emer); color: #fff; font-weight: 700; border-color: var(--emer); }
.rubrik-none { margin-top: 2.6%; font-size: 2.2cqw; }

.predikat-box { display: flex; align-items: center; gap: 3%; margin-top: 2.4%;
    background: #fff; border: 1.5px solid #e7dcc0; border-radius: 12px; padding: 1.6% 4%; }
.pk-sep { width: 1.5px; height: 4cqw; background: #e7dcc0; }
.pk-label { font-size: 1.3cqw; color: #8a7f6a; font-family: system-ui, sans-serif; text-transform: uppercase; letter-spacing: .08em; }
.pk-nilai { font-size: 2.1cqw; font-weight: 700; color: var(--emer); }
.pk-angka { font-size: 3cqw; font-weight: 700; font-family: system-ui, sans-serif; }

.kotak-catatan { width: 78%; margin-top: 2%; text-align: left;
    background: #fff; border: 1.5px solid #e7dcc0; border-left: 4px solid var(--gold); border-radius: 8px; padding: 1.4% 2.2%; }
.kotak-masukan { border-left-color: var(--emer); }
.kc-label { font-size: 1.3cqw; color: #8a7f6a; font-family: system-ui, sans-serif; text-transform: uppercase; letter-spacing: .08em; }
.kc-isi { font-size: 1.7cqw; line-height: 1.5; margin-top: .4%; font-style: italic; }
.kc-oleh { font-size: 1.3cqw; color: #8a7f6a; margin-top: .6%; font-family: system-ui, sans-serif; text-align: right; }

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
