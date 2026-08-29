<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api'
const router = useRouter()
const d = ref(null)
const loading = ref(true)
async function load() { try { d.value = (await api.get('/tahfidz')).data.data } catch (_) {} finally { loading.value = false } }
onMounted(load)
</script>
<template>
    <div v-if="loading" class="pt-10 flex justify-center"><div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div></div>
    <template v-else-if="d">
        <div class="grid grid-cols-3 gap-3 mb-5">
            <div class="rounded-2xl bg-white border border-gray-100 p-3 text-center"><p class="text-xl font-extrabold text-emerald-600">{{ d.persen }}%</p><p class="text-[10px] text-gray-400">Hafal</p></div>
            <div class="rounded-2xl bg-white border border-gray-100 p-3 text-center"><p class="text-xl font-extrabold text-[#0C78FF]">{{ d.juz_selesai }}</p><p class="text-[10px] text-gray-400">Juz selesai</p></div>
            <div class="rounded-2xl bg-white border border-gray-100 p-3 text-center"><p class="text-xl font-extrabold text-gray-700">{{ d.total_ayat }}</p><p class="text-[10px] text-gray-400">Total ayat</p></div>
        </div>

        <template v-if="d.tasmi_lulus.length">
            <p class="text-sm font-bold text-gray-800 mb-2">Tasmi' Lulus ({{ d.tasmi_lulus.length }})</p>
            <div v-for="t in d.tasmi_lulus" :key="t.id" class="rounded-2xl border border-emerald-100 bg-emerald-50/40 p-3 mb-2">
                <div class="flex items-center justify-between mb-2">
                    <div><p class="text-sm font-extrabold text-emerald-700">Juz {{ t.juz }}</p><p class="text-[10px] text-gray-400">{{ t.tanggal }} · penguji {{ t.penguji }}</p></div>
                    <p class="text-lg font-extrabold text-emerald-600">{{ t.nilai }}</p>
                </div>
                <button @click="router.push({ name: 'sertifikat', params: { jenis: 'tasmi', id: t.id } })" class="w-full py-2 rounded-lg bg-gradient-to-r from-emerald-500 to-emerald-600 text-white text-xs font-bold">Lihat Sertifikat</button>
            </div>
        </template>

        <p class="text-sm font-bold text-gray-800 mt-4 mb-2">Riwayat Setoran</p>
        <div v-if="!d.riwayat.length" class="text-xs text-gray-400 py-2">Belum ada setoran.</div>
        <div v-for="(r, i) in d.riwayat" :key="i" class="bg-white rounded-xl border border-gray-100 px-3 py-2 mb-2">
            <div class="flex justify-between"><span class="text-xs font-semibold text-gray-700 capitalize">{{ (r.jenis || '').replace('_', ' ') }}</span><span class="text-[11px]" :class="r.lulus ? 'text-emerald-600 font-bold' : 'text-gray-400'">{{ r.nilai ?? '' }}{{ r.lulus ? ' ✓' : '' }}</span></div>
            <p class="text-[10px] text-gray-400">{{ r.tanggal }} · {{ r.jumlah_ayat }} ayat<span v-if="r.catatan"> · {{ r.catatan }}</span></p>
        </div>
    </template>
</template>
