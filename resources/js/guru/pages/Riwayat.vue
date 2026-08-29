<script setup>
import { ref, onMounted } from 'vue'
import api from '../api'
import PageHeader from '../components/PageHeader.vue'

const list = ref([])
const loading = ref(true)
const error = ref('')

const statusClass = (s) => ({
    hadir: 'text-emerald-600 bg-emerald-50',
    terlambat: 'text-amber-600 bg-amber-50',
    alfa: 'text-red-600 bg-red-50',
    izin: 'text-blue-600 bg-blue-50',
    sakit: 'text-purple-600 bg-purple-50',
}[s] || 'text-gray-500 bg-gray-100')

async function load() {
    loading.value = true; error.value = ''
    try {
        const res = await api.get('/absensi/riwayat')
        list.value = res.data.data ?? res.data ?? []
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal memuat riwayat.'
    } finally { loading.value = false }
}
onMounted(load)
</script>

<template>
    <div>
        <PageHeader title="Riwayat Absensi" />

        <div v-if="loading" class="pt-10 flex justify-center">
            <div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div>
        </div>
        <div v-else-if="error" class="pt-8 text-center">
            <p class="text-sm text-gray-500">{{ error }}</p>
            <button @click="load" class="mt-3 px-4 py-2 rounded-xl bg-[#0C78FF] text-white text-sm font-semibold">Coba lagi</button>
        </div>
        <div v-else-if="!list.length" class="pt-16 text-center text-sm text-gray-400">Belum ada riwayat absensi.</div>

        <ul v-else class="space-y-2.5">
            <li v-for="a in list" :key="a.id" class="rounded-2xl bg-white border border-gray-100 p-3.5 flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-gray-50 grid place-items-center shrink-0">
                    <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-gray-800">{{ a.tanggal }}</p>
                    <p class="text-[11px] text-gray-400">
                        Masuk {{ a.jam_masuk || '--:--' }} · Pulang {{ a.jam_pulang || '--:--' }}
                        <span v-if="a.label_terlambat" class="text-amber-500"> · {{ a.label_terlambat }}</span>
                    </p>
                </div>
                <span class="text-[10px] font-bold px-2.5 py-1 rounded-full capitalize" :class="statusClass(a.status)">
                    {{ a.status || '—' }}
                </span>
            </li>
        </ul>
    </div>
</template>
