<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '../api'
import { toast } from '../store/toast'
import PageHeader from '../components/PageHeader.vue'
import BottomSheet from '../components/BottomSheet.vue'

const route = useRoute()
const id = route.params.id
const loading = ref(true)
const busy = ref(false)
const p = ref(null)
const santri = ref([])
const materi = ref('')

const STATUS = [
    { v: 'hadir', t: 'Hadir', c: 'bg-emerald-500' },
    { v: 'izin', t: 'Izin', c: 'bg-sky-500' },
    { v: 'sakit', t: 'Sakit', c: 'bg-violet-500' },
    { v: 'alpha', t: 'Alpha', c: 'bg-red-500' },
]
const terkunci = computed(() => p.value?.status === 'selesai')
const rekap = computed(() => {
    const r = { hadir: 0, izin: 0, sakit: 0, alpha: 0 }
    santri.value.forEach(s => { if (r[s.status] !== undefined) r[s.status]++ })
    return r
})

async function load() {
    loading.value = true
    try {
        const d = (await api.get(`/ekstrakurikuler/pertemuan/${id}`)).data.data
        p.value = d; santri.value = (d.santri ?? []).map(x => ({ ...x })); materi.value = d.materi || ''
    } catch (e) { toast.error(e.response?.data?.message || 'Gagal memuat.') }
    finally { loading.value = false }
}
onMounted(load)

function setStatus(s, v) { if (!terkunci.value) s.status = v }
function semua(v) { if (!terkunci.value) santri.value.forEach(s => s.status = v) }

const konfirm = ref(false)
async function simpan() {
    busy.value = true
    try {
        const res = await api.post(`/ekstrakurikuler/pertemuan/${id}/absensi`, {
            absensi: santri.value.map(s => ({ id: s.id, status: s.status })),
            materi: materi.value.trim() || undefined,
        })
        konfirm.value = false
        toast.success(res.data.message || 'Tersimpan.')
        await load()
    } catch (e) { konfirm.value = false; toast.error(e.response?.data?.message || 'Gagal menyimpan.'); if (e.response?.data?.code === 'TERKUNCI') await load() }
    finally { busy.value = false }
}
</script>

<template>
    <div>
        <PageHeader title="Absensi Pertemuan" />
        <div v-if="loading" class="pt-16 flex justify-center"><div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div></div>

        <template v-else-if="p">
            <div class="rounded-2xl bg-white border border-gray-100 p-4 mb-4">
                <div class="flex items-center justify-between">
                    <div><p class="text-sm font-extrabold text-gray-900">{{ p.nama }}</p><p class="text-[12px] text-gray-400">{{ p.tanggal }}</p></div>
                    <span class="text-[10px] font-bold px-2 py-1 rounded-full" :class="terkunci ? 'text-emerald-600 bg-emerald-50' : 'text-amber-600 bg-amber-50'">{{ terkunci ? 'Selesai' : 'Berlangsung' }}</span>
                </div>
            </div>

            <div class="flex items-center justify-between mb-2">
                <div class="flex gap-1.5 text-[11px] font-bold">
                    <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-600">H {{ rekap.hadir }}</span>
                    <span class="px-2 py-0.5 rounded-full bg-sky-50 text-sky-600">I {{ rekap.izin }}</span>
                    <span class="px-2 py-0.5 rounded-full bg-violet-50 text-violet-600">S {{ rekap.sakit }}</span>
                    <span class="px-2 py-0.5 rounded-full bg-red-50 text-red-600">A {{ rekap.alpha }}</span>
                </div>
                <button v-if="!terkunci" @click="semua('hadir')" class="text-xs font-bold text-[#0C78FF]">Semua Hadir</button>
            </div>

            <ul class="space-y-2 mb-4">
                <li v-for="s in santri" :key="s.id" class="rounded-2xl bg-white border border-gray-100 p-3">
                    <p class="text-sm font-bold text-gray-800 truncate mb-2">{{ s.nama }}</p>
                    <div class="flex gap-1.5">
                        <button v-for="st in STATUS" :key="st.v" @click="setStatus(s, st.v)" :disabled="terkunci"
                            class="flex-1 py-1.5 rounded-lg text-[11px] font-bold transition"
                            :class="s.status === st.v ? st.c + ' text-white' : 'bg-gray-100 text-gray-400'">{{ st.t }}</button>
                    </div>
                </li>
            </ul>

            <template v-if="!terkunci">
                <textarea v-model="materi" rows="2" placeholder="Materi/catatan pertemuan (opsional)" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-3"></textarea>
                <button @click="konfirm = true" :disabled="busy" class="w-full py-3.5 rounded-2xl bg-emerald-600 text-white font-bold disabled:opacity-60">Simpan & Kunci (Vakasi)</button>
            </template>
            <div v-else class="rounded-2xl bg-emerald-50 border border-emerald-100 p-4 text-center text-sm font-semibold text-emerald-700">Pertemuan selesai · vakasi tercatat ✓</div>
        </template>

        <BottomSheet v-model="konfirm" title="Simpan & Kunci Absensi?" subtitle="Setelah dikunci tak bisa diubah. Vakasi pembina tercatat.">
            <div class="flex gap-2">
                <button @click="konfirm = false" class="flex-1 py-3 rounded-xl bg-gray-100 text-gray-600 font-bold text-sm">Batal</button>
                <button @click="simpan" :disabled="busy" class="flex-1 py-3 rounded-xl bg-emerald-600 text-white font-bold text-sm disabled:opacity-60">{{ busy ? 'Menyimpan…' : 'Ya, Kunci' }}</button>
            </div>
        </BottomSheet>
    </div>
</template>
