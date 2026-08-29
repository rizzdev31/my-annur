<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { tanggalLokal } from '../tanggal'
import api from '../api'
import { toast } from '../store/toast'
import PageHeader from '../components/PageHeader.vue'
import BottomSheet from '../components/BottomSheet.vue'

const isPetugas = ref(null)     // null = belum tahu
const list = ref([])
const loading = ref(true)
const error = ref('')
const busy = ref(false)
const filter = ref('diajukan')

const FILTERS = [['diajukan', 'Menunggu'], ['disetujui', 'Disetujui'], ['ditolak', 'Ditolak'], ['', 'Semua']]
const JENIS = [['syari', "Syar'i"], ['non_syari', "Non-Syar'i"]]
const statusClass = (s) => ({ disetujui: 'text-emerald-600 bg-emerald-50', diajukan: 'text-amber-600 bg-amber-50', ditolak: 'text-red-600 bg-red-50' }[s] || 'text-gray-500 bg-gray-100')
const statusLabel = (s) => ({ diajukan: 'Menunggu', disetujui: 'Disetujui', ditolak: 'Ditolak' }[s] || s)
const filtered = computed(() => filter.value ? list.value.filter((z) => z.status === filter.value) : list.value)
const jumlahMenunggu = computed(() => list.value.filter((z) => z.status === 'diajukan').length)

async function load() {
    loading.value = true; error.value = ''
    try {
        const [st, dt] = await Promise.all([
            api.get('/perizinan/status'),
            api.get('/perizinan'), // petugas: semua; guru lain: laporan sendiri
        ])
        isPetugas.value = !!(st.data.data?.is_petugas)
        list.value = dt.data.data ?? []
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal memuat perizinan.'
    } finally { loading.value = false }
}
onMounted(load)

// ── Ajukan izin (petugas atas nama santri) ───────────────────────────────────
const ajukanSheet = ref(false)
const today = tanggalLokal()
const f = reactive({ santri: null, jenis: 'syari', alasan: '', tanggal_mulai: today, tanggal_selesai: today })
const cari = ref('')
const hasilSantri = ref([])
const cariBusy = ref(false)
let cariTimer = null

function bukaAjukan() {
    Object.assign(f, { santri: null, jenis: 'syari', alasan: '', tanggal_mulai: today, tanggal_selesai: today })
    cari.value = ''; hasilSantri.value = []
    ajukanSheet.value = true
}
function onCari() {
    clearTimeout(cariTimer)
    const q = cari.value.trim()
    if (q.length < 2) { hasilSantri.value = []; return }
    cariTimer = setTimeout(async () => {
        cariBusy.value = true
        try { hasilSantri.value = (await api.get('/perizinan/santri', { params: { q } })).data.data ?? [] }
        catch (_) { hasilSantri.value = [] }
        finally { cariBusy.value = false }
    }, 300)
}
function pilihSantri(s) { f.santri = s; cari.value = ''; hasilSantri.value = [] }

async function kirimAjukan() {
    if (!f.santri) return toast.warning('Pilih santri dulu.')
    if (f.alasan.trim().length < 3) return toast.warning('Alasan minimal 3 huruf.')
    if (f.tanggal_selesai < f.tanggal_mulai) return toast.warning('Tanggal selesai tidak boleh sebelum tanggal mulai.')
    busy.value = true
    try {
        await api.post('/perizinan', {
            santri_id: f.santri.id, jenis: f.jenis, alasan: f.alasan.trim(),
            tanggal_mulai: f.tanggal_mulai, tanggal_selesai: f.tanggal_selesai,
        })
        ajukanSheet.value = false
        toast.success('Izin diajukan. Menunggu persetujuan.')
        await load()
    } catch (e) {
        toast.error(e.response?.data?.errors ? Object.values(e.response.data.errors)[0][0] : (e.response?.data?.message || 'Gagal mengajukan izin.'))
    } finally { busy.value = false }
}

// ── Setujui / Tolak ──────────────────────────────────────────────────────────
const aksi = ref(null)          // { izin, tipe: 'setujui'|'tolak' }
const catatan = ref('')
function bukaAksi(izin, tipe) { aksi.value = { izin, tipe }; catatan.value = '' }
async function kirimAksi() {
    const { izin, tipe } = aksi.value
    if (tipe === 'tolak' && catatan.value.trim().length < 3) return toast.warning('Alasan penolakan wajib (min 3 huruf).')
    busy.value = true
    try {
        if (tipe === 'setujui') await api.post(`/perizinan/${izin.id}/setujui`, { catatan: catatan.value.trim() || undefined })
        else await api.post(`/perizinan/${izin.id}/tolak`, { alasan: catatan.value.trim() })
        aksi.value = null
        toast.success(tipe === 'setujui' ? 'Izin disetujui & wali diberi tahu.' : 'Izin ditolak.')
        await load()
    } catch (e) { toast.error(e.response?.data?.message || 'Gagal memproses.') }
    finally { busy.value = false }
}
</script>

<template>
    <div>
        <PageHeader title="Perizinan Santri" />

        <div v-if="loading" class="pt-16 flex justify-center"><div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div></div>
        <div v-else-if="error" class="pt-8 text-center">
            <p class="text-sm text-gray-500">{{ error }}</p>
            <button @click="load" class="mt-3 px-4 py-2 rounded-xl bg-[#0C78FF] text-white text-sm font-semibold">Coba lagi</button>
        </div>

        <template v-else>
            <!-- Info peran -->
            <p class="text-[11px] text-gray-400 mb-3 leading-snug">
                <template v-if="isPetugas">Anda <b>Petugas Perizinan</b> — verifikasi izin & boleh melaporkan.</template>
                <template v-else>Anda dapat <b>melaporkan izin santri</b>. Persetujuan dilakukan oleh Petugas Perizinan. Daftar di bawah adalah laporan Anda.</template>
            </p>

            <!-- Filter -->
            <div class="flex gap-1 bg-gray-100 rounded-2xl p-1 mb-4">
                <button v-for="[v, t] in FILTERS" :key="v" @click="filter = v"
                    class="flex-1 py-2 rounded-xl text-[12px] font-bold transition relative" :class="filter === v ? 'bg-white text-[#0C78FF] shadow-sm' : 'text-gray-400'">
                    {{ t }}
                    <span v-if="v === 'diajukan' && jumlahMenunggu" class="absolute -top-1 -right-0.5 min-w-4 h-4 px-1 rounded-full bg-amber-500 text-white text-[9px] grid place-items-center">{{ jumlahMenunggu }}</span>
                </button>
            </div>

            <div v-if="!filtered.length" class="pt-12 text-center text-sm text-gray-400">
                {{ !list.length ? (isPetugas ? 'Belum ada pengajuan izin.' : 'Anda belum melaporkan izin. Ketuk tombol untuk melapor.') : (filter === 'diajukan' ? 'Tidak ada izin menunggu.' : 'Belum ada data.') }}
            </div>

            <ul v-else class="space-y-3">
                <li v-for="z in filtered" :key="z.id" class="rounded-2xl bg-white border border-gray-100 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm font-bold text-gray-800 truncate">{{ z.santri }}</p>
                        <span class="text-[10px] font-bold px-2.5 py-1 rounded-full shrink-0" :class="statusClass(z.status)">{{ statusLabel(z.status) }}</span>
                    </div>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" :class="z.jenis === 'syari' ? 'text-violet-600 bg-violet-50' : 'text-sky-600 bg-sky-50'">{{ z.jenis_label || z.jenis }}</span>
                        <span class="text-[11px] text-gray-400">{{ z.tanggal_mulai }}<span v-if="z.tanggal_selesai && z.tanggal_selesai !== z.tanggal_mulai"> — {{ z.tanggal_selesai }}</span></span>
                    </div>
                    <p v-if="z.alasan" class="text-xs text-gray-600 mt-1.5">{{ z.alasan }}</p>
                    <p v-if="z.diajukan" class="text-[10px] text-gray-300 mt-1">Diajukan oleh: {{ z.diajukan }}</p>

                    <div v-if="z.status === 'diajukan' && isPetugas" class="flex gap-2 mt-3">
                        <button @click="bukaAksi(z, 'setujui')" class="flex-1 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold active:scale-[0.98] transition">Setujui</button>
                        <button @click="bukaAksi(z, 'tolak')" class="flex-1 py-2 rounded-xl bg-red-50 text-red-600 text-xs font-bold active:scale-[0.98] transition">Tolak</button>
                    </div>
                    <p v-else-if="z.status === 'diajukan' && !isPetugas" class="text-[11px] text-amber-600 mt-2">Menunggu persetujuan petugas.</p>
                    <p v-else-if="z.catatan" class="text-[11px] text-gray-400 mt-2 italic">Catatan petugas: {{ z.catatan }}</p>
                </li>
            </ul>

            <!-- FAB Ajukan -->
            <button @click="bukaAjukan"
                class="fixed bottom-24 right-5 z-30 h-13 pl-4 pr-5 py-3 rounded-2xl bg-[#0C78FF] text-white font-bold text-sm shadow-lg shadow-blue-500/30 flex items-center gap-2 active:scale-95 transition">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                {{ isPetugas ? 'Ajukan Izin' : 'Lapor Izin' }}
            </button>
        </template>

        <!-- Sheet ajukan -->
        <BottomSheet v-model="ajukanSheet" title="Lapor Izin Santri" subtitle="Atas nama santri — menunggu persetujuan petugas">
            <!-- Pilih santri -->
            <label class="block text-[11px] font-medium text-gray-600 mb-1">Santri <span class="text-red-500">*</span></label>
            <div v-if="f.santri" class="flex items-center justify-between rounded-xl bg-[#0C78FF]/5 border border-[#0C78FF]/20 px-3 py-2.5 mb-3">
                <div><p class="text-sm font-bold text-gray-800">{{ f.santri.nama }}</p><p v-if="f.santri.nip" class="text-[10px] text-gray-400">NIS {{ f.santri.nip }}</p></div>
                <button @click="f.santri = null" class="text-[11px] font-bold text-[#0C78FF]">Ganti</button>
            </div>
            <template v-else>
                <input v-model="cari" @input="onCari" type="text" placeholder="Cari nama / NIS santri…" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF] mb-2" />
                <div v-if="cariBusy" class="text-[11px] text-gray-400 py-1">Mencari…</div>
                <ul v-else-if="hasilSantri.length" class="rounded-xl border border-gray-100 divide-y divide-gray-50 mb-3 max-h-48 overflow-y-auto">
                    <li v-for="s in hasilSantri" :key="s.id" @click="pilihSantri(s)" class="px-3 py-2.5 active:bg-gray-50 cursor-pointer">
                        <p class="text-sm font-semibold text-gray-800">{{ s.nama }}</p><p v-if="s.nip" class="text-[10px] text-gray-400">NIS {{ s.nip }}</p>
                    </li>
                </ul>
                <p v-else-if="cari.trim().length >= 2" class="text-[11px] text-gray-400 py-1 mb-2">Tidak ditemukan.</p>
            </template>

            <!-- Jenis -->
            <label class="block text-[11px] font-medium text-gray-600 mb-1">Jenis Izin</label>
            <div class="flex gap-2 mb-3">
                <button v-for="j in JENIS" :key="j[0]" @click="f.jenis = j[0]"
                    class="flex-1 py-2.5 rounded-xl text-sm font-bold border transition" :class="f.jenis === j[0] ? 'bg-[#0C78FF] text-white border-[#0C78FF]' : 'bg-white text-gray-500 border-gray-200'">{{ j[1] }}</button>
            </div>

            <!-- Tanggal -->
            <div class="grid grid-cols-2 gap-3 mb-3">
                <div><label class="block text-[11px] font-medium text-gray-600 mb-1">Mulai</label><input v-model="f.tanggal_mulai" type="date" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]" /></div>
                <div><label class="block text-[11px] font-medium text-gray-600 mb-1">Selesai</label><input v-model="f.tanggal_selesai" type="date" :min="f.tanggal_mulai" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]" /></div>
            </div>

            <!-- Alasan -->
            <label class="block text-[11px] font-medium text-gray-600 mb-1">Alasan <span class="text-red-500">*</span></label>
            <textarea v-model="f.alasan" rows="2" placeholder="mis. sakit, keperluan keluarga…" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF] mb-4"></textarea>

            <button @click="kirimAjukan" :disabled="busy" class="w-full py-3 rounded-xl bg-[#0C78FF] text-white font-bold text-sm disabled:opacity-60">{{ busy ? 'Mengirim…' : 'Ajukan Izin' }}</button>
        </BottomSheet>

        <!-- Sheet setujui/tolak -->
        <BottomSheet :model-value="!!aksi" @update:model-value="aksi = null" :title="aksi?.tipe === 'setujui' ? 'Setujui Izin' : 'Tolak Izin'" :subtitle="aksi?.izin?.santri">
            <template v-if="aksi">
                <label class="block text-[11px] font-medium text-gray-600 mb-1">
                    {{ aksi.tipe === 'setujui' ? 'Catatan (opsional)' : 'Alasan penolakan' }}<span v-if="aksi.tipe === 'tolak'" class="text-red-500"> *</span>
                </label>
                <textarea v-model="catatan" rows="2" :placeholder="aksi.tipe === 'setujui' ? 'Catatan untuk wali…' : 'Wajib diisi…'" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-4" :class="aksi.tipe === 'setujui' ? 'focus:border-emerald-500' : 'focus:border-red-400'"></textarea>
                <div class="flex gap-2">
                    <button @click="aksi = null" class="flex-1 py-3 rounded-xl bg-gray-100 text-gray-600 font-bold text-sm">Batal</button>
                    <button @click="kirimAksi" :disabled="busy" class="flex-1 py-3 rounded-xl text-white font-bold text-sm disabled:opacity-60" :class="aksi.tipe === 'setujui' ? 'bg-emerald-600' : 'bg-red-600'">
                        {{ busy ? 'Memproses…' : (aksi.tipe === 'setujui' ? 'Setujui & WA Wali' : 'Tolak') }}
                    </button>
                </div>
            </template>
        </BottomSheet>
    </div>
</template>
