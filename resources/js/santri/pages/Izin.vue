<script setup>
import { ref, onMounted } from 'vue'
import api from '../api'
const list = ref([])
const loading = ref(true)
const sc = (s) => ({ disetujui: 'text-emerald-600 bg-emerald-50', diajukan: 'text-amber-600 bg-amber-50', ditolak: 'text-red-500 bg-red-50' }[s] || 'text-gray-500 bg-gray-100')
const lbl = (s) => ({ diajukan: 'Menunggu', disetujui: 'Disetujui', ditolak: 'Ditolak' }[s] || s)
async function load() { try { list.value = (await api.get('/izin')).data.data ?? [] } catch (_) {} finally { loading.value = false } }
onMounted(load)
</script>
<template>
    <div>
        <div v-if="loading" class="pt-10 flex justify-center"><div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div></div>
        <template v-else>
            <div v-if="!list.length" class="pt-14 text-center text-sm text-gray-400">Belum ada catatan izin.</div>
            <ul v-else class="space-y-3">
                <li v-for="(z, i) in list" :key="i" class="rounded-2xl bg-white border border-gray-100 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" :class="z.jenis === 'syari' ? 'text-violet-600 bg-violet-50' : 'text-sky-600 bg-sky-50'">{{ z.jenis_label }}</span>
                        <span class="text-[10px] font-bold px-2.5 py-1 rounded-full" :class="sc(z.status)">{{ lbl(z.status) }}</span>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1.5">{{ z.tanggal_mulai }}<span v-if="z.tanggal_selesai && z.tanggal_selesai !== z.tanggal_mulai"> — {{ z.tanggal_selesai }}</span></p>
                    <p v-if="z.alasan" class="text-sm text-gray-700 mt-1">{{ z.alasan }}</p>
                    <p v-if="z.catatan" class="text-[11px] text-gray-400 mt-1 italic">Catatan: {{ z.catatan }}</p>
                </li>
            </ul>
        </template>
    </div>
</template>
