<script setup>
import { ref, reactive, onMounted } from 'vue'
import { tanggalLokal } from '../tanggal'
import { useRoute, useRouter } from 'vue-router'
import api from '../api'
import { toast } from '../store/toast'
import PageHeader from '../components/PageHeader.vue'
import BottomSheet from '../components/BottomSheet.vue'

const route = useRoute()
const router = useRouter()
const id = route.params.id
const tab = ref('absensi')
const loading = ref(true)
const busy = ref(false)
const d = ref(null)

const GRADE = ['A', 'B', 'C']
const gradeCls = (g) => ({ A: 'bg-emerald-500', B: 'bg-blue-500', C: 'bg-amber-500' }[g])

async function loadDetail() {
    loading.value = true
    try { d.value = (await api.get(`/ekstrakurikuler/${id}`)).data.data }
    catch (e) { toast.error(e.response?.data?.message || 'Gagal memuat.'); router.back() }
    finally { loading.value = false }
}
onMounted(loadDetail)

// ── Mulai pertemuan ──────────────────────────────────────────────────────────
// Lokasi pembina dikirim bersama pertemuan — server yang memutuskan sah/tidak
// (mesin yang sama dengan absensi harian), jadi tak ada aturan ganda di sini.
const sheet = ref(false)
const gps = ref('idle')          // idle | getting | ok | fail
const pos = ref(null)
const f = reactive({ tanggal: tanggalLokal(), materi: '' })
function buka() {
    Object.assign(f, { tanggal: tanggalLokal(), materi: '' })
    pos.value = null; sheet.value = true
    mintaLokasi()
}
function mintaLokasi() {
    if (!navigator.geolocation) { gps.value = 'fail'; return }
    gps.value = 'getting'
    navigator.geolocation.getCurrentPosition(
        (p) => { pos.value = { lat: p.coords.latitude, lng: p.coords.longitude }; gps.value = 'ok' },
        () => { gps.value = 'fail' },
        { enableHighAccuracy: true, timeout: 8000 },
    )
}
async function mulai() {
    busy.value = true
    try {
        const res = await api.post(`/ekstrakurikuler/${id}/pertemuan`, {
            tanggal: f.tanggal,
            materi: f.materi.trim() || null,
            latitude: pos.value?.lat ?? null,
            longitude: pos.value?.lng ?? null,
        })
        sheet.value = false
        toast.success('Pertemuan dimulai.')
        router.push({ name: 'ekstra-pertemuan', params: { id: res.data.data.id } })
    } catch (e) { toast.error(e.response?.data?.message || 'Gagal memulai pertemuan.') }
    finally { busy.value = false }
}

// ── Penilaian ────────────────────────────────────────────────────────────────
const nilai = ref(null)
const nilaiList = ref([])
async function loadNilai() {
    if (nilai.value) return
    try {
        const res = (await api.get(`/ekstrakurikuler/${id}/penilaian`)).data.data
        nilai.value = res
        nilaiList.value = (res.santri ?? []).map(s => ({ ...s }))
    } catch (e) { toast.error(e.response?.data?.message || 'Gagal memuat penilaian.') }
}
function pilihTab(t) { tab.value = t; if (t === 'penilaian') loadNilai() }
function setGrade(s, field, g) { s[field] = s[field] === g ? null : g }
async function simpanNilai() {
    busy.value = true
    try {
        await api.post(`/ekstrakurikuler/${id}/penilaian`, {
            penilaian: nilaiList.value.map(s => ({ santri_id: s.santri_id, keaktifan: s.keaktifan, perkembangan: s.perkembangan, catatan: s.catatan || null })),
        })
        toast.success('Penilaian tersimpan.')
    } catch (e) { toast.error(e.response?.data?.message || 'Gagal menyimpan.') }
    finally { busy.value = false }
}
</script>

<template>
    <div>
        <PageHeader :title="d?.nama || 'Ekstrakurikuler'" />
        <div v-if="loading" class="pt-16 flex justify-center"><div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div></div>

        <template v-else-if="d">
            <div class="rounded-2xl bg-gradient-to-br from-[#0C78FF] to-[#06346B] text-white p-4 mb-4">
                <p class="text-sm font-bold">{{ d.nama }}</p>
                <p class="text-[12px] text-white/75 capitalize">{{ d.hari }}<span v-if="d.jam"> · {{ d.jam }}</span><span v-if="d.lokasi"> · {{ d.lokasi }}</span></p>
                <p class="text-[11px] text-white/70 mt-1">{{ d.anggota_count }} anggota · mendapatkan vakasi tiap pertemuan</p>
            </div>

            <div class="flex gap-2 mb-4 bg-gray-100 rounded-2xl p-1">
                <button @click="pilihTab('absensi')" class="flex-1 py-2 rounded-xl text-sm font-bold" :class="tab === 'absensi' ? 'bg-white text-[#0C78FF] shadow-sm' : 'text-gray-400'">Absensi</button>
                <button @click="pilihTab('penilaian')" class="flex-1 py-2 rounded-xl text-sm font-bold" :class="tab === 'penilaian' ? 'bg-white text-[#0C78FF] shadow-sm' : 'text-gray-400'">Penilaian</button>
            </div>

            <!-- ABSENSI -->
            <template v-if="tab === 'absensi'">
                <button @click="buka" :disabled="d.boleh_mulai === false"
                    class="w-full py-3 rounded-2xl bg-[#0C78FF] text-white font-bold mb-2 active:scale-[0.99] transition disabled:opacity-50 disabled:active:scale-100">
                    + Mulai Pertemuan
                </button>
                <p v-if="d.boleh_mulai === false" class="text-[11px] text-amber-600 mb-3 px-1">{{ d.alasan_tidak_boleh }}</p>
                <p v-else-if="d.wajib_lokasi !== false" class="text-[11px] text-gray-400 mb-3 px-1">Pertemuan direkam bersama titik lokasi Anda.</p>
                <div v-if="!d.pertemuan.length" class="pt-8 text-center text-sm text-gray-400">Belum ada pertemuan.</div>
                <ul v-else class="space-y-2.5">
                    <li v-for="p in d.pertemuan" :key="p.id" @click="router.push({ name: 'ekstra-pertemuan', params: { id: p.id } })"
                        class="rounded-2xl bg-white border border-gray-100 p-3.5 flex items-center justify-between active:scale-[0.99] transition">
                        <div><p class="text-sm font-bold text-gray-800">{{ p.tanggal }}</p><p v-if="p.materi" class="text-[11px] text-gray-400 line-clamp-1">{{ p.materi }}</p></div>
                        <div class="text-right">
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" :class="p.status === 'selesai' ? 'text-emerald-600 bg-emerald-50' : 'text-amber-600 bg-amber-50'">{{ p.status === 'selesai' ? 'Selesai' : 'Berlangsung' }}</span>
                            <p class="text-[10px] text-gray-400 mt-1">{{ p.hadir }} hadir</p>
                        </div>
                    </li>
                </ul>
            </template>

            <!-- PENILAIAN -->
            <template v-else>
                <p v-if="nilai" class="text-[11px] text-gray-400 mb-1">Periode: {{ nilai.periode }} · <b>A</b> Sangat Baik · <b>B</b> Baik · <b>C</b> Cukup</p>
                <div v-if="!nilai" class="pt-8 flex justify-center"><div class="w-7 h-7 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div></div>
                <template v-else>
                    <div v-if="!nilaiList.length" class="pt-8 text-center text-sm text-gray-400">Belum ada anggota.</div>
                    <ul v-else class="space-y-2.5 mb-4">
                        <li v-for="s in nilaiList" :key="s.santri_id" class="rounded-2xl bg-white border border-gray-100 p-3.5">
                            <p class="text-sm font-bold text-gray-800 mb-2">{{ s.nama }}</p>
                            <div class="space-y-2">
                                <div v-for="field in [['keaktifan','Keaktifan'],['perkembangan','Perkembangan']]" :key="field[0]" class="flex items-center gap-2">
                                    <span class="text-[11px] text-gray-500 w-24 shrink-0">{{ field[1] }}</span>
                                    <div class="flex gap-1.5 flex-1">
                                        <button v-for="g in GRADE" :key="g" @click="setGrade(s, field[0], g)"
                                            class="flex-1 py-1.5 rounded-lg text-[12px] font-extrabold transition"
                                            :class="s[field[0]] === g ? gradeCls(g) + ' text-white' : 'bg-gray-100 text-gray-400'">{{ g }}</button>
                                    </div>
                                </div>
                                <input v-model="s.catatan" type="text" placeholder="Catatan (opsional)" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-[12px] outline-none focus:border-[#0C78FF]" />
                            </div>
                        </li>
                    </ul>
                    <button v-if="nilaiList.length" @click="simpanNilai" :disabled="busy" class="w-full py-3.5 rounded-2xl bg-[#0C78FF] text-white font-bold disabled:opacity-60">{{ busy ? 'Menyimpan…' : 'Simpan Penilaian' }}</button>
                </template>
            </template>
        </template>

        <!-- Sheet mulai pertemuan -->
        <BottomSheet v-model="sheet" title="Mulai Pertemuan">
            <label class="block text-[11px] font-medium text-gray-600 mb-1">Tanggal</label>
            <input v-model="f.tanggal" type="date" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-3" />
            <label class="block text-[11px] font-medium text-gray-600 mb-1">Materi (opsional)</label>
            <textarea v-model="f.materi" rows="2" placeholder="Materi/kegiatan hari ini…" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-3"></textarea>

            <!-- Status lokasi — wajib bila ekskul ini terkunci lokasi -->
            <div v-if="d?.wajib_lokasi !== false" class="flex items-center gap-2 mb-4 px-3 py-2.5 rounded-xl"
                :class="gps === 'ok' ? 'bg-emerald-50' : gps === 'fail' ? 'bg-rose-50' : 'bg-gray-50'">
                <span class="w-2 h-2 rounded-full shrink-0"
                    :class="gps === 'ok' ? 'bg-emerald-500' : gps === 'fail' ? 'bg-rose-500' : 'bg-gray-300'"></span>
                <p class="text-[11px] flex-1"
                    :class="gps === 'ok' ? 'text-emerald-700' : gps === 'fail' ? 'text-rose-600' : 'text-gray-500'">
                    {{ gps === 'ok' ? 'Lokasi terdeteksi — pertemuan direkam di titik ini.'
                        : gps === 'getting' ? 'Mengambil lokasi…'
                        : gps === 'fail' ? 'Lokasi tidak didapat. Aktifkan izin lokasi, lalu coba lagi.'
                        : 'Menunggu lokasi…' }}
                </p>
                <button v-if="gps === 'fail'" @click="mintaLokasi" class="text-[11px] font-bold text-[#0C78FF] shrink-0">Coba lagi</button>
            </div>

            <button @click="mulai" :disabled="busy || (d?.wajib_lokasi !== false && gps !== 'ok')"
                class="w-full py-3 rounded-xl bg-[#0C78FF] text-white font-bold text-sm disabled:opacity-60">
                {{ busy ? 'Memulai…' : (d?.wajib_lokasi !== false && gps !== 'ok') ? 'Menunggu lokasi…' : 'Mulai & Isi Absensi' }}
            </button>
        </BottomSheet>
    </div>
</template>
