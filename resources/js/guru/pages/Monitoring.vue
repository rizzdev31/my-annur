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

// Tab hanya untuk modul yang diberikan admin.
const tabs = computed(() => [
    { k: 'absen_harian',   t: 'Absen Harian' },
    { k: 'absen_mengajar', t: 'Pembelajaran' },
    { k: 'perizinan',      t: 'Perizinan' },
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
        }
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal memuat data monitoring.'
    } finally { loading.value = false }
}
watch([tab, izinStatus], load)
onMounted(load)
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
                    </li>
                </ul>
                <p v-if="izin.boleh_setujui_izin" class="mt-3 text-[11px] text-gray-400 text-center">
                    Anda berhak menyetujui izin — tombol persetujuan menyusul.
                </p>
            </template>
        </template>
    </div>
</template>
