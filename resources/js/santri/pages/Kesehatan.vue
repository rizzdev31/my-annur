<script setup>
import { ref, onMounted } from 'vue'
import api from '../api'
const list = ref([])
const loading = ref(true)
const open = ref(null)
const sc = (s) => ({ selesai: 'text-emerald-600 bg-emerald-50', dalam_pengecekan: 'text-sky-600 bg-sky-50', menunggu: 'text-amber-600 bg-amber-50', ditolak: 'text-red-500 bg-red-50' }[s] || 'text-gray-500 bg-gray-100')
const lbl = (s) => ({ menunggu: 'Menunggu', dalam_pengecekan: 'Dalam pengecekan', selesai: 'Selesai', ditolak: 'Ditolak' }[s] || s)
async function load() { try { list.value = (await api.get('/kesehatan')).data.data ?? [] } catch (_) {} finally { loading.value = false } }
onMounted(load)
</script>
<template>
    <div>
        <div v-if="loading" class="pt-10 flex justify-center"><div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div></div>
        <template v-else>
            <div v-if="!list.length" class="pt-14 text-center text-sm text-gray-400">Tidak ada laporan kesehatan.</div>
            <ul v-else class="space-y-3">
                <li v-for="(l, i) in list" :key="i" class="rounded-2xl bg-white border border-gray-100 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm font-bold text-gray-800">{{ l.penyakit }}</p>
                        <span class="text-[10px] font-bold px-2.5 py-1 rounded-full shrink-0 capitalize" :class="sc(l.status)">{{ lbl(l.status) }}</span>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-1">{{ l.tanggal }}</p>
                    <img v-if="l.foto" :src="l.foto" class="w-full h-40 object-cover rounded-xl mt-2" @error="l.foto = null" />
                    <button v-if="l.riwayat?.length" @click="open = open === i ? null : i" class="mt-2 text-[11px] font-bold text-[#0C78FF]">{{ open === i ? 'Sembunyikan' : 'Lihat pemantauan' }}</button>
                    <ul v-if="open === i" class="mt-2 space-y-1.5">
                        <li v-for="(p, j) in l.riwayat" :key="j" class="text-[11px] text-gray-600 bg-gray-50 rounded-lg px-2.5 py-1.5">
                            <b>Hari {{ p.hari_ke }}</b> · {{ p.keputusan }}<span v-if="p.tanggal"> · {{ p.tanggal }}</span><span v-if="p.catatan"> — {{ p.catatan }}</span>
                        </li>
                    </ul>
                </li>
            </ul>
        </template>
    </div>
</template>
