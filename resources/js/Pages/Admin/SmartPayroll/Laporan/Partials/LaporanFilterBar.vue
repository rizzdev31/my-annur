<template>
    <div class="no-print bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5 mb-5">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

            <!-- Guru selector (combobox) -->
            <div class="lg:col-span-5">
                <label class="block text-xs font-semibold text-gray-500 mb-1.5">Guru</label>
                <div class="relative" ref="guruBox">
                    <button type="button" @click="toggleDropdown"
                        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl border border-gray-200 bg-white
                               text-left hover:border-indigo-300 focus:ring-2 focus:ring-indigo-200 transition-colors">
                        <template v-if="selectedGuru">
                            <img v-if="selectedGuru.foto" :src="selectedGuru.foto"
                                class="w-8 h-8 rounded-full object-cover" />
                            <div v-else
                                class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600">
                                {{ selectedGuru.nama?.charAt(0) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 truncate">{{ selectedGuru.nama }}</div>
                                <div class="text-xs text-gray-400 truncate">{{ selectedGuru.jabatan }}</div>
                            </div>
                        </template>
                        <span v-else class="text-sm text-gray-400 flex-1">Pilih guru…</span>
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div v-if="showDropdown"
                        class="absolute z-30 mt-1.5 w-full bg-white rounded-xl border border-gray-200 shadow-lg overflow-hidden">
                        <div class="p-2 border-b border-gray-100">
                            <input v-model="search" type="text" placeholder="Cari nama guru…" autofocus
                                class="w-full px-3 py-2 rounded-lg text-sm border border-gray-200 focus:ring-2 focus:ring-indigo-200" />
                        </div>
                        <ul class="max-h-64 overflow-y-auto py-1">
                            <li v-for="g in filteredGuru" :key="g.id">
                                <button type="button" @click="pilihGuru(g)"
                                    class="w-full flex items-center gap-3 px-3 py-2 text-left hover:bg-indigo-50 transition-colors"
                                    :class="g.id === form.guru_id ? 'bg-indigo-50' : ''">
                                    <img v-if="g.foto" :src="g.foto" class="w-7 h-7 rounded-full object-cover" />
                                    <div v-else
                                        class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center text-[11px] font-bold text-indigo-600">
                                        {{ g.nama?.charAt(0) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-sm font-medium text-gray-700 truncate">{{ g.nama }}</div>
                                        <div class="text-[11px] text-gray-400 truncate">{{ g.jabatan }}</div>
                                    </div>
                                </button>
                            </li>
                            <li v-if="!filteredGuru.length" class="px-3 py-6 text-center text-sm text-gray-400">
                                Guru tidak ditemukan.
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Mode -->
            <div class="lg:col-span-3">
                <label class="block text-xs font-semibold text-gray-500 mb-1.5">Periode</label>
                <div class="flex rounded-xl border border-gray-200 p-0.5 bg-gray-50">
                    <button v-for="m in modes" :key="m.value" type="button" @click="setMode(m.value)"
                        class="flex-1 px-2 py-2 rounded-lg text-xs font-semibold transition-colors"
                        :class="form.mode === m.value
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'text-gray-500 hover:text-gray-700'">
                        {{ m.label }}
                    </button>
                </div>
            </div>

            <!-- Kontrol tanggal/bulan -->
            <div class="lg:col-span-4">
                <label class="block text-xs font-semibold text-gray-500 mb-1.5">{{ controlLabel }}</label>

                <div v-if="form.mode === 'bulanan'" class="flex gap-2">
                    <select v-model.number="form.bulan" @change="applyFilter"
                        class="flex-1 px-3 py-2.5 rounded-xl text-sm border border-gray-200 bg-white focus:ring-2 focus:ring-indigo-200">
                        <option v-for="(b, i) in bulanList" :key="i" :value="i + 1">{{ b }}</option>
                    </select>
                    <select v-model.number="form.tahun" @change="applyFilter"
                        class="w-28 px-3 py-2.5 rounded-xl text-sm border border-gray-200 bg-white focus:ring-2 focus:ring-indigo-200">
                        <option v-for="t in tahunList" :key="t" :value="t">{{ t }}</option>
                    </select>
                </div>

                <div v-else-if="form.mode === 'harian'" class="flex items-center gap-2">
                    <input v-model="form.tanggal_awal" @change="applyFilter" type="date"
                        class="flex-1 min-w-0 px-3 py-2.5 rounded-xl text-sm border border-gray-200 bg-white focus:ring-2 focus:ring-indigo-200" />
                    <span class="text-xs text-gray-400 shrink-0">s/d</span>
                    <input v-model="form.tanggal_akhir" @change="applyFilter" type="date" :min="form.tanggal_awal"
                        class="flex-1 min-w-0 px-3 py-2.5 rounded-xl text-sm border border-gray-200 bg-white focus:ring-2 focus:ring-indigo-200" />
                </div>

                <input v-else v-model="form.tanggal" @change="applyFilter" type="date"
                    class="w-full px-3 py-2.5 rounded-xl text-sm border border-gray-200 bg-white focus:ring-2 focus:ring-indigo-200" />
            </div>
        </div>

        <!-- Aksi cetak -->
        <div v-if="canPrint" class="flex justify-end mt-4 pt-4 border-t border-gray-100">
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
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    guruList: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    routeName: { type: String, required: true },
    canPrint: { type: Boolean, default: false },
})

const modes = [
    { value: 'harian', label: 'Harian' },
    { value: 'mingguan', label: 'Mingguan' },
    { value: 'bulanan', label: 'Bulanan' },
]
const bulanList = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]
const tahunList = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i)

const today = new Date().toISOString().slice(0, 10)
const firstOfMonth = today.slice(0, 8) + '01'

const form = ref({
    guru_id: props.filters.guru_id ?? null,
    mode: props.filters.mode ?? 'bulanan',
    bulan: Number(props.filters.bulan ?? new Date().getMonth() + 1),
    tahun: Number(props.filters.tahun ?? new Date().getFullYear()),
    tanggal: props.filters.tanggal ?? today,
    tanggal_awal: props.filters.tanggal_awal ?? firstOfMonth,
    tanggal_akhir: props.filters.tanggal_akhir ?? today,
})

const controlLabel = computed(() => ({
    bulanan: 'Bulan & Tahun',
    harian: 'Rentang Tanggal',
    mingguan: 'Tanggal (pilih 1 hari dalam minggu)',
}[form.value.mode] ?? 'Tanggal'))

const search = ref('')
const showDropdown = ref(false)
const guruBox = ref(null)

const selectedGuru = computed(() =>
    props.guruList.find(g => g.id === form.value.guru_id) ?? null
)
const filteredGuru = computed(() => {
    const q = search.value.trim().toLowerCase()
    if (!q) return props.guruList
    return props.guruList.filter(g => (g.nama ?? '').toLowerCase().includes(q))
})

function toggleDropdown() {
    showDropdown.value = !showDropdown.value
    if (showDropdown.value) search.value = ''
}
function pilihGuru(g) {
    form.value.guru_id = g.id
    showDropdown.value = false
    applyFilter()
}
function setMode(mode) {
    if (form.value.mode === mode) return
    form.value.mode = mode
    applyFilter()
}
function applyFilter() {
    router.get(route(props.routeName), {
        guru_id: form.value.guru_id,
        mode: form.value.mode,
        bulan: form.value.bulan,
        tahun: form.value.tahun,
        tanggal: form.value.tanggal,
        tanggal_awal: form.value.tanggal_awal,
        tanggal_akhir: form.value.tanggal_akhir,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
function cetak() {
    window.print()
}

function onClickOutside(e) {
    if (guruBox.value && !guruBox.value.contains(e.target)) showDropdown.value = false
}
onMounted(() => document.addEventListener('click', onClickOutside))
onBeforeUnmount(() => document.removeEventListener('click', onClickOutside))
</script>
