<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { tanggalLokal } from '../tanggal'
import api from '../api'
import { kompresFoto } from '../foto'
import PageHeader from '../components/PageHeader.vue'

const router = useRouter()

const info = ref(null)
const jadwal = ref([])
const loading = ref(true)
const error = ref('')
const msg = ref(null)

// State form absen (modal)
const aktif = ref(null)          // jadwal yang sedang diabsen
const overrideAktif = ref(false) // true = "ajar sendiri" saat izin (override)
const materi = ref('')
const keterangan = ref('')
const foto = ref(null)
const fotoPreview = ref(null)
const saving = ref(false)

// Guru pengganti (saat izin)
const penggantiOpsi = ref([])
async function loadPengganti() {
    if (penggantiOpsi.value.length) return
    try { const o = await api.get('/absensi/mengajar/pengganti-opsi'); penggantiOpsi.value = o.data.data ?? [] } catch (_) {}
}
async function tunjukPengganti(j) {
    if (!j.pengganti_id) { msg.value = { ok: false, text: 'Pilih guru pengganti dulu.' }; return }
    j.assigning = true; msg.value = null
    try {
        await api.post('/absensi/mengajar/tunjuk-pengganti', {
            jadwal_mengajar_id: j.jadwal_id,
            pengganti_id: j.pengganti_id,
            tanggal: tanggalLokal(),
            keterangan: `Izin ${j.info_izin || ''}`.trim(),
        })
        msg.value = { ok: true, text: 'Pengganti ditunjuk. Kelas tidak akan kosong.' }
        await load()
    } catch (e) {
        msg.value = { ok: false, text: e.response?.data?.message || 'Gagal menunjuk pengganti.' }
    } finally { j.assigning = false }
}
async function batalPengganti(j) {
    if (!j.absensi_id) return
    if (!confirm('Batalkan penunjukan guru pengganti?')) return
    j.assigning = true; msg.value = null
    try {
        await api.post('/absensi/mengajar/batal-pengganti', { absensi_mengajar_id: j.absensi_id })
        msg.value = { ok: true, text: 'Penunjukan pengganti dibatalkan.' }
        await load()
    } catch (e) {
        msg.value = { ok: false, text: e.response?.data?.message || 'Gagal membatalkan pengganti.' }
    } finally { j.assigning = false }
}

const tipeBadge = (t) => ({
    tahfidz: 'bg-emerald-50 text-emerald-600',
    tahsin: 'bg-violet-50 text-violet-600',
}[t] || 'bg-blue-50 text-blue-600')

async function load() {
    loading.value = true; error.value = ''
    try {
        const res = await api.get('/absensi/mengajar/hari-ini')
        info.value = res.data.data ?? res.data
        jadwal.value = (info.value.jadwal ?? []).map(j => ({ ...j, pengganti_id: '', assigning: false }))
        // Muat opsi pengganti bila ada sesi saat izin yang bisa ditunjuk pengganti.
        if (jadwal.value.some(j => j.boleh_tunjuk_pengganti)) loadPengganti()
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal memuat jadwal mengajar.'
    } finally { loading.value = false }
}
onMounted(load)

function bukaAbsen(j, override = false) {
    aktif.value = j
    overrideAktif.value = override
    materi.value = ''
    keterangan.value = ''
    foto.value = null
    fotoPreview.value = null
    msg.value = null
}

async function pilihFoto(e) {
    let f = e.target.files?.[0]
    if (f) f = await kompresFoto(f)
    foto.value = f || null
    fotoPreview.value = f ? URL.createObjectURL(f) : null
}

async function kirim() {
    if (!foto.value) { msg.value = { ok: false, text: 'Foto bukti mengajar wajib diisi.' }; return }
    saving.value = true
    try {
        const fd = new FormData()
        fd.append('jadwal_mengajar_id', aktif.value.jadwal_id)
        fd.append('foto', foto.value)
        if (materi.value.trim()) fd.append('materi', materi.value.trim())
        if (keterangan.value.trim()) fd.append('keterangan', keterangan.value.trim())
        if (overrideAktif.value) fd.append('override_izin', '1')
        const res = await api.post('/absensi/mengajar/absen', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
        aktif.value = null
        msg.value = { ok: true, text: res.data.message || 'Absen mengajar tersimpan.' }
        await load()
    } catch (e) {
        msg.value = { ok: false, text: e.response?.data?.message || 'Gagal menyimpan absen.' }
    } finally { saving.value = false }
}
</script>

<template>
    <div>
        <PageHeader title="Absen Mengajar" />

        <div v-if="loading" class="pt-10 flex justify-center">
            <div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div>
        </div>
        <div v-else-if="error" class="pt-8 text-center">
            <p class="text-sm text-gray-500">{{ error }}</p>
            <button @click="load" class="mt-3 px-4 py-2 rounded-xl bg-[#0C78FF] text-white text-sm font-semibold">Coba lagi</button>
        </div>

        <template v-else>
            <p v-if="info.is_hari_libur" class="text-sm text-amber-700 bg-amber-50 rounded-xl px-3 py-2 mb-3">
                Hari libur: {{ info.nama_libur || '—' }}
            </p>
            <p v-if="msg" :class="msg.ok ? 'text-emerald-700 bg-emerald-50' : 'text-red-600 bg-red-50'"
                class="text-sm rounded-xl px-3 py-2 mb-3">{{ msg.text }}</p>

            <p class="text-xs text-gray-400 mb-3">{{ info.hari }}, {{ info.tanggal }} · {{ info.sudah_absen }}/{{ info.total }} sesi diabsen</p>

            <div v-if="!jadwal.length" class="pt-16 text-center text-sm text-gray-400">Tidak ada jadwal mengajar hari ini.</div>

            <ul v-else class="space-y-3">
                <li v-for="j in jadwal" :key="j.jadwal_id" class="rounded-2xl bg-white border border-gray-100 p-4">
                    <div class="flex items-start gap-3">
                        <div class="w-12 text-center shrink-0">
                            <p class="text-[11px] font-bold text-gray-700">{{ (j.jam_mulai || '').slice(0,5) }}</p>
                            <p class="text-[10px] text-gray-400">{{ (j.jam_selesai || '').slice(0,5) }}</p>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-bold text-gray-800 truncate">{{ j.mata_pelajaran }}</p>
                                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded capitalize" :class="tipeBadge(j.tipe)">{{ j.tipe }}</span>
                            </div>
                            <p class="text-[11px] text-gray-400">{{ j.kelas }} · {{ j.jumlah_jp }} JP<span v-if="j.ruangan && j.ruangan !== '—'"> · {{ j.ruangan }}</span></p>

                            <!-- Pengganti sudah ditunjuk (belum mengajar) -->
                            <div v-if="j.digantikan_oleh && (j.jp_terlaksana ?? 0) === 0"
                                class="mt-2 rounded-xl bg-sky-50 border border-sky-100 p-2.5">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-[11px] text-sky-700">👤 Pengganti: <b>{{ j.pengganti_nama || 'Ditunjuk' }}</b></p>
                                    <button @click="batalPengganti(j)" :disabled="j.assigning"
                                        class="text-[11px] font-bold text-red-500 disabled:opacity-50">Batalkan</button>
                                </div>
                                <button v-if="j.boleh_override_izin" @click="bukaAbsen(j, true)"
                                    class="mt-2 w-full py-1.5 rounded-lg bg-emerald-600 text-white text-[12px] font-bold">Batalkan & ajar sendiri</button>
                            </div>

                            <!-- Sudah benar-benar diabsen/diajar -->
                            <div v-else-if="j.sudah_absen && j.status !== 'izin'" class="mt-2">
                                <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">
                                    ✓ Diabsen · {{ j.jp_terlaksana ?? 0 }} JP
                                </span>
                                <p v-if="j.materi" class="text-[11px] text-gray-400 mt-1">Materi: {{ j.materi }}</p>
                                <button v-if="j.tipe !== 'tahfidz' && j.tipe !== 'tahsin'"
                                    @click="router.push({ name: 'absen-santri', params: { jadwalId: j.jadwal_id } })"
                                    class="mt-2 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#0C78FF]/10 text-[#0C78FF] text-xs font-bold active:scale-95 transition">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4z"/></svg>
                                    Absen Santri
                                </button>
                            </div>

                            <!-- Boleh absen normal -->
                            <button v-else-if="j.boleh_absen" @click="bukaAbsen(j)"
                                class="mt-2 px-4 py-1.5 rounded-lg bg-[#0C78FF] text-white text-xs font-bold">Absen Sekarang</button>

                            <!-- Saat izin: pilih guru pengganti / ajar sendiri (override) -->
                            <div v-else-if="j.is_izin_guru && j.boleh_tunjuk_pengganti"
                                class="mt-2 rounded-xl bg-amber-50 border border-amber-100 p-2.5">
                                <p class="text-[11px] font-bold text-amber-700 mb-1.5">
                                    Anda izin ({{ j.info_izin }}){{ j.is_dinas_luar ? ' — dinas luar' : '' }} · kelas ini kosong
                                </p>
                                <div class="flex gap-1.5">
                                    <select v-model="j.pengganti_id"
                                        class="flex-1 px-2 py-1.5 rounded-lg border border-gray-200 text-[12px] outline-none bg-white">
                                        <option value="">Pilih guru pengganti…</option>
                                        <option v-for="o in penggantiOpsi" :key="o.id" :value="o.id">{{ o.nama }}</option>
                                    </select>
                                    <button @click="tunjukPengganti(j)" :disabled="!j.pengganti_id || j.assigning"
                                        class="px-3 py-1.5 rounded-lg bg-[#0C78FF] text-white text-[12px] font-bold disabled:opacity-50">
                                        {{ j.assigning ? '…' : 'Tunjuk' }}
                                    </button>
                                </div>
                                <button v-if="j.boleh_override_izin" @click="bukaAbsen(j, true)"
                                    class="mt-1.5 w-full py-1.5 rounded-lg bg-emerald-600 text-white text-[12px] font-bold">
                                    Saya ajar sendiri (izin selesai)
                                </button>
                            </div>

                            <!-- Terblokir lain -->
                            <p v-else class="mt-2 text-[11px] text-gray-400">{{ j.pesan_blokir || j.info_izin || 'Belum bisa diabsen.' }}</p>
                        </div>
                    </div>
                </li>
            </ul>
        </template>

        <!-- Modal absen -->
        <Transition name="pop">
            <div v-if="aktif" class="fixed inset-0 z-[70] flex items-end justify-center" style="background: rgba(0,0,0,0.55)">
                <div class="w-full max-w-md bg-white rounded-t-3xl p-5 pb-8 safe-b">
                    <div class="w-10 h-1 bg-gray-200 rounded-full mx-auto mb-4"></div>
                    <h3 class="text-base font-extrabold text-gray-900">{{ aktif.mata_pelajaran }}</h3>
                    <p class="text-xs text-gray-400 mb-2">{{ aktif.kelas }} · {{ aktif.jumlah_jp }} JP</p>
                    <div v-if="overrideAktif" class="mb-3 rounded-xl bg-emerald-50 border border-emerald-100 px-3 py-2 text-[11px] text-emerald-700 leading-snug">
                        Anda mengajar sendiri meski sedang izin. Status harian tetap izin; kelas ini tercatat terlaksana.
                    </div>

                    <label class="block text-xs font-medium text-gray-600 mb-1">Materi (opsional)</label>
                    <textarea v-model="materi" rows="2" placeholder="Materi yang diajarkan…"
                        class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF] mb-3"></textarea>

                    <label class="block text-xs font-medium text-gray-600 mb-1">Foto Bukti Mengajar <span class="text-red-500">*wajib</span></label>
                    <div v-if="fotoPreview" class="mb-2">
                        <img :src="fotoPreview" class="w-full h-40 object-cover rounded-xl" />
                    </div>
                    <input type="file" accept="image/*" capture="environment" @change="pilihFoto"
                        class="block w-full text-xs text-gray-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-[#0C78FF]/10 file:text-[#0C78FF] file:text-xs file:font-semibold mb-4" />

                    <div class="flex gap-3">
                        <button @click="aktif = null" class="flex-1 py-3 rounded-xl bg-gray-100 text-gray-600 font-semibold text-sm">Batal</button>
                        <button @click="kirim" :disabled="saving" class="flex-1 py-3 rounded-xl bg-emerald-600 text-white font-bold text-sm disabled:opacity-60">
                            {{ saving ? 'Menyimpan…' : 'Simpan Absen' }}
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
