<template>
    <div id="report"
        class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden print:shadow-none print:border-0 print:rounded-none">

        <!-- Kop / Letterhead -->
        <div class="px-6 sm:px-8 py-6 border-b border-gray-100 flex items-center gap-4">
            <div class="shrink-0">
                <img v-if="guru.foto" :src="guru.foto"
                    class="w-16 h-16 rounded-full object-cover ring-2 ring-indigo-100" />
                <div v-else
                    class="w-16 h-16 rounded-full bg-indigo-600 flex items-center justify-center text-white text-2xl font-bold">
                    {{ guru.nama?.charAt(0) }}
                </div>
            </div>
            <div class="min-w-0">
                <h2 class="text-xl font-bold text-gray-900 uppercase truncate">{{ guru.nama }}</h2>
                <p class="text-sm text-gray-500">{{ guru.jabatan }}</p>
                <p class="text-xs text-gray-400 flex flex-wrap gap-x-4 gap-y-0.5 mt-0.5">
                    <span v-if="guru.nip">NIP. {{ guru.nip }}</span>
                    <span v-if="guru.email">{{ guru.email }}</span>
                </p>
            </div>
            <div class="ml-auto text-right shrink-0 hidden sm:block">
                <div class="text-[11px] font-semibold tracking-widest text-gray-400 uppercase">Absensi Mengajar</div>
                <div class="text-lg font-bold text-indigo-600 capitalize">{{ periodeLabel }}</div>
            </div>
        </div>

        <!-- Info kebijakan -->
        <div class="px-6 sm:px-8 pt-4 no-print">
            <p class="text-xs text-gray-500 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2">
                Mengajar jadwal sendiri <b>tidak dibayar vakasi</b> (sudah termasuk gaji pokok).
                Vakasi mengajar hanya untuk <b>sesi pengganti</b>; sesi yang digantikan / tidak terlaksana
                dikenai <b>potongan per sesi</b>.
            </p>
        </div>

        <!-- Ringkasan -->
        <div class="px-6 sm:px-8 py-5 bg-gray-50/60 border-b border-gray-100">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="bg-white rounded-xl border border-gray-100 px-4 py-3 text-center">
                    <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Mengajar Sendiri</div>
                    <div class="text-2xl font-bold mt-0.5 text-gray-800">{{ ringkasan.jp_mengajar_sendiri }} <span class="text-sm font-medium text-gray-400">JP</span></div>
                    <div class="text-[10px] text-gray-400 mt-0.5">{{ ringkasan.sesi_mengajar_sendiri }} sesi · masuk gaji pokok</div>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 px-4 py-3 text-center">
                    <div class="text-[11px] font-semibold text-sky-500 uppercase tracking-wide">Vakasi Pengganti</div>
                    <div class="text-base font-extrabold mt-1.5 text-sky-700 tabular-nums">{{ rupiah(ringkasan.vakasi_pengganti) }}</div>
                    <div class="text-[10px] text-gray-400 mt-0.5">{{ ringkasan.sesi_pengganti }} sesi · {{ ringkasan.jp_pengganti }} JP × {{ rupiah(ringkasan.tarif_per_jp) }}</div>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 px-4 py-3 text-center">
                    <div class="text-[11px] font-semibold text-red-500 uppercase tracking-wide">Potongan</div>
                    <div class="text-base font-extrabold mt-1.5 text-red-600 tabular-nums">− {{ rupiah(ringkasan.potongan_total) }}</div>
                    <div class="text-[10px] text-gray-400 mt-0.5">{{ ringkasan.sesi_dipotong }} sesi × {{ rupiah(ringkasan.potongan_per_sesi) }}</div>
                </div>
                <div class="rounded-xl border px-4 py-3 text-center"
                    :class="ringkasan.net_mengajar >= 0 ? 'bg-emerald-50 border-emerald-100' : 'bg-red-50 border-red-100'">
                    <div class="text-[11px] font-semibold uppercase tracking-wide"
                        :class="ringkasan.net_mengajar >= 0 ? 'text-emerald-500' : 'text-red-500'">Net Mengajar</div>
                    <div class="text-base font-extrabold mt-1.5 tabular-nums"
                        :class="ringkasan.net_mengajar >= 0 ? 'text-emerald-700' : 'text-red-700'">{{ rupiah(ringkasan.net_mengajar) }}</div>
                    <div class="text-[10px] text-gray-400 mt-0.5">vakasi pengganti − potongan</div>
                </div>
            </div>
        </div>

        <!-- Tabel mengajar -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-100/70">
                    <tr class="text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="px-3 py-3 text-center w-10">No</th>
                        <th class="px-3 py-3">Hari</th>
                        <th class="px-3 py-3">Tanggal</th>
                        <th class="px-3 py-3">Kelas</th>
                        <th class="px-3 py-3">Mata Pelajaran</th>
                        <th class="px-3 py-3 text-center">Jam</th>
                        <th class="px-3 py-3 text-center">JP</th>
                        <th class="px-3 py-3 text-center">Status</th>
                        <th class="px-3 py-3 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="!rows.length">
                        <td colspan="9" class="px-4 py-10 text-center text-gray-400 text-sm">
                            Tidak ada data mengajar pada periode ini.
                        </td>
                    </tr>
                    <tr v-for="row in rows" :key="row.no" class="hover:bg-indigo-50/30"
                        :class="row.jenis === 'dipotong' ? 'bg-red-50/30' : (row.jenis === 'pengganti' ? 'bg-sky-50/20' : '')">
                        <td class="px-3 py-2.5 text-center text-gray-400 tabular-nums">{{ row.no }}</td>
                        <td class="px-3 py-2.5 font-medium text-gray-700">{{ row.hari }}</td>
                        <td class="px-3 py-2.5 text-gray-600 tabular-nums">{{ row.tanggal }}</td>
                        <td class="px-3 py-2.5 text-gray-700">{{ row.kelas }}</td>
                        <td class="px-3 py-2.5 text-gray-600">{{ row.mapel }}</td>
                        <td class="px-3 py-2.5 text-center text-gray-600 tabular-nums">{{ row.jam }}</td>
                        <td class="px-3 py-2.5 text-center font-semibold text-gray-800 tabular-nums">{{ row.jp }}</td>
                        <td class="px-3 py-2.5 text-center">
                            <span class="inline-block px-2.5 py-1 rounded-md text-xs font-semibold border"
                                :class="jenisStyle(row.jenis)">
                                {{ row.status_label }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5 text-right tabular-nums"
                            :class="row.subtotal > 0 ? 'font-semibold text-sky-700' : (row.subtotal < 0 ? 'font-semibold text-red-600' : 'text-gray-300')">
                            <template v-if="row.subtotal > 0">{{ rupiah(row.subtotal) }}</template>
                            <template v-else-if="row.subtotal < 0">− {{ rupiah(-row.subtotal) }}</template>
                            <template v-else>—</template>
                        </td>
                    </tr>
                </tbody>
                <tfoot v-if="rows.length" class="bg-gray-50 border-t-2 border-gray-200">
                    <tr class="font-bold text-gray-800">
                        <td colspan="8" class="px-3 py-3 text-right uppercase text-xs tracking-wide text-gray-500">Net dari Mengajar</td>
                        <td class="px-3 py-3 text-right tabular-nums"
                            :class="ringkasan.net_mengajar >= 0 ? 'text-emerald-700' : 'text-red-700'">
                            {{ rupiah(ringkasan.net_mengajar) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>

<script setup>
defineProps({
    guru: { type: Object, default: () => ({}) },
    periodeLabel: { type: String, default: '' },
    rows: { type: Array, default: () => [] },
    ringkasan: { type: Object, default: () => ({}) },
})

function rupiah(n) {
    const v = Number(n || 0)
    return 'Rp ' + v.toLocaleString('id-ID')
}

function jenisStyle(jenis) {
    const map = {
        mengajar_sendiri: 'bg-gray-50 text-gray-600 border-gray-200',
        pengganti:        'bg-sky-50 text-sky-700 border-sky-200',
        dipotong:         'bg-red-50 text-red-700 border-red-200',
        libur:            'bg-orange-50 text-orange-700 border-orange-200',
        izin:             'bg-blue-50 text-blue-700 border-blue-200',
    }
    return map[jenis] ?? 'bg-gray-50 text-gray-500 border-gray-200'
}
</script>

<style scoped>
@media print {
    #report {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .no-print { display: none !important; }
}
</style>
