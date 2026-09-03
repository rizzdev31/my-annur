<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '../api'
import { toast } from '../store/toast'
import PageHeader from '../components/PageHeader.vue'
import BottomSheet from '../components/BottomSheet.vue'

const route = useRoute()
const jadwalId = route.params.jadwalId

const info = ref(null)
const santri = ref([])
const surahList = ref([])
const loading = ref(true)
const error = ref('')

// ── Absen (gerbang) ─────────────────────────────────────────────────────────
const absenStatus = reactive({})
const absenCatatan = ref('')
const absenSaving = ref(false)

async function load() {
    loading.value = true; error.value = ''
    try {
        const [r, s] = await Promise.all([
            api.get(`/education/tahfidz/jadwal/${jadwalId}/roster`),
            surahList.value.length ? Promise.resolve({ data: { data: surahList.value } }) : api.get('/education/surah'),
        ])
        info.value = r.data.data ?? r.data
        santri.value = info.value.santri ?? []
        surahList.value = s.data.data ?? s.data ?? []
        santri.value.forEach((x) => { if (!absenStatus[x.santri_id]) absenStatus[x.santri_id] = 'hadir' })
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal memuat roster.'
    } finally { loading.value = false }
}
onMounted(load)

async function submitAbsen() {
    absenSaving.value = true
    try {
        const res = await api.post('/education/tahfidz/absen', {
            jadwal_id: Number(jadwalId),
            absensi: santri.value.map((x) => ({ santri_id: x.santri_id, status: absenStatus[x.santri_id] || 'hadir' })),
            catatan: absenCatatan.value.trim() || null,
        })
        info.value.absensi_mengajar_id = res.data.data?.absensi_mengajar_id
        info.value.wajib_absen = false; info.value.sudah_absen = true
        toast.success(res.data.message || 'Absen tersimpan. Lanjut setoran.')
    } catch (e) {
        const code = e.response?.data?.code
        if (code === 'SUDAH_ABSEN' && e.response?.data?.data?.absensi_mengajar_id) {
            info.value.absensi_mengajar_id = e.response.data.data.absensi_mengajar_id
            info.value.wajib_absen = false; info.value.sudah_absen = true
            toast.info('Kehadiran sudah terkunci. Lanjut setoran.')
        } else {
            toast.error(e.response?.data?.message || 'Gagal menyimpan absen.')
        }
    } finally { absenSaving.value = false }
}

// ── Setoran ─────────────────────────────────────────────────────────────────
const aktif = ref(null)
const jenis = ref('ziyadah')
const f = reactive({ surah_mulai: '', ayat_mulai: '', surah_selesai: '', ayat_selesai: '', nilai: '', catatan: '' })
const saving = ref(false)

function bukaSetoran(s) {
    aktif.value = s
    jenis.value = s.perlu_murojaah ? 'murojaah_wajib' : 'ziyadah'
    Object.assign(f, {
        surah_mulai: s.lanjut_surah || '', ayat_mulai: s.lanjut_ayat || '',
        surah_selesai: s.lanjut_surah || '', ayat_selesai: '', nilai: '', catatan: '',
    })
}
const surahNama = (n) => surahList.value.find((x) => x.nomor == n)?.nama || (n ? `Surah ${n}` : '')

async function kirimSetoran() {
    if (!f.catatan.trim() || f.catatan.trim().length < 3) { toast.warning('Catatan wajib (min 3 huruf).'); return }
    if (jenis.value === 'ziyadah' && (!f.surah_mulai || !f.ayat_mulai || !f.nilai)) {
        toast.warning('Ziyadah: isi surah/ayat mulai & nilai.'); return
    }
    saving.value = true
    try {
        const body = {
            absensi_mengajar_id: info.value.absensi_mengajar_id || null,
            santri_id: aktif.value.santri_id, jenis: jenis.value, catatan: f.catatan.trim(),
        }
        if (jenis.value === 'ziyadah' || jenis.value === 'murojaah_tambahan') {
            body.surah_mulai = Number(f.surah_mulai) || null
            body.ayat_mulai = Number(f.ayat_mulai) || null
            body.surah_selesai = Number(f.surah_selesai || f.surah_mulai) || null
            body.ayat_selesai = Number(f.ayat_selesai) || null
        }
        if (jenis.value === 'ziyadah') body.nilai = Number(f.nilai)
        const res = await api.post('/education/tahfidz/setoran', body)
        aktif.value = null
        toast.success(res.data.message || 'Setoran tersimpan.')
        await load()
    } catch (e) {
        toast.error(e.response?.data?.message || 'Gagal menyimpan setoran.')
    } finally { saving.value = false }
}

const cycleAbsen = (id) => { const seq = ['hadir', 'telat', 'alpha']; absenStatus[id] = seq[(seq.indexOf(absenStatus[id]) + 1) % 3] }
const absenColor = (s) => ({ hadir: 'bg-emerald-100 text-emerald-700', telat: 'bg-amber-100 text-amber-700', alpha: 'bg-red-100 text-red-600' }[s])
const initials = (n) => (n || '?').split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase()

// ── Tunjuk penguji tasmi' ────────────────────────────────────────────────────
const tunjuk = ref(null)
const pengujiList = ref([])
const tForm = reactive({ juz: '', penguji_id: '' })
const tSaving = ref(false)

async function bukaTunjuk(s) {
    tunjuk.value = s
    tForm.juz = (s.juz_perlu_tasmi && s.juz_perlu_tasmi[0]) || ''
    tForm.penguji_id = ''
    if (!pengujiList.value.length) {
        try { const res = await api.get('/education/tahfidz/penguji-opsi'); pengujiList.value = res.data.data ?? res.data ?? [] } catch (_) {}
    }
}
async function kirimTunjuk() {
    if (!tForm.juz || !tForm.penguji_id) { toast.warning('Pilih juz & penguji.'); return }
    tSaving.value = true
    try {
        const res = await api.post('/education/tahfidz/tunjuk-tasmi', {
            santri_id: tunjuk.value.santri_id, juz: Number(tForm.juz), penguji_id: Number(tForm.penguji_id),
        })
        tunjuk.value = null
        toast.success(res.data.message || "Penguji tasmi' ditunjuk.")
        await load()
    } catch (e) {
        toast.error(e.response?.data?.message || 'Gagal menunjuk penguji.')
    } finally { tSaving.value = false }
}

const jenisTabs = [['ziyadah', 'Hafalan Baru'], ['murojaah_wajib', 'Murojaah'], ['murojaah_tambahan', 'Murojaah+']]

// Banner konteks alur: wajib absen → sudah absen → hari jadwal di luar jam → di luar hari jadwal.
const bannerInfo = computed(() => {
    const i = info.value
    if (!i) return null
    if (i.wajib_absen) return { c: 'amber', t: `Hari ini terjadwal${i.hari ? ` (${i.hari})` : ''}. Absen kehadiran santri dulu sebagai bukti mengajar — baru jurnal setoran terbuka.` }
    if (i.sudah_absen) return { c: 'emerald', t: 'Sudah absen mengajar hari ini. Setoran tersimpan bertanggal hari ini.' }
    if (i.is_today) return { c: 'sky', t: 'Hari jadwal tetapi di luar jam kelas — kehadiran dilimpahkan ke guru piket. Jurnal setoran tetap bisa diisi.' }
    return { c: 'emerald', t: 'Di luar hari jadwal — setoran/jurnal bisa dicatat kapan saja tanpa absen.' }
})
const bannerClass = { amber: 'bg-amber-50 border-amber-200 text-amber-700', emerald: 'bg-emerald-50 border-emerald-100 text-emerald-700', sky: 'bg-sky-50 border-sky-100 text-sky-700' }
</script>

<template>
    <div>
        <PageHeader :title="info?.kelas || 'Kelas Tahfidz'" />

        <div v-if="loading" class="pt-10 flex justify-center"><div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div></div>
        <div v-else-if="error" class="pt-8 text-center">
            <p class="text-sm text-gray-500">{{ error }}</p>
            <button @click="load" class="mt-3 px-4 py-2 rounded-xl bg-[#0C78FF] text-white text-sm font-semibold">Coba lagi</button>
        </div>

        <template v-else>
            <p class="text-xs text-gray-400 mb-3">{{ info.mapel }} · {{ info.total_santri }} santri</p>

            <!-- Banner konteks alur absen→jurnal -->
            <div v-if="bannerInfo" class="flex items-start gap-2 rounded-xl border px-3 py-2.5 mb-3 text-[11.5px] font-medium leading-snug" :class="bannerClass[bannerInfo.c]">
                <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ bannerInfo.t }}</span>
            </div>

            <!-- GERBANG ABSEN -->
            <div v-if="info.wajib_absen" class="rounded-2xl bg-amber-50 border border-amber-200 p-4 mb-4">
                <p class="text-sm font-bold text-amber-800 mb-1">Absen Kehadiran Dulu</p>
                <p class="text-[11px] text-amber-600 mb-3">Ketuk status tiap santri (Hadir → Telat → Alpha), lalu simpan.</p>
                <div class="space-y-1.5 mb-3 max-h-64 overflow-y-auto">
                    <div v-for="s in santri" :key="s.santri_id" class="flex items-center justify-between bg-white rounded-xl px-3 py-2">
                        <span class="text-sm text-gray-700 truncate">{{ s.nama }}</span>
                        <button @click="cycleAbsen(s.santri_id)" class="text-[11px] font-bold px-2.5 py-1 rounded-full capitalize transition" :class="absenColor(absenStatus[s.santri_id])">{{ absenStatus[s.santri_id] }}</button>
                    </div>
                </div>
                <input v-model="absenCatatan" type="text" placeholder="Catatan sesi (opsional)" class="w-full px-3 py-2.5 rounded-xl border border-amber-200 text-sm outline-none mb-3" />
                <button @click="submitAbsen" :disabled="absenSaving" class="w-full py-3 rounded-xl bg-amber-600 text-white font-bold text-sm disabled:opacity-60 active:scale-[0.99] transition">
                    {{ absenSaving ? 'Menyimpan…' : 'Simpan Kehadiran & Kunci' }}
                </button>
            </div>

            <!-- Daftar santri (setoran) — hanya setelah gerbang absen lewat -->
            <ul v-if="!info.wajib_absen" class="space-y-2.5">
                <li v-for="s in santri" :key="s.santri_id"
                    class="rounded-2xl bg-white border border-gray-100 p-3 active:scale-[0.99] transition"
                    @click="bukaSetoran(s)">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-emerald-50 grid place-items-center text-emerald-600 text-xs font-extrabold shrink-0">{{ initials(s.nama) }}</div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-gray-800 truncate">{{ s.nama }}</p>
                            <p class="text-[11px] text-gray-400">
                                {{ s.total_ayat }} ayat · {{ s.persen }}%
                                <span v-if="s.selesai_semua" class="text-emerald-600 font-bold"> · Khatam</span>
                                <span v-else-if="s.lanjut_surah" class="text-emerald-600"> · lanjut {{ surahNama(s.lanjut_surah) }} {{ s.lanjut_ayat }}</span>
                            </p>
                            <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden mt-1.5">
                                <div class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-emerald-600" :style="{ width: Math.min(100, s.persen) + '%' }"></div>
                            </div>
                        </div>
                        <button v-if="s.juz_perlu_tasmi && s.juz_perlu_tasmi.length" @click.stop="bukaTunjuk(s)"
                            class="text-[10px] font-bold text-white bg-emerald-600 px-2.5 py-1.5 rounded-lg shrink-0 active:scale-95 transition">Tasmi'</button>
                        <span v-else-if="s.perlu_murojaah" class="text-[9px] font-bold text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded shrink-0">Murojaah</span>
                    </div>
                </li>
            </ul>
        </template>

        <!-- Sheet setoran -->
        <BottomSheet :model-value="!!aktif" @update:model-value="aktif = null" :title="aktif?.nama" :subtitle="aktif ? `${aktif.total_ayat} ayat hafal · ${aktif.persen}%` : ''">
            <template v-if="aktif">
                <!-- Info hafalan terakhir -->
                <div class="mb-4 rounded-xl bg-gray-50 p-3 text-[11px] text-gray-500">
                    <p v-if="aktif.last_surah">Hafalan terakhir: <b class="text-gray-700">{{ surahNama(aktif.last_surah) }} {{ aktif.last_ayat_mulai }}</b><span v-if="aktif.last_surah_selesai"> – {{ surahNama(aktif.last_surah_selesai) }} {{ aktif.last_ayat_selesai }}</span></p>
                    <p v-else>Belum ada hafalan tercatat.</p>
                    <p v-if="aktif.lanjut_surah" class="text-emerald-600 mt-0.5">Saran lanjut: {{ surahNama(aktif.lanjut_surah) }} ayat {{ aktif.lanjut_ayat }}</p>
                    <p v-if="aktif.perlu_murojaah" class="text-amber-600 mt-0.5 font-semibold">⚠ Perlu murojaah dulu sebelum hafalan baru.</p>
                </div>

                <!-- Jenis (segmented) -->
                <div class="flex gap-1 bg-gray-100 rounded-2xl p-1 mb-4">
                    <button v-for="j in jenisTabs" :key="j[0]" @click="jenis = j[0]"
                        class="flex-1 py-2 rounded-xl text-[11px] font-bold transition" :class="jenis === j[0] ? 'bg-white text-[#0C78FF] shadow-sm' : 'text-gray-400'">{{ j[1] }}</button>
                </div>

                <template v-if="jenis === 'ziyadah' || jenis === 'murojaah_tambahan'">
                    <div class="grid grid-cols-2 gap-3 mb-2">
                        <div><label class="block text-[11px] font-medium text-gray-600 mb-1">Surah Mulai</label>
                            <select v-model="f.surah_mulai" class="w-full px-2 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]"><option value="">—</option><option v-for="su in surahList" :key="su.nomor" :value="su.nomor">{{ su.nomor }}. {{ su.nama }}</option></select></div>
                        <div><label class="block text-[11px] font-medium text-gray-600 mb-1">Ayat Mulai</label>
                            <input v-model="f.ayat_mulai" type="number" min="1" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]" /></div>
                        <div><label class="block text-[11px] font-medium text-gray-600 mb-1">Surah Selesai</label>
                            <select v-model="f.surah_selesai" class="w-full px-2 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]"><option value="">= mulai</option><option v-for="su in surahList" :key="su.nomor" :value="su.nomor">{{ su.nomor }}. {{ su.nama }}</option></select></div>
                        <div><label class="block text-[11px] font-medium text-gray-600 mb-1">Ayat Selesai</label>
                            <input v-model="f.ayat_selesai" type="number" min="1" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]" /></div>
                    </div>
                    <p class="text-[10px] text-gray-400 mb-3">Rentang hafalan {{ jenis === 'ziyadah' ? 'BARU (boleh juz mana saja)' : 'yang dimurojaah' }} — boleh lintas surah. Kosongkan <b>Surah Selesai</b> bila 1 surah. <b>Ayat kosong</b> = otomatis: mulai→ayat 1, selesai→akhir surah (surah penuh). Contoh: Abasa 1 → At-Takwir 8.</p>
                </template>
                <p v-else class="text-[11px] text-gray-400 bg-gray-50 rounded-xl px-3 py-2 mb-3">Murojaah wajib: rentang ditentukan otomatis oleh sistem.</p>

                <div v-if="jenis === 'ziyadah'" class="mb-3">
                    <label class="block text-[11px] font-medium text-gray-600 mb-1">Nilai (1–10) <span class="text-red-500">*</span></label>
                    <input v-model="f.nilai" type="number" min="1" max="10" step="0.5" placeholder="mis. 8.5" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]" />
                </div>

                <label class="block text-[11px] font-medium text-gray-600 mb-1">Catatan <span class="text-red-500">*</span></label>
                <textarea v-model="f.catatan" rows="2" placeholder="mis. lancar, perlu perbaikan tajwid…" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF] mb-4"></textarea>

                <div class="flex gap-3">
                    <button @click="aktif = null" class="flex-1 py-3 rounded-xl bg-gray-100 text-gray-600 font-semibold text-sm active:scale-[0.98] transition">Batal</button>
                    <button @click="kirimSetoran" :disabled="saving" class="flex-1 py-3 rounded-xl bg-gradient-to-r from-emerald-500 to-emerald-600 text-white font-bold text-sm shadow-lg shadow-emerald-600/20 disabled:opacity-60 active:scale-[0.98] transition">{{ saving ? 'Menyimpan…' : 'Simpan Setoran' }}</button>
                </div>
            </template>
        </BottomSheet>

        <!-- Sheet tunjuk penguji -->
        <BottomSheet :model-value="!!tunjuk" @update:model-value="tunjuk = null" title="Tunjuk Penguji Tasmi'" :subtitle="tunjuk ? `${tunjuk.nama} — juz siap tasmi'` : ''">
            <template v-if="tunjuk">
                <label class="block text-[11px] font-medium text-gray-600 mb-1">Juz</label>
                <select v-model="tForm.juz" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-3"><option value="">— pilih juz —</option><option v-for="j in (tunjuk.juz_perlu_tasmi || [])" :key="j" :value="j">Juz {{ j }}</option></select>
                <label class="block text-[11px] font-medium text-gray-600 mb-1">Penguji</label>
                <select v-model="tForm.penguji_id" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-4"><option value="">— pilih guru penguji —</option><option v-for="g in pengujiList" :key="g.id" :value="g.id">{{ g.nama }}</option></select>
                <div class="flex gap-3">
                    <button @click="tunjuk = null" class="flex-1 py-3 rounded-xl bg-gray-100 text-gray-600 font-semibold text-sm">Batal</button>
                    <button @click="kirimTunjuk" :disabled="tSaving" class="flex-1 py-3 rounded-xl bg-gradient-to-r from-emerald-500 to-emerald-600 text-white font-bold text-sm shadow-lg shadow-emerald-600/20 disabled:opacity-60">{{ tSaving ? 'Menyimpan…' : 'Tunjuk' }}</button>
                </div>
            </template>
        </BottomSheet>
    </div>
</template>
