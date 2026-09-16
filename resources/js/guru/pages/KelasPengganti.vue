<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api'
import { kompresFoto } from '../foto'
import PageHeader from '../components/PageHeader.vue'

// Kotak masuk SEMUA tugas inval (reguler, tahfidz, tahsin).
// Reguler diisi di sini; tahfidz & tahsin dibuka di menunya masing-masing karena
// roster, murojaah, dan catatan materinya hanya ada di sana.

const router = useRouter()
const list = ref([])
const loading = ref(true)
const error = ref('')
const msg = ref(null)

const aktif = ref(null)
const materi = ref('')
const keterangan = ref('')
const foto = ref(null)
const fotoPreview = ref(null)
const saving = ref(false)

async function load() {
    loading.value = true; error.value = ''
    try {
        const res = await api.get('/absensi/mengajar/pengganti-saya')
        const d = res.data.data ?? res.data
        list.value = d.kelas ?? d ?? []
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal memuat kelas pengganti.'
    } finally { loading.value = false }
}
onMounted(load)

function bukaAbsen(k) {
    aktif.value = k; materi.value = ''; keterangan.value = ''; foto.value = null; fotoPreview.value = null; msg.value = null
}
async function pilihFoto(e) {
    let f = e.target.files?.[0]; if (f) f = await kompresFoto(f); foto.value = f || null; fotoPreview.value = f ? URL.createObjectURL(f) : null
}

async function kirim() {
    if (!foto.value) { msg.value = { ok: false, text: 'Foto bukti mengajar wajib diisi.' }; return }
    saving.value = true
    try {
        const fd = new FormData()
        fd.append('absensi_mengajar_id', aktif.value.absensi_id)
        fd.append('foto', foto.value)
        // Multipart: kirim '1' bukan boolean agar lolos aturan validasi `boolean`.
        fd.append('sudah_buka_jurnal', '1')
        if (materi.value.trim()) fd.append('materi', materi.value.trim())
        if (keterangan.value.trim()) fd.append('keterangan', keterangan.value.trim())
        const res = await api.post('/absensi/mengajar/absen-pengganti', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
        const jadwalId = aktif.value.jadwal_id
        aktif.value = null
        msg.value = { ok: true, text: res.data.message || 'Absen pengganti tersimpan.' }
        // Langsung ke absensi santri — dulu langkah ini tidak ada, sehingga sesi
        // inval tidak pernah punya data kehadiran santri.
        router.push({ name: 'absen-santri', params: { jadwalId } })
    } catch (e) {
        msg.value = { ok: false, text: e.response?.data?.message || 'Gagal menyimpan absen.' }
        await load()
    } finally { saving.value = false }
}

const jam = (k) => `${(k.jam_mulai || '').slice(0, 5)}–${(k.jam_selesai || '').slice(0, 5)}`
const tipeBadge = (t) => ({ tahfidz: 'bg-emerald-50 text-emerald-600', tahsin: 'bg-violet-50 text-violet-600' }[t] || 'bg-blue-50 text-blue-600')
</script>

<template>
    <div>
        <PageHeader title="Kelas Pengganti" />

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

            <div class="rounded-xl bg-sky-50 border border-sky-100 px-3 py-2.5 mb-3 text-[11.5px] text-sky-800 leading-snug">
                Kelas yang dilimpahkan ke Anda karena guru asli izin. Absen, jurnal, dan absensi santri
                <b>hanya bisa diisi selama jam mengajar</b>. Bila tidak diisi, sesi tercatat
                <b>tidak terlaksana</b> — JP tidak diberikan dan tercatat di kinerja Anda.
            </div>

            <div v-if="!list.length" class="pt-16 text-center text-sm text-gray-400">Tidak ada kelas pengganti hari ini.</div>

            <ul v-else class="space-y-3">
                <li v-for="k in list" :key="k.absensi_id" class="rounded-2xl bg-white border p-4"
                    :class="k.status === 'tidak_terlaksana' ? 'border-red-100' : 'border-gray-100'">
                    <div class="flex items-start gap-3">
                        <div class="w-12 text-center shrink-0">
                            <p class="text-[11px] font-bold text-gray-700">{{ (k.jam_mulai || '').slice(0,5) }}</p>
                            <p class="text-[10px] text-gray-400">{{ (k.jam_selesai || '').slice(0,5) }}</p>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-bold text-gray-800 truncate">{{ k.mata_pelajaran }}</p>
                                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded capitalize shrink-0" :class="tipeBadge(k.tipe)">{{ k.tipe }}</span>
                            </div>
                            <p class="text-[11px] text-gray-400">{{ k.kelas }} · {{ k.jumlah_jp }} JP<span v-if="!k.is_hari_ini"> · {{ k.tanggal }}</span></p>
                            <p class="text-[11px] text-violet-500 mt-0.5">Gantikan: {{ k.guru_asli }}</p>

                            <!-- Gagal: lewat jam tanpa diisi -->
                            <p v-if="k.status === 'tidak_terlaksana'"
                                class="mt-2 text-[10px] font-bold text-red-600 bg-red-50 rounded-lg px-2 py-1">
                                ✕ Tidak terlaksana — tidak diisi selama jam mengajar
                            </p>

                            <!-- Sudah diisi -->
                            <div v-else-if="k.sudah_diajar" class="mt-2">
                                <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">
                                    ✓ Sudah diabsen · {{ k.jp_terlaksana }} JP masuk
                                </span>
                                <button v-if="k.tipe === 'reguler' && k.dalam_jam"
                                    @click="router.push({ name: 'absen-santri', params: { jadwalId: k.jadwal_id } })"
                                    class="mt-2 block px-3 py-1.5 rounded-lg bg-[#0C78FF]/10 text-[#0C78FF] text-xs font-bold">Absen Santri</button>
                                <button v-else-if="k.route && k.dalam_jam" @click="router.push(k.route)"
                                    class="mt-2 block px-3 py-1.5 rounded-lg bg-[#0C78FF]/10 text-[#0C78FF] text-xs font-bold capitalize">Lanjut di menu {{ k.tipe }}</button>
                            </div>

                            <!-- Belum waktunya -->
                            <p v-else-if="k.belum_mulai" class="mt-2 text-[11px] text-gray-500">
                                Bisa diisi mulai pukul <b>{{ (k.jam_mulai || '').slice(0,5) }}</b><span v-if="!k.is_hari_ini"> pada {{ k.tanggal }}</span>.
                            </p>

                            <!-- Dalam jam: isi sekarang -->
                            <template v-else-if="k.boleh_isi">
                                <button v-if="k.tipe === 'reguler'" @click="bukaAbsen(k)"
                                    class="mt-2 px-4 py-1.5 rounded-lg bg-[#0C78FF] text-white text-xs font-bold">
                                    Absen Pengganti <span class="font-normal opacity-80">· s/d {{ (k.jam_selesai || '').slice(0,5) }}</span>
                                </button>
                                <button v-else @click="router.push(k.route)"
                                    class="mt-2 px-4 py-1.5 rounded-lg text-white text-xs font-bold capitalize"
                                    :class="k.tipe === 'tahfidz' ? 'bg-emerald-600' : 'bg-violet-600'">
                                    Buka kelas {{ k.tipe }} <span class="font-normal opacity-80">· s/d {{ (k.jam_selesai || '').slice(0,5) }}</span>
                                </button>
                            </template>

                            <!-- Jam sudah lewat, menunggu dicatat sistem -->
                            <p v-else class="mt-2 text-[10px] font-bold text-red-600 bg-red-50 rounded-lg px-2 py-1">
                                Jam mengajar {{ jam(k) }} sudah berakhir — tidak bisa diisi lagi.
                            </p>
                        </div>
                    </div>
                </li>
            </ul>
        </template>

        <!-- Modal absen pengganti (reguler) -->
        <Transition name="pop">
            <div v-if="aktif" class="fixed inset-0 z-[70] flex items-end justify-center" style="background: rgba(0,0,0,0.55)">
                <div class="w-full max-w-md bg-white rounded-t-3xl p-5 pb-8 safe-b">
                    <div class="w-10 h-1 bg-gray-200 rounded-full mx-auto mb-4"></div>
                    <h3 class="text-base font-extrabold text-gray-900">{{ aktif.mata_pelajaran }}</h3>
                    <p class="text-xs text-gray-400 mb-3">{{ aktif.kelas }} · gantikan {{ aktif.guru_asli }}</p>

                    <p class="text-[11px] text-emerald-700 bg-emerald-50 rounded-xl px-3 py-2 mb-3">
                        Kirim sebelum pukul <b>{{ (aktif.jam_selesai || '').slice(0,5) }}</b> agar {{ aktif.jumlah_jp }} JP masuk ke Anda.
                        Setelah ini Anda diarahkan ke absensi santri.
                    </p>

                    <label class="block text-xs font-medium text-gray-600 mb-1">Materi (opsional)</label>
                    <textarea v-model="materi" rows="2" placeholder="Materi yang diajarkan…"
                        class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF] mb-3"></textarea>

                    <label class="block text-xs font-medium text-gray-600 mb-1">Foto Bukti Mengajar <span class="text-red-500">*wajib</span></label>
                    <div v-if="fotoPreview" class="mb-2"><img :src="fotoPreview" class="w-full h-40 object-cover rounded-xl" /></div>
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
