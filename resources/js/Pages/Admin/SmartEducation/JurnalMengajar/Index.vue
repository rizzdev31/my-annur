<template>
    <AdminLayout title="Jurnal Mengajar" subtitle="Smart Education">

        <Head title="Jurnal Mengajar" />

        <div class="mb-6">
            <h2 class="text-xl font-semibold text-gray-900">Jurnal & Absensi Santri</h2>
            <p class="text-sm text-gray-400 mt-0.5">Monitoring sesi mengajar sekolah dan rekap kehadiran santri.</p>
        </div>

        <!-- Ringkasan -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-5">
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-xs text-gray-400">Total Sesi</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ summary.total_sesi }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-xs text-gray-400">Sudah Absen Santri</p>
                <p class="text-2xl font-bold text-indigo-600 mt-1">{{ summary.sudah_isi }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-xs text-gray-400">Hadir</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1">{{ summary.total_hadir }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-xs text-gray-400">Telat</p>
                <p class="text-2xl font-bold text-amber-600 mt-1">{{ summary.total_telat }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-xs text-gray-400">Izin</p>
                <p class="text-2xl font-bold text-sky-600 mt-1">{{ summary.total_izin }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-xs text-gray-400">Sakit</p>
                <p class="text-2xl font-bold text-violet-600 mt-1">{{ summary.total_sakit }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-xs text-gray-400">Alpha</p>
                <p class="text-2xl font-bold text-red-600 mt-1">{{ summary.total_alpha }}</p>
            </div>
        </div>

        <!-- Filter -->
        <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-5 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Dari</label>
                <input v-model="f.dari" type="date" :class="fieldCls" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Sampai</label>
                <input v-model="f.sampai" type="date" :class="fieldCls" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Kelas</label>
                <select v-model="f.kelas_id" :class="fieldCls">
                    <option :value="null">Semua kelas</option>
                    <option v-for="k in kelasOpsi" :key="k.id" :value="k.id">{{ k.nama }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Guru</label>
                <select v-model="f.guru_id" :class="fieldCls">
                    <option :value="null">Semua guru</option>
                    <option v-for="g in guruOpsi" :key="g.id" :value="g.id">{{ g.nama }}</option>
                </select>
            </div>
            <button @click="terapkan"
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors">
                Terapkan
            </button>
        </div>

        <!-- Tabel sesi -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Tanggal</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Kelas / Mapel</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide hidden md:table-cell">Guru</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Absensi Santri</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="s in sesi" :key="s.id" class="hover:bg-gray-50/40 transition-colors">
                        <td class="px-5 py-3.5">
                            <p class="text-sm font-medium text-gray-800">{{ formatTgl(s.tanggal) }}</p>
                            <p class="text-xs text-gray-400">{{ s.jp }} JP · {{ statusLabel(s.status_sesi) }}</p>
                        </td>
                        <td class="px-5 py-3.5">
                            <p class="text-sm font-medium text-gray-800">{{ s.kelas }}</p>
                            <p class="text-xs text-gray-400">{{ s.mapel }}</p>
                            <!-- Sesi ujian tetap tercatat di jurnal kelas, tapi
                                 jangan terbaca sebagai pembelajaran biasa. -->
                            <p v-if="s.is_ujian" class="inline-flex items-center gap-1 mt-1 text-[10px] font-bold px-1.5 py-0.5 rounded bg-rose-50 text-rose-700">
                                UJIAN<span v-if="s.ujian" class="font-semibold">· {{ s.ujian }}</span>
                                <span v-if="s.ruangan_ujian" class="font-normal opacity-70">· R.{{ s.ruangan_ujian }}</span>
                            </p>
                        </td>
                        <td class="px-5 py-3.5 hidden md:table-cell">
                            <span class="text-sm text-gray-600">{{ s.guru }}</span>
                        </td>
                        <td class="px-5 py-3.5">
                            <div v-if="s.sudah_absen_santri" class="flex flex-wrap gap-1.5">
                                <span class="px-2 py-0.5 rounded-lg bg-emerald-50 text-emerald-700 text-xs font-medium">H {{ s.hadir }}</span>
                                <span class="px-2 py-0.5 rounded-lg bg-amber-50 text-amber-700 text-xs font-medium">T {{ s.telat }}</span>
                                <span v-if="s.izin" class="px-2 py-0.5 rounded-lg bg-sky-50 text-sky-700 text-xs font-medium">I {{ s.izin }}</span>
                                <span v-if="s.sakit" class="px-2 py-0.5 rounded-lg bg-violet-50 text-violet-700 text-xs font-medium">S {{ s.sakit }}</span>
                                <span class="px-2 py-0.5 rounded-lg bg-red-50 text-red-700 text-xs font-medium">A {{ s.alpha }}</span>
                                <span class="text-xs text-gray-400 self-center">/ {{ s.total_santri }}</span>
                            </div>
                            <span v-else class="text-xs text-gray-400">Belum diisi</span>
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <button @click="lihat(s)"
                                class="px-3 py-1.5 rounded-lg text-xs font-medium text-indigo-600 hover:bg-indigo-50 transition-colors">
                                Lihat
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!sesi.length">
                        <td colspan="5" class="py-14 text-center text-sm text-gray-400">
                            Tidak ada sesi mengajar pada rentang ini.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal detail -->
        <Transition name="modal">
            <div v-if="detail" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="detail = null" />
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
                    <div class="flex items-start justify-between mb-1">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ detail.kelas }} — {{ detail.mapel }}</h3>
                            <p class="text-xs text-gray-400">{{ formatTgl(detail.tanggal) }} · {{ detail.guru }} · {{ detail.jp }} JP</p>
                        </div>
                        <button @click="detail = null" class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div v-if="detail.materi" class="mb-4 mt-2 p-3 rounded-xl bg-gray-50 text-sm text-gray-700">
                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Materi</span>
                        <p class="mt-1">{{ detail.materi }}</p>
                    </div>

                    <!-- Dua jalur koreksi admin: status sesi & absensi santri -->
                    <div class="flex flex-wrap gap-2 mb-4">
                        <button @click="bukaKoreksiSesi(detail)"
                            class="px-3 py-1.5 rounded-lg bg-amber-50 text-amber-700 text-xs font-semibold">
                            Koreksi Sesi
                        </button>
                        <button @click="bukaKoreksiRoster(detail)"
                            class="px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 text-xs font-semibold">
                            {{ detail.santri.length ? 'Koreksi Absensi Santri' : 'Isi Absensi Santri' }}
                        </button>
                        <span v-if="detail.is_koreksi" class="px-2.5 py-1.5 rounded-lg bg-gray-100 text-gray-500 text-[11px] font-semibold">
                            pernah dikoreksi
                        </span>
                    </div>

                    <div v-if="detail.santri.length" class="space-y-1.5">
                        <div v-for="(st, i) in detail.santri" :key="i"
                            class="flex items-center justify-between px-3 py-2 rounded-lg hover:bg-gray-50">
                            <div>
                                <p class="text-sm font-medium text-gray-800">{{ st.nama }}</p>
                                <p class="text-xs text-gray-400 font-mono">
                                    {{ st.nip }}<span v-if="st.dikoreksi" class="ml-1 text-gray-300">· dikoreksi</span>
                                </p>
                            </div>
                            <span :class="['px-2.5 py-1 rounded-lg text-xs font-semibold', badgeStatus(st.status)]">
                                {{ statusSantriLabel(st.status) }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="py-8 text-center text-sm text-gray-400">Absensi santri belum diisi guru.</p>
                </div>
            </div>
        </Transition>

        <!-- ═══════════ MODAL KOREKSI SESI ═══════════ -->
        <div v-if="kSesi" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/50">
            <div class="bg-white rounded-2xl w-full max-w-md p-6 max-h-[92vh] overflow-y-auto">
                <h3 class="text-base font-semibold text-gray-900">Koreksi Sesi</h3>
                <p class="text-xs text-gray-400 mt-0.5 mb-4">
                    {{ kSesi.kelas }} — {{ kSesi.mapel }} · {{ formatTgl(kSesi.tanggal) }} · {{ kSesi.guru }}
                </p>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Status pelaksanaan</label>
                        <select v-model="fSesi.status" :class="fieldCls + ' w-full'">
                            <option value="terlaksana">Terlaksana</option>
                            <option value="tidak_terlaksana">Tidak terlaksana</option>
                            <option value="pengganti">Diampu pengganti</option>
                            <option value="izin">Izin (netral)</option>
                            <option value="libur">Libur (netral)</option>
                        </select>
                        <p class="text-[11px] text-gray-400 mt-1">
                            JP otomatis mengikuti status bila dikosongkan: terlaksana/izin/libur = {{ kSesi.jp_jadwal }} JP,
                            tidak terlaksana = 0.
                        </p>
                    </div>
                    <div v-if="fSesi.status === 'pengganti'">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Guru pengganti</label>
                        <select v-model.number="fSesi.digantikan_oleh" :class="fieldCls + ' w-full'">
                            <option :value="null">— pilih</option>
                            <option v-for="g in guruOpsi" :key="g.id" :value="g.id">{{ g.nama }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">JP</label>
                            <input v-model.number="fSesi.jp_terlaksana" type="number" min="0" max="20" :class="fieldCls + ' w-full'" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Jam mulai</label>
                            <input v-model="fSesi.jam_mulai_aktual" type="time" :class="fieldCls + ' w-full'" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Jam selesai</label>
                            <input v-model="fSesi.jam_selesai_aktual" type="time" :class="fieldCls + ' w-full'" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Materi / jurnal</label>
                        <textarea v-model="fSesi.materi" rows="2" :class="fieldCls + ' w-full'"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">
                            Alasan koreksi <span class="text-red-500">*</span>
                        </label>
                        <input v-model="fSesi.alasan_koreksi" type="text" placeholder="cth: guru mengajar tapi lupa absen"
                            :class="fieldCls + ' w-full'" />
                        <p class="text-[11px] text-gray-400 mt-1">Tercatat di log koreksi beserta nama Anda.</p>
                    </div>
                </div>

                <div class="flex gap-2 mt-5">
                    <button @click="kSesi = null" class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold">Batal</button>
                    <button @click="simpanKoreksiSesi" :disabled="!fSesi.alasan_koreksi || busy"
                        class="flex-1 py-2.5 rounded-xl bg-amber-600 text-white text-sm font-semibold disabled:opacity-50">
                        Simpan Koreksi
                    </button>
                </div>
            </div>
        </div>

        <!-- ═══════════ MODAL KOREKSI ABSENSI SANTRI ═══════════ -->
        <div v-if="kRoster" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/50">
            <div class="bg-white rounded-2xl w-full max-w-xl p-6 max-h-[92vh] overflow-y-auto">
                <h3 class="text-base font-semibold text-gray-900">Koreksi Absensi Santri</h3>
                <p class="text-xs text-gray-400 mt-0.5 mb-3">
                    {{ kRoster.kelas }} — {{ kRoster.mapel }} · {{ formatTgl(kRoster.tanggal) }} · {{ kRoster.guru }}
                </p>

                <p v-if="rosterLoading" class="py-8 text-center text-sm text-gray-400">Memuat daftar santri…</p>
                <template v-else>
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        <button v-for="st in STATUS" :key="st.v" @click="tandaiSemua(st.v)"
                            :class="['px-2.5 py-1 rounded-lg text-[11px] font-semibold', badgeStatus(st.v)]">
                            Semua {{ st.t }}
                        </button>
                        <span class="ml-auto text-[11px] text-gray-400">{{ rosterBerubah }} perubahan</span>
                    </div>

                    <div class="rounded-xl border border-gray-200 divide-y divide-gray-50 max-h-72 overflow-y-auto mb-3">
                        <div v-for="r in rosterRows" :key="r.santri_id" class="flex items-center justify-between gap-2 px-3 py-2">
                            <div class="min-w-0">
                                <p class="text-sm text-gray-800 truncate">{{ r.nama }}</p>
                                <p class="text-[11px] text-gray-400 truncate">
                                    <span v-if="r.sakit_health">Sakit · Smart Health</span>
                                    <span v-else-if="r.izin_disetujui">Izin disetujui</span>
                                    <span v-else-if="!r.tersimpan" class="text-amber-600">belum ada catatan</span>
                                    <span v-else-if="r.dikoreksi_oleh">dikoreksi {{ r.dikoreksi_oleh }}</span>
                                    <span v-else>{{ r.nip }}</span>
                                </p>
                            </div>
                            <div class="flex gap-1 shrink-0">
                                <button v-for="st in STATUS" :key="st.v" @click="r.status = st.v"
                                    class="w-8 py-1.5 rounded-lg text-[11px] font-bold transition"
                                    :class="r.status === st.v ? st.c + ' text-white' : 'bg-gray-100 text-gray-400'">
                                    {{ st.t.charAt(0) }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <input v-model="fRoster.alasan" type="text" placeholder="Alasan koreksi (opsional, masuk log)"
                        :class="fieldCls + ' w-full mb-2'" />
                    <label class="flex items-start gap-2.5 mb-4 cursor-pointer">
                        <input v-model="fRoster.kabari_wali" type="checkbox" class="mt-0.5 w-4 h-4 accent-indigo-600" />
                        <span class="text-[11px] text-gray-600 leading-snug">
                            Kabari wali lewat WhatsApp untuk santri yang <b>statusnya berubah</b>
                            (izin &amp; sakit dari Smart Health tetap dilewati).
                        </span>
                    </label>
                </template>

                <div class="flex gap-2">
                    <button @click="kRoster = null" class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold">Tutup</button>
                    <button @click="simpanKoreksiRoster" :disabled="busy || rosterLoading || !rosterBerubah"
                        class="flex-1 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-semibold disabled:opacity-50">
                        {{ rosterBerubah ? `Simpan ${rosterBerubah} Perubahan` : 'Belum ada perubahan' }}
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    sesi: { type: Array, default: () => [] },
    filter: { type: Object, default: () => ({}) },
    kelasOpsi: { type: Array, default: () => [] },
    guruOpsi: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
})

const f = reactive({
    dari: props.filter.dari,
    sampai: props.filter.sampai,
    kelas_id: props.filter.kelas_id ?? null,
    guru_id: props.filter.guru_id ?? null,
})

const detail = ref(null)
const busy = ref(false)

const STATUS = [
    { v: 'hadir', t: 'Hadir', c: 'bg-emerald-500' },
    { v: 'telat', t: 'Telat', c: 'bg-amber-500' },
    { v: 'izin',  t: 'Izin',  c: 'bg-sky-500' },
    { v: 'sakit', t: 'Sakit', c: 'bg-violet-500' },
    { v: 'alpha', t: 'Alpha', c: 'bg-red-500' },
]

// ── Koreksi status sesi ────────────────────────────────────────────────────
const kSesi = ref(null)
const fSesi = reactive({
    status: 'terlaksana', jp_terlaksana: null, jam_mulai_aktual: '', jam_selesai_aktual: '',
    materi: '', digantikan_oleh: null, alasan_koreksi: '',
})

function bukaKoreksiSesi(s) {
    kSesi.value = s
    Object.assign(fSesi, {
        status: s.status_sesi === 'hadir' ? 'terlaksana' : s.status_sesi,
        jp_terlaksana: s.jp ?? null,
        jam_mulai_aktual: '', jam_selesai_aktual: '',
        materi: s.materi ?? '', digantikan_oleh: null, alasan_koreksi: '',
    })
}
function simpanKoreksiSesi() {
    busy.value = true
    router.post(route('admin.smart-education.jurnal.sesi.koreksi', kSesi.value.id), {
        ...fSesi,
        jam_mulai_aktual: fSesi.jam_mulai_aktual || null,
        jam_selesai_aktual: fSesi.jam_selesai_aktual || null,
        materi: fSesi.materi || null,
        digantikan_oleh: fSesi.status === 'pengganti' ? fSesi.digantikan_oleh : null,
    }, {
        preserveScroll: true,
        onSuccess: () => { kSesi.value = null; detail.value = null },
        onFinish: () => busy.value = false,
    })
}

// ── Koreksi absensi santri ────────────────────────────────────────────────
const kRoster = ref(null)
const rosterRows = ref([])
const rosterAwal = ref({})
const rosterLoading = ref(false)
const fRoster = reactive({ alasan: '', kabari_wali: false })

const rosterBerubah = computed(() =>
    rosterRows.value.filter(r => r.status !== rosterAwal.value[r.santri_id]).length)

async function bukaKoreksiRoster(s) {
    kRoster.value = s; rosterRows.value = []; rosterLoading.value = true
    fRoster.alasan = ''; fRoster.kabari_wali = false
    try {
        const res = await fetch(route('admin.smart-education.jurnal.sesi.roster', s.id),
            { headers: { Accept: 'application/json' } })
        const d = (await res.json())?.data
        rosterRows.value = (d?.santri ?? []).map(r => ({ ...r }))
        rosterAwal.value = Object.fromEntries(rosterRows.value.map(r => [r.santri_id, r.status]))
        if (d) Object.assign(kRoster.value, { kelas: d.kelas, mapel: d.mapel, guru: d.guru })
    } catch (_) { rosterRows.value = [] } finally { rosterLoading.value = false }
}
// "Semua X" tidak menimpa santri yang izin disetujui / sakit Smart Health —
// datanya dari modul lain, bukan tebakan admin.
function tandaiSemua(v) {
    rosterRows.value.forEach(r => {
        if (v === 'hadir' && (r.izin_disetujui || r.sakit_health)) return
        r.status = v
    })
}
function simpanKoreksiRoster() {
    busy.value = true
    router.post(route('admin.smart-education.jurnal.sesi.koreksi-roster', kRoster.value.id), {
        absensi: rosterRows.value
            .filter(r => r.status !== rosterAwal.value[r.santri_id])
            .map(r => ({ santri_id: r.santri_id, status: r.status })),
        alasan: fRoster.alasan || null,
        kabari_wali: fRoster.kabari_wali,
    }, {
        preserveScroll: true,
        onSuccess: () => { kRoster.value = null; detail.value = null },
        onFinish: () => busy.value = false,
    })
}

function terapkan() {
    router.get(route('admin.smart-education.jurnal.index'), {
        dari: f.dari, sampai: f.sampai, kelas_id: f.kelas_id, guru_id: f.guru_id,
    }, { preserveState: true, preserveScroll: true })
}

function lihat(s) { detail.value = s }

function formatTgl(t) {
    if (!t) return '—'
    return new Date(t).toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })
}
function statusLabel(s) {
    return { terlaksana: 'Terlaksana', hadir: 'Hadir', libur: 'Libur', izin: 'Izin', tidak_terlaksana: 'Tidak terlaksana' }[s] ?? s
}
function statusSantriLabel(s) {
    return { hadir: 'Hadir', telat: 'Telat', izin: 'Izin', sakit: 'Sakit', alpha: 'Alpha' }[s] ?? s
}
function badgeStatus(s) {
    return {
        hadir: 'bg-emerald-50 text-emerald-700',
        telat: 'bg-amber-50 text-amber-700',
        izin: 'bg-sky-50 text-sky-700',
        sakit: 'bg-violet-50 text-violet-700',
        alpha: 'bg-red-50 text-red-700',
    }[s] ?? 'bg-gray-100 text-gray-600'
}

const fieldCls = 'px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all bg-white'
</script>

<style scoped>
.modal-enter-active { transition: all 0.2s ease; }
.modal-leave-active { transition: all 0.15s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
