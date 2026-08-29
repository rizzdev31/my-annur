<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '../api'
import PageHeader from '../components/PageHeader.vue'

const route = useRoute()
const jadwalId = route.params.jadwalId

const info = ref(null)
const santri = ref([])
const loading = ref(true)
const error = ref('')
const msg = ref(null)

// ── Absen (gerbang) ─────────────────────────────────────────────────────────
const absenStatus = reactive({})
const absenCatatan = ref('')
const absenSaving = ref(false)

async function load() {
    loading.value = true; error.value = ''
    try {
        const res = await api.get(`/education/tahsin/jadwal/${jadwalId}/roster`)
        info.value = res.data.data ?? res.data
        santri.value = info.value.santri ?? []
        santri.value.forEach((x) => { if (!absenStatus[x.santri_id]) absenStatus[x.santri_id] = 'hadir' })
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal memuat roster.'
    } finally { loading.value = false }
}
onMounted(load)

async function submitAbsen() {
    absenSaving.value = true; msg.value = null
    try {
        const payload = {
            jadwal_id: Number(jadwalId),
            absensi: santri.value.map((x) => ({ santri_id: x.santri_id, status: absenStatus[x.santri_id] || 'hadir' })),
            catatan: absenCatatan.value.trim() || null,
        }
        const res = await api.post('/education/tahsin/absen', payload)
        info.value.absensi_mengajar_id = res.data.data?.absensi_mengajar_id
        info.value.wajib_absen = false; info.value.sudah_absen = true
        msg.value = { ok: true, text: res.data.message || 'Absen tersimpan. Lanjut penilaian.' }
    } catch (e) {
        const code = e.response?.data?.code
        if (code === 'SUDAH_ABSEN' && e.response?.data?.data?.absensi_mengajar_id) {
            info.value.absensi_mengajar_id = e.response.data.data.absensi_mengajar_id
            info.value.wajib_absen = false; info.value.sudah_absen = true
            msg.value = { ok: true, text: 'Kehadiran sudah terkunci. Lanjut penilaian.' }
        } else {
            msg.value = { ok: false, text: e.response?.data?.message || 'Gagal menyimpan absen.' }
        }
    } finally { absenSaving.value = false }
}

// ── Penilaian ───────────────────────────────────────────────────────────────
const aktif = ref(null)
const materiList = ref([])
const levelSelesai = ref(false)
const loadingMateri = ref(false)
const f = reactive({ materi_id: '', nilai: '', catatan: '' })
const saving = ref(false)

async function bukaNilai(s) {
    aktif.value = s; msg.value = null; materiList.value = []; levelSelesai.value = false
    Object.assign(f, { materi_id: '', nilai: '', catatan: '' })
    loadingMateri.value = true
    try {
        const res = await api.get(`/education/tahsin/santri/${s.santri_id}/materi`)
        const d = res.data.data ?? res.data
        materiList.value = d.materi ?? []
        levelSelesai.value = !!d.level_selesai
        // default: materi pertama yang belum lulus
        const belum = materiList.value.find((m) => !m.lulus)
        f.materi_id = (belum || materiList.value[0])?.materi_id || ''
    } catch (_) {/* diamkan */} finally { loadingMateri.value = false }
}

async function kirimNilai() {
    msg.value = null
    if (!f.materi_id) { msg.value = { ok: false, text: 'Pilih materi dulu.' }; return }
    if (!f.nilai || f.nilai < 1 || f.nilai > 10) { msg.value = { ok: false, text: 'Nilai 1–10.' }; return }
    if (!f.catatan.trim() || f.catatan.trim().length < 3) { msg.value = { ok: false, text: 'Catatan wajib (min 3 huruf).' }; return }
    saving.value = true
    try {
        const res = await api.post('/education/tahsin/nilai', {
            absensi_mengajar_id: info.value.absensi_mengajar_id || null,
            santri_id: aktif.value.santri_id,
            materi_id: Number(f.materi_id),
            nilai: Number(f.nilai),
            catatan: f.catatan.trim(),
        })
        aktif.value = null
        msg.value = { ok: true, text: res.data.message || 'Nilai tersimpan.' }
        await load()
    } catch (e) {
        msg.value = { ok: false, text: e.response?.data?.message || 'Gagal menyimpan nilai.' }
    } finally { saving.value = false }
}

// ── Tasnif: ujian kenaikan level (tunjuk penguji) ────────────────────────────
const tasnif = ref(null)          // santri yang akan diujikan
const pengujiList = ref([])
const tPenguji = ref('')
const tSaving = ref(false)

async function bukaTasnif(s) {
    tasnif.value = s; tPenguji.value = ''; msg.value = null
    if (!pengujiList.value.length) {
        try { const r = await api.get('/education/tahsin/penguji-opsi'); pengujiList.value = r.data.data ?? [] } catch (_) {/* diamkan */}
    }
    // default penguji = guru pengampu (diri sendiri) bila tersedia
    const saya = pengujiList.value.find((g) => g.saya)
    if (saya) tPenguji.value = saya.id
}
async function kirimTasnif() {
    if (!tPenguji.value) { msg.value = { ok: false, text: 'Pilih penguji.' }; return }
    tSaving.value = true; msg.value = null
    try {
        const res = await api.post('/education/tahsin/tunjuk-tasnif', {
            santri_id: tasnif.value.santri_id, level: tasnif.value.level, penguji_id: Number(tPenguji.value),
        })
        tasnif.value = null
        msg.value = { ok: true, text: res.data.message || 'Penguji tasnif ditunjuk. Cek menu Tasmi\' & Tasnif.' }
        await load()
    } catch (e) {
        msg.value = { ok: false, text: e.response?.data?.message || 'Gagal menunjuk penguji.' }
    } finally { tSaving.value = false }
}

const cycleAbsen = (id) => { const seq = ['hadir', 'telat', 'alpha']; absenStatus[id] = seq[(seq.indexOf(absenStatus[id]) + 1) % 3] }
const absenColor = (s) => ({ hadir: 'bg-emerald-100 text-emerald-700', telat: 'bg-amber-100 text-amber-700', alpha: 'bg-red-100 text-red-600' }[s])
</script>

<template>
    <div>
        <PageHeader :title="info?.kelas || 'Kelas Tahsin'" />

        <div v-if="loading" class="pt-10 flex justify-center">
            <div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div>
        </div>
        <div v-else-if="error" class="pt-8 text-center">
            <p class="text-sm text-gray-500">{{ error }}</p>
            <button @click="load" class="mt-3 px-4 py-2 rounded-xl bg-[#0C78FF] text-white text-sm font-semibold">Coba lagi</button>
        </div>

        <template v-else>
            <p v-if="msg" :class="msg.ok ? 'text-emerald-700 bg-emerald-50' : 'text-red-600 bg-red-50'"
                class="text-sm rounded-xl px-3 py-2 mb-3">{{ msg.text }}</p>

            <p class="text-xs text-gray-400 mb-3">Level {{ info.level || '—' }} · {{ info.total_santri }} santri</p>

            <!-- GERBANG ABSEN -->
            <div v-if="info.wajib_absen" class="rounded-2xl bg-amber-50 border border-amber-200 p-4 mb-4">
                <p class="text-sm font-bold text-amber-800 mb-1">Absen Kehadiran Dulu</p>
                <p class="text-[11px] text-amber-600 mb-3">Ketuk status tiap santri (Hadir → Telat → Alpha), lalu simpan.</p>
                <div class="space-y-1.5 mb-3 max-h-64 overflow-y-auto">
                    <div v-for="s in santri" :key="s.santri_id" class="flex items-center justify-between bg-white rounded-xl px-3 py-2">
                        <span class="text-sm text-gray-700 truncate">{{ s.nama }}</span>
                        <button @click="cycleAbsen(s.santri_id)" class="text-[11px] font-bold px-2.5 py-1 rounded-full capitalize" :class="absenColor(absenStatus[s.santri_id])">{{ absenStatus[s.santri_id] }}</button>
                    </div>
                </div>
                <input v-model="absenCatatan" type="text" placeholder="Catatan sesi (opsional)" class="w-full px-3 py-2.5 rounded-xl border border-amber-200 text-sm outline-none mb-3" />
                <button @click="submitAbsen" :disabled="absenSaving" class="w-full py-3 rounded-xl bg-amber-600 text-white font-bold text-sm disabled:opacity-60">
                    {{ absenSaving ? 'Menyimpan…' : 'Simpan Kehadiran & Kunci' }}
                </button>
            </div>

            <!-- Daftar santri -->
            <ul class="space-y-2.5">
                <li v-for="s in santri" :key="s.santri_id"
                    class="rounded-2xl bg-white border border-gray-100 p-3.5"
                    :class="info.wajib_absen ? 'opacity-50 pointer-events-none' : ''">
                    <div class="flex items-center gap-3">
                        <div class="flex-1 min-w-0" @click="!info.wajib_absen && bukaNilai(s)">
                            <p class="text-sm font-bold text-gray-800 truncate">{{ s.nama }}</p>
                            <p class="text-[11px] text-gray-400">
                                Level {{ s.level || '—' }} · {{ s.materi_lulus }}/{{ s.materi_total }} materi lulus
                                <span v-if="s.level_selesai" class="text-emerald-600 font-bold"> · Lengkap</span>
                            </p>
                        </div>
                        <button v-if="s.level_selesai" @click.stop="bukaTasnif(s)"
                            class="text-[10px] font-bold text-white bg-violet-600 px-2.5 py-1 rounded-lg active:scale-95 transition">Ujian Tasnif</button>
                        <button @click="!info.wajib_absen && bukaNilai(s)" class="text-[10px] font-bold text-[#0C78FF] bg-[#0C78FF]/10 px-2.5 py-1 rounded-lg">Nilai</button>
                    </div>
                </li>
            </ul>
        </template>

        <!-- Modal nilai -->
        <Transition name="pop">
            <div v-if="aktif" class="fixed inset-0 z-[70] flex items-end justify-center" style="background: rgba(0,0,0,0.55)">
                <div class="w-full max-w-md bg-white rounded-t-3xl p-5 pb-8 safe-b max-h-[92vh] overflow-y-auto">
                    <div class="w-10 h-1 bg-gray-200 rounded-full mx-auto mb-4"></div>
                    <h3 class="text-base font-extrabold text-gray-900">{{ aktif.nama }}</h3>
                    <p class="text-xs text-gray-400 mb-4">Level {{ aktif.level || '—' }} · {{ aktif.materi_lulus }}/{{ aktif.materi_total }} lulus</p>

                    <div v-if="loadingMateri" class="py-6 flex justify-center"><div class="w-6 h-6 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div></div>
                    <template v-else>
                        <label class="block text-[11px] font-medium text-gray-600 mb-1">Materi</label>
                        <select v-model="f.materi_id" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-3">
                            <option value="">— pilih materi —</option>
                            <option v-for="m in materiList" :key="m.materi_id" :value="m.materi_id">
                                {{ m.nama }}{{ m.lulus ? ' ✓' : (m.sudah_dinilai ? ' (belum lulus)' : '') }}
                            </option>
                        </select>

                        <label class="block text-[11px] font-medium text-gray-600 mb-1">Nilai (1–10) <span class="text-red-500">*</span></label>
                        <input v-model="f.nilai" type="number" min="1" max="10" step="0.5" placeholder="mis. 8.5" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-3" />

                        <label class="block text-[11px] font-medium text-gray-600 mb-1">Catatan <span class="text-red-500">*</span></label>
                        <textarea v-model="f.catatan" rows="2" placeholder="mis. makhroj sudah baik, perlu latihan panjang-pendek…" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-4"></textarea>

                        <div class="flex gap-3">
                            <button @click="aktif = null" class="flex-1 py-3 rounded-xl bg-gray-100 text-gray-600 font-semibold text-sm">Batal</button>
                            <button @click="kirimNilai" :disabled="saving" class="flex-1 py-3 rounded-xl bg-violet-600 text-white font-bold text-sm disabled:opacity-60">
                                {{ saving ? 'Menyimpan…' : 'Simpan Nilai' }}
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </Transition>

        <!-- Modal tunjuk penguji Tasnif (ujian kenaikan level) -->
        <Transition name="pop">
            <div v-if="tasnif" class="fixed inset-0 z-[70] flex items-end justify-center" style="background: rgba(0,0,0,0.55)">
                <div class="w-full max-w-md bg-white rounded-t-3xl p-5 pb-8 safe-b">
                    <div class="w-10 h-1 bg-gray-200 rounded-full mx-auto mb-4"></div>
                    <h3 class="text-base font-extrabold text-gray-900">Ujian Kenaikan (Tasnif)</h3>
                    <p class="text-xs text-gray-400 mb-4">{{ tasnif.nama }} · Level {{ tasnif.level }} → naik level</p>

                    <div class="rounded-xl bg-violet-50 border border-violet-100 p-3 mb-4 text-[12px] text-violet-700">
                        Semua materi level ini sudah lengkap. Tunjuk penguji untuk ujian kenaikan level (rubrik: Pemahaman Materi, Kelancaran, Fashohah, Makhorijul Huruf). Lulus (≥8) → santri otomatis naik level + sertifikat.
                    </div>

                    <label class="block text-[11px] font-medium text-gray-600 mb-1">Penguji</label>
                    <select v-model="tPenguji" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-4">
                        <option value="">— pilih penguji —</option>
                        <option v-for="g in pengujiList" :key="g.id" :value="g.id">{{ g.nama }}{{ g.saya ? ' (saya)' : '' }}</option>
                    </select>

                    <div class="flex gap-3">
                        <button @click="tasnif = null" class="flex-1 py-3 rounded-xl bg-gray-100 text-gray-600 font-semibold text-sm">Batal</button>
                        <button @click="kirimTasnif" :disabled="tSaving" class="flex-1 py-3 rounded-xl bg-violet-600 text-white font-bold text-sm disabled:opacity-60">
                            {{ tSaving ? 'Menyimpan…' : 'Tunjuk Penguji' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.pop-enter-active, .pop-leave-active { transition: opacity .2s ease; }
.pop-enter-from, .pop-leave-to { opacity: 0; }
</style>
