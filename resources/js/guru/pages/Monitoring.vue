<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '../api'
import { tanggalLokal } from '../tanggal'
import { monitoring, muatMonitoring, bolehModul } from '../store/monitoring'
import PageHeader from '../components/PageHeader.vue'

const loading = ref(true)
const error = ref('')
const tanggal = ref(tanggalLokal())
const cari = ref('')
const tab = ref('')

const data = ref(null)          // absen harian
const mengajar = ref(null)      // absen mengajar
const izin = ref(null)          // perizinan
const izinStatus = ref('pending')
const tugas = ref(null)         // tugas tambahan
const tugasStatus = ref('')
const kinerja = ref(null)       // kinerja

// Aksi persetujuan izin (final)
const aksi = ref(null)          // { izin, tipe: 'setujui'|'tolak' }
const catatanAksi = ref('')
const jamAksi = ref('')
const saving = ref(false)

// Tab hanya untuk modul yang diberikan admin.
const tabs = computed(() => [
    { k: 'absen_harian',   t: 'Absen Harian' },
    { k: 'absen_mengajar', t: 'Pembelajaran' },
    { k: 'perizinan',      t: 'Perizinan' },
    { k: 'tugas_tambahan', t: 'Tugas' },
    { k: 'kinerja',        t: 'Kinerja' },
].filter((x) => bolehModul(x.k)))

const adaAkses = computed(() => tabs.value.length > 0)

const ST = {
    hadir:            { t: 'Hadir',      c: 'bg-emerald-50 text-emerald-700' },
    terlaksana:       { t: 'Terlaksana', c: 'bg-emerald-50 text-emerald-700' },
    terlambat:        { t: 'Terlambat',  c: 'bg-amber-50 text-amber-700' },
    izin:             { t: 'Izin',       c: 'bg-sky-50 text-sky-700' },
    sakit:            { t: 'Sakit',      c: 'bg-violet-50 text-violet-700' },
    dinas_luar:       { t: 'Dinas Luar', c: 'bg-indigo-50 text-indigo-700' },
    pengganti:        { t: 'Pengganti',  c: 'bg-sky-50 text-sky-700' },
    libur:            { t: 'Libur',      c: 'bg-gray-100 text-gray-500' },
    alfa:             { t: 'Alfa',       c: 'bg-red-50 text-red-600' },
    tidak_terlaksana: { t: 'Tak terlaksana', c: 'bg-red-50 text-red-600' },
    terlewat:         { t: 'Terlewat',   c: 'bg-red-50 text-red-600' },
    belum:            { t: 'Belum',      c: 'bg-gray-100 text-gray-500' },
    pending:          { t: 'Menunggu',   c: 'bg-amber-50 text-amber-700' },
    disetujui:        { t: 'Disetujui',  c: 'bg-emerald-50 text-emerald-700' },
    ditolak:          { t: 'Ditolak',    c: 'bg-red-50 text-red-600' },
}
const lbl = (s) => ST[s]?.t ?? s
const wrn = (s) => ST[s]?.c ?? 'bg-gray-100 text-gray-500'

const saring = (list) => {
    const q = cari.value.trim().toLowerCase()
    return q ? (list ?? []).filter((g) => (g.nama || g.guru || '').toLowerCase().includes(q)) : (list ?? [])
}

async function load() {
    if (!monitoring.dimuat) await muatMonitoring()
    if (!tab.value) tab.value = tabs.value[0]?.k ?? ''
    if (!adaAkses.value) { loading.value = false; return }

    loading.value = true; error.value = ''
    try {
        if (tab.value === 'absen_harian') {
            data.value = (await api.get('/monitoring/absen-harian', { params: { tanggal: tanggal.value } })).data.data
        } else if (tab.value === 'absen_mengajar') {
            mengajar.value = (await api.get('/monitoring/absen-mengajar', { params: { tanggal: tanggal.value } })).data.data
        } else if (tab.value === 'perizinan') {
            izin.value = (await api.get('/monitoring/perizinan', { params: { status: izinStatus.value || undefined } })).data.data
        } else if (tab.value === 'tugas_tambahan') {
            tugas.value = (await api.get('/monitoring/tugas-tambahan', { params: { status: tugasStatus.value || undefined } })).data.data
        } else if (tab.value === 'kinerja') {
            kinerja.value = (await api.get('/monitoring/kinerja')).data.data
        }
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal memuat data monitoring.'
    } finally { loading.value = false }
}
watch([tab, izinStatus, tugasStatus], load)
onMounted(load)

function bukaAksi(i, tipe) { aksi.value = { izin: i, tipe }; catatanAksi.value = ''; jamAksi.value = '' }
async function kirimAksi() {
    const { izin: i, tipe } = aksi.value
    if (tipe === 'tolak' && catatanAksi.value.trim().length < 3) {
        error.value = 'Alasan penolakan wajib (min 3 huruf).'; return
    }
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

        <div v-if="loading && !tabs.length" class="pt-16 flex justify-center">
            <div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div>
        </div>

        <div v-else-if="!adaAkses" class="pt-10 text-center px-6">
            <p class="text-sm text-gray-500">Anda belum diberi akses monitoring.</p>
            <p class="text-[11px] text-gray-400 mt-1">Hak ini diberikan oleh admin.</p>
        </div>

        <template v-else>
            <!-- Tab modul -->
            <div v-if="tabs.length > 1" class="flex gap-1 bg-gray-100 rounded-2xl p-1 mb-3 overflow-x-auto">
                <button v-for="x in tabs" :key="x.k" @click="tab = x.k"
                    class="flex-1 shrink-0 whitespace-nowrap py-2 px-3 rounded-xl text-[12px] font-bold transition"
                    :class="tab === x.k ? 'bg-white text-[#0C78FF] shadow-sm' : 'text-gray-400'">{{ x.t }}</button>
            </div>

            <!-- Filter atas -->
            <div v-if="tab !== 'perizinan'" class="flex items-center gap-2 mb-3">
                <input v-model="tanggal" @change="load" type="date"
                    class="flex-1 min-w-0 px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none bg-white" />
            </div>
            <div v-else class="flex gap-1 bg-gray-100 rounded-2xl p-1 mb-3">
                <button v-for="s in [['pending','Menunggu'],['disetujui','Disetujui'],['ditolak','Ditolak'],['','Semua']]" :key="s[0]"
                    @click="izinStatus = s[0]"
                    class="flex-1 py-1.5 rounded-xl text-[11px] font-bold transition"
                    :class="izinStatus === s[0] ? 'bg-white text-[#0C78FF] shadow-sm' : 'text-gray-400'">{{ s[1] }}</button>
            </div>

            <div v-if="loading" class="pt-10 flex justify-center">
                <div class="w-7 h-7 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div>
            </div>
            <div v-else-if="error" class="pt-8 text-center">
                <p class="text-sm text-gray-500">{{ error }}</p>
                <button @click="load" class="mt-3 px-4 py-2 rounded-xl bg-[#0C78FF] text-white text-sm font-semibold">Coba lagi</button>
            </div>

            <!-- ── ABSEN HARIAN ── -->
            <template v-else-if="tab === 'absen_harian' && data">
                <div v-if="Object.keys(data.ringkasan || {}).length" class="flex flex-wrap gap-1.5 mb-3">
                    <span v-for="(n, s) in data.ringkasan" :key="s" :class="['px-2.5 py-1 rounded-full text-[11px] font-bold', wrn(s)]">{{ lbl(s) }} {{ n }}</span>
                </div>
                <input v-model="cari" placeholder="Cari nama guru…" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-3" />
                <div v-if="!saring(data.guru).length" class="pt-10 text-center text-sm text-gray-400">
                    {{ data.total ? 'Tidak ada guru cocok.' : 'Belum ada guru yang ditugaskan untuk Anda pantau.' }}
                </div>
                <ul v-else class="space-y-2">
                    <li v-for="g in saring(data.guru)" :key="g.tenaga_pendidik_id" class="rounded-2xl bg-white border border-gray-100 p-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-gray-800 truncate">{{ g.nama }}</p>
                                <p class="text-[11px] text-gray-400 truncate">{{ g.jabatan }}</p>
                            </div>
                            <span :class="['shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold', wrn(g.status)]">{{ lbl(g.status) }}</span>
                        </div>
                        <div v-if="g.jam_masuk || g.jam_pulang || g.menit_terlambat" class="mt-1.5 flex flex-wrap gap-x-3 text-[11px] text-gray-500">
                            <span v-if="g.jam_masuk">Masuk <b class="text-gray-700">{{ g.jam_masuk }}</b></span>
                            <span v-if="g.jam_pulang">Pulang <b class="text-gray-700">{{ g.jam_pulang }}</b></span>
                            <span v-if="g.menit_terlambat > 0" class="text-amber-600 font-semibold">Telat {{ g.menit_terlambat }} mnt</span>
                        </div>
                    </li>
                </ul>
            </template>

            <!-- ── ABSENSI PEMBELAJARAN ── -->
            <template v-else-if="tab === 'absen_mengajar' && mengajar">
                <div v-if="Object.keys(mengajar.ringkasan || {}).length" class="flex flex-wrap gap-1.5 mb-3">
                    <span v-for="(n, s) in mengajar.ringkasan" :key="s" :class="['px-2.5 py-1 rounded-full text-[11px] font-bold', wrn(s)]">{{ lbl(s) }} {{ n }}</span>
                </div>
                <input v-model="cari" placeholder="Cari nama guru…" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-3" />
                <div v-if="!saring(mengajar.guru).length" class="pt-10 text-center text-sm text-gray-400">Tidak ada jadwal mengajar pada tanggal ini.</div>
                <ul v-else class="space-y-2">
                    <li v-for="g in saring(mengajar.guru)" :key="g.tenaga_pendidik_id" class="rounded-2xl bg-white border border-gray-100 p-3">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-gray-800 truncate">{{ g.nama }}</p>
                                <p class="text-[11px] text-gray-400">{{ g.beres }}/{{ g.total }} sesi beres</p>
                            </div>
                            <span v-if="g.bermasalah" class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-50 text-red-600">{{ g.bermasalah }} bermasalah</span>
                            <span v-else class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700">Aman</span>
                        </div>
                        <div v-for="s in g.sesi" :key="s.jadwal_id" class="flex items-center gap-2 py-1.5 border-t border-gray-50">
                            <span class="shrink-0 text-[10px] text-gray-400 w-[76px]">{{ s.jam }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[12px] font-semibold text-gray-700 truncate">{{ s.mata_pelajaran }}</p>
                                <p class="text-[10px] text-gray-400 truncate">
                                    {{ s.kelas }}<span v-if="s.pengganti"> · diganti {{ s.pengganti }}</span>
                                </p>
                            </div>
                            <span :class="['shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold', wrn(s.status)]">{{ lbl(s.status) }}</span>
                        </div>
                    </li>
                </ul>
            </template>

            <!-- ── PERIZINAN (lihat) ── -->
            <template v-else-if="tab === 'perizinan' && izin">
                <div v-if="Object.keys(izin.ringkasan || {}).length" class="flex flex-wrap gap-1.5 mb-3">
                    <span v-for="(n, s) in izin.ringkasan" :key="s" :class="['px-2.5 py-1 rounded-full text-[11px] font-bold', wrn(s)]">{{ lbl(s) }} {{ n }}</span>
                </div>
                <input v-model="cari" placeholder="Cari nama guru…" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-3" />
                <div v-if="!saring(izin.izin).length" class="pt-10 text-center text-sm text-gray-400">Tidak ada pengajuan izin.</div>
                <ul v-else class="space-y-2">
                    <li v-for="i in saring(izin.izin)" :key="i.id" class="rounded-2xl bg-white border border-gray-100 p-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-gray-800 truncate">{{ i.guru }}</p>
                                <p class="text-[11px] text-gray-500 truncate">
                                    {{ i.jenis }}<span v-if="i.sementara"> · sementara</span><span v-if="i.datang_terlambat"> · datang terlambat</span>
                                </p>
                            </div>
                            <span :class="['shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold', wrn(i.status)]">{{ lbl(i.status) }}</span>
                        </div>
                        <p class="text-[11px] text-gray-400 mt-1">
                            {{ i.tanggal_mulai }}<span v-if="i.tanggal_selesai && i.tanggal_selesai !== i.tanggal_mulai"> — {{ i.tanggal_selesai }}</span>
                            <span v-if="i.jumlah_hari"> · {{ i.jumlah_hari }} hari</span>
                        </p>
                        <p v-if="i.alasan" class="text-[12px] text-gray-600 mt-1">{{ i.alasan }}</p>
                        <p v-if="i.catatan_admin" class="text-[11px] text-gray-400 mt-1 italic">Catatan: {{ i.catatan_admin }}</p>

                        <div v-if="izin.boleh_setujui_izin && i.status === 'pending'" class="flex gap-2 mt-2.5">
                            <button @click="bukaAksi(i, 'setujui')"
                                class="flex-1 py-2 rounded-lg bg-emerald-600 text-white text-[12px] font-bold active:scale-[0.98] transition">Setujui</button>
                            <button @click="bukaAksi(i, 'tolak')"
                                class="flex-1 py-2 rounded-lg bg-red-50 text-red-600 text-[12px] font-bold active:scale-[0.98] transition">Tolak</button>
                        </div>
                    </li>
                </ul>
            </template>

            <!-- ── TUGAS TAMBAHAN ── -->
            <template v-else-if="tab === 'tugas_tambahan' && tugas">
                <div class="flex gap-1 bg-gray-100 rounded-2xl p-1 mb-3">
                    <button v-for="s in [['','Semua'],['belum','Belum'],['sedang','Dikerjakan'],['selesai','Selesai']]" :key="s[0]"
                        @click="tugasStatus = s[0]" class="flex-1 py-1.5 rounded-xl text-[11px] font-bold transition"
                        :class="tugasStatus === s[0] ? 'bg-white text-[#0C78FF] shadow-sm' : 'text-gray-400'">{{ s[1] }}</button>
                </div>
                <input v-model="cari" placeholder="Cari nama guru…" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-3" />
                <div v-if="!saring(tugas.tugas).length" class="pt-10 text-center text-sm text-gray-400">Tidak ada penugasan.</div>
                <ul v-else class="space-y-2">
                    <li v-for="t in saring(tugas.tugas)" :key="t.id" class="rounded-2xl bg-white border border-gray-100 p-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-gray-800 truncate">{{ t.judul }}</p>
                                <p class="text-[11px] text-gray-500 truncate">{{ t.guru }}</p>
                            </div>
                            <span :class="['shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold',
                                t.status === 'selesai' ? 'bg-emerald-50 text-emerald-700' : t.status === 'sedang' ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-500']">
                                {{ t.status === 'selesai' ? 'Selesai' : t.status === 'sedang' ? 'Dikerjakan' : 'Belum' }}
                            </span>
                        </div>
                        <p class="text-[11px] text-gray-400 mt-1">
                            {{ t.mulai }}<span v-if="t.selesai && t.selesai !== t.mulai"> — {{ t.selesai }}</span>
                            <span v-if="t.dilaporkan"> · dilaporkan {{ t.dilaporkan }}</span>
                        </p>
                        <p v-if="t.laporan" class="text-[12px] text-gray-600 mt-1 line-clamp-2">{{ t.laporan }}</p>
                    </li>
                </ul>
            </template>

            <!-- ── KINERJA (ringkas + komponen, tanpa rupiah) ── -->
            <template v-else-if="tab === 'kinerja' && kinerja">
                <p class="text-[11px] text-gray-400 mb-2">Bulan {{ kinerja.bulan }}/{{ kinerja.tahun }} · skor terendah di atas.</p>
                <input v-model="cari" placeholder="Cari nama guru…" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-3" />
                <div v-if="!saring(kinerja.guru).length" class="pt-10 text-center text-sm text-gray-400">Belum ada data kinerja.</div>
                <ul v-else class="space-y-2">
                    <li v-for="g in saring(kinerja.guru)" :key="g.tenaga_pendidik_id" class="rounded-2xl bg-white border border-gray-100 p-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-gray-800 truncate">{{ g.nama }}</p>
                                <p class="text-[11px] text-gray-400 truncate">{{ g.jabatan }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-lg font-extrabold leading-none"
                                    :class="g.skor === null ? 'text-gray-300' : g.skor >= 80 ? 'text-emerald-600' : g.skor >= 70 ? 'text-amber-600' : 'text-red-500'">
                                    {{ g.skor ?? '–' }}
                                </p>
                                <p class="text-[9px] text-gray-400">{{ g.grade ?? 'belum' }}</p>
                            </div>
                        </div>
                        <div v-if="g.komponen" class="grid grid-cols-4 gap-1.5 mt-2">
                            <div v-for="(v, k) in g.komponen" :key="k" class="rounded-lg bg-gray-50 py-1.5 text-center">
                                <p class="text-[8px] text-gray-400 capitalize leading-tight">{{ k }}</p>
                                <p class="text-[12px] font-bold text-gray-700">{{ v ?? '–' }}</p>
                            </div>
                        </div>
                        <p v-if="g.absensi" class="text-[11px] text-gray-500 mt-1.5">
                            Hadir {{ g.absensi.hadir }} · Telat {{ g.absensi.terlambat }} · Izin {{ g.absensi.izin }} · Alfa {{ g.absensi.alfa }}
                            <span v-if="g.mengajar"> · Sesi {{ g.mengajar.sesi_terlaksana }}/{{ g.mengajar.sesi_jadwal }}</span>
                        </p>
                    </li>
                </ul>
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
