<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import api from '../api'
import PageHeader from '../components/PageHeader.vue'

const list = ref([])
const loading = ref(true)
const error = ref('')

const rp = (n) => 'Rp ' + (Number(n) || 0).toLocaleString('id-ID')
const statusClass = (s) => ({
    dibayar: 'text-emerald-600 bg-emerald-50',
    final: 'text-blue-600 bg-blue-50',
    draft: 'text-gray-500 bg-gray-100',
}[s] || 'text-gray-500 bg-gray-100')

async function load() {
    loading.value = true; error.value = ''
    try {
        const res = await api.get('/payroll/riwayat')
        list.value = res.data.data ?? res.data ?? []
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal memuat slip gaji.'
    } finally { loading.value = false }
}
onMounted(load)
</script>

<template>
    <div>
        <PageHeader title="Slip Gaji" />

        <div v-if="loading" class="pt-10 flex justify-center">
            <div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div>
        </div>
        <div v-else-if="error" class="pt-8 text-center">
            <p class="text-sm text-gray-500">{{ error }}</p>
            <button @click="load" class="mt-3 px-4 py-2 rounded-xl bg-[#0C78FF] text-white text-sm font-semibold">Coba lagi</button>
        </div>
        <div v-else-if="!list.length" class="pt-16 text-center text-sm text-gray-400">Belum ada riwayat penggajian.</div>

        <ul v-else class="space-y-3">
            <RouterLink v-for="g in list" :key="g.id" :to="{ name: 'slip-detail', params: { id: g.id } }"
                class="block rounded-2xl bg-white border border-gray-100 p-4 active:scale-[0.99] transition">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm font-bold text-gray-800">{{ g.nama_periode }}</p>
                    <span class="text-[10px] font-bold px-2.5 py-1 rounded-full" :class="statusClass(g.status)">
                        {{ g.status_label || g.status }}
                    </span>
                </div>
                <p class="text-2xl font-extrabold text-[#0C78FF]">{{ rp(g.gaji_bersih) }}</p>
                <div class="mt-2 flex items-center justify-between">
                    <div class="flex items-center gap-4 text-[11px] text-gray-400">
                        <span>Pendapatan {{ rp(g.total_pendapatan) }}</span>
                        <span>Potongan {{ rp(g.total_potongan) }}</span>
                    </div>
                    <span class="text-[11px] font-semibold text-[#0C78FF]">Lihat slip ›</span>
                </div>
                <p v-if="g.dibayar_pada" class="mt-1 text-[11px] text-emerald-600">Dibayar {{ g.dibayar_pada }}</p>
            </RouterLink>
        </ul>
    </div>
</template>
