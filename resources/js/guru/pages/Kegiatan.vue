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
const k = ref(null)
const peserta = ref([])          // editable copy
const semuaGuru = ref([])

const STATUS = [
    { v: 'hadir', t: 'Hadir', c: 'bg-emerald-500' },
    { v: 'terlambat', t: 'Telat', c: 'bg-amber-500' },
    { v: 'izin', t: 'Izin', c: 'bg-sky-500' },
    { v: 'alfa', t: 'Alfa', c: 'bg-red-500' },
]
const selesai = computed(() => k.value?.status === 'selesai')
const initials = (n) => (n || '?').split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase()

async function load() {
    loading.value = true
    try {
        const d = (await api.get(`/kegiatan/${id}`)).data.data
        k.value = d
        peserta.value = (d.peserta ?? []).map(x => ({ ...x }))
        semuaGuru.value = d.semua_guru ?? []
    } catch (e) { toast.error(e.response?.data?.message || 'Gagal memuat kegiatan.') }
    finally { loading.value = false }
}
onMounted(load)

function nowHHmm() {
    const d = new Date()
    return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}
function setStatus(p, v) {
    if (selesai.value) return
    p.status_kehadiran = v
    // Jam otomatis diisi waktu sekarang saat hadir/terlambat (bila belum diisi);
    // izin/alfa membersihkan jam. Meniru logic Flutter kegiatan_screen.
    if (v === 'hadir' || v === 'terlambat') {
        if (!p.jam_hadir) p.jam_hadir = nowHHmm()
    } else {
        p.jam_hadir = null
    }
}

// Kirim draft absensi saat ini ke backend (tanpa toast/reload). Dipakai simpan & selesaikan.
async function pushAbsensi() {
    const rows = peserta.value.filter(p => ['hadir', 'terlambat', 'izin', 'alfa'].includes(p.status_kehadiran))
    if (!rows.length) return { ok: false, empty: true }
    await api.patch(`/kegiatan/${id}/absensi-bulk`, {
        absensi: rows.map(p => ({ id: p.id, status_kehadiran: p.status_kehadiran, jam_hadir: p.jam_hadir || null, keterangan: p.keterangan || null })),
    })
    return { ok: true }
}

async function simpanAbsensi() {
    busy.value = true
    try {
        const r = await pushAbsensi()
        if (r.empty) return toast.warning('Tandai kehadiran minimal satu peserta.')
        toast.success('Absensi tersimpan.')
        await load()
    } catch (e) { toast.error(e.response?.data?.message || 'Gagal menyimpan absensi.') }
    finally { busy.value = false }
}

// ── Tambah peserta ───────────────────────────────────────────────────────────
const addSheet = ref(false)
const picked = ref([])
const cari = ref('')
const filteredGuru = computed(() => {
    const q = cari.value.trim().toLowerCase()
    return q ? semuaGuru.value.filter(g => g.nama.toLowerCase().includes(q)) : semuaGuru.value
})
function openAdd() { picked.value = []; cari.value = ''; addSheet.value = true }
function togglePick(gid) { const i = picked.value.indexOf(gid); i >= 0 ? picked.value.splice(i, 1) : picked.value.push(gid) }
async function tambahPeserta() {
    if (!picked.value.length) return toast.warning('Pilih guru dulu.')
    busy.value = true
    try {
        await api.post(`/kegiatan/${id}/peserta`, { tenaga_pendidik_ids: picked.value })
        addSheet.value = false
        toast.success('Peserta ditambahkan.')
        await load()
    } catch (e) { toast.error(e.response?.data?.message || 'Gagal menambah peserta.') }
    finally { busy.value = false }
}

// ── Selesaikan kegiatan ──────────────────────────────────────────────────────
const konfirmSelesai = ref(false)
async function selesaikan() {
    busy.value = true
    try {
        // Auto-simpan draft absensi dulu (best-effort) agar jam/status terbaru tak hilang. Meniru Flutter.
        try { await pushAbsensi() } catch (_) {/* lanjut selesaikan */}
        const res = await api.post(`/kegiatan/${id}/selesaikan`)
        konfirmSelesai.value = false
        toast.success(res.data?.message || 'Kegiatan diselesaikan. Vakasi didistribusikan.')
        await load()
    } catch (e) { toast.error(e.response?.data?.message || 'Gagal menyelesaikan.') }
    finally { busy.value = false }
}
</script>

<template>
    <div>
        <PageHeader title="Detail Kegiatan" />
        <div v-if="loading" class="pt-16 flex justify-center"><div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div></div>

        <template v-else-if="k">
            <!-- Header -->
            <div class="rounded-2xl bg-white border border-gray-100 p-5 mb-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h1 class="text-base font-extrabold text-gray-900 leading-tight">{{ k.nama_kegiatan }}</h1>
                        <p class="text-[12px] text-gray-400 mt-1">{{ k.tanggal_kegiatan }}<span v-if="k.jam_mulai"> · {{ k.jam_mulai }}<span v-if="k.jam_selesai">–{{ k.jam_selesai }}</span></span></p>
                        <p v-if="k.lokasi" class="text-[12px] text-gray-400">📍 {{ k.lokasi }}</p>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-1 rounded-full shrink-0" :class="selesai ? 'text-emerald-600 bg-emerald-50' : 'text-amber-600 bg-amber-50'">{{ selesai ? 'Selesai' : 'Berlangsung' }}</span>
                </div>
                <p v-if="k.deskripsi" class="text-[12px] text-gray-500 mt-2">{{ k.deskripsi }}</p>
                <div v-if="k.vakasi_per_peserta" class="mt-3 text-[12px] font-bold text-[#0C78FF]">Peserta hadir mendapatkan vakasi</div>
            </div>

            <!-- Peserta -->
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-bold text-gray-700">Peserta ({{ peserta.length }})</p>
                <button v-if="!selesai" @click="openAdd" class="text-xs font-bold text-[#0C78FF]">+ Tambah</button>
            </div>

            <div v-if="!peserta.length" class="rounded-2xl border border-dashed border-gray-200 p-6 text-center text-sm text-gray-400 mb-4">Belum ada peserta.</div>
            <ul v-else class="space-y-2.5 mb-4">
                <li v-for="p in peserta" :key="p.id" class="rounded-2xl bg-white border border-gray-100 p-3.5">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-[#0C78FF]/10 text-[#0C78FF] grid place-items-center text-xs font-bold overflow-hidden shrink-0">
                            <img v-if="p.foto" :src="p.foto" class="w-full h-full object-cover" @error="p.foto = null" />
                            <span v-else>{{ initials(p.nama) }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-gray-800 truncate">{{ p.nama }}</p>
                            <p class="text-[11px] text-gray-400 truncate">{{ p.jabatan }}<span v-if="selesai && p.vakasi_diberikan" class="text-emerald-600 font-semibold"> · Vakasi ✓</span></p>
                        </div>
                    </div>
                    <div class="flex gap-1.5 mt-3">
                        <button v-for="s in STATUS" :key="s.v" @click="setStatus(p, s.v)" :disabled="selesai"
                            class="flex-1 py-1.5 rounded-lg text-[11px] font-bold transition"
                            :class="p.status_kehadiran === s.v ? s.c + ' text-white' : 'bg-gray-100 text-gray-400'">{{ s.t }}</button>
                    </div>
                    <!-- Jam hadir (hadir/terlambat) -->
                    <div v-if="p.status_kehadiran === 'hadir' || p.status_kehadiran === 'terlambat'" class="flex items-center gap-2 mt-2.5">
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="text-[11px] text-gray-400">Jam hadir</span>
                        <input v-model="p.jam_hadir" type="time" :disabled="selesai"
                            class="ml-auto px-2 py-1 rounded-lg border border-gray-200 text-[12px] font-semibold text-gray-700 outline-none focus:border-[#0C78FF] disabled:bg-gray-50" />
                    </div>
                </li>
            </ul>

            <!-- Aksi -->
            <template v-if="!selesai">
                <button @click="simpanAbsensi" :disabled="busy" class="w-full py-3.5 rounded-2xl bg-[#0C78FF] text-white font-bold disabled:opacity-60 mb-2">Simpan Absensi</button>
                <button @click="konfirmSelesai = true" :disabled="busy" class="w-full py-3.5 rounded-2xl bg-emerald-600 text-white font-bold disabled:opacity-60">Selesaikan & Distribusi Vakasi</button>
            </template>
            <div v-else class="rounded-2xl bg-emerald-50 border border-emerald-100 p-4 text-center text-sm font-semibold text-emerald-700">Kegiatan selesai · vakasi telah didistribusikan ✓</div>
        </template>

        <!-- Sheet tambah peserta -->
        <BottomSheet v-model="addSheet" :title="`Tambah Peserta`" :subtitle="`${picked.length} dipilih`">
            <input v-model="cari" type="text" placeholder="Cari guru…" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-3" />
            <div class="max-h-[45vh] overflow-y-auto -mx-1 px-1 space-y-1.5 mb-4">
                <div v-if="!filteredGuru.length" class="text-center text-sm text-gray-400 py-6">Tidak ada guru.</div>
                <button v-for="g in filteredGuru" :key="g.id" @click="togglePick(g.id)"
                    class="w-full flex items-center gap-3 p-2.5 rounded-xl border transition text-left"
                    :class="picked.includes(g.id) ? 'border-[#0C78FF] bg-[#0C78FF]/5' : 'border-gray-100'">
                    <div class="w-9 h-9 rounded-full bg-gray-100 text-gray-500 grid place-items-center text-[11px] font-bold overflow-hidden shrink-0">
                        <img v-if="g.foto" :src="g.foto" class="w-full h-full object-cover" @error="g.foto = null" />
                        <span v-else>{{ initials(g.nama) }}</span>
                    </div>
                    <div class="flex-1 min-w-0"><p class="text-sm font-semibold text-gray-800 truncate">{{ g.nama }}</p><p class="text-[11px] text-gray-400 truncate">{{ g.jabatan }}</p></div>
                    <div class="w-5 h-5 rounded-full border-2 grid place-items-center shrink-0" :class="picked.includes(g.id) ? 'border-[#0C78FF] bg-[#0C78FF]' : 'border-gray-300'">
                        <svg v-if="picked.includes(g.id)" class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                    </div>
                </button>
            </div>
            <button @click="tambahPeserta" :disabled="busy || !picked.length" class="w-full py-3 rounded-xl bg-[#0C78FF] text-white font-bold text-sm disabled:opacity-60">Tambah {{ picked.length || '' }} Peserta</button>
        </BottomSheet>

        <!-- Konfirmasi selesai -->
        <BottomSheet v-model="konfirmSelesai" title="Selesaikan Kegiatan?" subtitle="Vakasi didistribusikan ke peserta hadir & tidak bisa diubah.">
            <div class="flex gap-2">
                <button @click="konfirmSelesai = false" class="flex-1 py-3 rounded-xl bg-gray-100 text-gray-600 font-bold text-sm">Batal</button>
                <button @click="selesaikan" :disabled="busy" class="flex-1 py-3 rounded-xl bg-emerald-600 text-white font-bold text-sm disabled:opacity-60">{{ busy ? 'Memproses…' : 'Ya, Selesaikan' }}</button>
            </div>
        </BottomSheet>
    </div>
</template>
