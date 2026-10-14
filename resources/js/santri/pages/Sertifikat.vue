<script setup>
/**
 * Sertifikat untuk wali/santri — tasmi' maupun tasnif, tata letaknya memakai
 * komponen bersama `@/Shared/SertifikatQuran.vue` (2 halaman: depan untuk nama,
 * belakang untuk nilai) sehingga identik dengan yang dilihat guru dan admin.
 */
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../api'
import { toast } from '../store/toast'
import SertifikatQuran from '@/Shared/SertifikatQuran.vue'

const route = useRoute()
const router = useRouter()
const jenis = route.params.jenis === 'tasnif' ? 'tasnif' : 'tasmi'
const id = route.params.id
const loading = ref(true)
const c = ref(null)

const url = jenis === 'tasmi' ? `/tahfidz/tasmi/${id}/sertifikat` : `/tahsin/tasnif/${id}/sertifikat`

async function load() {
    try { c.value = (await api.get(url)).data.data }
    catch (e) { toast.error(e.response?.data?.message || 'Sertifikat tidak tersedia.'); router.back() }
    finally { loading.value = false }
}
onMounted(load)
function cetak() { window.print() }
</script>

<template>
    <div class="cert-root min-h-screen bg-[#EEF1F4]">
        <div class="no-print sticky top-0 z-10 flex items-center justify-between px-4 h-14 bg-white/90 backdrop-blur border-b border-gray-100">
            <button @click="router.back()" class="w-9 h-9 rounded-xl bg-gray-100 grid place-items-center text-gray-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <span class="text-sm font-bold text-gray-700">{{ jenis === 'tasmi' ? "Sertifikat Tasmi'" : 'Sertifikat Tasnif' }}</span>
            <button v-if="c" @click="cetak" class="px-3.5 h-9 rounded-xl bg-[#0C78FF] text-white text-xs font-bold flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a1 1 0 001-1v-4a1 1 0 00-1-1H9a1 1 0 00-1 1v4a1 1 0 001 1zm0-12V5a1 1 0 011-1h4a1 1 0 011 1v4"/></svg>
                Cetak
            </button>
        </div>

        <div v-if="loading" class="pt-24 flex justify-center">
            <div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div>
        </div>

        <div v-else-if="c" class="p-4">
            <p class="no-print text-center text-[11px] text-gray-400 mb-3">
                Dua lembar: halaman depan (nama) dan halaman nilai.
            </p>
            <SertifikatQuran :data="c" :jenis="jenis" />
        </div>
    </div>
</template>

<style scoped>
@media print {
    .no-print { display: none !important; }
    .cert-root { background: #fff !important; padding: 0 !important; }
}
:global(html), :global(body) { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
</style>
