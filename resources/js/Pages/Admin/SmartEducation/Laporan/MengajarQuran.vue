<template>
    <AdminLayout title="Mengajar Tahfidz & Tahsin" subtitle="Smart Education">

        <Head title="Rekap Mengajar Tahfidz & Tahsin" />

        <div class="print:hidden">
            <div class="flex flex-wrap gap-2 mb-5">
                <Link :href="route('admin.smart-education.laporan.index')" :class="tabCls">Jurnal Pembelajaran</Link>
                <Link :href="route('admin.smart-education.laporan.kehadiran-santri')" :class="tabCls">Kehadiran Santri</Link>
                <span class="px-4 py-2 rounded-xl text-sm font-semibold bg-emerald-600 text-white shadow-sm shadow-emerald-200">Mengajar Tahfidz & Tahsin</span>
                <Link :href="route('admin.smart-education.laporan.tahfidz')" :class="tabCls">Tahfidz</Link>
                <Link :href="route('admin.smart-education.laporan.tahsin')" :class="tabCls">Tahsin</Link>
            </div>

            <!-- Filter -->
            <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-3 flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Kop Sekolah</label>
                    <select v-model="kopKey" :class="fieldCls">
                        <option v-for="k in kopOpsi" :key="k.key" :value="k.key">{{ k.nama }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Periode</label>
                    <select v-model="f.mode" :class="fieldCls">
                        <option value="harian">Rentang Tanggal</option>
                        <option value="mingguan">Per Minggu</option>
                        <option value="bulanan">Per Bulan</option>
                    </select>
                </div>

                <template v-if="f.mode === 'bulanan'">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Bulan</label>
                        <select v-model.number="f.bulan" :class="fieldCls">
                            <option v-for="(b, i) in namaBulan" :key="i" :value="i + 1">{{ b }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Tahun</label>
                        <select v-model.number="f.tahun" :class="fieldCls">
                            <option v-for="t in tahunOpsi" :key="t" :value="t">{{ t }}</option>
                        </select>
                    </div>
                </template>
                <div v-else-if="f.mode === 'mingguan'">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Tanggal dalam minggu</label>
                    <input v-model="f.tanggal" type="date" :class="fieldCls" />
                </div>
                <template v-else>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Dari</label>
                        <input v-model="f.dari" type="date" :class="fieldCls" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Sampai</label>
                        <input v-model="f.sampai" type="date" :class="fieldCls" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Pilihan cepat</label>
                        <div class="flex flex-wrap gap-1.5">
                            <button v-for="c in rentangCepat" :key="c.label" type="button" @click="pakaiRentang(c)"
                                class="px-2.5 py-1.5 rounded-lg border border-gray-200 text-xs font-medium text-gray-600 hover:border-emerald-400 hover:text-emerald-700">
                                {{ c.label }}
                            </button>
                        </div>
                    </div>
                </template>

                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Program</label>
                    <select v-model="f.tipe" :class="fieldCls">
                        <option :value="null">Tahfidz & Tahsin</option>
                        <option value="tahfidz">Tahfidz</option>
                        <option value="tahsin">Tahsin</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Guru (opsional)</label>
                    <select v-model="f.guru_id" :class="fieldCls">
                        <option :value="null">Semua guru</option>
                        <option v-for="g in guruOpsi" :key="g.id" :value="g.id">{{ g.nama }}</option>
                    </select>
                </div>
                <button @click="terapkan" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">Tampilkan</button>

                <div class="ml-auto flex items-end gap-2">
                    <button @click="ttdBuka = !ttdBuka" class="px-3 py-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-600 text-sm font-semibold rounded-xl">Tanda Tangan</button>
                    <button @click="cetak" class="px-4 py-2 bg-slate-700 hover:bg-slate-800 text-white text-sm font-semibold rounded-xl">Cetak / PDF</button>
                </div>
            </div>

            <div v-if="ttdBuka" class="bg-white rounded-2xl border border-gray-200 p-4 mb-3 grid grid-cols-2 md:grid-cols-4 gap-3">
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Kota</label><input v-model="ttd.kota" :class="fieldCls + ' w-full'" /></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Jabatan Kiri</label><input v-model="ttd.kiriJab" :class="fieldCls + ' w-full'" /></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Nama Kiri</label><input v-model="ttd.kiriNama" :class="fieldCls + ' w-full'" /></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">NIP Kiri</label><input v-model="ttd.kiriNip" :class="fieldCls + ' w-full'" /></div>
                <div class="col-start-1"><label class="block text-xs font-medium text-gray-500 mb-1">Jabatan Kanan</label><input v-model="ttd.kananJab" :class="fieldCls + ' w-full'" /></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Nama Kanan</label><input v-model="ttd.kananNama" :class="fieldCls + ' w-full'" /></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">NIP Kanan</label><input v-model="ttd.kananNip" :class="fieldCls + ' w-full'" /></div>
            </div>

            <!-- Cara baca -->
            <div class="bg-emerald-50/60 border border-emerald-100 rounded-2xl p-4 mb-5 text-xs text-emerald-900/80 leading-relaxed">
                <p class="font-semibold text-emerald-900 mb-1">Cara baca</p>
                <p><b>Seharusnya masuk</b> = sesi yang jamnya sudah lewat dan memang tanggung jawabnya
                    (terjadwal dikurangi hari libur, izin, dan sesi yang dialihkan ke guru pengganti).
                    <b>Masuk</b> = benar-benar mengajar. <b>Tidak masuk</b> = tidak mengisi absen &amp; jurnal sampai batas waktu.
                    <b>%</b> = Masuk dibagi Seharusnya masuk.</p>
                <p class="mt-1">Kolom kecil di kanan hanya pelengkap: <b>Terjadwal</b> (total sesi menurut jadwal),
                    <b>Izin / Dialihkan</b> (tidak dihitung sebagai kelalaian), <b>Inval</b> (menggantikan guru lain, di luar jadwalnya sendiri),
                    <b>JP</b>, dan <b>Jurnal kosong</b> (diabsen tetapi tanpa satu pun setoran/penilaian).
                    Tugas inval yang tidak dikerjakan muncul di bawah angka Inval — tidak ikut kolom
                    Tidak Masuk karena bukan bagian dari jadwalnya sendiri.</p>
            </div>
        </div>

        <div v-if="peringatanEra" class="bg-amber-50 border border-amber-200 rounded-2xl p-4 mb-5 text-xs text-amber-900 leading-relaxed print:hidden">
            <p class="font-semibold mb-0.5">Periode ini mencampur dua cara pencatatan</p>
            <p>Pencatatan otomatis &quot;tidak mengisi absen &amp; jurnal&quot; baru berlaku
                {{ tglLabel(batasPencatatan) }}. Sesi sebelum tanggal itu masuk ke
                <b>{{ total.tanpa_catatan }} sesi tanpa catatan</b> — ikut dihitung sebagai tidak masuk,
                tetapi bukan berarti gurunya dinyatakan lalai oleh sistem waktu itu.
                Untuk perbandingan yang adil, pilih rentang mulai {{ tglLabel(batasPencatatan) }}.</p>
        </div>

        <div id="laporan-cetak" class="bg-white rounded-2xl border border-gray-200 p-6 sm:p-9 print:p-0 print:border-0 print:rounded-none">
            <div class="kop-head-wrap"><KopSurat :kop="kop" /></div>

            <div class="text-center mt-5 mb-5">
                <h2 class="inline-block text-base sm:text-lg font-bold text-gray-900 uppercase tracking-wide border-b-2 border-[#2E3160] pb-1">
                    Rekap Mengajar Guru {{ judulProgram }}
                </h2>
                <p v-if="guru" class="mt-2 text-sm text-gray-700">Guru : <b>{{ guru.nama }}</b></p>
                <p class="text-xs text-gray-500 mt-0.5">Periode: {{ periodeLabel }}</p>
            </div>

            <!-- Ringkasan -->
            <div class="grid grid-cols-3 md:grid-cols-6 gap-2 mb-6 text-center">
                <div class="border border-gray-300 rounded-lg py-2"><p class="text-[11px] text-gray-500">Guru</p><p class="text-lg font-bold">{{ total.guru }}</p></div>
                <div class="border border-gray-300 rounded-lg py-2"><p class="text-[11px] text-gray-500">Seharusnya Masuk</p><p class="text-lg font-bold">{{ total.seharusnya }}</p></div>
                <div class="border border-gray-300 rounded-lg py-2"><p class="text-[11px] text-gray-500">Masuk</p><p class="text-lg font-bold text-emerald-600">{{ total.mengajar }}<span v-if="total.inval" class="text-xs text-sky-600"> +{{ total.inval }} inval</span></p></div>
                <div class="border border-gray-300 rounded-lg py-2"><p class="text-[11px] text-gray-500">Tidak Masuk</p><p class="text-lg font-bold text-red-600">{{ total.tidak_masuk }}</p></div>
                <div class="border border-gray-300 rounded-lg py-2"><p class="text-[11px] text-gray-500">Terjadwal</p><p class="text-lg font-bold text-gray-600">{{ total.terjadwal }}</p></div>
                <div class="border border-gray-300 rounded-lg py-2"><p class="text-[11px] text-gray-500">Keterlaksanaan</p><p class="text-lg font-bold" :class="persenCls(total.persen)">{{ fmtPersen(total.persen) }}</p></div>
            </div>

            <!-- Tabel per guru -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead><tr class="bg-[#2E3160] text-white">
                        <th class="border border-gray-200 px-2 py-2 w-8">No</th>
                        <th class="border border-gray-200 px-2 py-2 text-left">Guru</th>
                        <th class="border border-gray-200 px-2 py-2">Program</th>
                        <th class="border border-gray-200 px-2 py-2 text-left">Kelas</th>
                        <th class="border border-gray-200 px-2 py-2">Seharusnya<br><span class="font-normal text-[10px] opacity-75">masuk</span></th>
                        <th class="border border-gray-200 px-2 py-2">Masuk</th>
                        <th class="border border-gray-200 px-2 py-2">Tidak<br>Masuk</th>
                        <th class="border border-gray-200 px-2 py-2">%</th>
                        <th class="border border-gray-200 px-2 py-2 text-[10px] font-normal opacity-80">Terjadwal</th>
                        <th class="border border-gray-200 px-2 py-2 text-[10px] font-normal opacity-80">Izin /<br>Dialihkan</th>
                        <th class="border border-gray-200 px-2 py-2 text-[10px] font-normal opacity-80">Inval</th>
                        <th class="border border-gray-200 px-2 py-2 text-[10px] font-normal opacity-80">JP</th>
                        <th class="border border-gray-200 px-2 py-2 text-[10px] font-normal opacity-80">Jurnal<br>Kosong</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="(b, i) in baris" :key="b.guru_id + b.tipe" class="hover:bg-gray-50/60">
                            <td class="border border-gray-200 px-2 py-2 text-center">{{ i + 1 }}</td>
                            <td class="border border-gray-200 px-2 py-2">
                                <Link :href="linkGuru(b.guru_id)" class="font-semibold text-emerald-700 hover:underline print:text-gray-900 print:no-underline">{{ b.guru }}</Link>
                                <p class="text-[11px] text-gray-400">{{ b.hari_mengajar }} hari mengajar</p>
                            </td>
                            <td class="border border-gray-200 px-2 py-2 text-center">
                                <span class="px-2 py-0.5 rounded text-[11px] font-semibold capitalize" :class="b.tipe === 'tahfidz' ? 'bg-emerald-50 text-emerald-700' : 'bg-violet-50 text-violet-700'">{{ b.tipe }}</span>
                            </td>
                            <td class="border border-gray-200 px-2 py-2 text-xs text-gray-600">{{ b.kelas.join(', ') || '—' }}</td>
                            <td class="border border-gray-200 px-2 py-2 text-center font-semibold">{{ b.seharusnya }}</td>
                            <td class="border border-gray-200 px-2 py-2 text-center font-semibold text-emerald-700">{{ b.mengajar }}</td>
                            <td class="border border-gray-200 px-2 py-2 text-center" :class="b.tidak_masuk ? 'text-red-600 font-semibold' : 'text-gray-300'">
                                {{ b.tidak_masuk }}
                                <span v-if="b.tanpa_catatan" class="block text-[10px] font-normal text-amber-600">{{ b.tanpa_catatan }} tanpa catatan</span>
                            </td>
                            <td class="border border-gray-200 px-2 py-2 text-center font-bold" :class="persenCls(b.persen)">{{ fmtPersen(b.persen) }}</td>
                            <td class="border border-gray-200 px-2 py-2 text-center text-xs text-gray-500">{{ b.terjadwal }}</td>
                            <td class="border border-gray-200 px-2 py-2 text-center text-xs text-gray-500">{{ b.izin }} / {{ b.digantikan }}</td>
                            <td class="border border-gray-200 px-2 py-2 text-center text-xs" :class="b.inval ? 'text-sky-700 font-semibold' : 'text-gray-300'">
                                {{ b.inval }}
                                <span v-if="b.inval_tidak_datang" class="block text-[10px] text-red-600">{{ b.inval_tidak_datang }} tak datang</span>
                            </td>
                            <td class="border border-gray-200 px-2 py-2 text-center text-xs text-gray-500">{{ b.jp }}</td>
                            <td class="border border-gray-200 px-2 py-2 text-center text-xs" :class="b.jurnal_kosong ? 'text-amber-600 font-semibold' : 'text-gray-300'">{{ b.jurnal_kosong }}</td>
                        </tr>
                        <tr v-if="!baris.length"><td colspan="13" class="border border-gray-200 py-10 text-center text-gray-400">Tidak ada sesi tahfidz/tahsin pada periode ini.</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Rincian sesi satu guru -->
            <div v-if="detail" class="mt-6">
                <h3 class="text-sm font-bold text-gray-800 mb-2">Rincian Sesi — {{ guru?.nama }}</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead><tr class="bg-[#2E3160] text-white">
                            <th class="border border-gray-200 px-2 py-2 w-8">No</th>
                            <th class="border border-gray-200 px-2 py-2 text-left">Tanggal</th>
                            <th class="border border-gray-200 px-2 py-2">Jam</th>
                            <th class="border border-gray-200 px-2 py-2">Program</th>
                            <th class="border border-gray-200 px-2 py-2 text-left">Kelas</th>
                            <th class="border border-gray-200 px-2 py-2">Status</th>
                            <th class="border border-gray-200 px-2 py-2">Santri Hadir</th>
                            <th class="border border-gray-200 px-2 py-2">{{ f.tipe === 'tahsin' ? 'Penilaian' : f.tipe === 'tahfidz' ? 'Setoran' : 'Setoran / Penilaian' }}</th>
                            <th class="border border-gray-200 px-2 py-2 text-left">Keterangan</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="(s, i) in detail" :key="i">
                                <td class="border border-gray-200 px-2 py-1.5 text-center">{{ i + 1 }}</td>
                                <td class="border border-gray-200 px-2 py-1.5 whitespace-nowrap">{{ tglLabel(s.tanggal) }}</td>
                                <td class="border border-gray-200 px-2 py-1.5 text-center whitespace-nowrap">{{ s.jam }}</td>
                                <td class="border border-gray-200 px-2 py-1.5 text-center capitalize">{{ s.tipe }}</td>
                                <td class="border border-gray-200 px-2 py-1.5">{{ s.kelas }}</td>
                                <td class="border border-gray-200 px-2 py-1.5 text-center">
                                    <span class="px-2 py-0.5 rounded-full border text-[11px] font-semibold whitespace-nowrap" :class="katCls(s.kategori)">{{ katLabel(s.kategori) }}</span>
                                </td>
                                <td class="border border-gray-200 px-2 py-1.5 text-center">{{ s.santri_total ? `${s.santri_hadir}/${s.santri_total}` : '—' }}</td>
                                <td class="border border-gray-200 px-2 py-1.5 text-center"
                                    :class="['mengajar','inval'].includes(s.kategori) && !s.aktivitas ? 'text-amber-600 font-semibold' : ''">
                                    {{ ['mengajar','inval'].includes(s.kategori) ? (s.aktivitas || 'kosong') : '—' }}
                                </td>
                                <td class="border border-gray-200 px-2 py-1.5 text-xs text-gray-500">{{ s.keterangan || '' }}</td>
                            </tr>
                            <tr v-if="!detail.length"><td colspan="9" class="border border-gray-200 py-8 text-center text-gray-400">Tidak ada sesi.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tanda tangan -->
            <div class="grid grid-cols-2 gap-6 mt-10 text-sm text-center">
                <div>
                    <p>&nbsp;</p>
                    <p>{{ ttd.kiriJab }}</p>
                    <div class="h-20"></div>
                    <p class="font-semibold underline underline-offset-2">{{ ttd.kiriNama || '(……………………………)' }}</p>
                    <p class="text-xs">NIP. {{ ttd.kiriNip || '……………………' }}</p>
                </div>
                <div>
                    <p>{{ ttd.kota }}, {{ tanggalCetak }}</p>
                    <p>{{ ttd.kananJab }}</p>
                    <div class="h-20"></div>
                    <p class="font-semibold underline underline-offset-2">{{ ttd.kananNama || '(……………………………)' }}</p>
                    <p class="text-xs">NIP. {{ ttd.kananNip || '……………………' }}</p>
                </div>
            </div>

            <div class="kop-foot-wrap mt-6"><KopFooter :kop="kop" /></div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import KopSurat from './Partials/KopSurat.vue'
import KopFooter from './Partials/KopFooter.vue'

const props = defineProps({
    batasPencatatan: { type: String, default: null },
    mulaiPeriode: { type: String, default: null },
    filter: { type: Object, default: () => ({}) },
    periodeLabel: { type: String, default: '' },
    tanggalCetak: { type: String, default: '' },
    baris: { type: Array, default: () => [] },
    total: { type: Object, default: () => ({}) },
    detail: { type: Array, default: null },
    guru: { type: Object, default: null },
    guruOpsi: { type: Array, default: () => [] },
    kopOpsi: { type: Array, default: () => [] },
})

const hariIni = new Date().toISOString().slice(0, 10)
const f = reactive({
    mode: props.filter.mode ?? 'harian',
    bulan: props.filter.bulan ?? new Date().getMonth() + 1,
    tahun: props.filter.tahun ?? new Date().getFullYear(),
    tanggal: props.filter.tanggal ?? hariIni,
    dari: props.filter.dari ?? hariIni,
    sampai: props.filter.sampai ?? hariIni,
    tipe: props.filter.tipe ?? null,
    guru_id: props.filter.guru_id ?? null,
})

// Rentang cepat — mengisi Dari/Sampai lalu langsung memuat laporan.
const iso = (d) => d.toISOString().slice(0, 10)
const rentangCepat = [
    { label: '7 hari terakhir', hari: 6 },
    { label: '30 hari terakhir', hari: 29 },
    { label: 'Bulan ini', bulanIni: true },
]
function pakaiRentang(c) {
    const kini = new Date()
    if (c.bulanIni) {
        f.dari = iso(new Date(kini.getFullYear(), kini.getMonth(), 1))
        f.sampai = iso(kini)
    } else {
        const awal = new Date(kini); awal.setDate(awal.getDate() - c.hari)
        f.dari = iso(awal); f.sampai = iso(kini)
    }
    f.mode = 'harian'
    terapkan()
}

// Sesi sebelum pencatatan otomatis berlaku ikut terhitung sebagai "tanpa catatan" —
// beri tahu pembacanya supaya angkanya tidak disalahartikan.
const peringatanEra = computed(() => props.batasPencatatan && props.mulaiPeriode
    && props.mulaiPeriode < props.batasPencatatan && (props.total?.tanpa_catatan ?? 0) > 0)

const kopKey = ref(props.kopOpsi[0]?.key ?? 'smp')
const kop = computed(() => props.kopOpsi.find(k => k.key === kopKey.value) ?? props.kopOpsi[0] ?? { nama: '', alamat: '' })
const ttdBuka = ref(false)
const ttd = reactive({ kota: 'Sidoarjo', kiriJab: 'Kepala Sekolah', kiriNama: '', kiriNip: '', kananJab: 'Koordinator Tahfidz & Tahsin,', kananNama: '', kananNip: '' })

const namaBulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
const tahunOpsi = computed(() => { const t = new Date().getFullYear(); return [t - 2, t - 1, t, t + 1] })
const judulProgram = computed(() => f.tipe === 'tahfidz' ? 'Tahfidz' : f.tipe === 'tahsin' ? 'Tahsin' : 'Tahfidz & Tahsin')

function paramsPeriode() {
    const base = { mode: f.mode, tipe: f.tipe }
    if (f.mode === 'bulanan') return { ...base, bulan: f.bulan, tahun: f.tahun }
    if (f.mode === 'mingguan') return { ...base, tanggal: f.tanggal }
    return { ...base, dari: f.dari, sampai: f.sampai }
}
function terapkan() {
    router.get(route('admin.smart-education.laporan.mengajar-quran'), { ...paramsPeriode(), guru_id: f.guru_id },
        { preserveState: true, preserveScroll: true })
}
const linkGuru = (id) => route('admin.smart-education.laporan.mengajar-quran', { ...paramsPeriode(), guru_id: id })
function cetak() { window.print() }

const fmtPersen = (v) => (v === null || v === undefined ? '—' : `${Number(v).toFixed(1).replace(/\.0$/, '')}%`)
function persenCls(v) {
    if (v === null || v === undefined) return 'text-gray-400'
    if (v >= 90) return 'text-emerald-600'
    if (v >= 75) return 'text-amber-600'
    return 'text-red-600'
}
const KAT = {
    mengajar:         ['Mengajar', 'bg-emerald-50 text-emerald-700 border-emerald-200'],
    inval:            ['Inval', 'bg-sky-50 text-sky-700 border-sky-200'],
    tidak_terlaksana: ['Tidak terlaksana', 'bg-red-50 text-red-700 border-red-200'],
    tanpa_catatan:    ['Tanpa catatan', 'bg-amber-50 text-amber-700 border-amber-200'],
    izin:             ['Izin', 'bg-slate-50 text-slate-600 border-slate-200'],
    digantikan:       ['Digantikan', 'bg-slate-50 text-slate-600 border-slate-200'],
}
const katLabel = (k) => KAT[k]?.[0] ?? k
const katCls = (k) => KAT[k]?.[1] ?? 'bg-gray-50 text-gray-500 border-gray-200'
const tglLabel = (t) => new Date(t + 'T00:00:00').toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })

const tabCls = 'px-4 py-2 rounded-xl text-sm font-semibold bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'
const fieldCls = 'px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 transition-all bg-white'
</script>

<style scoped>
@media print {
    @page { size: landscape; margin: 10mm; }
    #laporan-cetak { font-size: 10px; }
    :deep(*) { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
}
</style>
