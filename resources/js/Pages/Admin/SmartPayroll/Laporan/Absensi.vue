<template>
    <AdminLayout title="Laporan Absensi">
        <div class="space-y-6">

            <!-- Header + Mode Switcher -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Laporan Absensi</h1>
                    <p class="text-sm text-gray-500 mt-0.5">{{ meta?.label }}</p>
                </div>
                <div class="flex items-center gap-1 bg-gray-100 p-1 rounded-xl">
                    <button v-for="m in modes" :key="m.value" @click="setMode(m.value)" :class="['px-4 py-2 rounded-lg text-sm font-semibold transition-all',
                        activeMode === m.value
                            ? 'bg-white text-indigo-600 shadow-sm'
                            : 'text-gray-500 hover:text-gray-700']">
                        {{ m.label }}
                    </button>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="bg-white rounded-2xl border border-gray-100 p-4 shadow-sm">
                <div class="flex flex-wrap gap-3 items-end">
                    <div v-if="activeMode === 'harian'" class="flex flex-col gap-1">
                        <label class="text-xs font-medium text-gray-500">Tanggal</label>
                        <input type="date" v-model="form.tanggal"
                            class="border border-gray-200 rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <template v-if="activeMode !== 'harian'">
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-medium text-gray-500">Bulan</label>
                            <select v-model="form.bulan" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
                                <option v-for="(b, i) in bulanList" :key="i + 1" :value="i + 1">{{ b }}</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-medium text-gray-500">Tahun</label>
                            <select v-model="form.tahun" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
                                <option v-for="y in tahunList" :key="y" :value="y">{{ y }}</option>
                            </select>
                        </div>
                    </template>
                    <div v-if="activeMode === 'mingguan'" class="flex flex-col gap-1">
                        <label class="text-xs font-medium text-gray-500">Minggu ke-</label>
                        <select v-model="form.minggu" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
                            <option v-for="n in 5" :key="n" :value="n">Minggu {{ n }}</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-medium text-gray-500">Jabatan</label>
                        <select v-model="form.jabatan_id"
                            class="border border-gray-200 rounded-lg px-3 py-2 text-sm min-w-[140px]">
                            <option value="">Semua</option>
                            <option v-for="j in jabatan" :key="j.id" :value="j.id">{{ j.nama_jabatan }}</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1 flex-1 min-w-[180px]">
                        <label class="text-xs font-medium text-gray-500">Cari</label>
                        <input v-model="form.search" type="text" placeholder="Cari guru..."
                            class="border border-gray-200 rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <button @click="applyFilter"
                        class="px-5 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 transition self-end">
                        Tampilkan
                    </button>
                </div>
            </div>

            <!-- Info Jadwal (mode harian) -->
            <div v-if="activeMode === 'harian' && ringkasan?.jadwal_masuk"
                class="flex flex-wrap gap-4 bg-indigo-50 border border-indigo-100 rounded-xl px-5 py-3">
                <div class="flex items-center gap-2 text-sm text-indigo-700">
                    <span class="font-semibold">Jam Masuk:</span>
                    <span class="font-bold font-mono">{{ ringkasan.jadwal_masuk }}</span>
                </div>
                <div class="flex items-center gap-2 text-sm text-indigo-700">
                    <span class="font-semibold">Toleransi:</span>
                    <span class="font-bold">{{ ringkasan.toleransi }} menit</span>
                </div>
                <div class="flex items-center gap-2 text-sm text-indigo-700">
                    <span class="font-semibold">Batas Terlambat:</span>
                    <span class="font-bold font-mono">{{ batasTerlambat }}</span>
                </div>
                <div v-if="meta?.hari_libur" class="ml-auto">
                    <span
                        class="px-3 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-full border border-red-200">
                        🎉 Hari Libur: {{ meta.hari_libur }}
                    </span>
                </div>
            </div>

            <!-- Stat Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                <div v-for="s in statCards" :key="s.label" :class="['rounded-xl p-4 flex items-center gap-3', s.bg]">
                    <span class="text-xl">{{ s.icon }}</span>
                    <div>
                        <div :class="['text-2xl font-black leading-tight', s.text]">{{ s.value }}</div>
                        <div class="text-xs font-medium opacity-70">{{ s.label }}</div>
                    </div>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════════════════════════ -->
            <!-- TABEL HARIAN -->
            <!-- ════════════════════════════════════════════════════════════════════ -->
            <div v-if="activeMode === 'harian'"
                class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-800">
                        Detail Absensi — {{ meta?.label }}
                    </h2>
                    <span class="text-xs text-gray-400">{{ laporan.length }} guru</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                <th class="px-5 py-3">Guru</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Jam Masuk</th>
                                <th class="px-4 py-3">Jam Pulang</th>
                                <th class="px-4 py-3">Durasi</th>
                                <th class="px-4 py-3">Terlambat</th>
                                <th class="px-4 py-3">Lokasi</th>
                                <th class="px-4 py-3">Foto</th>
                                <th class="px-4 py-3">Ket.</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="row in laporan" :key="row.id" :class="['transition-colors hover:bg-gray-50/60',
                                row.status === 'terlambat' ? 'border-l-2 border-l-amber-400' : '',
                                row.status === 'alfa' ? 'border-l-2 border-l-red-400' : '']">

                                <!-- Guru -->
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="relative">
                                            <img v-if="row.foto" :src="row.foto"
                                                class="w-9 h-9 rounded-full object-cover border-2 border-white shadow-sm" />
                                            <div v-else class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-400 to-indigo-600
                               flex items-center justify-center text-white text-sm font-bold shadow-sm">
                                                {{ row.nama?.charAt(0)?.toUpperCase() }}
                                            </div>
                                            <span v-if="row.is_koreksi"
                                                class="absolute -top-1 -right-1 w-3 h-3 bg-blue-500 rounded-full border-2 border-white"
                                                title="Data koreksi admin"></span>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-800 text-sm leading-tight">{{ row.nama
                                                }}</div>
                                            <div class="text-xs text-gray-400">{{ row.jabatan }}</div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Status -->
                                <td class="px-4 py-3.5">
                                    <span :class="['px-2.5 py-1 rounded-full text-xs font-semibold border',
                                        statusStyle(row.status).badge]">
                                        {{ statusStyle(row.status).label }}
                                    </span>
                                </td>

                                <!-- Jam Masuk -->
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-mono font-semibold text-gray-800">
                                            {{ row.jam_masuk ?? '—' }}
                                        </span>
                                        <span v-if="row.status === 'terlambat'" class="text-amber-500 text-xs">⚠</span>
                                    </div>
                                </td>

                                <!-- Jam Pulang -->
                                <td class="px-4 py-3.5 font-mono text-gray-700">
                                    {{ row.jam_pulang ?? '—' }}
                                </td>

                                <!-- Durasi -->
                                <td class="px-4 py-3.5 text-xs text-gray-500">
                                    {{ row.durasi_label ?? '—' }}
                                </td>

                                <!-- Terlambat -->
                                <td class="px-4 py-3.5">
                                    <div v-if="row.menit_terlambat > 0">
                                        <span class="text-amber-600 font-semibold text-xs block">
                                            {{ row.label_terlambat }}
                                        </span>
                                        <span class="text-xs text-gray-400">{{ row.menit_terlambat }}m</span>
                                    </div>
                                    <span v-else class="text-gray-300 text-sm">—</span>
                                </td>

                                <!-- Lokasi -->
                                <td class="px-4 py-3.5">
                                    <div v-if="row.validasi_lokasi && row.validasi_lokasi !== 'belum'"
                                        class="flex flex-col gap-0.5">
                                        <span :class="['px-2 py-0.5 rounded-md text-xs font-semibold border inline-block',
                                            lokasiStyle(row.validasi_lokasi, row.lokasi_valid).cls]">
                                            {{ row.label_lokasi ?? row.validasi_lokasi }}
                                        </span>
                                        <span v-if="row.jarak_meter" class="text-[10px] text-gray-400">
                                            {{ row.jarak_meter }}m
                                        </span>
                                        <span v-if="row.nama_wifi"
                                            class="text-[10px] text-gray-400 truncate max-w-[90px]">
                                            {{ row.nama_wifi }}
                                        </span>
                                    </div>
                                    <span v-else class="text-gray-300 text-sm">—</span>
                                </td>

                                <!-- Foto -->
                                <td class="px-4 py-3.5">
                                    <div class="flex gap-1.5">
                                        <button v-if="row.foto_masuk_url"
                                            @click="lihatFoto(row.foto_masuk_url, 'Foto Masuk — ' + row.nama)" class="group relative w-10 h-10 rounded-lg overflow-hidden border border-gray-200
                             hover:border-indigo-400 hover:shadow-md transition">
                                            <img :src="row.foto_masuk_url"
                                                class="w-full h-full object-cover group-hover:scale-110 transition" />
                                            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition
                                  flex items-center justify-center opacity-0 group-hover:opacity-100">
                                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                                                </svg>
                                            </div>
                                        </button>
                                        <button v-if="row.foto_pulang_url"
                                            @click="lihatFoto(row.foto_pulang_url, 'Foto Pulang — ' + row.nama)" class="group relative w-10 h-10 rounded-lg overflow-hidden border border-gray-200
                             hover:border-emerald-400 hover:shadow-md transition">
                                            <img :src="row.foto_pulang_url"
                                                class="w-full h-full object-cover group-hover:scale-110 transition" />
                                        </button>
                                        <span v-if="!row.foto_masuk_url && !row.foto_pulang_url"
                                            class="text-gray-300 text-sm">—</span>
                                    </div>
                                </td>

                                <!-- Keterangan -->
                                <td class="px-4 py-3.5 max-w-[120px]">
                                    <span v-if="row.keterangan" :title="row.keterangan"
                                        class="text-xs text-gray-500 truncate block cursor-help">
                                        {{ row.keterangan }}
                                    </span>
                                    <span v-else class="text-gray-300 text-sm">—</span>
                                </td>

                            </tr>
                            <tr v-if="laporan.length === 0">
                                <td colspan="9" class="px-5 py-16 text-center">
                                    <div class="text-gray-300 text-4xl mb-3">📋</div>
                                    <div class="text-gray-400 font-medium">Belum ada data absensi</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════════════════════════ -->
            <!-- TABEL MINGGUAN -->
            <!-- ════════════════════════════════════════════════════════════════════ -->
            <div v-if="activeMode === 'mingguan'"
                class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="font-semibold text-gray-800">Rekap Mingguan — {{ meta?.label }}</h2>
                    <p class="text-xs text-gray-400 mt-0.5">{{ meta?.total_hari }} hari kerja</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                <th class="px-5 py-3">Guru</th>
                                <th class="px-4 py-3 text-center">Hadir</th>
                                <th class="px-4 py-3 text-center">Terlambat</th>
                                <th class="px-4 py-3 text-center">Izin</th>
                                <th class="px-4 py-3 text-center">Sakit</th>
                                <th class="px-4 py-3 text-center">Alfa</th>
                                <th class="px-4 py-3 text-right">Total Terlambat</th>
                                <th class="px-4 py-3 text-right">% Hadir</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="row in laporan" :key="row.id" class="hover:bg-gray-50/60">
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <img v-if="row.foto" :src="row.foto"
                                            class="w-8 h-8 rounded-full object-cover" />
                                        <div v-else class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center
                             text-xs font-bold text-indigo-600">
                                            {{ row.nama?.charAt(0) }}
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-800">{{ row.nama }}</div>
                                            <div class="text-xs text-gray-400">{{ row.jabatan }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 text-center font-bold text-emerald-600">{{ row.hadir }}</td>
                                <td class="px-4 py-3.5 text-center">
                                    <span :class="row.terlambat > 0 ? 'text-amber-600 font-bold' : 'text-gray-300'">
                                        {{ row.terlambat }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-center text-blue-500">{{ row.izin }}</td>
                                <td class="px-4 py-3.5 text-center text-purple-500">{{ row.sakit }}</td>
                                <td class="px-4 py-3.5 text-center">
                                    <span :class="row.alfa > 0 ? 'text-red-600 font-bold' : 'text-gray-300'">{{ row.alfa
                                        }}</span>
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    <span v-if="row.menit_terlambat > 0" class="text-amber-600 font-semibold text-xs">
                                        {{ formatMenit(row.menit_terlambat) }}
                                    </span>
                                    <span v-else class="text-gray-300">—</span>
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    <span :class="['font-bold text-sm',
                                        row.pct_hadir >= 90 ? 'text-emerald-600' :
                                            row.pct_hadir >= 75 ? 'text-amber-600' : 'text-red-500']">
                                        {{ row.pct_hadir }}%
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════════════════════════ -->
            <!-- TABEL BULANAN -->
            <!-- ════════════════════════════════════════════════════════════════════ -->
            <div v-if="activeMode === 'bulanan'"
                class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="font-semibold text-gray-800">Rekap Bulanan — {{ meta?.label }}</h2>
                    <p class="text-xs text-gray-400 mt-0.5">
                        {{ meta?.total_hari_kerja }} hari kerja efektif
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                <th class="px-5 py-3">Guru</th>
                                <th class="px-4 py-3 text-center">Hadir</th>
                                <th class="px-4 py-3 text-center">Terlambat</th>
                                <th class="px-4 py-3 text-center">Izin</th>
                                <th class="px-4 py-3 text-center">Sakit</th>
                                <th class="px-4 py-3 text-center">Alfa</th>
                                <th class="px-4 py-3 text-right">Keterlambatan</th>
                                <th class="px-4 py-3 text-right">% Hadir</th>
                                <th class="px-4 py-3 text-right">Skor</th>
                                <th class="px-4 py-3 text-center">Detail</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="row in laporan" :key="row.id" class="hover:bg-gray-50/60">
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <img v-if="row.foto" :src="row.foto"
                                            class="w-8 h-8 rounded-full object-cover" />
                                        <div v-else class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center
                             text-xs font-bold text-indigo-600">
                                            {{ row.nama?.charAt(0) }}
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-800">{{ row.nama }}</div>
                                            <div class="text-xs text-gray-400">{{ row.jabatan }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 text-center font-bold text-emerald-600">{{ row.hadir }}</td>
                                <td class="px-4 py-3.5 text-center">
                                    <span :class="row.terlambat > 0 ? 'text-amber-600 font-bold' : 'text-gray-300'">
                                        {{ row.terlambat }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-center text-blue-500">{{ row.izin }}</td>
                                <td class="px-4 py-3.5 text-center text-purple-500">{{ row.sakit }}</td>
                                <td class="px-4 py-3.5 text-center">
                                    <span :class="row.alfa > 0 ? 'text-red-600 font-bold' : 'text-gray-300'">{{ row.alfa
                                        }}</span>
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    <span v-if="row.menit_terlambat > 0" class="text-amber-600 font-semibold text-xs">
                                        {{ row.label_terlambat }}
                                    </span>
                                    <span v-else class="text-gray-300">—</span>
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    <span :class="['font-bold text-sm',
                                        row.pct_hadir >= 90 ? 'text-emerald-600' :
                                            row.pct_hadir >= 75 ? 'text-amber-600' : 'text-red-500']">
                                        {{ row.pct_hadir }}%
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    <span :class="['px-2.5 py-1 rounded-lg text-xs font-bold',
                                        row.skor_absensi >= 90 ? 'bg-emerald-50 text-emerald-700' :
                                            row.skor_absensi >= 75 ? 'bg-amber-50 text-amber-700' :
                                                'bg-red-50 text-red-700']">
                                        {{ row.skor_absensi }}%
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-center">
                                    <Link
                                        :href="route('admin.smart-payroll.laporan.kehadiran', { guru_id: row.id, mode: 'bulanan', bulan: form.bulan, tahun: form.tahun })"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold
                                               text-indigo-600 bg-indigo-50 hover:bg-indigo-100 transition-colors">
                                        Lihat
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5l7 7-7 7" />
                                        </svg>
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- Modal Foto -->
        <Teleport to="body">
            <div v-if="fotoModal.url" @click.self="fotoModal = { url: null, title: '' }"
                class="fixed inset-0 bg-black/80 z-[100] flex items-center justify-center p-4">
                <div class="relative max-w-lg w-full">
                    <div class="text-white text-sm font-semibold mb-2 text-center opacity-80">
                        {{ fotoModal.title }}
                    </div>
                    <img :src="fotoModal.url" class="w-full max-h-[75vh] object-contain rounded-2xl shadow-2xl" />
                    <button @click="fotoModal = { url: null, title: '' }"
                        class="absolute -top-10 right-0 text-white/70 hover:text-white text-2xl">✕</button>
                </div>
            </div>
        </Teleport>

    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

// ─── Props ────────────────────────────────────────────────────────────────────
const props = defineProps({
    mode: { type: String, default: 'harian' },
    laporan: { type: Array, default: () => [] },
    ringkasan: { type: Object, default: () => ({}) },
    meta: { type: Object, default: () => ({}) },
    bulan: { type: Number, default: () => new Date().getMonth() + 1 },
    tahun: { type: Number, default: () => new Date().getFullYear() },
    tanggal: { type: String, default: () => new Date().toISOString().slice(0, 10) },
    jabatan: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
})

// ─── Data ─────────────────────────────────────────────────────────────────────
const modes = [
    { value: 'harian', label: 'Harian' },
    { value: 'mingguan', label: 'Mingguan' },
    { value: 'bulanan', label: 'Bulanan' },
]
const bulanList = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
]
const tahunList = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i)
const activeMode = ref(props.filters.mode ?? props.mode)
const fotoModal = ref({ url: null, title: '' })

const form = ref({
    tanggal: props.filters.tanggal ?? props.tanggal,
    bulan: Number(props.filters.bulan ?? props.bulan),
    tahun: Number(props.filters.tahun ?? props.tahun),
    minggu: Number(props.filters.minggu ?? 1),
    jabatan_id: props.filters.jabatan_id ?? '',
    search: props.filters.search ?? '',
})

// ─── Computed ─────────────────────────────────────────────────────────────────
const batasTerlambat = computed(() => {
    if (!props.ringkasan?.jadwal_masuk) return '—'
    const [h, m] = props.ringkasan.jadwal_masuk.split(':').map(Number)
    const tol = Number(props.ringkasan.toleransi ?? 15)
    const total = h * 60 + m + tol
    const hh = String(Math.floor(total / 60)).padStart(2, '0')
    const mm = String(total % 60).padStart(2, '0')
    return hh + ':' + mm
})

const statCards = computed(() => {
    const r = props.ringkasan ?? {}
    const base = [
        { label: 'Hadir', value: r.hadir ?? r.rata_hadir ?? '—', bg: 'bg-emerald-50', text: 'text-emerald-600', icon: '✓' },
        { label: 'Terlambat', value: r.terlambat ?? '—', bg: 'bg-amber-50', text: 'text-amber-600', icon: '⏱' },
        { label: 'Izin', value: r.izin ?? '—', bg: 'bg-blue-50', text: 'text-blue-600', icon: '📋' },
        { label: 'Sakit', value: r.sakit ?? '—', bg: 'bg-purple-50', text: 'text-purple-600', icon: '🏥' },
        { label: 'Alfa', value: r.alfa ?? r.total_alfa ?? '—', bg: 'bg-red-50', text: 'text-red-600', icon: '✗' },
        { label: 'Belum', value: r.belum ?? '—', bg: 'bg-gray-100', text: 'text-gray-500', icon: '○' },
    ]
    return base
})

// ─── Methods ──────────────────────────────────────────────────────────────────
function setMode(mode) {
    activeMode.value = mode
    applyFilter()
}

function applyFilter() {
    router.get(route('admin.laporan.absensi'), {
        mode: activeMode.value,
        tanggal: form.value.tanggal,
        bulan: form.value.bulan,
        tahun: form.value.tahun,
        minggu: form.value.minggu,
        jabatan_id: form.value.jabatan_id,
        search: form.value.search,
    }, { preserveState: true, replace: true })
}

function lihatFoto(url, title = '') {
    fotoModal.value = { url, title }
}

function formatMenit(menit) {
    if (!menit) return '—'
    const j = Math.floor(menit / 60)
    const m = menit % 60
    return j > 0 ? j + 'j ' + m + 'm' : menit + 'm'
}

// ─── Style Helpers ────────────────────────────────────────────────────────────
function statusStyle(status) {
    const map = {
        hadir: { badge: 'bg-emerald-50 text-emerald-700 border-emerald-200', label: 'Hadir' },
        terlambat: { badge: 'bg-amber-50 text-amber-700 border-amber-200', label: 'Terlambat' },
        izin: { badge: 'bg-blue-50 text-blue-700 border-blue-200', label: 'Izin' },
        izin_sakit: { badge: 'bg-blue-50 text-blue-700 border-blue-200', label: 'Izin Sakit' },
        sakit: { badge: 'bg-purple-50 text-purple-700 border-purple-200', label: 'Sakit' },
        alfa: { badge: 'bg-red-50 text-red-700 border-red-200', label: 'Alfa' },
        dinas_luar: { badge: 'bg-indigo-50 text-indigo-700 border-indigo-200', label: 'Dinas Luar' },
        libur: { badge: 'bg-gray-50 text-gray-500 border-gray-200', label: 'Libur' },
        belum: { badge: 'bg-gray-50 text-gray-400 border-gray-200', label: 'Belum Absen' },
    }
    return map[status] ?? { badge: 'bg-gray-50 text-gray-500 border-gray-200', label: status ?? '—' }
}

function lokasiStyle(tipe, valid) {
    if (valid) return { cls: 'bg-emerald-50 text-emerald-700 border-emerald-200' }
    if (tipe === 'invalid') return { cls: 'bg-red-50 text-red-700 border-red-200' }
    return { cls: 'bg-gray-50 text-gray-500 border-gray-200' }
}
</script>