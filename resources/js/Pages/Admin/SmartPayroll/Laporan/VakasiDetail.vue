<template>
    <AdminLayout>

        <!-- TOOLBAR -->
        <div class="no-print flex items-center justify-between gap-3 mb-5">
            <div class="flex items-center gap-3">
                <button @click="$inertia.back()"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-sm font-semibold
                           text-gray-600 bg-white border border-gray-200 hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Kembali
                </button>
                <h1 class="text-lg font-bold text-gray-800">Laporan Vakasi — {{ guru.nama }}</h1>
            </div>
            <button @click="cetak"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold
                       text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Cetak
            </button>
        </div>

        <!-- DOKUMEN -->
        <div id="report"
            class="bg-white max-w-3xl mx-auto text-gray-900 ring-1 ring-gray-300 rounded-lg overflow-hidden shadow-sm print:shadow-none print:ring-0 print:rounded-none">

            <!-- KOP -->
            <div class="flex items-center gap-4 px-6 py-5 border-b-[3px] border-double border-gray-800">
                <img v-if="logo" :src="logo" class="w-12 h-12 object-contain shrink-0" />
                <div v-else
                    class="w-12 h-12 rounded-full bg-indigo-600 flex items-center justify-center text-white text-base font-bold shrink-0">
                    AN</div>
                <div class="flex-1 text-center">
                    <h2 class="text-xl sm:text-2xl font-extrabold uppercase leading-tight tracking-tight">{{ instansi.nama }}</h2>
                    <p class="text-[11px] text-gray-500 mt-1 max-w-md mx-auto leading-snug">{{ instansi.alamat }}</p>
                </div>
                <div class="text-right shrink-0 w-28">
                    <div class="text-base font-bold uppercase tracking-wide text-indigo-700 leading-tight">Laporan Vakasi</div>
                    <div class="text-[10px] italic text-gray-400 mt-0.5 leading-tight">{{ instansi.perihal }}</div>
                </div>
            </div>

            <!-- NAMA / BULAN -->
            <div class="grid grid-cols-2 text-[13px] border-b border-gray-300">
                <div class="px-6 py-2.5 flex gap-2 border-r border-gray-300">
                    <span class="w-14 text-gray-400 font-medium">Nama</span><span class="text-gray-300">:</span>
                    <span class="font-bold uppercase">{{ guru.nama }}</span>
                </div>
                <div class="px-6 py-2.5 flex gap-2">
                    <span class="w-14 text-gray-400 font-medium">Bulan</span><span class="text-gray-300">:</span>
                    <span class="font-bold uppercase">{{ periode.label }}</span>
                </div>
            </div>
            <div class="grid grid-cols-2 text-[12px] border-b-2 border-gray-800 bg-gray-50/60">
                <div class="px-6 py-1.5 flex gap-2 border-r border-gray-300">
                    <span class="w-14 text-gray-400 font-medium">Jabatan</span><span class="text-gray-300">:</span>
                    <span class="text-gray-600">{{ guru.jabatan }}</span>
                </div>
                <div class="px-6 py-1.5 flex gap-2">
                    <span class="w-14 text-gray-400 font-medium">NIP</span><span class="text-gray-300">:</span>
                    <span class="text-gray-600">{{ guru.nip || '—' }}</span>
                </div>
            </div>

            <!-- SECTIONS -->
            <template v-if="vakasi.sections.length">
                <div v-for="sec in vakasi.sections" :key="sec.key">
                    <!-- header komponen -->
                    <div class="flex items-center bg-gray-800 text-white px-5 py-2">
                        <span class="text-[12px] font-bold uppercase tracking-wider flex-1">{{ sec.label }}</span>
                        <span class="text-[11px] text-gray-300">{{ sec.count }} item</span>
                    </div>
                    <!-- baris -->
                    <div v-for="(r, i) in sec.rows" :key="i"
                        class="flex items-baseline gap-3 px-5 py-2 border-b border-gray-100 text-[13px]">
                        <div class="flex-1 leading-snug">
                            {{ r.keterangan }}
                            <span v-if="r.jumlah_satuan > 1 && r.satuan" class="text-gray-400 text-[11px]">
                                · {{ r.jumlah_satuan }} {{ r.satuan }} × {{ rupiah(r.nilai_per_satuan) }}
                            </span>
                        </div>
                        <div class="tabular-nums whitespace-nowrap font-medium">{{ rupiah(r.subtotal) }}</div>
                    </div>
                    <!-- subtotal komponen -->
                    <div class="flex items-center px-5 py-2 bg-gray-50 border-b border-gray-300 text-[12px]">
                        <span class="flex-1 text-gray-500 font-semibold uppercase tracking-wide">Subtotal {{ sec.label }}</span>
                        <span class="tabular-nums font-bold text-gray-700">{{ rupiah(sec.subtotal) }}</span>
                    </div>
                </div>
            </template>
            <div v-else class="px-5 py-10 text-center text-gray-400 italic text-sm border-b border-gray-300">
                Tidak ada komponen vakasi pada periode ini.
            </div>

            <!-- GRAND TOTAL -->
            <div class="flex items-center gap-4 bg-emerald-600 text-white px-6 py-3 border-b-2 border-gray-800">
                <span class="font-bold uppercase tracking-wide text-sm">Total Vakasi Diterima</span>
                <span class="text-xl font-extrabold tabular-nums ml-auto">{{ rupiah(vakasi.total) }}</span>
            </div>

            <!-- FOOTER -->
            <div class="flex items-end justify-between px-8 py-5 gap-6">
                <div>
                    <div class="text-[10px] uppercase tracking-widest text-gray-300 font-semibold mb-1">No.</div>
                    <div class="text-4xl font-extrabold text-gray-300 leading-none tabular-nums">{{ vakasi.id }}</div>
                    <div class="mt-2 text-[10px] text-gray-400 italic">
                        Status penggajian: {{ vakasi.status_badge?.label ?? vakasi.status }}
                    </div>
                </div>
                <div class="text-center text-[12px] min-w-[180px]">
                    <div class="text-gray-500">Sidoarjo, {{ tglCetak }}</div>
                    <div class="text-gray-700 mt-0.5">Bendahara,</div>
                    <div class="h-16"></div>
                    <div class="font-bold underline uppercase decoration-1 underline-offset-2">{{ instansi.bendahara }}</div>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({
    instansi: { type: Object, default: () => ({}) },
    guru: { type: Object, default: () => ({}) },
    periode: { type: Object, default: () => ({}) },
    vakasi: { type: Object, default: () => ({ sections: [] }) },
    logo: { type: String, default: null },
})

const tglCetak = new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })

function rupiah(n) {
    return 'Rp ' + Number(n || 0).toLocaleString('id-ID')
}
function cetak() {
    window.print()
}
</script>

<style scoped>
@media print {
    .no-print {
        display: none !important;
    }
    #report {
        max-width: 100% !important;
        margin: 0 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>
