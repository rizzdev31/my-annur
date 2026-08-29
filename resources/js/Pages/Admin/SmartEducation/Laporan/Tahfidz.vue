<template>
    <AdminLayout title="Laporan Tahfidz" subtitle="Smart Education">

        <Head title="Laporan Tahfidz" />

        <div class="print:hidden">
            <div class="flex gap-2 mb-5">
                <Link :href="route('admin.smart-education.laporan.index')"
                    class="px-4 py-2 rounded-xl text-sm font-semibold bg-white border border-gray-200 text-gray-600 hover:bg-gray-50">Jurnal Pembelajaran</Link>
                <span class="px-4 py-2 rounded-xl text-sm font-semibold bg-teal-600 text-white shadow-sm shadow-teal-200">Tahfidz</span>
                <Link :href="route('admin.smart-education.laporan.tahsin')"
                    class="px-4 py-2 rounded-xl text-sm font-semibold bg-white border border-gray-200 text-gray-600 hover:bg-gray-50">Tahsin</Link>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-3 flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Kop Sekolah</label>
                    <select v-model="kopKey" :class="fieldCls">
                        <option v-for="k in kopOpsi" :key="k.key" :value="k.key">{{ k.nama }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Kelas Tahfidz</label>
                    <select v-model="f.kelas_id" @change="f.santri_id = null" :class="fieldCls">
                        <option :value="null">Pilih kelas...</option>
                        <option v-for="k in kelasOpsi" :key="k.id" :value="k.id">{{ k.nama }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Per Anak (opsional)</label>
                    <select v-model="f.santri_id" :class="fieldCls" :disabled="!f.kelas_id">
                        <option :value="null">Semua santri (per kelas)</option>
                        <option v-for="s in santriOpsi" :key="s.id" :value="s.id">{{ s.nama_lengkap }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Periode / Semester</label>
                    <select v-model="f.ta_id" :class="fieldCls">
                        <option :value="null">Semua (kumulatif)</option>
                        <option v-for="t in tahunAjaranOpsi" :key="t.id" :value="t.id">{{ t.label }}</option>
                    </select>
                </div>
                <button @click="terapkan" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-sm font-semibold rounded-xl">Tampilkan</button>
                <div class="ml-auto flex items-end gap-2">
                    <button @click="ttdBuka = !ttdBuka" class="px-3 py-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-600 text-sm font-semibold rounded-xl">Tanda Tangan</button>
                    <button v-if="mode" @click="cetak" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                        Cetak / PDF
                    </button>
                </div>
            </div>

            <!-- Pengaturan tanda tangan -->
            <div v-if="ttdBuka" class="bg-white rounded-2xl border border-gray-200 p-4 mb-5 grid grid-cols-2 md:grid-cols-4 gap-3">
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Kota</label><input v-model="ttd.kota" :class="fieldCls + ' w-full'" /></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Jabatan Kiri</label><input v-model="ttd.kiriJab" :class="fieldCls + ' w-full'" /></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Nama Kiri</label><input v-model="ttd.kiriNama" :class="fieldCls + ' w-full'" placeholder="Nama Kepala Sekolah" /></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">NIP Kiri</label><input v-model="ttd.kiriNip" :class="fieldCls + ' w-full'" /></div>
                <div class="col-start-1"><label class="block text-xs font-medium text-gray-500 mb-1">Jabatan Kanan</label><input v-model="ttd.kananJab" :class="fieldCls + ' w-full'" /></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Nama Kanan</label><input v-model="ttd.kananNama" :class="fieldCls + ' w-full'" placeholder="Nama Pengampu / Wali Kelas" /></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">NIP Kanan</label><input v-model="ttd.kananNip" :class="fieldCls + ' w-full'" /></div>
            </div>
        </div>

        <div v-if="!mode" class="print:hidden bg-white rounded-2xl border border-dashed border-gray-300 py-16 text-center">
            <p class="text-sm text-gray-500">Pilih kelas (dan opsional santri) lalu <b>Tampilkan</b>.</p>
        </div>

        <!-- DOKUMEN -->
        <div v-else id="laporan-cetak" class="bg-white rounded-2xl border border-gray-200 p-6 sm:p-9 print:p-0 print:border-0 print:rounded-none">
            <div class="kop-head-wrap">
                <KopSurat :kop="kop" />
            </div>

            <div class="text-center mt-5 mb-4">
                <h2 class="inline-block text-base sm:text-lg font-bold text-gray-900 uppercase tracking-wide border-b-2 border-[#2E3160] pb-1">
                    Laporan Pencapaian Tahfidz
                </h2>
                <p class="mt-2 text-sm text-gray-700">
                    <template v-if="mode === 'anak'">Santri : <b>{{ detail.santri.nama }}</b> (NIP {{ detail.santri.nip }})</template>
                    <template v-else>Kelas : <b>{{ kelas.nama }}</b></template>
                </p>
                <p class="text-xs text-gray-500 mt-0.5">Periode: {{ periodeLabel }}</p>
            </div>

            <!-- MODE KELAS -->
            <div v-if="mode === 'kelas'" class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead><tr class="bg-[#2E3160] text-white">
                        <th class="border border-gray-200 px-2 py-2 w-10">No</th>
                        <th class="border border-gray-200 px-2 py-2 text-left">Nama &amp; NIP</th>
                        <th class="border border-gray-200 px-2 py-2 text-center">Total Ayat</th>
                        <th class="border border-gray-200 px-2 py-2 text-center">Progres</th>
                        <th class="border border-gray-200 px-2 py-2 text-center">Juz Selesai</th>
                        <th class="border border-gray-200 px-2 py-2 text-center">Hafalan Baru<br><span class="text-[10px] font-normal">(ayat, periode)</span></th>
                        <th class="border border-gray-200 px-2 py-2 text-center">Murojaah<br><span class="text-[10px] font-normal">(kali, periode)</span></th>
                        <th class="border border-gray-200 px-2 py-2 text-center">Rata Nilai</th>
                        <th class="border border-gray-200 px-2 py-2 text-left">Catatan Terakhir</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="r in rows" :key="r.no">
                            <td class="border border-gray-200 px-2 py-2 text-center">{{ r.no }}</td>
                            <td class="border border-gray-200 px-2 py-2"><p class="font-semibold">{{ r.nama }}</p><p class="text-xs text-gray-500 font-mono">{{ r.nip }}</p></td>
                            <td class="border border-gray-200 px-2 py-2 text-center font-semibold">{{ r.total_ayat }}</td>
                            <td class="border border-gray-200 px-2 py-2 text-center">{{ r.persen }}%</td>
                            <td class="border border-gray-200 px-2 py-2 text-center">{{ r.juz_selesai }} / 30</td>
                            <td class="border border-gray-200 px-2 py-2 text-center text-teal-700 font-semibold">+{{ r.ayat_baru }}</td>
                            <td class="border border-gray-200 px-2 py-2 text-center">{{ r.murojaah }}×</td>
                            <td class="border border-gray-200 px-2 py-2 text-center font-semibold">{{ r.rata_nilai ?? '—' }}</td>
                            <td class="border border-gray-200 px-2 py-2 text-gray-600 text-xs">{{ r.catatan_terakhir || '—' }}</td>
                        </tr>
                        <tr v-if="!rows.length"><td colspan="9" class="border border-gray-200 py-8 text-center text-gray-400">Belum ada data.</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- MODE ANAK -->
            <div v-else-if="mode === 'anak'">
                <div class="grid grid-cols-4 gap-3 mb-5 text-center">
                    <div class="border border-gray-300 rounded-lg py-2"><p class="text-xs text-gray-500">Total Hafalan</p><p class="text-lg font-bold">{{ detail.status.total_ayat }} ayat</p></div>
                    <div class="border border-gray-300 rounded-lg py-2"><p class="text-xs text-gray-500">Pencapaian</p><p class="text-lg font-bold text-teal-600">{{ detail.status.persen }}%</p></div>
                    <div class="border border-gray-300 rounded-lg py-2"><p class="text-xs text-gray-500">Juz Selesai</p><p class="text-lg font-bold">{{ detail.status.juz_selesai_total }}</p></div>
                    <div class="border border-gray-300 rounded-lg py-2"><p class="text-xs text-gray-500">Hafalan Baru (periode)</p><p class="text-lg font-bold text-teal-700">+{{ detail.ayat_baru }} ayat</p></div>
                </div>

                <!-- Rekap per jenis setoran -->
                <p class="text-sm font-semibold text-gray-700 mb-2">Rekap Aktivitas (periode)</p>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
                    <div v-for="r in detail.recap" :key="r.jenis" class="border border-gray-300 rounded-lg p-3">
                        <p class="text-xs font-semibold text-gray-700">{{ r.label }}</p>
                        <p class="text-sm mt-1"><b>{{ r.count }}</b> kali<span v-if="r.jenis === 'ziyadah'"> · {{ r.ayat }} ayat</span></p>
                        <p class="text-xs text-gray-500">Rata nilai: <b class="text-gray-800">{{ r.rata ?? '—' }}</b></p>
                    </div>
                </div>

                <p class="text-sm font-semibold text-gray-700 mb-2">Progres per Juz</p>
                <div class="grid grid-cols-10 gap-1 mb-2">
                    <div v-for="j in detail.juz_grid" :key="j.juz" :class="['text-center text-xs py-1.5 rounded border', juzCls(j.status)]">{{ j.juz }}</div>
                </div>
                <div class="flex flex-wrap gap-3 mb-5 text-[11px] text-gray-500">
                    <span><span class="inline-block w-3 h-3 rounded-sm bg-sky-100 border border-sky-300 align-middle"></span> Berjalan</span>
                    <span><span class="inline-block w-3 h-3 rounded-sm bg-amber-100 border border-amber-300 align-middle"></span> Selesai (perlu tasmi)</span>
                    <span><span class="inline-block w-3 h-3 rounded-sm bg-emerald-100 border border-emerald-400 align-middle"></span> Tasmi lulus</span>
                </div>

                <p class="text-sm font-semibold text-gray-700 mb-2">Riwayat Setoran (Hafalan Baru, Murojaah, Tasmi')</p>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead><tr class="bg-[#2E3160] text-white">
                            <th class="border border-gray-200 px-2 py-1.5 text-left">Tanggal</th>
                            <th class="border border-gray-200 px-2 py-1.5 text-left">Jenis</th>
                            <th class="border border-gray-200 px-2 py-1.5 text-left">Rentang</th>
                            <th class="border border-gray-200 px-2 py-1.5 text-center">Ayat</th>
                            <th class="border border-gray-200 px-2 py-1.5 text-center">Nilai</th>
                            <th class="border border-gray-200 px-2 py-1.5 text-center">Ket.</th>
                            <th class="border border-gray-200 px-2 py-1.5 text-left">Catatan</th>
                            <th class="border border-gray-200 px-2 py-1.5 text-left">Penilai</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="(r, i) in detail.riwayat" :key="i">
                                <td class="border border-gray-200 px-2 py-1.5 whitespace-nowrap">{{ r.tanggal }}</td>
                                <td class="border border-gray-200 px-2 py-1.5">{{ r.label }}</td>
                                <td class="border border-gray-200 px-2 py-1.5">{{ r.rentang }}</td>
                                <td class="border border-gray-200 px-2 py-1.5 text-center">{{ r.jumlah_ayat }}</td>
                                <td class="border border-gray-200 px-2 py-1.5 text-center font-semibold">{{ r.nilai ?? '—' }}</td>
                                <td class="border border-gray-200 px-2 py-1.5 text-center">
                                    <span v-if="r.lulus === true" class="text-emerald-600 font-semibold">Lulus</span>
                                    <span v-else-if="r.lulus === false" class="text-red-500">Belum</span>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="border border-gray-200 px-2 py-1.5 text-gray-600">{{ r.catatan || '—' }}</td>
                                <td class="border border-gray-200 px-2 py-1.5">
                                    {{ r.guru }}
                                    <span v-if="r.penguji" class="block text-[10px] text-teal-700 font-semibold">Penguji Tasmi'</span>
                                </td>
                            </tr>
                            <tr v-if="!detail.riwayat.length"><td colspan="8" class="border border-gray-200 py-6 text-center text-gray-400">Belum ada setoran pada periode ini.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TANDA TANGAN -->
            <div class="flex justify-between mt-10 text-sm text-gray-800">
                <div class="text-center w-60">
                    <p>Mengetahui,</p>
                    <p>{{ ttd.kiriJab }}</p>
                    <div class="h-20"></div>
                    <p class="font-semibold underline underline-offset-2">{{ ttd.kiriNama || '(……………………………)' }}</p>
                    <p class="text-xs">NIP. {{ ttd.kiriNip || '……………………' }}</p>
                </div>
                <div class="text-center w-60">
                    <p>{{ ttd.kota }}, {{ tanggalCetak }}</p>
                    <p>{{ ttd.kananJab }}</p>
                    <div class="h-20"></div>
                    <p class="font-semibold underline underline-offset-2">{{ ttd.kananNama || '(……………………………)' }}</p>
                    <p class="text-xs">NIP. {{ ttd.kananNip || '……………………' }}</p>
                </div>
            </div>

            <!-- FOOTER kop (navy) -->
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
    mode: { type: String, default: null },
    kelas: { type: Object, default: null },
    rows: { type: Array, default: () => [] },
    detail: { type: Object, default: null },
    filter: { type: Object, default: () => ({}) },
    periodeLabel: { type: String, default: 'Semua (kumulatif)' },
    tanggalCetak: { type: String, default: '' },
    kelasOpsi: { type: Array, default: () => [] },
    santriOpsi: { type: Array, default: () => [] },
    tahunAjaranOpsi: { type: Array, default: () => [] },
    kopOpsi: { type: Array, default: () => [] },
    logo: { type: String, default: null },
})

const kopKey = ref(props.kopOpsi[0]?.key ?? 'smp')
const kop = computed(() => props.kopOpsi.find(k => k.key === kopKey.value) ?? props.kopOpsi[0] ?? { nama: '', alamat: '' })
const f = reactive({ kelas_id: props.filter.kelas_id ?? null, santri_id: props.filter.santri_id ?? null, ta_id: props.filter.ta_id ?? null })

const ttdBuka = ref(false)
const ttd = reactive({
    kota: 'Sidoarjo', kiriJab: 'Kepala Sekolah', kiriNama: '', kiriNip: '',
    kananJab: 'Guru Pengampu,', kananNama: '', kananNip: '',
})

function terapkan() {
    router.get(route('admin.smart-education.laporan.tahfidz'),
        { kelas_id: f.kelas_id, santri_id: f.santri_id, ta_id: f.ta_id },
        { preserveState: true, preserveScroll: true })
}
function cetak() { window.print() }
function juzCls(s) {
    return { belum: 'bg-gray-50 text-gray-400 border-gray-200', berjalan: 'bg-sky-50 text-sky-700 border-sky-300',
        selesai: 'bg-amber-50 text-amber-700 border-amber-300', tasmi_lulus: 'bg-emerald-50 text-emerald-700 border-emerald-400' }[s] ?? 'bg-gray-50 border-gray-200'
}
const fieldCls = 'px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-100 transition-all bg-white'
</script>

<style scoped>
@media print {
    @page { margin: 12mm; }
    #laporan-cetak { font-size: 11px; }
    :deep(*) { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .kop-head-wrap { margin-bottom: 2mm; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
}
</style>
