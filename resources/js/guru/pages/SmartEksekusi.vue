<script setup>
import { ref, reactive, onMounted } from 'vue'
import { tanggalLokal } from '../tanggal'
import api from '../api'
import PageHeader from '../components/PageHeader.vue'

const tab = ref('lapor')
const msg = ref(null)
const loading = ref(false)

// ── Form lapor ──────────────────────────────────────────────────────────────
const jenisOpts = [
    { key: 'pelanggaran', label: 'Pelanggaran', c: 'text-red-500', bg: 'bg-red-50', bd: 'border-red-500' },
    { key: 'apresiasi',   label: 'Apresiasi',   c: 'text-emerald-600', bg: 'bg-emerald-50', bd: 'border-emerald-500' },
    { key: 'konselor',    label: 'Konselor (BK)', c: 'text-[#0C78FF]', bg: 'bg-blue-50', bd: 'border-[#0C78FF]' },
]
const jenis = ref('pelanggaran')
const kodeList = ref([])
const f = reactive({ kode: '', tanggal: tanggalLokal(), catatan: '' })
const saving = ref(false)

// santri picker (pelaku + korban)
const pelaku = ref(null)
const korban = ref(null)
const pickTarget = ref(null) // 'pelaku' | 'korban'
const q = ref('')
const results = ref([])
let timer = null

async function loadKode() {
    f.kode = ''; kodeList.value = []
    try { const res = await api.get(`/smart-habbit/kode/${jenis.value}`); kodeList.value = res.data.data ?? res.data ?? [] } catch (_) {}
}
function gantiJenis(j) { jenis.value = j; loadKode() }
onMounted(loadKode)

function cari() {
    clearTimeout(timer)
    timer = setTimeout(async () => {
        try { const res = await api.get('/smart-habbit/santri', { params: { q: q.value } }); results.value = res.data.data ?? res.data ?? [] } catch (_) {}
    }, 300)
}
function pilih(s) {
    if (pickTarget.value === 'korban') korban.value = s; else pelaku.value = s
    pickTarget.value = null; q.value = ''; results.value = []
}

async function kirim() {
    msg.value = null
    if (!f.kode) { msg.value = { ok: false, text: 'Pilih kode terlebih dahulu.' }; return }
    if (!pelaku.value) { msg.value = { ok: false, text: 'Pilih santri (pelaku/subjek).' }; return }
    saving.value = true
    try {
        await api.post('/smart-habbit/eksekusi', {
            jenis: jenis.value, kode: f.kode, tanggal: f.tanggal,
            catatan: f.catatan.trim() || null,
            pelaku_santri_id: pelaku.value.id,
            korban_santri_id: korban.value?.id ?? null,
        })
        msg.value = { ok: true, text: 'Laporan terkirim ke RamahAnak (BK).' }
        f.kode = ''; f.catatan = ''; pelaku.value = null; korban.value = null
    } catch (e) {
        const errs = e.response?.data?.errors
        msg.value = { ok: false, text: errs ? Object.values(errs)[0][0] : (e.response?.data?.message || 'Gagal mengirim.') }
    } finally { saving.value = false }
}

// ── Outbox ──────────────────────────────────────────────────────────────────
const outbox = ref([])
async function loadOutbox() {
    loading.value = true
    try { const res = await api.get('/smart-habbit/outbox'); outbox.value = res.data.data ?? res.data ?? [] } catch (_) {}
    finally { loading.value = false }
}
function pilihTab(t) { tab.value = t; msg.value = null; if (t === 'outbox') loadOutbox() }
async function retry(o) {
    try { await api.post(`/smart-habbit/outbox/${o.id}/retry`); msg.value = { ok: true, text: 'Dikirim ulang.' }; await loadOutbox() }
    catch (e) { msg.value = { ok: false, text: e.response?.data?.message || 'Gagal.' } }
}
const outColor = (s) => ({ sent: 'text-emerald-600 bg-emerald-50', pending: 'text-amber-600 bg-amber-50', duplicate: 'text-gray-500 bg-gray-100', failed: 'text-red-500 bg-red-50' }[s] || 'text-gray-500 bg-gray-100')
</script>

<template>
    <div>
        <PageHeader title="Smart Eksekusi" />

        <div class="flex gap-2 mb-4 bg-gray-100 rounded-2xl p-1">
            <button @click="pilihTab('lapor')" class="flex-1 py-2 rounded-xl text-sm font-bold" :class="tab === 'lapor' ? 'bg-white text-[#0C78FF] shadow-sm' : 'text-gray-400'">Lapor</button>
            <button @click="pilihTab('outbox')" class="flex-1 py-2 rounded-xl text-sm font-bold" :class="tab === 'outbox' ? 'bg-white text-[#0C78FF] shadow-sm' : 'text-gray-400'">Outbox</button>
        </div>

        <p v-if="msg" :class="msg.ok ? 'text-emerald-700 bg-emerald-50' : 'text-red-600 bg-red-50'" class="text-sm rounded-xl px-3 py-2 mb-3">{{ msg.text }}</p>

        <!-- ══ LAPOR ══ -->
        <template v-if="tab === 'lapor'">
            <p class="text-[11px] text-gray-400 mb-2">Laporkan santri ke Guru BK (RamahAnak).</p>

            <div class="grid grid-cols-3 gap-2 mb-4">
                <button v-for="j in jenisOpts" :key="j.key" @click="gantiJenis(j.key)"
                    class="py-2.5 rounded-xl text-[12px] font-bold border" :class="jenis === j.key ? [j.bg, j.c, j.bd] : 'bg-white text-gray-400 border-gray-200'">{{ j.label }}</button>
            </div>

            <div class="rounded-2xl bg-white border border-gray-100 p-4 space-y-3">
                <div>
                    <label class="block text-[11px] font-medium text-gray-600 mb-1">Kode</label>
                    <select v-model="f.kode" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none">
                        <option value="">— pilih kode —</option>
                        <option v-for="k in kodeList" :key="k.kode" :value="k.kode">{{ k.kode }} · {{ k.label }}<span v-if="k.poin"> ({{ k.poin }})</span></option>
                    </select>
                </div>

                <!-- Santri pelaku -->
                <div>
                    <label class="block text-[11px] font-medium text-gray-600 mb-1">{{ jenis === 'apresiasi' ? 'Santri' : 'Pelaku / Subjek' }}</label>
                    <div v-if="pelaku" class="flex items-center justify-between bg-blue-50 rounded-xl px-3 py-2.5">
                        <span class="text-sm font-semibold text-blue-700">{{ pelaku.nama }}</span>
                        <button @click="pelaku = null" class="text-[11px] text-blue-600 font-bold">Ganti</button>
                    </div>
                    <button v-else @click="pickTarget = 'pelaku'; q = ''; results = []" class="w-full text-left px-3 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-400">+ pilih santri</button>
                </div>

                <!-- Korban (opsional, untuk pelanggaran) -->
                <div v-if="jenis === 'pelanggaran'">
                    <label class="block text-[11px] font-medium text-gray-600 mb-1">Korban (opsional)</label>
                    <div v-if="korban" class="flex items-center justify-between bg-gray-50 rounded-xl px-3 py-2.5">
                        <span class="text-sm font-semibold text-gray-700">{{ korban.nama }}</span>
                        <button @click="korban = null" class="text-[11px] text-gray-500 font-bold">Hapus</button>
                    </div>
                    <button v-else @click="pickTarget = 'korban'; q = ''; results = []" class="w-full text-left px-3 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-400">+ pilih korban</button>
                </div>

                <div>
                    <label class="block text-[11px] font-medium text-gray-600 mb-1">Tanggal</label>
                    <input v-model="f.tanggal" type="date" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none" />
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-gray-600 mb-1">Catatan (opsional)</label>
                    <textarea v-model="f.catatan" rows="2" placeholder="kronologi / keterangan…" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none"></textarea>
                </div>

                <button @click="kirim" :disabled="saving" class="w-full py-3 rounded-xl bg-[#0C78FF] text-white font-bold text-sm disabled:opacity-60">{{ saving ? 'Mengirim…' : 'Kirim Laporan' }}</button>
            </div>
        </template>

        <!-- ══ OUTBOX ══ -->
        <template v-else>
            <div v-if="loading" class="pt-10 flex justify-center"><div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div></div>
            <div v-else-if="!outbox.length" class="pt-12 text-center text-sm text-gray-400">Belum ada laporan terkirim.</div>
            <ul v-else class="space-y-2.5">
                <li v-for="o in outbox" :key="o.id" class="rounded-2xl bg-white border border-gray-100 p-3.5">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-bold text-gray-800 capitalize">{{ o.jenis }} · {{ o.kode }}</p>
                        <span class="text-[10px] font-bold px-2.5 py-1 rounded-full capitalize" :class="outColor(o.status)">{{ o.status }}</span>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">{{ o.tanggal }}<span v-if="o.sent_at"> · terkirim {{ o.sent_at }}</span></p>
                    <p v-if="o.error" class="text-[11px] text-red-500 mt-1">{{ o.error }}</p>
                    <button v-if="o.status === 'failed' || o.status === 'pending'" @click="retry(o)" class="mt-2 text-[11px] font-bold text-[#0C78FF]">Kirim ulang</button>
                </li>
            </ul>
        </template>

        <!-- Sheet pilih santri -->
        <Transition name="pop">
            <div v-if="pickTarget" class="fixed inset-0 z-[70] flex items-end justify-center" style="background: rgba(0,0,0,0.55)">
                <div class="w-full max-w-md bg-white rounded-t-3xl p-5 pb-8 safe-b">
                    <div class="w-10 h-1 bg-gray-200 rounded-full mx-auto mb-4"></div>
                    <h3 class="text-base font-extrabold text-gray-900 mb-3">Pilih {{ pickTarget === 'korban' ? 'Korban' : 'Santri' }}</h3>
                    <input v-model="q" @input="cari" type="text" placeholder="Cari nama / NIP…" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-2" />
                    <ul v-if="results.length" class="border border-gray-100 rounded-xl max-h-64 overflow-y-auto divide-y divide-gray-50">
                        <li v-for="s in results" :key="s.id" @click="pilih(s)" class="px-3 py-2.5 text-sm text-gray-700 active:bg-gray-50">{{ s.nama }} <span class="text-[11px] text-gray-400">· {{ s.nip }}</span></li>
                    </ul>
                    <p v-else class="text-center text-xs text-gray-400 py-6">Ketik untuk mencari santri.</p>
                    <button @click="pickTarget = null" class="w-full mt-3 py-3 rounded-xl bg-gray-100 text-gray-600 font-semibold text-sm">Batal</button>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.pop-enter-active, .pop-leave-active { transition: opacity .2s ease; }
.pop-enter-from, .pop-leave-to { opacity: 0; }
</style>
