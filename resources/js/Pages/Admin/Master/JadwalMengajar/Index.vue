<template>
    <AdminLayout title="Jadwal Mengajar" subtitle="Master Data">

        <Head title="Jadwal Mengajar" />

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Jadwal Mengajar</h2>
                <p class="text-sm text-gray-400 mt-0.5">
                    <span v-if="tahunAjaran">{{ tahunAjaran.nama }}</span>
                    <span v-else class="text-amber-500">Belum ada tahun ajaran aktif</span>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Link :href="route('admin.master.mata-pelajaran.index')"
                    class="px-3.5 py-2 rounded-xl border border-gray-200 text-sm text-gray-600 hover:bg-gray-50 transition-colors flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    Mata Pelajaran
                </Link>
                <button @click="showModalTambah = true"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm shadow-indigo-200">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Tambah Slot
                </button>
            </div>
        </div>

        <!-- Filter -->
        <div class="flex items-center gap-3 mb-5">
            <select v-model="filterGuru"
                class="px-3 py-2 rounded-xl border border-gray-200 text-sm text-gray-600 focus:outline-none focus:border-indigo-500 bg-white min-w-48">
                <option value="">Semua Guru</option>
                <option v-for="g in guru" :key="g.id" :value="g.id">{{ g.nama }}</option>
            </select>
            <div class="flex gap-1.5 flex-wrap">
                <button v-for="h in hariAll" :key="h.key" @click="filterHari = filterHari === h.key ? '' : h.key"
                    :class="[
                        'px-3 py-1.5 rounded-xl text-xs font-medium transition-all border',
                        filterHari === h.key
                            ? 'bg-indigo-600 text-white border-indigo-600'
                            : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'
                    ]">
                    {{ h.label }}
                </button>
            </div>

            <!-- Toggle tampilan -->
            <div class="ml-auto flex items-center gap-1 bg-gray-100 rounded-xl p-1">
                <button @click="viewMode = 'guru'" :class="['px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors', viewMode === 'guru' ? 'bg-white text-indigo-700 shadow-sm' : 'text-gray-500']">Per Guru</button>
                <button @click="viewMode = 'harian'" :class="['px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5', viewMode === 'harian' ? 'bg-white text-indigo-700 shadow-sm' : 'text-gray-500']">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 14h18M3 6h18M3 18h18" /></svg>
                    Harian (Tabel)
                </button>
            </div>
        </div>

        <!-- View: Per Guru -->
        <div v-if="viewMode === 'guru'" class="space-y-4">
            <div v-for="guru in guruFiltered" :key="guru.id"
                class="bg-white rounded-2xl border border-gray-200 overflow-hidden">

                <!-- Header guru -->
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <img v-if="guru.foto" :src="guru.foto" class="w-9 h-9 rounded-full object-cover shrink-0" />
                        <div v-else
                            class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center shrink-0">
                            <span class="text-sm font-bold text-indigo-700">{{ guru.nama?.charAt(0) }}</span>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-800">{{ guru.nama }}</p>
                            <p class="text-xs text-gray-400">{{ guru.jabatan }}</p>
                        </div>
                    </div>
                    <!-- JP stats guru ini -->
                    <div v-if="jpStats[guru.id]" class="flex items-center gap-4 text-right">
                        <div>
                            <p class="text-base font-bold text-indigo-700">{{ jpStats[guru.id].total_jp_minggu }} JP</p>
                            <p class="text-xs text-gray-400">per minggu</p>
                        </div>
                        <div>
                            <p class="text-base font-bold text-teal-700">~{{ jpStats[guru.id].total_jp_bulan }} JP</p>
                            <p class="text-xs text-gray-400">estimasi/bulan</p>
                        </div>
                    </div>
                </div>

                <!-- Grid hari -->
                <div
                    class="grid grid-cols-1 md:grid-cols-5 lg:grid-cols-7 divide-y md:divide-y-0 md:divide-x divide-gray-50">
                    <div v-for="h in hariAll" :key="h.key" v-show="!filterHari || filterHari === h.key"
                        class="px-3 py-3 min-h-16">

                        <!-- Label hari -->
                        <div class="flex items-center justify-between mb-2">
                            <span :class="['text-xs font-semibold px-2 py-0.5 rounded-lg', h.bg, h.text]">
                                {{ h.short }}
                            </span>
                            <!-- Tombol tambah slot di hari ini -->
                            <button @click="tambahSlotHari(guru.id, h.key)"
                                class="w-5 h-5 rounded-md bg-gray-100 hover:bg-indigo-100 text-gray-400 hover:text-indigo-600 flex items-center justify-center transition-colors">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                        d="M12 4v16m8-8H4" />
                                </svg>
                            </button>
                        </div>

                        <!-- Slot-slot di hari ini -->
                        <div class="space-y-1.5">
                            <div v-for="slot in getSlot(guru.id, h.key)" :key="slot.id" :class="['group relative rounded-xl p-2 border transition-all cursor-pointer',
                                badgeMapel(slot.mapel_kategori).bg, 'border-transparent hover:border-gray-300']"
                                @click="editSlot(slot)">
                                <!-- Jam -->
                                <p class="text-xs font-bold text-gray-700">
                                    {{ slot.jam_mulai }}–{{ slot.jam_selesai }}
                                </p>
                                <!-- Mapel -->
                                <p
                                    :class="['text-xs font-semibold truncate mt-0.5', badgeMapel(slot.mapel_kategori).text]">
                                    {{ slot.mapel_nama }}
                                </p>
                                <!-- Kelas + JP -->
                                <div class="flex items-center justify-between mt-1">
                                    <span class="text-xs text-gray-500">{{ slot.kelas }}</span>
                                    <span class="text-xs font-bold text-gray-600">{{ slot.jumlah_jp }}JP</span>
                                </div>
                                <!-- Hapus -->
                                <button @click.stop="hapusSlot(slot)"
                                    class="absolute top-1 right-1 w-4 h-4 rounded opacity-0 group-hover:opacity-100 bg-red-100 hover:bg-red-200 text-red-500 flex items-center justify-center transition-all">
                                    <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Empty day -->
                            <div v-if="!getSlot(guru.id, h.key).length"
                                class="text-center py-2 text-xs text-gray-300 italic">
                                Libur
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- View: Harian (Tabel) -->
        <div v-if="viewMode === 'harian'" class="space-y-5">
            <div v-for="h in jadwalHarian" :key="h.key" class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <!-- Header hari -->
                <div class="flex items-center gap-3 px-5 py-3.5 border-b border-gray-100">
                    <span :class="['inline-flex items-center justify-center w-10 h-10 rounded-xl text-sm font-bold', h.bg, h.text]">{{ h.short }}</span>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">{{ h.label }}</p>
                        <p class="text-xs text-gray-400">{{ h.rows.length }} slot mengajar</p>
                    </div>
                </div>
                <!-- Tabel -->
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] uppercase tracking-wide text-gray-400 bg-gray-50/70">
                                <th class="px-5 py-2.5 font-semibold">Kelas</th>
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Jam Mengajar</th>
                                <th class="px-4 py-2.5 font-semibold">Nama Guru</th>
                                <th class="px-5 py-2.5 font-semibold">Mata Pelajaran</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="j in h.rows" :key="j.id" class="hover:bg-indigo-50/40 transition-colors">
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 text-xs font-bold">{{ j.kelas || '—' }}</span>
                                    <span v-if="j.ruangan && j.ruangan !== '—'" class="ml-1.5 text-[11px] text-gray-400">R. {{ j.ruangan }}</span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="font-semibold text-gray-700 tabular-nums">{{ jam5(j.jam_mulai) }}–{{ jam5(j.jam_selesai) }}</span>
                                    <span class="ml-1 text-[11px] text-gray-400">· {{ j.jumlah_jp }} JP</span>
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ j.guru_nama }}</td>
                                <td class="px-5 py-3">
                                    <span class="text-gray-800">{{ j.mapel_nama }}</span>
                                    <span v-if="j.mapel_kode" class="ml-1 text-[11px] text-gray-400">({{ j.mapel_kode }})</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div v-if="!jadwalHarian.length" class="bg-white rounded-2xl border border-gray-200 py-16 text-center">
                <p class="text-sm text-gray-400">Tidak ada jadwal untuk filter ini.</p>
            </div>
        </div>

        <!-- Empty state (per guru) -->
        <div v-if="viewMode === 'guru' && !guruFiltered.length" class="bg-white rounded-2xl border border-gray-200 py-16 text-center">
            <p class="text-sm text-gray-400">
                {{ filterGuru ? 'Guru ini belum memiliki jadwal.' : 'Belum ada jadwal mengajar.' }}
            </p>
            <button @click="showModalTambah = true"
                class="mt-3 inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors">
                Tambah Jadwal Sekarang
            </button>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- MODAL TAMBAH / EDIT SLOT                                          -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <Transition name="modal">
            <div v-if="showModalTambah || showModalEdit"
                class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="closeModal" />
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">

                    <!-- Header modal -->
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="text-base font-semibold text-gray-900">
                            {{ showModalEdit ? 'Edit Slot Jadwal' : 'Tambah Slot Jadwal' }}
                        </h3>
                        <p class="text-xs text-gray-400 mt-0.5">
                            Guru bisa memiliki banyak slot di hari yang sama
                        </p>
                    </div>

                    <div class="px-6 py-5 space-y-4 max-h-[70vh] overflow-y-auto">

                        <!-- Tahun Ajaran -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Tahun Ajaran <span class="text-red-500">*</span>
                            </label>
                            <select v-model="slotForm.tahun_ajaran_id" :class="inputCls(slotErrors.tahun_ajaran_id)">
                                <option value="">-- Pilih --</option>
                                <option v-for="t in semua_tahun" :key="t.id" :value="t.id">
                                    {{ t.nama }} — {{ t.semester }}
                                </option>
                            </select>
                            <ErrMsg :e="slotErrors.tahun_ajaran_id" />
                        </div>

                        <!-- Guru -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Guru <span class="text-red-500">*</span>
                            </label>
                            <select v-model="slotForm.tenaga_pendidik_id"
                                :class="inputCls(slotErrors.tenaga_pendidik_id)">
                                <option value="">-- Pilih Guru --</option>
                                <option v-for="g in guru" :key="g.id" :value="g.id">
                                    {{ g.nama }}{{ g.jabatan ? ` — ${g.jabatan}` : '' }}
                                </option>
                            </select>
                            <ErrMsg :e="slotErrors.tenaga_pendidik_id" />
                        </div>

                        <!-- Hari -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Hari <span class="text-red-500">*</span>
                            </label>
                            <div class="flex flex-wrap gap-2">
                                <button v-for="h in hariAll" :key="h.key" type="button" @click="slotForm.hari = h.key"
                                    :class="[
                                        'px-3 py-1.5 rounded-xl text-sm font-medium border-2 transition-all',
                                        slotForm.hari === h.key
                                            ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                            : 'border-gray-200 text-gray-600 hover:border-gray-300'
                                    ]">
                                    {{ h.label }}
                                </button>
                            </div>
                            <ErrMsg :e="slotErrors.hari" />
                        </div>

                        <!-- Jam Mulai - Selesai - JP -->
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Jam Mulai <span class="text-red-500">*</span>
                                </label>
                                <input v-model="slotForm.jam_mulai" type="time"
                                    :class="inputCls(slotErrors.jam_mulai)" />
                                <ErrMsg :e="slotErrors.jam_mulai" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Jam Selesai <span class="text-red-500">*</span>
                                </label>
                                <input v-model="slotForm.jam_selesai" type="time"
                                    :class="inputCls(slotErrors.jam_selesai)" />
                                <ErrMsg :e="slotErrors.jam_selesai" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    JP <span class="text-red-500">*</span>
                                </label>
                                <input v-model.number="slotForm.jumlah_jp" type="number" min="1" max="20"
                                    :class="inputCls(slotErrors.jumlah_jp)" />
                                <ErrMsg :e="slotErrors.jumlah_jp" />
                            </div>
                        </div>

                        <!-- Info JP & vakasi -->
                        <div v-if="slotForm.jumlah_jp > 0"
                            class="flex items-center gap-2 px-3.5 py-2.5 bg-amber-50 border border-amber-100 rounded-xl text-xs text-amber-700">
                            <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Vakasi mengajar: <strong>{{ slotForm.jumlah_jp }} JP × rate/JP</strong> dari setting vakasi
                            mengajar yang aktif
                        </div>

                        <!-- Mata Pelajaran -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Mata Pelajaran <span class="text-red-500">*</span>
                            </label>
                            <select v-model="slotForm.mata_pelajaran_id"
                                :class="inputCls(slotErrors.mata_pelajaran_id)">
                                <option value="">-- Pilih Mata Pelajaran --</option>
                                <optgroup v-for="(items, ting) in mapelByTingkat" :key="ting"
                                    :label="ting === 'null' ? 'Semua Tingkat' : 'Tingkat ' + ting">
                                    <option v-for="m in items" :key="m.id" :value="m.id">
                                        [{{ m.kode }}] {{ m.nama }}
                                    </option>
                                </optgroup>
                            </select>
                            <ErrMsg :e="slotErrors.mata_pelajaran_id" />
                        </div>

                        <!-- Kelas & Ruangan -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Kelas <span class="text-red-500">*</span>
                                </label>
                                <select v-model="slotForm.kelas_id" :class="inputCls(slotErrors.kelas_id)">
                                    <option value="" disabled>Pilih kelas...</option>
                                    <option v-for="k in kelasOptions" :key="k.id" :value="k.id">{{ k.nama }}</option>
                                </select>
                                <ErrMsg :e="slotErrors.kelas_id" />
                                <p v-if="!kelasOptions.length" class="mt-1 text-xs text-amber-600">
                                    Belum ada kelas. Tambahkan di Smart Education → Kelas.
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Ruangan</label>
                                <input v-model="slotForm.ruangan" type="text" placeholder="cth: Lab IPA, Aula"
                                    :class="inputCls()" />
                            </div>
                        </div>
                    </div>

                    <!-- Footer modal -->
                    <div class="flex gap-3 px-6 pb-5 pt-1 border-t border-gray-100">
                        <button @click="closeModal"
                            class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50">
                            Batal
                        </button>
                        <button @click="submitSlot" :disabled="menyimpan"
                            class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-60 transition-colors">
                            {{ menyimpan ? 'Menyimpan...' : (showModalEdit ? 'Simpan Perubahan' : 'Tambah Slot') }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </AdminLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { confirm } from '@/composables/useConfirm'

const props = defineProps({
    jadwal: { type: Array, default: () => [] },
    guruList: { type: Array, default: () => [] },
    jpStats: { type: Object, default: () => ({}) },
    tahunAjaran: { type: Object, default: null },
    semua_tahun: { type: Array, default: () => [] },
    mapel: { type: Array, default: () => [] },
    guru: { type: Array, default: () => [] },
    kelasList: { type: Array, default: () => [] },
})

// ── Filter ────────────────────────────────────────────────────────────────────
const filterGuru = ref('')
const filterHari = ref('')
const viewMode = ref('guru')   // 'guru' | 'harian'

const hariAll = [
    { key: 'senin', label: 'Senin', short: 'Sen', bg: 'bg-blue-100', text: 'text-blue-800' },
    { key: 'selasa', label: 'Selasa', short: 'Sel', bg: 'bg-violet-100', text: 'text-violet-800' },
    { key: 'rabu', label: 'Rabu', short: 'Rab', bg: 'bg-teal-100', text: 'text-teal-800' },
    { key: 'kamis', label: 'Kamis', short: 'Kam', bg: 'bg-amber-100', text: 'text-amber-800' },
    { key: 'jumat', label: "Jum'at", short: 'Jum', bg: 'bg-indigo-100', text: 'text-indigo-800' },
    { key: 'sabtu', label: 'Sabtu', short: 'Sab', bg: 'bg-rose-100', text: 'text-rose-800' },
    { key: 'ahad', label: 'Ahad', short: 'Ahd', bg: 'bg-gray-100', text: 'text-gray-600' },
]

// Guru yang ditampilkan — filter berdasarkan pilihan dan yang punya jadwal
const guruFiltered = computed(() => {
    const guruPunyaJadwal = new Set(props.jadwal.map(j => j.guru_id))
    return props.guruList.filter(g =>
        (!filterGuru.value || g.id === filterGuru.value) &&
        (guruPunyaJadwal.has(g.id) || !filterGuru.value || g.id === filterGuru.value)
    )
})

// Helper: ambil slot untuk guru + hari tertentu
function getSlot(guruId, hari) {
    return props.jadwal.filter(j => j.guru_id === guruId && j.hari === hari)
}

// View HARIAN — jadwal dikelompokkan per hari, tiap hari tabel (Kelas, Jam, Guru, Mapel)
const jam5 = (t) => (t || '').slice(0, 5)
const jadwalHarian = computed(() => {
    return hariAll
        .filter(h => !filterHari.value || filterHari.value === h.key)
        .map(h => ({
            ...h,
            rows: props.jadwal
                .filter(j => j.hari === h.key && (!filterGuru.value || j.guru_id === filterGuru.value))
                .slice()
                .sort((a, b) =>
                    String(a.kelas || '').localeCompare(String(b.kelas || ''), 'id', { numeric: true }) ||
                    String(a.jam_mulai || '').localeCompare(String(b.jam_mulai || '')),
                ),
        }))
        .filter(h => h.rows.length)
})

// Warna slot berdasarkan kategori mapel
function badgeMapel(kategori) {
    const map = {
        'Agama': { bg: 'bg-emerald-50', text: 'text-emerald-700' },
        'Umum': { bg: 'bg-blue-50', text: 'text-blue-700' },
        'Ekskul': { bg: 'bg-purple-50', text: 'text-purple-700' },
        'Tahfidz': { bg: 'bg-amber-50', text: 'text-amber-700' },
    }
    return map[kategori] ?? { bg: 'bg-gray-50', text: 'text-gray-700' }
}

// Kelas Smart Education yang cocok dengan tahun ajaran terpilih di form.
const kelasOptions = computed(() =>
    props.kelasList.filter(k =>
        !slotForm.tahun_ajaran_id || !k.tahun_ajaran_id ||
        Number(k.tahun_ajaran_id) === Number(slotForm.tahun_ajaran_id)
    )
)

// Mapel grouped by tingkat untuk select
const mapelByTingkat = computed(() => {
    const g = {}
    props.mapel.forEach(m => {
        const k = m.tingkat ?? 'null'
        if (!g[k]) g[k] = []
        g[k].push(m)
    })
    return g
})

// ── Modal Slot ────────────────────────────────────────────────────────────────
const showModalTambah = ref(false)
const showModalEdit = ref(false)
const menyimpan = ref(false)
const editTargetId = ref(null)
const slotErrors = ref({})

const defaultForm = () => ({
    tahun_ajaran_id: props.tahunAjaran?.id ?? '',
    tenaga_pendidik_id: '',
    mata_pelajaran_id: '',
    hari: '',
    jam_mulai: '07:30',
    jam_selesai: '08:10',
    jumlah_jp: 1,
    kelas_id: '',
    ruangan: '',
})

const slotForm = reactive(defaultForm())

function tambahSlotHari(guruId, hari) {
    Object.assign(slotForm, defaultForm())
    slotForm.tenaga_pendidik_id = guruId
    slotForm.hari = hari
    slotErrors.value = {}
    showModalTambah.value = true
}

function editSlot(slot) {
    Object.assign(slotForm, {
        tahun_ajaran_id: slot.tahun_ajaran_id ?? props.tahunAjaran?.id ?? '',
        tenaga_pendidik_id: slot.guru_id,
        mata_pelajaran_id: slot.mapel_id,
        hari: slot.hari,
        jam_mulai: slot.jam_mulai,
        jam_selesai: slot.jam_selesai,
        jumlah_jp: slot.jumlah_jp,
        kelas_id: slot.kelas_id ?? '',
        ruangan: slot.ruangan ?? '',
    })
    editTargetId.value = slot.id
    slotErrors.value = {}
    showModalEdit.value = true
}

function closeModal() {
    showModalTambah.value = false
    showModalEdit.value = false
    editTargetId.value = null
    slotErrors.value = {}
    Object.assign(slotForm, defaultForm())
}

function submitSlot() {
    menyimpan.value = true
    const payload = { ...slotForm }

    if (showModalEdit.value) {
        router.put(route('admin.master.jadwal-mengajar.update', editTargetId.value), payload, {
            onSuccess: () => closeModal(),
            onError: (e) => { slotErrors.value = e },
            onFinish: () => menyimpan.value = false,
        })
    } else {
        router.post(route('admin.master.jadwal-mengajar.store'), payload, {
            onSuccess: () => closeModal(),
            onError: (e) => { slotErrors.value = e },
            onFinish: () => menyimpan.value = false,
        })
    }
}

async function hapusSlot(slot) {
    if (!(await confirm({ title: 'Hapus slot jadwal?', message: `${slot.mapel_nama} (${slot.jam_mulai}–${slot.jam_selesai}) ${slot.kelas}`, variant: 'danger', confirmLabel: 'Ya, Hapus' }))) return
    router.delete(route('admin.master.jadwal-mengajar.destroy', slot.id), { preserveScroll: true })
}

function inputCls(e) {
    const b = 'w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 transition-all bg-white'
    return e ? `${b} border-red-300 focus:ring-red-100` : `${b} border-gray-200 focus:border-indigo-500 focus:ring-indigo-100`
}
</script>

<script>
const ErrMsg = { props: { e: String }, template: `<p v-if="e" class="mt-1 text-xs text-red-500">{{ e }}</p>` }
export { ErrMsg }
</script>

<style scoped>
.modal-enter-active {
    transition: all 0.2s ease;
}

.modal-leave-active {
    transition: all 0.15s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>