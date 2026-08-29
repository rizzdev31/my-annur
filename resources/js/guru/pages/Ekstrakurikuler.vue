<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api'
import { toast } from '../store/toast'
import PageHeader from '../components/PageHeader.vue'

const router = useRouter()
const list = ref([])
const loading = ref(true)

async function load() {
    try { list.value = (await api.get('/ekstrakurikuler/saya')).data.data ?? [] }
    catch (e) { toast.error(e.response?.data?.message || 'Gagal memuat ekskul.') }
    finally { loading.value = false }
}
onMounted(load)
</script>

<template>
    <div>
        <PageHeader title="Ekstrakurikuler" />
        <div v-if="loading" class="pt-16 flex justify-center"><div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div></div>
        <template v-else>
            <p class="text-xs text-gray-400 mb-3">Ekskul yang Anda ampu. Isi absensi tiap pertemuan (dapat vakasi) & penilaian A/B/C per semester.</p>
            <div v-if="!list.length" class="pt-16 text-center text-sm text-gray-400">Belum ada ekstrakurikuler yang Anda ampu.</div>
            <ul v-else class="space-y-3">
                <li v-for="e in list" :key="e.id" @click="router.push({ name: 'ekstra-detail', params: { id: e.id } })"
                    class="rounded-2xl bg-white border border-gray-100 p-4 active:scale-[0.99] transition">
                    <div class="flex items-start gap-3">
                        <div class="w-11 h-11 rounded-xl bg-[#0C78FF]/10 grid place-items-center shrink-0">
                            <svg class="w-6 h-6 text-[#0C78FF]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-gray-800">{{ e.nama }}</p>
                            <p class="text-[11px] text-gray-400 capitalize">
                                <span v-if="e.hari">{{ e.hari }}<span v-if="e.jam_mulai"> {{ e.jam_mulai }}–{{ e.jam_selesai }}</span> · </span>
                                {{ e.anggota }} anggota · {{ e.pertemuan }} pertemuan
                            </p>
                            <p class="text-[11px] font-semibold text-emerald-600 mt-0.5">Mendapatkan vakasi tiap pertemuan</p>
                        </div>
                        <svg class="w-5 h-5 text-gray-300 mt-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                    </div>
                </li>
            </ul>
        </template>
    </div>
</template>
