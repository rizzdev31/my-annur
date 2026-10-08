<template>
    <AdminLayout title="Ujian Sekolah" subtitle="Smart Education">

        <Head title="Laporan Ujian Sekolah" />

        <div class="print:hidden">
            <div class="flex flex-wrap gap-2 mb-5">
                <Link :href="route('admin.smart-education.laporan.index')"
                    class="px-4 py-2 rounded-xl text-sm font-semibold bg-white border border-gray-200 text-gray-600 hover:bg-gray-50">Jurnal Pembelajaran</Link>
                <Link :href="route('admin.smart-education.laporan.kehadiran-santri')"
                    class="px-4 py-2 rounded-xl text-sm font-semibold bg-white border border-gray-200 text-gray-600 hover:bg-gray-50">Kehadiran Santri</Link>
                <span class="px-4 py-2 rounded-xl text-sm font-semibold bg-indigo-600 text-white shadow-sm shadow-indigo-200">Ujian Sekolah</span>
                <Link :href="route('admin.smart-education.laporan.mengajar-quran')"
                    class="px-4 py-2 rounded-xl text-sm font-semibold bg-white border border-gray-200 text-gray-600 hover:bg-gray-50">Mengajar Tahfidz &amp; Tahsin</Link>
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
                    <label class="block text-xs font-medium text-gray-500 mb-1">Jendela</label>
                    <select v-model="f.mode" :class="fieldCls">
                        <option value="paket">Per paket ujian</option>
                        <option value="tanggal">Rentang tanggal</option>
                    </select>
                </div>
                <div v-if="f.mode === 'paket'">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Paket Ujian</label>
                    <select v-model.number="f.ujian_id" :class="fieldCls">
                        <option v-for="p in paketList" :key="p.id" :value="p.id">
                            {{ p.label }}{{ p.dibatalkan ? ' (dibatalkan)' : '' }}
                        </option>
                    </select>
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
                </template>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Kelas</label>
                    <select v-model.number="f.kelas_id" :class="fieldCls">
                        <option :value="null">Semua</option>
                        <option v-for="k in kelasOpsi" :key="k.id" :value="k.id">{{ k.nama }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Penjaga</label>
                    <select v-model.number="f.penjaga_id" :class="fieldCls">
                        <option :value="null">Semua</option>
                        <option v-for="g in guruOpsi" :key="g.id" :value="g.id">{{ g.nama }}</option>
                    </select>
                </div>
                <div class="flex gap-2 ml-auto">
                    <button @click="ttdBuka = !ttdBuka"
                        class="px-4 py-2 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold hover:bg-gray-50">
                        Tanda Tangan
                    </button>
                    <button @click="terapkan"
                        class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">Tampilkan</button>
                    <button @click="cetak"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold hover:bg-gray-50">
                        Cetak / PDF
                    </button>
                </div>
            </div>

            <div v-if="ttdBuka" class="bg-white rounded-2xl border border-gray-200 p-4 mb-3 grid grid-cols-2 md:grid-cols-4 gap-3">
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Kota</label><input v-model="ttd.kota" :class="fieldCls + ' w-full'" /></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Jabatan Kiri</label><input v-model="ttd.kiriJab" :class="fieldCls + ' w-full'" /></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Nama Kiri</label><input v-model="ttd.kiriNama" :class="fieldCls + ' w-full'" placeholder="Nama Kepala Sekolah" /></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">NIP Kiri</label><input v-model="ttd.kiriNip" :class="fieldCls + ' w-full'" /></div>
                <div class="col-start-1"><label class="block text-xs font-medium text-gray-500 mb-1">Jabatan Kanan</label><input v-model="ttd.kananJab" :class="fieldCls + ' w-full'" /></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Nama Kanan</label><input v-model="ttd.kananNama" :class="fieldCls + ' w-full'" placeholder="Nama Ketua Panitia" /></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">NIP Kanan</label><input v-model="ttd.kananNip" :class="fieldCls + ' w-full'" /></div>
            </div>

            <div class="bg-indigo-50/60 border border-indigo-100 rounded-2xl p-4 mb-5 text-xs text-indigo-900/80 leading-relaxed">
                <p class="font-semibold text-indigo-900 mb-1">Cara hitung</p>
                <p>
                    Dasarnya adalah <b>penugasan sesi ujian</b>, bukan jadwal pembelajaran. Satu baris = satu kelas
                    pada satu jam ujian. Kolom <b>Penjaga</b> menampilkan yang ditugaskan; bila sesinya di-inval,
                    nama penggantinya ikut ditulis — dan <b>penggantinya itulah yang berhak atas vakasi</b>, dengan
                    nominal penjaga yang sama.
                </p>
                <p class="mt-1">
                    <b>Vakasi dihitung per sesi</b> dan hanya untuk sesi yang benar-benar dijaga (penjaganya sudah
                    mengabsen). Sesi yang belum berpenjaga atau tidak dijaga tampil Rp 0 agar terlihat, bukan hilang.
                    Kehadiran santri diambil dari absensi yang diisi penjaga pada sesi itu.
                </p>
            </div>
        </div>

        <div id="laporan-cetak" class="bg-white rounded-2xl border border-gray-200 p-6 sm:p-9 print:p-0 print:border-0 print:rounded-none">
            <div class="kop-head-wrap">
                <KopSurat :kop="kop" />
            </div>

            <div class="text-center mt-5 mb-5">
                <h2 class="inline-block text-base sm:text-lg font-bold text-gray-900 uppercase tracking-wide border-b-2 border-[#2E3160] pb-1">
                    Laporan Pelaksanaan Ujian Sekolah
                </h2>
                <p class="mt-2 text-sm text-gray-700">{{ label }}</p>
                <p class="text-xs text-gray-500 mt-0.5">Periode: {{ periodeLabel }}</p>
            </div>

            <!-- Ringkasan -->
            <div class="grid grid-cols-3 md:grid-cols-6 gap-2 mb-6 text-center">
                <div class="border border-gray-300 rounded-lg py-2.5">
                    <p class="text-[11px] text-gray-500">Sesi Ujian</p>
                    <p class="text-lg font-bold text-gray-900 mt-0.5">{{ ringkasan.sesi }}</p>
                </div>
                <div class="border border-gray-300 rounded-lg py-2.5">
                    <p class="text-[11px] text-gray-500">Dijaga</p>
                    <p class="text-lg font-bold text-emerald-600 mt-0.5">{{ ringkasan.dijaga }}</p>
                </div>
                <div class="border border-gray-300 rounded-lg py-2.5">
                    <p class="text-[11px] text-gray-500">Tidak Dijaga</p>
                    <p class="text-lg font-bold text-red-600 mt-0.5">{{ ringkasan.tidak_dijaga }}</p>
                </div>
                <div class="border border-gray-300 rounded-lg py-2.5">
                    <p class="text-[11px] text-gray-500">Belum Ada Penjaga</p>
                    <p class="text-lg font-bold text-amber-600 mt-0.5">{{ ringkasan.belum_penjaga }}</p>
                </div>
                <div class="border border-gray-300 rounded-lg py-2.5">
                    <p class="text-[11px] text-gray-500">Kehadiran Santri</p>
                    <p class="text-lg font-bold text-indigo-600 mt-0.5">
                        {{ ringkasan.persen_santri !== null ? ringkasan.persen_santri + '%' : '—' }}
                    </p>
                </div>
                <div class="border border-gray-300 rounded-lg py-2.5">
                    <p class="text-[11px] text-gray-500">Vakasi Penjaga</p>
                    <p class="text-lg font-bold text-gray-900 mt-0.5">{{ rupiahSingkat(ringkasan.vakasi) }}</p>
                </div>
            </div>

            <p v-if="ringkasan.roster_kosong" class="text-[11px] text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-5">
                <b>{{ ringkasan.roster_kosong }} sesi sudah dijaga tetapi absensi santrinya belum diisi.</b>
                Kehadiran ujian pada sesi itu belum tercatat di jurnal kelas.
            </p>

            <!-- Rekap per penjaga -->
            <template v-if="perPenjaga.length">
                <h3 class="text-sm font-bold text-gray-800 mb-2">A. Rekapitulasi per Penjaga</h3>
                <table class="w-full border-collapse text-xs mb-6">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-300 px-2 py-2 text-left w-8">No</th>
                            <th class="border border-gray-300 px-2 py-2 text-left">Penjaga</th>
                            <th class="border border-gray-300 px-2 py-2 text-center">Sesi</th>
                            <th class="border border-gray-300 px-2 py-2 text-center">Dijaga</th>
                            <th class="border border-gray-300 px-2 py-2 text-center">Tidak Dijaga</th>
                            <th class="border border-gray-300 px-2 py-2 text-center">Belum</th>
                            <th class="border border-gray-300 px-2 py-2 text-right">Vakasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(r, i) in perPenjaga" :key="r.penjaga">
                            <td class="border border-gray-300 px-2 py-1.5 text-center">{{ i + 1 }}</td>
                            <td class="border border-gray-300 px-2 py-1.5">{{ r.penjaga }}</td>
                            <td class="border border-gray-300 px-2 py-1.5 text-center tabular-nums">{{ r.sesi }}</td>
                            <td class="border border-gray-300 px-2 py-1.5 text-center tabular-nums text-emerald-700">{{ r.dijaga }}</td>
                            <td class="border border-gray-300 px-2 py-1.5 text-center tabular-nums"
                                :class="r.tidak_dijaga ? 'text-red-600 font-semibold' : 'text-gray-300'">{{ r.tidak_dijaga }}</td>
                            <td class="border border-gray-300 px-2 py-1.5 text-center tabular-nums text-gray-500">{{ r.belum }}</td>
                            <td class="border border-gray-300 px-2 py-1.5 text-right tabular-nums">{{ rupiah(r.vakasi) }}</td>
                        </tr>
                        <tr class="bg-gray-50 font-bold">
                            <td class="border border-gray-300 px-2 py-1.5 text-center" colspan="2">Jumlah</td>
                            <td class="border border-gray-300 px-2 py-1.5 text-center tabular-nums">{{ totalKolom('sesi') }}</td>
                            <td class="border border-gray-300 px-2 py-1.5 text-center tabular-nums">{{ totalKolom('dijaga') }}</td>
                            <td class="border border-gray-300 px-2 py-1.5 text-center tabular-nums">{{ totalKolom('tidak_dijaga') }}</td>
                            <td class="border border-gray-300 px-2 py-1.5 text-center tabular-nums">{{ totalKolom('belum') }}</td>
                            <td class="border border-gray-300 px-2 py-1.5 text-right tabular-nums">{{ rupiah(ringkasan.vakasi) }}</td>
                        </tr>
                    </tbody>
                </table>
            </template>

            <!-- Rekap per kelas -->
            <template v-if="perKelas.length">
                <h3 class="text-sm font-bold text-gray-800 mb-2">B. Rekapitulasi per Kelas</h3>
                <table class="w-full border-collapse text-xs mb-6">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-300 px-2 py-2 text-left w-8">No</th>
                            <th class="border border-gray-300 px-2 py-2 text-left">Kelas</th>
                            <th class="border border-gray-300 px-2 py-2 text-center">Sesi</th>
                            <th class="border border-gray-300 px-2 py-2 text-center">Dijaga</th>
                            <th class="border border-gray-300 px-2 py-2 text-center">Jumlah Santri</th>
                            <th class="border border-gray-300 px-2 py-2 text-center">Alpha</th>
                            <th class="border border-gray-300 px-2 py-2 text-center">Kehadiran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(r, i) in perKelas" :key="r.kelas">
                            <td class="border border-gray-300 px-2 py-1.5 text-center">{{ i + 1 }}</td>
                            <td class="border border-gray-300 px-2 py-1.5">{{ r.kelas }}</td>
                            <td class="border border-gray-300 px-2 py-1.5 text-center tabular-nums">{{ r.sesi }}</td>
                            <td class="border border-gray-300 px-2 py-1.5 text-center tabular-nums">{{ r.dijaga }}</td>
                            <td class="border border-gray-300 px-2 py-1.5 text-center tabular-nums">{{ r.santri || '—' }}</td>
                            <td class="border border-gray-300 px-2 py-1.5 text-center tabular-nums"
                                :class="r.alpha ? 'text-red-600 font-semibold' : 'text-gray-300'">{{ r.alpha }}</td>
                            <td class="border border-gray-300 px-2 py-1.5 text-center tabular-nums font-semibold">
                                {{ r.persen !== null ? r.persen + '%' : '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </template>

            <!-- Rincian per sesi -->
            <h3 class="text-sm font-bold text-gray-800 mb-2">C. Rincian Sesi Ujian</h3>
            <table class="w-full border-collapse text-[11px]">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border border-gray-300 px-2 py-2 text-left w-8">No</th>
                        <th class="border border-gray-300 px-2 py-2 text-left">Tanggal</th>
                        <th class="border border-gray-300 px-2 py-2 text-left">Jam</th>
                        <th class="border border-gray-300 px-2 py-2 text-left">Kelas</th>
                        <th class="border border-gray-300 px-2 py-2 text-left">Mata Ujian</th>
                        <th class="border border-gray-300 px-2 py-2 text-left">Penjaga</th>
                        <th class="border border-gray-300 px-2 py-2 text-center">Status</th>
                        <th class="border border-gray-300 px-2 py-2 text-center">H / I / S / A</th>
                        <th class="border border-gray-300 px-2 py-2 text-right">Vakasi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(r, i) in rows" :key="r.id">
                        <td class="border border-gray-300 px-2 py-1.5 text-center">{{ i + 1 }}</td>
                        <td class="border border-gray-300 px-2 py-1.5 whitespace-nowrap">{{ r.tanggal_label }}</td>
                        <td class="border border-gray-300 px-2 py-1.5 whitespace-nowrap tabular-nums">
                            {{ r.jam }}
                            <span v-if="r.jam_mulai_aktual" class="block text-[10px] text-gray-400">mulai {{ r.jam_mulai_aktual }}</span>
                        </td>
                        <td class="border border-gray-300 px-2 py-1.5">
                            {{ r.kelas }}
                            <span v-if="r.ruangan" class="block text-[10px] text-gray-400">R. {{ r.ruangan }}</span>
                        </td>
                        <td class="border border-gray-300 px-2 py-1.5">{{ r.mapel }}</td>
                        <td class="border border-gray-300 px-2 py-1.5">
                            {{ r.penjaga }}
                            <span v-if="r.inval_oleh" class="block text-[10px] text-sky-700">inval: {{ r.inval_oleh }}</span>
                        </td>
                        <td class="border border-gray-300 px-2 py-1.5 text-center whitespace-nowrap">
                            <span :class="['px-1.5 py-0.5 rounded print:bg-transparent print:px-0', badge(r.status)]">{{ r.status_label }}</span>
                        </td>
                        <td class="border border-gray-300 px-2 py-1.5 text-center tabular-nums whitespace-nowrap">
                            <template v-if="r.santri.terisi">
                                {{ r.santri.hadir + r.santri.telat }} / {{ r.santri.izin }} / {{ r.santri.sakit }} /
                                <span :class="r.santri.alpha ? 'text-red-600 font-bold' : ''">{{ r.santri.alpha }}</span>
                                <span class="block text-[10px] text-gray-400">{{ r.santri.persen }}% dari {{ r.santri.total }}</span>
                            </template>
                            <span v-else class="text-gray-300">belum diisi</span>
                        </td>
                        <td class="border border-gray-300 px-2 py-1.5 text-right tabular-nums">
                            {{ r.vakasi_berhak > 0 ? rupiah(r.vakasi_berhak) : '—' }}
                            <span v-if="r.vakasi_dibayar" class="block text-[10px] text-emerald-700">dibayar</span>
                        </td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="9" class="border border-gray-300 px-2 py-8 text-center text-gray-400">
                            Tidak ada sesi ujian pada jendela ini.
                        </td>
                    </tr>
                </tbody>
            </table>

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

            <div class="kop-foot-wrap mt-6">
                <KopFooter :kop="kop" />
            </div>
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
    mode: { type: String, default: 'paket' },
    label: { type: String, default: '' },
    paketList: { type: Array, default: () => [] },
    paketId: { type: Number, default: null },
    rows: { type: Array, default: () => [] },
    perPenjaga: { type: Array, default: () => [] },
    perKelas: { type: Array, default: () => [] },
    ringkasan: { type: Object, default: () => ({}) },
    filter: { type: Object, default: () => ({}) },
    periodeLabel: { type: String, default: '' },
    tanggalCetak: { type: String, default: '' },
    kelasOpsi: { type: Array, default: () => [] },
    guruOpsi: { type: Array, default: () => [] },
    kopOpsi: { type: Array, default: () => [] },
})

const fieldCls = 'px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-indigo-500'

const ttdBuka = ref(false)
const kopKey = ref(props.kopOpsi[0]?.key ?? 'smp')
const kop = computed(() => props.kopOpsi.find(k => k.key === kopKey.value) ?? props.kopOpsi[0] ?? { nama: '', alamat: '' })

const ttd = reactive({
    kota: 'Sidoarjo', kiriJab: 'Kepala Sekolah', kiriNama: '', kiriNip: '',
    kananJab: 'Ketua Panitia Ujian', kananNama: '', kananNip: '',
})

const f = reactive({
    mode: props.mode,
    ujian_id: props.paketId,
    kelas_id: props.filter.kelas_id ?? null,
    penjaga_id: props.filter.penjaga_id ?? null,
    dari: props.filter.dari,
    sampai: props.filter.sampai,
})

const rupiah = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID')
const rupiahSingkat = (n) => {
    const v = Number(n || 0)
    return v >= 1000000 ? 'Rp ' + (v / 1000000).toFixed(1) + ' jt'
        : v >= 1000 ? 'Rp ' + Math.round(v / 1000) + ' rb' : rupiah(v)
}
const totalKolom = (k) => props.perPenjaga.reduce((a, r) => a + Number(r[k] || 0), 0)

function badge(st) {
    return {
        dijaga: 'bg-emerald-50 text-emerald-700',
        dijaga_inval: 'bg-emerald-50 text-emerald-700',
        inval: 'bg-sky-50 text-sky-700',
        ditugaskan: 'bg-gray-100 text-gray-600',
        tidak_dijaga: 'bg-red-50 text-red-600',
        belum_penjaga: 'bg-amber-50 text-amber-700',
    }[st] ?? 'bg-gray-100 text-gray-600'
}

function terapkan() {
    router.get(route('admin.smart-education.laporan.ujian'), {
        mode: f.mode,
        ujian_id: f.mode === 'paket' ? f.ujian_id : null,
        dari: f.mode === 'tanggal' ? f.dari : null,
        sampai: f.mode === 'tanggal' ? f.sampai : null,
        kelas_id: f.kelas_id, penjaga_id: f.penjaga_id,
    }, { preserveState: true, preserveScroll: true })
}
const cetak = () => window.print()
</script>
