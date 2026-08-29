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
                <div class="text-[11px] font-semibold tracking-widest text-gray-400 uppercase">Periode Kehadiran</div>
                <div class="text-lg font-bold text-indigo-600 capitalize">{{ periodeLabel }}</div>
            </div>
        </div>

        <!-- Ringkasan -->
        <div class="px-6 sm:px-8 py-5 bg-gray-50/60 border-b border-gray-100">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="bg-white rounded-xl border border-gray-100 px-4 py-3 text-center">
                    <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Alpha</div>
                    <div class="text-2xl font-bold mt-0.5" :class="ringkasan.alpha > 0 ? 'text-red-600' : 'text-gray-800'">
                        {{ ringkasan.alpha }}
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 px-4 py-3 text-center">
                    <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Terlambat</div>
                    <div class="text-base font-bold mt-1.5"
                        :class="ringkasan.total_menit_terlambat > 0 ? 'text-amber-600' : 'text-gray-800'">
                        {{ ringkasan.terlambat_jam }}<span class="text-xs font-medium">j</span>
                        {{ ringkasan.terlambat_menit }}<span class="text-xs font-medium">m</span>
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 px-4 py-3 text-center">
                    <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Sakit</div>
                    <div class="text-2xl font-bold mt-0.5 text-purple-600">{{ ringkasan.sakit }}</div>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 px-4 py-3 text-center">
                    <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Izin</div>
                    <div class="text-2xl font-bold mt-0.5 text-blue-600">{{ ringkasan.izin }}</div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 mt-4">
                <div class="flex items-center gap-4 text-xs text-gray-500">
                    <span>Hadir: <b class="text-emerald-600">{{ ringkasan.hadir }}</b></span>
                    <span>Hari Kerja: <b class="text-gray-700">{{ ringkasan.total_hari_kerja }}</b></span>
                    <span>Skor: <b class="text-gray-700">{{ ringkasan.skor }}%</b></span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Predikat Absensi</span>
                    <span class="px-3 py-1 rounded-lg text-xs font-bold border" :class="predikatStyle">
                        {{ ringkasan.predikat }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Tabel harian -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-100/70">
                    <tr class="text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-3">Hari</th>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3 text-center">Jam Masuk</th>
                        <th class="px-4 py-3 text-center">Jam Keluar</th>
                        <th class="px-4 py-3 text-center">Shift</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="!rows.length">
                        <td colspan="7" class="px-4 py-10 text-center text-gray-400 text-sm">
                            Tidak ada data pada periode ini.
                        </td>
                    </tr>
                    <tr v-for="row in rows" :key="row.tanggal_raw"
                        :class="row.is_weekend ? 'bg-gray-50/40' : ''" class="hover:bg-indigo-50/30">
                        <td class="px-4 py-2.5 font-medium text-gray-700">{{ row.hari }}</td>
                        <td class="px-4 py-2.5 text-gray-600 tabular-nums">{{ row.tanggal }}</td>
                        <td class="px-4 py-2.5 text-center tabular-nums"
                            :class="row.jam_masuk === '-' ? 'text-gray-300' : 'text-gray-700'">
                            {{ row.jam_masuk }}
                        </td>
                        <td class="px-4 py-2.5 text-center tabular-nums"
                            :class="row.jam_pulang === '-' ? 'text-gray-300' : 'text-gray-700'">
                            {{ row.jam_pulang }}
                        </td>
                        <td class="px-4 py-2.5 text-center text-gray-400">{{ row.shift }}</td>
                        <td class="px-4 py-2.5 text-center">
                            <span class="inline-block px-2.5 py-1 rounded-md text-xs font-semibold border"
                                :class="statusStyle(row.status)">
                                {{ row.status_label }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-gray-500 text-xs">
                            <span v-if="row.label_terlambat" class="text-amber-600 font-semibold mr-1">
                                +{{ row.label_terlambat }}
                            </span>
                            {{ row.keterangan || '—' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    guru: { type: Object, default: () => ({}) },
    periodeLabel: { type: String, default: '' },
    rows: { type: Array, default: () => [] },
    ringkasan: { type: Object, default: () => ({}) },
})

const predikatStyle = computed(() => {
    const p = props.ringkasan?.predikat
    if (p === 'SANGAT BAIK') return 'bg-emerald-50 text-emerald-700 border-emerald-200'
    if (p === 'BAIK') return 'bg-green-50 text-green-700 border-green-200'
    if (p === 'CUKUP') return 'bg-amber-50 text-amber-700 border-amber-200'
    return 'bg-red-50 text-red-700 border-red-200'
})

function statusStyle(status) {
    const map = {
        hadir: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        terlambat: 'bg-amber-50 text-amber-700 border-amber-200',
        izin: 'bg-blue-50 text-blue-700 border-blue-200',
        izin_sakit: 'bg-blue-50 text-blue-700 border-blue-200',
        cuti: 'bg-sky-50 text-sky-700 border-sky-200',
        sakit: 'bg-purple-50 text-purple-700 border-purple-200',
        dinas_luar: 'bg-indigo-50 text-indigo-700 border-indigo-200',
        alfa: 'bg-red-50 text-red-700 border-red-200',
        libur: 'bg-orange-50 text-orange-700 border-orange-200',
        off: 'bg-gray-100 text-gray-500 border-gray-200',
        belum: 'bg-gray-50 text-gray-400 border-gray-200',
    }
    return map[status] ?? 'bg-gray-50 text-gray-500 border-gray-200'
}
</script>

<style scoped>
/* Pastikan warna badge/status ikut tercetak (browser default menghapus background) */
@media print {
    #report {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>
