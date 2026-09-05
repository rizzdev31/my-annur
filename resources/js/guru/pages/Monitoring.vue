<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../api'
import { tanggalLokal } from '../tanggal'
import { monitoring, muatMonitoring } from '../store/monitoring'
import PageHeader from '../components/PageHeader.vue'

const loading = ref(true)
const error = ref('')
const tanggal = ref(tanggalLokal())
const d = ref(null)

// Aksi persetujuan izin
const aksi = ref(null)
const catatanAksi = ref('')
const jamAksi = ref('')
const saving = ref(false)

// Section yang dibentangkan (default ringkas agar dashboard tetap terbaca)
const buka = ref({})
const toggle = (k) => (buka.value[k] = !buka.value[k])

const punya = (k) => (d.value?.modul ?? []).includes(k)

const ST = {
    hadir: { t: 'Hadir', c: 'text-emerald-700 bg-emerald-50' },
    terlaksana: { t: 'Terlaksana', c: 'text-emerald-700 bg-emerald-50' },
    terlambat: { t: 'Terlambat', c: 'text-amber-700 bg-amber-50' },
    izin: { t: 'Izin', c: 'text-sky-700 bg-sky-50' },
    sakit: { t: 'Sakit', c: 'text-violet-700 bg-violet-50' },
    dinas_luar: { t: 'Dinas Luar', c: 'text-indigo-700 bg-indigo-50' },
    pengganti: { t: 'Pengganti', c: 'text-sky-700 bg-sky-50' },
    libur: { t: 'Libur', c: 'text-gray-500 bg-gray-100' },
    alfa: { t: 'Alfa', c: 'text-red-600 bg-red-50' },
    tidak_terlaksana: { t: 'Tak terlaksana', c: 'text-red-600 bg-red-50' },
    terlewat: { t: 'Terlewat', c: 'text-red-600 bg-red-50' },
    belum: { t: 'Belum', c: 'text-gray-500 bg-gray-100' },
}
const lbl = (s) => ST[s]?.t ?? s
const wrn = (s) => ST[s]?.c ?? 'text-gray-500 bg-gray-100'

// Kartu statistik kehadiran (urut: yang perlu perhatian dulu)
const tiles = computed(() => {
    const r = d.value?.absen?.ringkasan
    if (!r) return []
    const urut = ['belum', 'alfa', 'terlambat', 'hadir', 'izin', 'sakit', 'dinas_luar']
    return urut.filter((k) => r[k]).map((k) => ({ k, n: r[k], t: lbl(k), c: wrn(k) }))
})

async function load() {
    if (!monitoring.dimuat) await muatMonitoring()
    loading.value = true; error.value = ''
    try {
        d.value = (await api.get('/monitoring/dashboard', { params: { tanggal: tanggal.value } })).data.data
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal memuat dashboard monitoring.'
    } finally { loading.value = false }
}
onMounted(load)

function bukaAksi(i, tipe) { aksi.value = { izin: i, tipe }; catatanAksi.value = ''; jamAksi.value = '' }
async function kirimAksi() {
    const { izin: i, tipe } = aksi.value
    if (tipe === 'tolak' && catatanAksi.value.trim().length < 3) { error.value = 'Alasan penolakan wajib (min 3 huruf).'; return }
    saving.value = true; error.value = ''
    try {
        const body = { catatan: catatanAksi.value.trim() || undefined }
        if (tipe === 'setujui' && i.datang_terlambat && jamAksi.value) body.jam_mulai = jamAksi.value
        await api.post(`/monitoring/perizinan/${i.id}/${tipe}`, body)
        aksi.value = null
        await load()
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal memproses izin.'
    } finally { saving.value = false }
}
</script>

<template>
    <div>
        <PageHeader title="Monitoring" />

        <div v-if="loading" class="pt-16 flex justify-center">
            <div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div>
        </div>

        <div v-else-if="error" class="pt-8 text-center">
            <p class="text-sm text-gray-500">{{ error }}</p>
            <button @click="load" class="mt-3 px-4 py-2 rounded-xl bg-[#0C78FF] text-white text-sm font-semibold">Coba lagi</button>
        </div>

        <template v-else-if="d">
            <!-- Kepala: tanggal -->
            <div class="flex items-center gap-2 mb-3">
                <input v-model="tanggal" @change="load" type="date"
                    class="flex-1 min-w-0 px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none bg-white" />
                <span class="shrink-0 text-[11px] text-gray-400">{{ d.total_guru }} guru</span>
            </div>
            <p class="text-[11px] text-gray-400 -mt-1 mb-3">{{ d.hari }}</p>

            <div v-if="!d.total_guru" class="pt-10 text-center text-sm text-gray-400">
                Belum ada guru yang ditugaskan untuk Anda pantau.
            </div>

            <template v-else>
                <!-- PERLU PERHATIAN -->
                <div v-if="d.perhatian.length" class="rounded-2xl bg-red-50 border border-red-100 p-3 mb-3">
                    <p class="text-[12px] font-extrabold text-red-700 mb-1.5">⚠ Perlu perhatian</p>
                    <div v-for="(x, i) in d.perhatian" :key="i" class="py-1 border-t border-red-100/70 first:border-0">
                        <p class="text-[12px] font-bold text-red-700">{{ x.teks }}</p>
                        <p class="text-[11px] text-red-500/90 leading-snug">{{ x.nama.join(' · ') }}</p>
                    </div>
                </div>
                <div v-else class="rounded-2xl bg-emerald-50 border border-emerald-100 p-3 mb-3 text-center">
                    <p class="text-[12px] font-bold text-emerald-700">✓ Tidak ada masalah hari ini</p>
                </div>

                <!-- KEHADIRAN -->
                <template v-if="punya('absen_harian') && d.absen">
                    <div class="grid grid-cols-3 gap-2 mb-2">
                        <div v-for="t in tiles" :key="t.k" :class="['rounded-2xl p-2.5 text-center', t.c]">
                            <p class="text-xl font-extrabold leading-none">{{ t.n }}</p>
                            <p class="text-[10px] font-semibold mt-0.5">{{ t.t }}</p>
                        </div>
                    </div>
                    <div class="rounded-2xl bg-white border border-gray-100 p-3 mb-3">
                        <button @click="toggle('absen')" class="w-full flex items-center justify-between gap-2">
                            <span class="text-[13px] font-extrabold text-gray-800">Kehadiran Guru</span>
                            <span class="text-[11px] text-[#0C78FF] font-bold">{{ buka.absen ? 'Tutup' : 'Lihat semua' }}</span>
                        </button>
                        <ul v-if="buka.absen" class="mt-2 divide-y divide-gray-50">
                            <li v-for="(g, i) in d.absen.guru" :key="i" class="py-2 flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-[12px] font-semibold text-gray-800 truncate">{{ g.nama }}</p>
                                    <p class="text-[10px] text-gray-400">
                                        <span v-if="g.jam_masuk">masuk {{ g.jam_masuk }}</span>
                                        <span v-if="g.menit_terlambat > 0" class="text-amber-600"> · telat {{ g.menit_terlambat }} mnt</span>
                                    </p>
                                </div>
                                <span :class="['shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold', wrn(g.status)]">{{ lbl(g.status) }}</span>
                            </li>
                        </ul>
                    </div>
                </template>

                <!-- JADWAL MENGAJAR HARI INI -->
                <div v-if="punya('absen_mengajar') && d.mengajar" class="rounded-2xl bg-white border border-gray-100 p-3 mb-3">
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <span class="text-[13px] font-extrabold text-gray-800">Jadwal Mengajar Hari Ini</span>
                        <span class="shrink-0 text-[11px] font-bold"
                            :class="d.mengajar.bermasalah ? 'text-red-600' : 'text-emerald-600'">
                            {{ d.mengajar.beres }}/{{ d.mengajar.total }} beres
                        </span>
                    </div>
                    <p v-if="!d.mengajar.total" class="text-[11px] text-gray-400 py-2">Tidak ada jadwal mengajar.</p>
                    <ul v-else class="divide-y divide-gray-50">
                        <li v-for="(s, i) in (buka.mengajar ? d.mengajar.sesi : d.mengajar.sesi.slice(0, 5))" :key="i"
                            class="py-2 flex items-center gap-2">
                            <span class="shrink-0 text-[10px] text-gray-400 w-[76px]">{{ s.jam }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[12px] font-semibold text-gray-800 truncate">{{ s.guru }}</p>
                                <p class="text-[10px] text-gray-400 truncate">
                                    {{ s.mata_pelajaran }} · {{ s.kelas }}<span v-if="s.pengganti"> · diganti {{ s.pengganti }}</span>
                                </p>
                            </div>
                            <span :class="['shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold', wrn(s.status)]">{{ lbl(s.status) }}</span>
                        </li>
                    </ul>
                    <button v-if="d.mengajar.sesi.length > 5" @click="toggle('mengajar')"
                        class="w-full mt-1 py-1.5 text-[11px] font-bold text-[#0C78FF]">
                        {{ buka.mengajar ? 'Tutup' : `Lihat semua (${d.mengajar.sesi.length})` }}
                    </button>
                </div>

                <!-- PERIZINAN -->
                <div v-if="punya('perizinan') && d.izin" class="rounded-2xl bg-white border border-gray-100 p-3 mb-3">
                    <p class="text-[13px] font-extrabold text-gray-800 mb-1.5">Perizinan</p>

                    <p class="text-[11px] font-bold text-gray-500 mb-1">Sedang izin hari ini
                        <span class="text-gray-400 font-normal">({{ d.izin.aktif_hari_ini.length }})</span></p>
                    <p v-if="!d.izin.aktif_hari_ini.length" class="text-[11px] text-gray-400 mb-2">Tidak ada.</p>
                    <ul v-else class="mb-2">
                        <li v-for="(z, i) in d.izin.aktif_hari_ini" :key="i" class="py-1 flex items-center justify-between gap-2">
                            <span class="text-[12px] text-gray-800 truncate min-w-0">{{ z.guru }}</span>
                            <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold text-sky-700 bg-sky-50">{{ z.jenis }}</span>
                        </li>
                    </ul>

                    <p class="text-[11px] font-bold text-gray-500 mb-1 pt-1 border-t border-gray-50">Menunggu keputusan
                        <span class="text-gray-400 font-normal">({{ d.izin.menunggu.length }})</span></p>
                    <p v-if="!d.izin.menunggu.length" class="text-[11px] text-gray-400">Tidak ada.</p>
                    <ul v-else class="divide-y divide-gray-50">
                        <li v-for="z in d.izin.menunggu" :key="z.id" class="py-2">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-[12px] font-semibold text-gray-800 truncate">{{ z.guru }}</p>
                                    <p class="text-[10px] text-gray-400">{{ z.jenis }} · {{ z.mulai }}<span v-if="z.selesai && z.selesai !== z.mulai"> — {{ z.selesai }}</span></p>
                                </div>
                                <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold text-amber-700 bg-amber-50">Menunggu</span>
                            </div>
                            <p v-if="z.alasan" class="text-[11px] text-gray-500 mt-0.5">{{ z.alasan }}</p>
                            <div v-if="d.izin.boleh_setujui_izin" class="flex gap-2 mt-2">
                                <button @click="bukaAksi(z, 'setujui')"
                                    class="flex-1 py-2 rounded-lg bg-emerald-600 text-white text-[12px] font-bold active:scale-[0.98] transition">Setujui</button>
                                <button @click="bukaAksi(z, 'tolak')"
                                    class="flex-1 py-2 rounded-lg bg-red-50 text-red-600 text-[12px] font-bold active:scale-[0.98] transition">Tolak</button>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- TUGAS TAMBAHAN -->
                <div v-if="punya('tugas_tambahan') && d.tugas" class="rounded-2xl bg-white border border-gray-100 p-3 mb-3">
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <span class="text-[13px] font-extrabold text-gray-800">Tugas Tambahan Berjalan</span>
                        <span class="shrink-0 text-[11px] font-bold text-gray-500">{{ d.tugas.berjalan }}</span>
                    </div>
                    <p v-if="!d.tugas.berjalan" class="text-[11px] text-gray-400 py-1">Tidak ada tugas berjalan.</p>
                    <ul v-else class="divide-y divide-gray-50">
                        <li v-for="(t, i) in (buka.tugas ? d.tugas.daftar : d.tugas.daftar.slice(0, 5))" :key="i"
                            class="py-2 flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-[12px] font-semibold text-gray-800 truncate">{{ t.judul }}</p>
                                <p class="text-[10px] text-gray-400 truncate">{{ t.guru }}<span v-if="t.selesai"> · s/d {{ t.selesai }}</span></p>
                            </div>
                            <span :class="['shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold',
                                t.status === 'sedang' ? 'text-amber-700 bg-amber-50' : 'text-gray-500 bg-gray-100']">
                                {{ t.status === 'sedang' ? 'Dikerjakan' : 'Belum' }}
                            </span>
                        </li>
                    </ul>
                    <button v-if="d.tugas.daftar.length > 5" @click="toggle('tugas')"
                        class="w-full mt-1 py-1.5 text-[11px] font-bold text-[#0C78FF]">
                        {{ buka.tugas ? 'Tutup' : `Lihat semua (${d.tugas.daftar.length})` }}
                    </button>
                </div>

                <!-- KINERJA -->
                <div v-if="punya('kinerja') && d.kinerja" class="rounded-2xl bg-white border border-gray-100 p-3 mb-3">
                    <p class="text-[13px] font-extrabold text-gray-800 mb-0.5">Kinerja Terendah Bulan Ini</p>
                    <p class="text-[10px] text-gray-400 mb-1.5">Perlu perhatian lebih dulu.</p>
                    <p v-if="!d.kinerja.terendah.length" class="text-[11px] text-gray-400">Belum ada data kinerja.</p>
                    <ul v-else class="divide-y divide-gray-50">
                        <li v-for="(g, i) in d.kinerja.terendah" :key="i" class="py-1.5 flex items-center justify-between gap-2">
                            <span class="text-[12px] text-gray-800 truncate min-w-0">{{ g.guru }}</span>
                            <span class="shrink-0 text-[13px] font-extrabold"
                                :class="g.skor >= 80 ? 'text-emerald-600' : g.skor >= 70 ? 'text-amber-600' : 'text-red-500'">{{ g.skor }}</span>
                        </li>
                    </ul>
                </div>
            </template>
        </template>

        <!-- Sheet konfirmasi setujui/tolak izin -->
        <div v-if="aksi" class="fixed inset-0 z-[70] flex items-end justify-center" style="background: rgba(0,0,0,.55)">
            <div class="w-full max-w-md bg-white rounded-t-3xl p-5 pb-8 safe-b">
                <div class="w-10 h-1 bg-gray-200 rounded-full mx-auto mb-4"></div>
                <h3 class="text-base font-extrabold text-gray-900">{{ aksi.tipe === 'setujui' ? 'Setujui Izin' : 'Tolak Izin' }}</h3>
                <p class="text-xs text-gray-400 mb-1">{{ aksi.izin.guru }} · {{ aksi.izin.jenis }}</p>
                <p class="text-[11px] text-amber-600 mb-3">Keputusan Anda bersifat final dan tercatat atas nama Anda.</p>

                <template v-if="aksi.tipe === 'setujui' && aksi.izin.datang_terlambat">
                    <label class="block text-[11px] font-medium text-gray-600 mb-1">Batas jam datang (opsional)</label>
                    <input v-model="jamAksi" type="time" step="60" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-3" />
                </template>

                <label class="block text-[11px] font-medium text-gray-600 mb-1">
                    {{ aksi.tipe === 'setujui' ? 'Catatan (opsional)' : 'Alasan penolakan' }}
                    <span v-if="aksi.tipe === 'tolak'" class="text-red-500">*</span>
                </label>
                <textarea v-model="catatanAksi" rows="2" :placeholder="aksi.tipe === 'setujui' ? 'Catatan untuk guru…' : 'Wajib diisi…'"
                    class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-4"></textarea>

                <div class="flex gap-2">
                    <button @click="aksi = null" class="flex-1 py-3 rounded-xl bg-gray-100 text-gray-600 font-bold text-sm">Batal</button>
                    <button @click="kirimAksi" :disabled="saving"
                        :class="['flex-1 py-3 rounded-xl text-white font-bold text-sm disabled:opacity-60', aksi.tipe === 'setujui' ? 'bg-emerald-600' : 'bg-red-600']">
                        {{ saving ? 'Memproses…' : (aksi.tipe === 'setujui' ? 'Ya, Setujui' : 'Ya, Tolak') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
