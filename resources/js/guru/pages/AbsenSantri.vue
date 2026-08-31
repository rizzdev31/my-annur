<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../api'
import { toast } from '../store/toast'
import PageHeader from '../components/PageHeader.vue'
import BottomSheet from '../components/BottomSheet.vue'

const route = useRoute()
const router = useRouter()
const jadwalId = route.params.jadwalId
const loading = ref(true)
const busy = ref(false)
const d = ref(null)
const santri = ref([])
const materi = ref('')

const STATUS = [
    { v: 'hadir', t: 'Hadir', c: 'bg-emerald-500' },
    { v: 'telat', t: 'Telat', c: 'bg-amber-500' },
    { v: 'izin',  t: 'Izin',  c: 'bg-sky-500' },
    { v: 'sakit', t: 'Sakit', c: 'bg-violet-500' },
    { v: 'alpha', t: 'Alpha', c: 'bg-red-500' },
]
const terkunci = computed(() => d.value?.sudah_isi_santri === true)
const belumAbsenMengajar = computed(() => !d.value?.absensi_mengajar_id)
const rekap = computed(() => {
    const r = { hadir: 0, telat: 0, izin: 0, sakit: 0, alpha: 0 }
    santri.value.forEach(s => { if (r[s.status] !== undefined) r[s.status]++ })
    return r
})
const adaIzinAuto = computed(() => santri.value.some(s => s.izin_disetujui))
const adaSakitAuto = computed(() => santri.value.some(s => s.sakit_health))
const adaAuto = computed(() => adaIzinAuto.value || adaSakitAuto.value)

async function load() {
    loading.value = true
    try {
        const res = (await api.get(`/absensi/mengajar/${jadwalId}/santri`)).data.data
        d.value = res
        santri.value = (res.santri ?? []).map(s => ({ ...s }))
    } catch (e) {
        const c = e.response?.data
        toast.error(c?.message || 'Gagal memuat roster santri.')
        if (c?.code === 'KELAS_BELUM_SINKRON') router.back()
    } finally { loading.value = false }
}
onMounted(load)

function setStatus(s, v) { if (!terkunci.value) s.status = v }
// "Semua Hadir" tak menimpa santri yang izin disetujui / sakit (Smart Health) — biarkan apa adanya.
function tandaiSemua(v) {
    if (terkunci.value) return
    santri.value.forEach(s => { if (!(v === 'hadir' && (s.izin_disetujui || s.sakit_health))) s.status = v })
}

const konfirm = ref(false)
async function simpan() {
    if (belumAbsenMengajar.value) return toast.warning('Absen mengajar dulu sebelum absen santri.')
    busy.value = true
    try {
        const res = await api.post('/absensi/mengajar/absen-santri', {
            absensi_mengajar_id: d.value.absensi_mengajar_id,
            absensi: santri.value.map(s => ({ santri_id: s.santri_id, status: s.status })),
            materi: materi.value.trim() || undefined,
        })
        konfirm.value = false
        toast.success(res.data?.message || 'Absensi santri tersimpan & terkunci.')
        await load()
    } catch (e) {
        konfirm.value = false
        toast.error(e.response?.data?.message || 'Gagal menyimpan absensi santri.')
        if (e.response?.data?.code === 'ABSENSI_TERKUNCI') await load()
    } finally { busy.value = false }
}
</script>

<template>
    <div>
        <PageHeader title="Absen Santri" />
        <div v-if="loading" class="pt-16 flex justify-center"><div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div></div>

        <template v-else-if="d">
            <!-- Info kelas -->
            <div class="rounded-2xl bg-white border border-gray-100 p-4 mb-4">
                <p class="text-sm font-extrabold text-gray-900">{{ d.mata_pelajaran }}</p>
                <p class="text-[12px] text-gray-400">{{ d.kelas }} · {{ d.total_santri }} santri</p>
            </div>

            <div v-if="belumAbsenMengajar" class="rounded-2xl bg-amber-50 border border-amber-100 p-4 mb-4 text-sm text-amber-700">
                Anda belum absen mengajar untuk sesi ini. Lakukan <b>Absen Mengajar</b> dulu, baru bisa absen santri.
            </div>
            <div v-else-if="terkunci" class="rounded-2xl bg-emerald-50 border border-emerald-100 p-3 mb-4 text-[13px] font-semibold text-emerald-700 text-center">
                ✓ Absensi santri sudah dikunci (final)
            </div>

            <!-- Rekap + tandai semua -->
            <div class="flex items-center justify-between gap-2 mb-2">
                <div class="flex gap-1 text-[11px] font-bold flex-wrap">
                    <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-600">H {{ rekap.hadir }}</span>
                    <span class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-600">T {{ rekap.telat }}</span>
                    <span class="px-2 py-0.5 rounded-full bg-sky-50 text-sky-600">I {{ rekap.izin }}</span>
                    <span class="px-2 py-0.5 rounded-full bg-violet-50 text-violet-600">S {{ rekap.sakit }}</span>
                    <span class="px-2 py-0.5 rounded-full bg-red-50 text-red-600">A {{ rekap.alpha }}</span>
                </div>
                <button v-if="!terkunci && !belumAbsenMengajar" @click="tandaiSemua('hadir')" class="text-xs font-bold text-[#0C78FF] shrink-0">Semua Hadir</button>
            </div>

            <!-- Info auto-terisi dari Perizinan / Smart Health -->
            <div v-if="adaAuto && !belumAbsenMengajar" class="rounded-xl bg-sky-50 border border-sky-100 px-3 py-2 mb-2 text-[11px] text-sky-700 leading-snug">
                <template v-if="adaIzinAuto">📝 Santri dengan izin <b>disetujui</b> otomatis ditandai <b>Izin</b>. </template>
                <template v-if="adaSakitAuto">🤒 Santri yang sedang sakit (<b>Smart Health</b>) otomatis ditandai <b>Sakit</b>. </template>
                Anda tetap bisa mengubahnya bila perlu.
            </div>

            <!-- Daftar santri -->
            <div v-if="!santri.length" class="rounded-2xl border border-dashed border-gray-200 p-6 text-center text-sm text-gray-400 mb-4">Belum ada santri di kelas ini.</div>
            <ul v-else class="space-y-2 mb-4">
                <li v-for="s in santri" :key="s.santri_id" class="rounded-2xl bg-white border border-gray-100 p-3">
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-gray-800 truncate">{{ s.nama }}</p>
                            <p v-if="s.sakit_health" class="text-[10px] font-bold text-violet-600 truncate">🤒 Sakit · Smart Health</p>
                            <p v-else-if="s.izin_disetujui" class="text-[10px] font-bold text-sky-600 truncate">📝 Izin {{ s.izin_jenis }} · disetujui</p>
                            <p v-else-if="s.nip" class="text-[10px] text-gray-400">NIS {{ s.nip }}</p>
                        </div>
                        <div class="flex gap-1 shrink-0">
                            <button v-for="st in STATUS" :key="st.v" @click="setStatus(s, st.v)" :disabled="terkunci || belumAbsenMengajar"
                                class="w-8 py-1.5 rounded-lg text-[11px] font-bold transition"
                                :class="s.status === st.v ? st.c + ' text-white' : 'bg-gray-100 text-gray-400'">{{ st.t.charAt(0) }}</button>
                        </div>
                    </div>
                </li>
            </ul>

            <!-- Materi + simpan -->
            <template v-if="!terkunci && !belumAbsenMengajar">
                <textarea v-model="materi" rows="2" placeholder="Materi/jurnal (opsional)" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-3"></textarea>
                <button @click="konfirm = true" :disabled="busy || !santri.length" class="w-full py-3.5 rounded-2xl bg-emerald-600 text-white font-bold disabled:opacity-60">Simpan & Kunci Absensi</button>
            </template>
        </template>

        <!-- Konfirmasi -->
        <BottomSheet v-model="konfirm" title="Simpan Absensi Santri?" subtitle="Setelah disimpan, absensi terkunci & tidak bisa diubah.">
            <div class="flex gap-1.5 flex-wrap text-[12px] font-bold mb-4">
                <span class="px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-600">Hadir {{ rekap.hadir }}</span>
                <span class="px-2.5 py-1 rounded-full bg-amber-50 text-amber-600">Telat {{ rekap.telat }}</span>
                <span v-if="rekap.izin" class="px-2.5 py-1 rounded-full bg-sky-50 text-sky-600">Izin {{ rekap.izin }}</span>
                <span v-if="rekap.sakit" class="px-2.5 py-1 rounded-full bg-violet-50 text-violet-600">Sakit {{ rekap.sakit }}</span>
                <span class="px-2.5 py-1 rounded-full bg-red-50 text-red-600">Alpha {{ rekap.alpha }}</span>
            </div>
            <div class="flex gap-2">
                <button @click="konfirm = false" class="flex-1 py-3 rounded-xl bg-gray-100 text-gray-600 font-bold text-sm">Batal</button>
                <button @click="simpan" :disabled="busy" class="flex-1 py-3 rounded-xl bg-emerald-600 text-white font-bold text-sm disabled:opacity-60">{{ busy ? 'Menyimpan…' : 'Ya, Simpan' }}</button>
            </div>
        </BottomSheet>
    </div>
</template>
