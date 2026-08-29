<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api'
const router = useRouter()
const d = ref(null)
const loading = ref(true)
async function load() { try { d.value = (await api.get('/tahsin')).data.data } catch (_) {} finally { loading.value = false } }
onMounted(load)
</script>
<template>
    <div v-if="loading" class="pt-10 flex justify-center"><div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div></div>
    <template v-else-if="d">
        <div class="rounded-2xl bg-gradient-to-br from-violet-500 to-violet-700 text-white p-4 mb-5 text-center">
            <p class="text-[11px] text-white/70">Tingkat saat ini</p>
            <p class="text-2xl font-extrabold">{{ d.level_label }}</p>
        </div>

        <template v-if="d.tasnif_lulus.length">
            <p class="text-sm font-bold text-gray-800 mb-2">Tasnif Lulus ({{ d.tasnif_lulus.length }})</p>
            <div v-for="t in d.tasnif_lulus" :key="t.id" class="rounded-2xl border border-violet-100 bg-violet-50/40 p-3 mb-2">
                <div class="flex items-center justify-between mb-2">
                    <div><p class="text-sm font-extrabold text-violet-700">{{ t.level_label }}</p><p class="text-[10px] text-gray-400">{{ t.tanggal }} · penguji {{ t.penguji }}</p></div>
                    <p class="text-lg font-extrabold text-violet-600">{{ t.nilai }}</p>
                </div>
                <button @click="router.push({ name: 'sertifikat', params: { jenis: 'tasnif', id: t.id } })" class="w-full py-2 rounded-lg bg-gradient-to-r from-violet-500 to-violet-600 text-white text-xs font-bold">Lihat Sertifikat</button>
            </div>
        </template>

        <p class="text-sm font-bold text-gray-800 mt-4 mb-2">Materi Level {{ d.level }}</p>
        <div v-if="!d.materi.length" class="text-xs text-gray-400 py-2">Belum ada materi.</div>
        <div v-for="(m, i) in d.materi" :key="i" class="flex items-center justify-between bg-white rounded-xl border border-gray-100 px-3 py-2 mb-1.5">
            <span class="text-sm text-gray-700">{{ m.nama }}</span>
            <span class="text-[11px]" :class="m.lulus ? 'text-emerald-600 font-bold' : 'text-gray-400'">{{ m.lulus ? 'Lulus ✓' : (m.sudah_dinilai ? 'Belum lulus' : 'Belum dinilai') }}</span>
        </div>

        <p class="text-sm font-bold text-gray-800 mt-4 mb-2">Riwayat Penilaian</p>
        <div v-for="(r, i) in d.riwayat" :key="i" class="bg-white rounded-xl border border-gray-100 px-3 py-2 mb-2">
            <div class="flex justify-between"><span class="text-xs font-semibold text-gray-700 truncate">{{ r.materi }}</span><span class="text-[11px]" :class="r.lulus ? 'text-emerald-600 font-bold' : 'text-gray-400'">{{ r.nilai }}{{ r.lulus ? ' ✓' : '' }}</span></div>
            <p class="text-[10px] text-gray-400">{{ r.tanggal }} · Level {{ r.level }}<span v-if="r.catatan"> · {{ r.catatan }}</span></p>
        </div>
    </template>
</template>
