<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api'
import PageHeader from '../components/PageHeader.vue'

const router = useRouter()

const info = ref(null)
const jadwal = ref([])
const loading = ref(true)
const error = ref('')
const msg = ref(null)

// State form absen (modal)
const aktif = ref(null)          // jadwal yang sedang diabsen
const materi = ref('')
const keterangan = ref('')
const foto = ref(null)
const fotoPreview = ref(null)
const saving = ref(false)

const tipeBadge = (t) => ({
    tahfidz: 'bg-emerald-50 text-emerald-600',
    tahsin: 'bg-violet-50 text-violet-600',
}[t] || 'bg-blue-50 text-blue-600')

async function load() {
    loading.value = true; error.value = ''
    try {
        const res = await api.get('/absensi/mengajar/hari-ini')
        info.value = res.data.data ?? res.data
        jadwal.value = info.value.jadwal ?? []
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal memuat jadwal mengajar.'
    } finally { loading.value = false }
}
onMounted(load)

function bukaAbsen(j) {
    aktif.value = j
    materi.value = ''
    keterangan.value = ''
    foto.value = null
    fotoPreview.value = null
    msg.value = null
}

function pilihFoto(e) {
    const f = e.target.files?.[0]
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

                            <!-- Sudah absen -->
                            <div v-if="j.sudah_absen" class="mt-2">
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
                            <!-- Boleh absen -->
                            <button v-else-if="j.boleh_absen" @click="bukaAbsen(j)"
                                class="mt-2 px-4 py-1.5 rounded-lg bg-[#0C78FF] text-white text-xs font-bold">Absen Sekarang</button>
                            <!-- Terblokir -->
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
                    <p class="text-xs text-gray-400 mb-4">{{ aktif.kelas }} · {{ aktif.jumlah_jp }} JP</p>

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
