<script setup>
import { ref, onMounted } from 'vue'
import api from '../api'
const d = ref(null)
const loading = ref(true)
const bulan = ref(new Date().toISOString().slice(0, 7))
const sc = (s) => ({ hadir: 'text-emerald-600 bg-emerald-50', telat: 'text-amber-600 bg-amber-50', alpha: 'text-red-500 bg-red-50' }[s] || 'text-gray-500 bg-gray-100')
async function load() { loading.value = true; try { d.value = (await api.get('/controlling', { params: { bulan: bulan.value } })).data.data } catch (_) {} finally { loading.value = false } }
onMounted(load)
</script>
<template>
    <div>
        <p class="text-[12px] text-gray-400 mb-2">Kehadiran kegiatan pesantren (sholat berjamaah, kajian, dll).</p>
        <input v-model="bulan" @change="load" type="month" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-4" />
        <div v-if="loading" class="pt-10 flex justify-center"><div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div></div>
        <template v-else-if="d">
            <div class="grid grid-cols-4 gap-2 mb-4">
                <div v-for="[k, lbl, c] in [['hadir','Hadir','text-emerald-600'],['telat','Telat','text-amber-600'],['alpha','Alpha','text-red-500'],['total','Total','text-gray-700']]" :key="k" class="rounded-xl bg-white border border-gray-100 p-2.5 text-center">
                    <p class="text-lg font-extrabold" :class="c">{{ d.rekap[k] }}</p><p class="text-[9px] text-gray-400">{{ lbl }}</p>
                </div>
            </div>
            <div v-if="!d.rows.length" class="pt-10 text-center text-sm text-gray-400">Belum ada data kegiatan bulan ini.</div>
            <ul v-else class="space-y-2">
                <li v-for="(r, i) in d.rows" :key="i" class="flex items-center justify-between bg-white rounded-xl border border-gray-100 px-3 py-2.5">
                    <div class="min-w-0"><p class="text-sm font-semibold text-gray-800 truncate">{{ r.kegiatan }}</p><p class="text-[10px] text-gray-400">{{ r.tanggal }}<span v-if="r.jam"> · {{ r.jam }}</span></p></div>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full capitalize shrink-0" :class="sc(r.status)">{{ r.status }}</span>
                </li>
            </ul>
        </template>
    </div>
</template>
