<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import api from '../api'
import PageHeader from '../components/PageHeader.vue'

const tab = ref('menilai')
const loading = ref(true)
const msg = ref(null)

const status = ref(null)
const riwayat = ref([])
const kategori = ref([])
const saya = ref([])
const busy = ref(null)

const isPiket = computed(() => !!status.value?.is_piket)
const apresiasiCats = computed(() => kategori.value.filter((k) => k.jenis === 'apresiasi'))
const catatanCats = computed(() => kategori.value.filter((k) => k.jenis === 'catatan'))

async function loadMenilai() {
    loading.value = true
    try {
        const [r, k] = await Promise.all([api.get('/piket/penilaian'), api.get('/piket/kategori')])
        const d = r.data.data ?? r.data
        status.value = d.status
        riwayat.value = d.riwayat ?? []
        kategori.value = k.data.data ?? k.data ?? []
    } catch (e) { msg.value = { ok: false, text: e.response?.data?.message || 'Gagal memuat.' } }
    finally { loading.value = false }
}
async function loadSaya() {
    loading.value = true
    try {
        const res = await api.get('/piket/penilaian-saya')
        saya.value = res.data.data ?? res.data ?? []
    } catch (e) { msg.value = { ok: false, text: e.response?.data?.message || 'Gagal memuat.' } }
    finally { loading.value = false }
}
function pilihTab(t) {
    tab.value = t; msg.value = null
    if (t === 'menilai') loadMenilai()
    else if (t === 'saya') loadSaya()
    else loadKelas()
}
onMounted(loadMenilai)

// ── Absen Kelas (handoff — piket isi sesi yg guru asli tak konfirmasi) ───────
const sesiBoleh = ref(false)
const sesiAlasan = ref(null)
const sesiList = ref([])
async function loadKelas() {
    loading.value = true
    try {
        const res = await api.get('/piket/sesi')
        const d = res.data.data ?? res.data
        sesiBoleh.value = !!d.boleh; sesiAlasan.value = d.alasan; sesiList.value = d.sesi ?? []
    } catch (e) { msg.value = { ok: false, text: e.response?.data?.message || 'Gagal memuat.' } }
    finally { loading.value = false }
}

const rosterSesi = ref(null)
const rosterSantri = ref([])
const rAbsen = reactive({})
const rMateri = ref('')
const rLoading = ref(false)
const rSaving = ref(false)
async function bukaRoster(s) {
    rosterSesi.value = s; rosterSantri.value = []; rMateri.value = ''; msg.value = null; rLoading.value = true
    try {
        const res = await api.get(`/piket/roster/${s.jadwal_id}`)
        const d = res.data.data ?? res.data
        rosterSantri.value = d.santri ?? []
        rosterSantri.value.forEach((x) => { if (!rAbsen[x.santri_id]) rAbsen[x.santri_id] = 'hadir' })
    } catch (e) { msg.value = { ok: false, text: e.response?.data?.message || 'Gagal memuat roster.' }; rosterSesi.value = null }
    finally { rLoading.value = false }
}
async function submitAbsenKelas() {
    rSaving.value = true; msg.value = null
    try {
        await api.post('/piket/absen-kelas', {
            jadwal_id: rosterSesi.value.jadwal_id,
            absensi: rosterSantri.value.map((x) => ({ santri_id: x.santri_id, status: rAbsen[x.santri_id] || 'hadir' })),
            materi: rMateri.value.trim() || null,
        })
        rosterSesi.value = null
        msg.value = { ok: true, text: 'Absensi kelas tersimpan. Sesi ditandai tidak terlaksana (guru asli tanpa vakasi).' }
        await loadKelas()
    } catch (e) { msg.value = { ok: false, text: e.response?.data?.message || 'Gagal menyimpan.' } }
    finally { rSaving.value = false }
}
const rCycle = (id) => { const seq = ['hadir', 'telat', 'alpha']; rAbsen[id] = seq[(seq.indexOf(rAbsen[id]) + 1) % 3] }
const absColor = (s) => ({ hadir: 'bg-emerald-100 text-emerald-700', telat: 'bg-amber-100 text-amber-700', alpha: 'bg-red-100 text-red-600' }[s])

// ── Beri penilaian ──────────────────────────────────────────────────────────
const showForm = ref(false)
const guruQ = ref('')
const guruResults = ref([])
const guruSel = ref(null)
const katSel = ref(null)
const catatan = ref('')
const saving = ref(false)
let timer = null

function buka() { showForm.value = true; guruQ.value = ''; guruResults.value = []; guruSel.value = null; katSel.value = null; catatan.value = ''; msg.value = null }
function cariGuru() {
    clearTimeout(timer)
    timer = setTimeout(async () => {
        try { const res = await api.get('/piket/guru', { params: { q: guruQ.value } }); guruResults.value = res.data.data ?? res.data ?? [] } catch (_) {}
    }, 300)
}
function pilihGuru(g) { guruSel.value = g; guruResults.value = []; guruQ.value = g.nama }

async function kirim() {
    msg.value = null
    if (!guruSel.value) { msg.value = { ok: false, text: 'Pilih guru dulu.' }; return }
    if (!katSel.value) { msg.value = { ok: false, text: 'Pilih kategori penilaian.' }; return }
    saving.value = true
    try {
        await api.post('/piket/penilaian', {
            guru_dinilai_id: guruSel.value.id, kategori_id: katSel.value.id,
            catatan: catatan.value.trim() || null,
        })
        showForm.value = false
        msg.value = { ok: true, text: 'Penilaian tersimpan.' }
        await loadMenilai()
    } catch (e) { msg.value = { ok: false, text: e.response?.data?.message || 'Gagal menyimpan.' } }
    finally { saving.value = false }
}

async function laporanAman() {
    const c = prompt('Catatan harian piket:', 'Semua aman.')
    if (!c) return
    try { await api.post('/piket/laporan-harian', { catatan: c }); msg.value = { ok: true, text: 'Laporan harian tersimpan.' }; await loadMenilai() }
    catch (e) { msg.value = { ok: false, text: e.response?.data?.message || 'Gagal.' } }
}

async function sanggah(p) {
    const alasan = prompt('Alasan sanggahan:')
    if (!alasan || alasan.trim().length < 3) return
    busy.value = p.id
    try { await api.post(`/piket/penilaian/${p.id}/sanggah`, { alasan: alasan.trim() }); msg.value = { ok: true, text: 'Sanggahan diajukan.' }; await loadSaya() }
    catch (e) { msg.value = { ok: false, text: e.response?.data?.message || 'Gagal.' } }
    finally { busy.value = null }
}

const sanggahLabel = (s) => ({ diajukan: 'Sanggahan diproses', diterima: 'Sanggahan diterima', ditolak: 'Sanggahan ditolak' }[s] || null)
</script>

<template>
    <div>
        <PageHeader title="Guru Piket" />

        <RouterLink to="/piket/kegiatan"
            class="flex items-center gap-3 bg-[#0C78FF]/5 border border-[#0C78FF]/20 rounded-2xl p-3 mb-4">
            <div class="w-9 h-9 rounded-xl bg-[#0C78FF]/15 grid place-items-center text-[#0C78FF]">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            </div>
            <div class="flex-1">
                <p class="text-sm font-bold text-gray-800">Absen Kegiatan</p>
                <p class="text-xs text-gray-400">Catat kehadiran guru di kegiatan penting</p>
            </div>
            <svg class="w-4 h-4 text-[#0C78FF]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M9 5l7 7-7 7"/></svg>
        </RouterLink>

        <!-- Tab -->
        <div class="flex gap-1 mb-4 bg-gray-100 rounded-2xl p-1">
            <button @click="pilihTab('menilai')" class="flex-1 py-2 rounded-xl text-[13px] font-bold" :class="tab === 'menilai' ? 'bg-white text-[#0C78FF] shadow-sm' : 'text-gray-400'">Menilai</button>
            <button @click="pilihTab('kelas')" class="flex-1 py-2 rounded-xl text-[13px] font-bold" :class="tab === 'kelas' ? 'bg-white text-[#0C78FF] shadow-sm' : 'text-gray-400'">Absen Kelas</button>
            <button @click="pilihTab('saya')" class="flex-1 py-2 rounded-xl text-[13px] font-bold" :class="tab === 'saya' ? 'bg-white text-[#0C78FF] shadow-sm' : 'text-gray-400'">Nilai Saya</button>
        </div>

        <p v-if="msg" :class="msg.ok ? 'text-emerald-700 bg-emerald-50' : 'text-red-600 bg-red-50'" class="text-sm rounded-xl px-3 py-2 mb-3">{{ msg.text }}</p>

        <div v-if="loading" class="pt-10 flex justify-center"><div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div></div>

        <!-- ══ MENILAI ══ -->
        <template v-else-if="tab === 'menilai'">
            <div v-if="!isPiket" class="rounded-2xl bg-amber-50 border border-amber-200 p-4 text-sm text-amber-700">
                {{ status?.alasan || 'Anda tidak ditugaskan piket hari ini.' }}
            </div>
            <template v-else>
                <div class="rounded-2xl bg-gradient-to-br from-[#06346B] to-[#0C78FF] text-white p-4 mb-4">
                    <p class="text-sm font-bold">Anda bertugas piket hari ini</p>
                    <p class="text-[11px] text-white/70 mt-0.5">{{ status.window?.aktif ? 'Sedang aktif (sudah absen masuk).' : (status.alasan || 'Aktif setelah absen masuk.') }}</p>
                </div>

                <div class="flex gap-2 mb-4">
                    <button @click="buka" class="flex-1 py-3 rounded-2xl bg-[#0C78FF] text-white font-bold text-sm">+ Beri Penilaian</button>
                    <button @click="laporanAman" class="px-4 py-3 rounded-2xl bg-emerald-50 text-emerald-600 font-bold text-sm">Lapor Harian</button>
                </div>

                <h2 class="text-sm font-bold text-gray-800 mb-2">Penilaian Hari Ini</h2>
                <div v-if="!riwayat.length" class="py-8 text-center text-sm text-gray-400">Belum ada penilaian hari ini.</div>
                <ul v-else class="space-y-2.5">
                    <li v-for="p in riwayat" :key="p.id" class="rounded-2xl bg-white border border-gray-100 p-3.5">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-bold text-gray-800">{{ p.guru }}</p>
                            <span class="text-[11px] font-bold px-2 py-0.5 rounded-full" :class="p.jenis === 'apresiasi' ? 'text-emerald-600 bg-emerald-50' : 'text-red-500 bg-red-50'">{{ p.poin > 0 ? '+' : '' }}{{ p.poin }}</span>
                        </div>
                        <p class="text-[11px] text-gray-500 mt-0.5">{{ p.kategori }} · {{ p.waktu }}</p>
                        <p v-if="p.catatan" class="text-[11px] text-gray-400 mt-1">{{ p.catatan }}</p>
                    </li>
                </ul>
            </template>
        </template>

        <!-- ══ ABSEN KELAS (HANDOFF) ══ -->
        <template v-else-if="tab === 'kelas'">
            <div v-if="!sesiBoleh" class="rounded-2xl bg-amber-50 border border-amber-200 p-4 text-sm text-amber-700">
                {{ sesiAlasan || 'Absen kelas hanya saat Anda bertugas piket & sudah absen masuk.' }}
            </div>
            <template v-else>
                <p class="text-[11px] text-gray-400 mb-3">Sesi yang <b>gurunya belum konfirmasi</b> setelah jam mengajar berakhir. Piket mengisi kehadiran santri — sesi ditandai <b>tidak terlaksana</b> (vakasi guru asli tidak diberikan).</p>
                <div v-if="!sesiList.length" class="pt-12 text-center text-sm text-gray-400">Tidak ada sesi yang perlu diisi. Semua terkonfirmasi ✓</div>
                <ul v-else class="space-y-3">
                    <li v-for="s in sesiList" :key="s.jadwal_id" class="rounded-2xl bg-white border border-gray-100 p-4">
                        <div class="flex items-start gap-3">
                            <div class="w-12 text-center shrink-0">
                                <svg class="w-6 h-6 mx-auto text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0L3.16 16.25A2 2 0 005 19z"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-gray-800 truncate">{{ s.mata_pelajaran }}</p>
                                <p class="text-[11px] text-gray-400">{{ s.kelas }} · {{ s.jam }}</p>
                                <p class="text-[11px] text-red-500 mt-0.5">Guru: {{ s.guru }} (belum konfirmasi)</p>
                                <button @click="bukaRoster(s)" class="mt-2 px-4 py-1.5 rounded-lg bg-[#0C78FF] text-white text-xs font-bold">Isi Kehadiran</button>
                            </div>
                        </div>
                    </li>
                </ul>
            </template>
        </template>

        <!-- ══ PENILAIAN SAYA ══ -->
        <template v-else-if="tab === 'saya'">
            <p class="text-xs text-gray-400 mb-3">Penilaian dari guru piket atas kinerja Anda. Bisa disanggah bila keliru.</p>
            <div v-if="!saya.length" class="pt-10 text-center text-sm text-gray-400">Belum ada penilaian piket atas Anda.</div>
            <ul v-else class="space-y-2.5">
                <li v-for="p in saya" :key="p.id" class="rounded-2xl bg-white border border-gray-100 p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-bold text-gray-800">{{ p.kategori || (p.jenis === 'apresiasi' ? 'Apresiasi' : 'Catatan') }}</p>
                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full" :class="Number(p.poin) >= 0 ? 'text-emerald-600 bg-emerald-50' : 'text-red-500 bg-red-50'">{{ Number(p.poin) > 0 ? '+' : '' }}{{ p.poin }}</span>
                    </div>
                    <p v-if="p.catatan" class="text-[12px] text-gray-500 mt-1">{{ p.catatan }}</p>
                    <p class="text-[11px] text-gray-400 mt-1">{{ p.tanggal || p.waktu }}<span v-if="p.penilai"> · oleh {{ p.penilai }}</span></p>
                    <p v-if="sanggahLabel(p.status_sanggah)" class="text-[11px] mt-1 font-semibold" :class="p.status_sanggah === 'diterima' ? 'text-emerald-600' : p.status_sanggah === 'ditolak' ? 'text-red-500' : 'text-amber-600'">{{ sanggahLabel(p.status_sanggah) }}</p>
                    <button v-if="!p.status_sanggah || p.status_sanggah === 'tidak_ada'" @click="sanggah(p)" :disabled="busy === p.id"
                        class="mt-2 text-[11px] font-bold text-[#0C78FF] disabled:opacity-60">Sanggah penilaian</button>
                </li>
            </ul>
        </template>

        <!-- Form beri penilaian -->
        <Transition name="pop">
            <div v-if="showForm" class="fixed inset-0 z-[70] flex items-end justify-center" style="background: rgba(0,0,0,0.55)">
                <div class="w-full max-w-md bg-white rounded-t-3xl p-5 pb-8 safe-b max-h-[92vh] overflow-y-auto">
                    <div class="w-10 h-1 bg-gray-200 rounded-full mx-auto mb-4"></div>
                    <h3 class="text-base font-extrabold text-gray-900 mb-4">Beri Penilaian</h3>

                    <label class="block text-[11px] font-medium text-gray-600 mb-1">Guru dinilai</label>
                    <div v-if="guruSel" class="flex items-center justify-between bg-blue-50 rounded-xl px-3 py-2.5 mb-3">
                        <span class="text-sm font-semibold text-blue-700">{{ guruSel.nama }}</span>
                        <button @click="guruSel = null; guruQ = ''" class="text-[11px] text-blue-600 font-bold">Ganti</button>
                    </div>
                    <template v-else>
                        <input v-model="guruQ" @input="cariGuru" type="text" placeholder="Cari nama guru…" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-2" />
                        <ul v-if="guruResults.length" class="border border-gray-100 rounded-xl mb-3 max-h-40 overflow-y-auto divide-y divide-gray-50">
                            <li v-for="g in guruResults" :key="g.id" @click="pilihGuru(g)" class="px-3 py-2 text-sm text-gray-700 active:bg-gray-50 flex justify-between">
                                <span>{{ g.nama }}</span>
                                <span class="text-[10px] text-gray-400 capitalize">{{ (g.status_absen || '').replace('_',' ') }}</span>
                            </li>
                        </ul>
                    </template>

                    <label class="block text-[11px] font-medium text-gray-600 mb-1 mt-1">Kategori</label>
                    <p class="text-[10px] font-bold text-emerald-600 mb-1">Apresiasi (+)</p>
                    <div class="flex flex-wrap gap-1.5 mb-2">
                        <button v-for="k in apresiasiCats" :key="k.id" @click="katSel = k"
                            class="text-[11px] font-semibold px-2.5 py-1.5 rounded-lg border" :class="katSel?.id === k.id ? 'bg-emerald-500 text-white border-emerald-500' : 'bg-white text-emerald-600 border-emerald-200'">{{ k.nama }} +{{ k.poin }}</button>
                    </div>
                    <p class="text-[10px] font-bold text-red-500 mb-1">Catatan (−)</p>
                    <div class="flex flex-wrap gap-1.5 mb-3">
                        <button v-for="k in catatanCats" :key="k.id" @click="katSel = k"
                            class="text-[11px] font-semibold px-2.5 py-1.5 rounded-lg border" :class="katSel?.id === k.id ? 'bg-red-500 text-white border-red-500' : 'bg-white text-red-500 border-red-200'">{{ k.nama }} −{{ k.poin }}</button>
                    </div>

                    <label class="block text-[11px] font-medium text-gray-600 mb-1">Catatan (opsional)</label>
                    <textarea v-model="catatan" rows="2" placeholder="keterangan tambahan…" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-4"></textarea>

                    <div class="flex gap-3">
                        <button @click="showForm = false" class="flex-1 py-3 rounded-xl bg-gray-100 text-gray-600 font-semibold text-sm">Batal</button>
                        <button @click="kirim" :disabled="saving" class="flex-1 py-3 rounded-xl bg-[#0C78FF] text-white font-bold text-sm disabled:opacity-60">{{ saving ? 'Menyimpan…' : 'Simpan' }}</button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Modal roster absen kelas (handoff) -->
        <Transition name="pop">
            <div v-if="rosterSesi" class="fixed inset-0 z-[70] flex items-end justify-center" style="background: rgba(0,0,0,0.55)">
                <div class="w-full max-w-md bg-white rounded-t-3xl p-5 pb-8 safe-b max-h-[92vh] overflow-y-auto">
                    <div class="w-10 h-1 bg-gray-200 rounded-full mx-auto mb-4"></div>
                    <h3 class="text-base font-extrabold text-gray-900">{{ rosterSesi.mata_pelajaran }}</h3>
                    <p class="text-xs text-gray-400 mb-1">{{ rosterSesi.kelas }} · gantikan {{ rosterSesi.guru }}</p>
                    <p class="text-[11px] text-amber-600 bg-amber-50 rounded-lg px-2 py-1.5 mb-3">Sesi akan ditandai <b>tidak terlaksana</b> — guru asli tidak mendapat vakasi.</p>

                    <div v-if="rLoading" class="py-6 flex justify-center"><div class="w-6 h-6 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div></div>
                    <template v-else>
                        <p class="text-[11px] text-gray-400 mb-2">Ketuk status tiap santri (Hadir → Telat → Alpha).</p>
                        <div class="space-y-1.5 mb-3 max-h-64 overflow-y-auto">
                            <div v-for="s in rosterSantri" :key="s.santri_id" class="flex items-center justify-between bg-gray-50 rounded-xl px-3 py-2">
                                <span class="text-sm text-gray-700 truncate">{{ s.nama }}</span>
                                <button @click="rCycle(s.santri_id)" class="text-[11px] font-bold px-2.5 py-1 rounded-full capitalize" :class="absColor(rAbsen[s.santri_id])">{{ rAbsen[s.santri_id] }}</button>
                            </div>
                        </div>
                        <input v-model="rMateri" type="text" placeholder="Materi/keterangan (opsional)" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-4" />
                        <div class="flex gap-3">
                            <button @click="rosterSesi = null" class="flex-1 py-3 rounded-xl bg-gray-100 text-gray-600 font-semibold text-sm">Batal</button>
                            <button @click="submitAbsenKelas" :disabled="rSaving || !rosterSantri.length" class="flex-1 py-3 rounded-xl bg-[#0C78FF] text-white font-bold text-sm disabled:opacity-60">{{ rSaving ? 'Menyimpan…' : 'Simpan Kehadiran' }}</button>
                        </div>
                    </template>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.pop-enter-active, .pop-leave-active { transition: opacity .2s ease; }
.pop-enter-from, .pop-leave-to { opacity: 0; }
</style>
