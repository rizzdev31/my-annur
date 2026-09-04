<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../api'
import { tanggalLokal } from '../tanggal'
import { monitoring, muatMonitoring, bolehModul } from '../store/monitoring'
import PageHeader from '../components/PageHeader.vue'

const loading = ref(true)
const error = ref('')
const tanggal = ref(tanggalLokal())
const data = ref(null)
const cari = ref('')

// Fase 1: modul Absen Harian. Modul lain menyusul (tab akan bertambah).
const adaAkses = computed(() => bolehModul('absen_harian'))

const STATUS = {
    hadir:      { t: 'Hadir',      c: 'bg-emerald-50 text-emerald-700' },
    terlambat:  { t: 'Terlambat',  c: 'bg-amber-50 text-amber-700' },
    izin:       { t: 'Izin',       c: 'bg-sky-50 text-sky-700' },
    sakit:      { t: 'Sakit',      c: 'bg-violet-50 text-violet-700' },
    dinas_luar: { t: 'Dinas Luar', c: 'bg-indigo-50 text-indigo-700' },
    alfa:       { t: 'Alfa',       c: 'bg-red-50 text-red-600' },
    belum:      { t: 'Belum absen', c: 'bg-gray-100 text-gray-500' },
}
const labelStatus = (s) => STATUS[s]?.t ?? s
const warnaStatus = (s) => STATUS[s]?.c ?? 'bg-gray-100 text-gray-500'

const guruTersaring = computed(() => {
    const q = cari.value.trim().toLowerCase()
    const list = data.value?.guru ?? []
    return q ? list.filter((g) => g.nama.toLowerCase().includes(q)) : list
})

async function load() {
    if (!monitoring.dimuat) await muatMonitoring()
    if (!adaAkses.value) { loading.value = false; return }
    loading.value = true; error.value = ''
    try {
        const res = await api.get('/monitoring/absen-harian', { params: { tanggal: tanggal.value } })
        data.value = res.data.data ?? res.data
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal memuat data monitoring.'
    } finally { loading.value = false }
}
onMounted(load)
</script>

<template>
    <div>
        <PageHeader title="Monitoring" />

        <div v-if="loading" class="pt-16 flex justify-center">
            <div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div>
        </div>

        <!-- Tak punya hak -->
        <div v-else-if="!adaAkses" class="pt-10 text-center px-6">
            <p class="text-sm text-gray-500">Anda belum diberi akses monitoring absensi.</p>
            <p class="text-[11px] text-gray-400 mt-1">Hak ini diberikan oleh admin.</p>
        </div>

        <div v-else-if="error" class="pt-8 text-center">
            <p class="text-sm text-gray-500">{{ error }}</p>
            <button @click="load" class="mt-3 px-4 py-2 rounded-xl bg-[#0C78FF] text-white text-sm font-semibold">Coba lagi</button>
        </div>

        <template v-else-if="data">
            <!-- Tanggal -->
            <div class="flex items-center gap-2 mb-3">
                <input v-model="tanggal" @change="load" type="date"
                    class="flex-1 min-w-0 px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none bg-white" />
                <span class="shrink-0 text-[11px] text-gray-400">{{ data.total }} guru</span>
            </div>

            <!-- Ringkasan -->
            <div v-if="Object.keys(data.ringkasan || {}).length" class="flex flex-wrap gap-1.5 mb-3">
                <span v-for="(n, s) in data.ringkasan" :key="s"
                    :class="['px-2.5 py-1 rounded-full text-[11px] font-bold', warnaStatus(s)]">
                    {{ labelStatus(s) }} {{ n }}
                </span>
            </div>

            <input v-model="cari" placeholder="Cari nama guru…"
                class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-3" />

            <div v-if="!guruTersaring.length" class="pt-10 text-center text-sm text-gray-400">
                {{ data.total ? 'Tidak ada guru cocok.' : 'Belum ada guru yang ditugaskan untuk Anda pantau.' }}
            </div>

            <ul v-else class="space-y-2">
                <li v-for="g in guruTersaring" :key="g.tenaga_pendidik_id"
                    class="rounded-2xl bg-white border border-gray-100 p-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-gray-800 truncate">{{ g.nama }}</p>
                            <p class="text-[11px] text-gray-400 truncate">{{ g.jabatan }}</p>
                        </div>
                        <span :class="['shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold', warnaStatus(g.status)]">
                            {{ labelStatus(g.status) }}
                        </span>
                    </div>
                    <div v-if="g.jam_masuk || g.jam_pulang || g.menit_terlambat" class="mt-1.5 flex flex-wrap gap-x-3 gap-y-0.5 text-[11px] text-gray-500">
                        <span v-if="g.jam_masuk">Masuk <b class="text-gray-700">{{ g.jam_masuk }}</b></span>
                        <span v-if="g.jam_pulang">Pulang <b class="text-gray-700">{{ g.jam_pulang }}</b></span>
                        <span v-if="g.menit_terlambat > 0" class="text-amber-600 font-semibold">Telat {{ g.menit_terlambat }} mnt</span>
                    </div>
                    <p v-if="g.keterangan" class="mt-1 text-[11px] text-gray-400 truncate">{{ g.keterangan }}</p>
                </li>
            </ul>
        </template>
    </div>
</template>
