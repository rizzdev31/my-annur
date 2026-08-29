<template>
    <AdminLayout title="Laporan" subtitle="Smart Education">

        <Head title="Laporan Smart Education" />

        <!-- ══ Toolbar (disembunyikan saat cetak) ══════════════════════════ -->
        <div class="print:hidden">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Laporan</h2>
                    <p class="text-sm text-gray-400 mt-0.5">Bungkus rapi jurnal pembelajaran & tahfidz per kelas.</p>
                </div>
            </div>

            <!-- Tab jenis laporan -->
            <div class="flex gap-2 mb-5">
                <span class="px-4 py-2 rounded-xl text-sm font-semibold bg-indigo-600 text-white shadow-sm shadow-indigo-200">Jurnal Pembelajaran</span>
                <Link :href="route('admin.smart-education.laporan.tahfidz')"
                    class="px-4 py-2 rounded-xl text-sm font-semibold bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors">Tahfidz</Link>
                <Link :href="route('admin.smart-education.laporan.tahsin')"
                    class="px-4 py-2 rounded-xl text-sm font-semibold bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors">Tahsin</Link>
            </div>

            <!-- Filter -->
            <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-5 flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Kop Sekolah</label>
                    <select v-model="kopKey" :class="fieldCls">
                        <option v-for="k in kopOpsi" :key="k.key" :value="k.key">{{ k.nama }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Kelas</label>
                    <select v-model="f.kelas_id" :class="fieldCls">
                        <option :value="null">Semua kelas</option>
                        <option v-for="k in kelasOpsi" :key="k.id" :value="k.id">{{ k.nama }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Guru</label>
                    <select v-model="f.guru_id" :class="fieldCls">
                        <option :value="null">Semua guru</option>
                        <option v-for="g in guruOpsi" :key="g.id" :value="g.id">{{ g.nama }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Dari</label>
                    <input v-model="f.dari" type="date" :class="fieldCls" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Sampai</label>
                    <input v-model="f.sampai" type="date" :class="fieldCls" />
                </div>
                <button @click="terapkan"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors">
                    Tampilkan
                </button>
                <button v-if="kelas || guru" @click="cetak"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl transition-colors inline-flex items-center gap-2 ml-auto">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Cetak / PDF
                </button>
            </div>
        </div>

        <!-- ══ Prompt pilih kelas ══════════════════════════════════════════ -->
        <div v-if="!kelas && !guru" class="print:hidden bg-white rounded-2xl border border-dashed border-gray-300 py-16 text-center">
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <p class="text-sm text-gray-500 font-medium">Pilih <b>kelas</b> dan/atau <b>guru</b> lalu klik <b>Tampilkan</b> untuk menyusun laporan.</p>
        </div>

        <!-- ══ DOKUMEN LAPORAN ═════════════════════════════════════════════ -->
        <div v-else id="laporan-cetak"
            class="bg-white rounded-2xl border border-gray-200 p-6 sm:p-9 print:p-0 print:border-0 print:rounded-none">

            <!-- KOP (navbar) — di print jadi kop berulang tiap halaman -->
            <div class="kop-head-wrap">
                <KopSurat :kop="kop" />
            </div>

            <div class="kop-body">
                <!-- Judul dokumen -->
                <div class="text-center mt-5 mb-4">
                    <h2 class="inline-block text-base sm:text-lg font-bold text-gray-900 uppercase tracking-wide border-b-2 border-[#2E3160] pb-1">
                        Laporan Jurnal Pembelajaran
                    </h2>
                    <div class="mt-3 flex flex-wrap justify-center gap-x-2 gap-y-1.5 text-[12px]">
                        <span v-if="kelas" class="px-2.5 py-1 rounded-full bg-[#2E3160]/[0.07] text-[#2E3160] font-semibold">Kelas: {{ kelas.nama }}</span>
                        <span v-if="guru" class="px-2.5 py-1 rounded-full bg-[#2E3160]/[0.07] text-[#2E3160] font-semibold">Guru: {{ guru.nama }}</span>
                        <span class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-600 font-medium">Periode: {{ periodeLabel }}</span>
                    </div>
                </div>

                <!-- Tabel modern -->
                <div class="overflow-hidden rounded-xl ring-1 ring-gray-200 print:ring-0 print:rounded-none">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="bg-[#2E3160] text-white">
                                <th class="px-3 py-3 text-center text-[11px] font-bold uppercase tracking-wider w-9">No</th>
                                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-wider w-28">Tanggal</th>
                                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-wider">Guru &amp; Mata Pelajaran</th>
                                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-wider">Deskripsi Materi</th>
                                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-wider">Kehadiran</th>
                                <th class="px-3 py-3 text-center text-[11px] font-bold uppercase tracking-wider w-28">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(r, i) in rows" :key="r.no" class="align-top border-b border-gray-100 last:border-0"
                                :class="i % 2 ? 'bg-slate-50/60' : 'bg-white'">
                                <td class="px-3 py-2.5 text-center font-semibold text-gray-500 tabular-nums">{{ r.no }}</td>
                                <td class="px-3 py-2.5 whitespace-nowrap text-gray-700 tabular-nums">{{ r.tanggal }}</td>
                                <td class="px-3 py-2.5">
                                    <p class="font-semibold text-gray-800 leading-tight">{{ r.guru }}</p>
                                    <p class="text-[11px] text-gray-400 tabular-nums">NIP. {{ r.nip }}</p>
                                    <p class="inline-block mt-1 text-[10px] font-semibold px-1.5 py-0.5 rounded bg-[#2E3160]/[0.08] text-[#2E3160]">{{ r.mapel }}</p>
                                    <p v-if="!kelas" class="text-[10px] text-gray-400 mt-0.5">Kelas: {{ r.kelas }}</p>
                                </td>
                                <td class="px-3 py-2.5 text-gray-700 leading-snug">{{ r.deskripsi || '—' }}</td>
                                <td class="px-3 py-2.5">
                                    <template v-if="r.kehadiran.terisi">
                                        <div class="flex flex-wrap gap-1 mb-1">
                                            <span class="px-1.5 py-0.5 rounded-md bg-emerald-50 text-emerald-700 text-[10.5px] font-semibold">H {{ r.kehadiran.hadir }}</span>
                                            <span class="px-1.5 py-0.5 rounded-md bg-amber-50 text-amber-700 text-[10.5px] font-semibold">T {{ r.kehadiran.telat }}</span>
                                            <span class="px-1.5 py-0.5 rounded-md bg-red-50 text-red-700 text-[10.5px] font-semibold">A {{ r.kehadiran.alpha }}</span>
                                            <span class="text-[10.5px] text-gray-400 self-center">/ {{ r.kehadiran.total }}</span>
                                        </div>
                                        <p v-if="r.kehadiran.telat_nama.length" class="text-[10.5px] text-gray-500 leading-snug"><b class="text-amber-700">Telat:</b> {{ r.kehadiran.telat_nama.join(', ') }}</p>
                                        <p v-if="r.kehadiran.alpha_nama.length" class="text-[10.5px] text-gray-500 leading-snug"><b class="text-red-700">Alpha:</b> {{ r.kehadiran.alpha_nama.join(', ') }}</p>
                                    </template>
                                    <span v-else class="text-[11px] text-gray-400 italic">Belum diisi</span>
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <span class="inline-flex items-center gap-1 text-[10.5px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-1 rounded-full">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                        Disetujui
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!rows.length">
                                <td colspan="6" class="px-3 py-12 text-center text-gray-400">Tidak ada jurnal pembelajaran pada periode ini.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pengesahan -->
                <div class="flex justify-end mt-8 mb-2">
                    <div class="text-center text-[13px] text-gray-700 w-60">
                        <p>Sidoarjo, {{ tanggalCetak }}</p>
                        <p class="mt-0.5">Disetujui Oleh,</p>
                        <div class="h-16"></div>
                        <p class="font-bold text-gray-900 underline underline-offset-2">Administrator</p>
                        <p class="text-[11px] text-gray-500">{{ kop.brand }}</p>
                    </div>
                </div>
            </div>

            <!-- FOOTER kop (navy) — di print pinned bawah tiap halaman -->
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
    rows: { type: Array, default: () => [] },
    kelas: { type: Object, default: null },
    guru: { type: Object, default: null },
    filter: { type: Object, default: () => ({}) },
    periodeLabel: { type: String, default: '' },
    tanggalCetak: { type: String, default: '' },
    kelasOpsi: { type: Array, default: () => [] },
    guruOpsi: { type: Array, default: () => [] },
    kopOpsi: { type: Array, default: () => [] },
    logo: { type: String, default: null },
})

const kopKey = ref(props.kopOpsi[0]?.key ?? 'smp')
const kop = computed(() => props.kopOpsi.find(k => k.key === kopKey.value) ?? props.kopOpsi[0] ?? { nama: '', alamat: '' })

const f = reactive({
    kelas_id: props.filter.kelas_id ?? null,
    guru_id: props.filter.guru_id ?? null,
    dari: props.filter.dari,
    sampai: props.filter.sampai,
})

function terapkan() {
    router.get(route('admin.smart-education.laporan.index'), {
        kelas_id: f.kelas_id, guru_id: f.guru_id, dari: f.dari, sampai: f.sampai,
    }, { preserveState: true, preserveScroll: true })
}

function cetak() {
    window.print()
}

const fieldCls = 'px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all bg-white'
</script>

<style scoped>
@media print {
    @page { margin: 12mm; }
    #laporan-cetak { font-size: 11px; }
    /* Warna kop navy, badge & tabel ikut tercetak */
    :deep(*) {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    /* Kop menempel di atas; footer navy di akhir dokumen */
    .kop-head-wrap { margin-bottom: 2mm; }
    table { page-break-inside: auto; }
    tr { page-break-inside: avoid; page-break-after: auto; }
    thead { display: table-header-group; }  /* header tabel berulang tiap halaman */
    tfoot { display: table-footer-group; }
}
</style>
