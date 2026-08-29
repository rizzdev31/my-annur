<template>
    <AdminLayout title="Smart Tahfidz" subtitle="Smart Education">

        <Head title="Smart Tahfidz" />

        <div class="mb-6">
            <h2 class="text-xl font-semibold text-gray-900">Smart Tahfidz</h2>
            <p class="text-sm text-gray-400 mt-0.5">Pengaturan tahfidz & tahsin — jadwal, setoran, murojaah, tasmi', dan penilaian.</p>
        </div>

        <!-- Statistik -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
            <div class="bg-white rounded-2xl border border-gray-200 p-4"><p class="text-xs text-gray-400">Mapel Tahfidz</p><p class="text-2xl font-bold text-emerald-600 mt-1">{{ stat.mapel_tahfidz }}</p></div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4"><p class="text-xs text-gray-400">Mapel Tahsin</p><p class="text-2xl font-bold text-violet-600 mt-1">{{ stat.mapel_tahsin }}</p></div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4"><p class="text-xs text-gray-400">Kelas Tahfidz</p><p class="text-2xl font-bold text-indigo-600 mt-1">{{ stat.kelas_tahfidz }}</p></div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4"><p class="text-xs text-gray-400">Santri Aktif</p><p class="text-2xl font-bold text-gray-900 mt-1">{{ stat.santri }}</p></div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4"><p class="text-xs text-gray-400">Jadwal Tahfidz</p><p class="text-2xl font-bold text-sky-600 mt-1">{{ stat.jadwal_tahfidz }}</p></div>
        </div>

        <div class="grid lg:grid-cols-2 gap-6">
            <!-- Generator jadwal -->
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h3 class="text-base font-semibold text-gray-900 mb-1">Generator Jadwal Tahfidz</h3>
                <p class="text-xs text-gray-400 mb-4">Buat semua slot mingguan (Sen–Jum) sekaligus sesuai pola. Tahun ajaran aktif: <b>{{ tahunAjaranAktif || '—' }}</b></p>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Guru Pengampu</label>
                        <select v-model="gen.tenaga_pendidik_id" :class="fieldCls">
                            <option :value="null">Pilih guru...</option>
                            <option v-for="g in guruOpsi" :key="g.id" :value="g.id">{{ g.nama }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Kelas Tahfidz</label>
                            <select v-model="gen.kelas_id" :class="fieldCls">
                                <option :value="null">Pilih kelas...</option>
                                <option v-for="k in kelasTahfidzOpsi" :key="k.id" :value="k.id">{{ k.nama }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Mapel</label>
                            <select v-model="gen.mata_pelajaran_id" :class="fieldCls">
                                <option :value="null">Pilih mapel...</option>
                                <option v-for="m in mapelTahfidz" :key="m.id" :value="m.id">{{ m.nama }} ({{ m.tipe }})</option>
                            </select>
                        </div>
                    </div>
                    <button @click="generate" :disabled="!genValid || generating"
                        class="w-full py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold disabled:opacity-50 transition-colors">
                        {{ generating ? 'Membuat...' : 'Buat Jadwal Standar (8 slot)' }}
                    </button>
                    <p v-if="kelasTahfidzOpsi.length === 0 || mapelTahfidz.length === 0" class="text-xs text-amber-600">
                        Lengkapi dulu kelas tahfidz & mapel tipe tahfidz/tahsin.
                    </p>
                </div>
            </div>

            <!-- Pola sesi & jam -->
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h3 class="text-base font-semibold text-gray-900 mb-1">Pola Sesi & Jam</h3>
                <p class="text-xs text-gray-400 mb-4">Dipakai generator untuk menentukan hari & jam tiap sesi.</p>
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div><label class="block text-xs font-medium text-gray-500 mb-1">Sesi Pagi Mulai</label><input v-model="form.jam_pagi_mulai" type="time" :class="fieldCls" /></div>
                    <div><label class="block text-xs font-medium text-gray-500 mb-1">Sesi Pagi Selesai</label><input v-model="form.jam_pagi_selesai" type="time" :class="fieldCls" /></div>
                    <div><label class="block text-xs font-medium text-gray-500 mb-1">Sesi Sore Mulai</label><input v-model="form.jam_sore_mulai" type="time" :class="fieldCls" /></div>
                    <div><label class="block text-xs font-medium text-gray-500 mb-1">Sesi Sore Selesai</label><input v-model="form.jam_sore_selesai" type="time" :class="fieldCls" /></div>
                </div>
                <label class="block text-xs font-medium text-gray-500 mb-1.5">Pola per Hari</label>
                <div class="space-y-1.5 mb-3">
                    <div v-for="h in hariList" :key="h" class="flex items-center gap-3">
                        <span class="w-16 text-sm capitalize text-gray-700">{{ h }}</span>
                        <label class="flex items-center gap-1.5 text-xs cursor-pointer">
                            <input type="checkbox" :checked="isPola(h,'pagi')" @change="togglePola(h,'pagi')" class="rounded" /> Pagi
                        </label>
                        <label class="flex items-center gap-1.5 text-xs cursor-pointer">
                            <input type="checkbox" :checked="isPola(h,'sore')" @change="togglePola(h,'sore')" class="rounded" /> Sore
                        </label>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="w-24">
                        <label class="block text-xs font-medium text-gray-500 mb-1">JP / sesi</label>
                        <input v-model.number="form.jp_per_sesi" type="number" min="1" max="5" :class="fieldCls" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Setting penilaian -->
        <div class="bg-white rounded-2xl border border-gray-200 p-5 mt-6">
            <h3 class="text-base font-semibold text-gray-900 mb-1">Setting Penilaian</h3>
            <p class="text-xs text-gray-400 mb-4">Berlaku untuk tasmi' (tahfidz) & tes materi (tahsin).</p>
            <div class="flex flex-wrap items-end gap-3">
                <div class="w-28"><label class="block text-xs font-medium text-gray-500 mb-1">Nilai Min</label><input v-model.number="form.nilai_min" type="number" :class="fieldCls" /></div>
                <div class="w-28"><label class="block text-xs font-medium text-gray-500 mb-1">Nilai Maks</label><input v-model.number="form.nilai_maks" type="number" :class="fieldCls" /></div>
                <div class="w-28"><label class="block text-xs font-medium text-gray-500 mb-1">Ambang Lulus</label><input v-model.number="form.nilai_lulus" type="number" step="0.5" :class="fieldCls" /></div>
                <div class="w-40"><label class="block text-xs font-medium text-gray-500 mb-1">Vakasi Tasmi' (Rp)</label><input v-model.number="form.vakasi_tasmi" type="number" min="0" :class="fieldCls" /></div>
                <p class="text-xs text-gray-500 bg-gray-50 rounded-lg px-3 py-2 flex-1">Nilai <b>≥ {{ form.nilai_lulus }}</b> = <span class="text-emerald-600 font-semibold">Lulus</span>. Vakasi tasmi' jadi honor guru penguji (masuk tugas tambahan).</p>
            </div>
            <button @click="simpanSetting" :disabled="saving"
                class="mt-4 px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-60 transition-colors">
                {{ saving ? 'Menyimpan...' : 'Simpan Setting & Pola' }}
            </button>
        </div>

        <!-- Sinkronisasi Pencapaian Awal -->
        <div class="bg-white rounded-2xl border border-gray-200 p-5 mt-6">
            <h3 class="text-base font-semibold text-gray-900 mb-1">Sinkronisasi Pencapaian Awal</h3>
            <p class="text-xs text-gray-400 mb-4">Seed hafalan santri yang sudah berjalan sebelum sistem dipakai. Juz dicentang = <b>lulus tasmi</b>; posisi tengah (surah &amp; ayat terakhir) → juz berjalan, ayat sebelumnya dianggap <b>lulus (nilai 8 otomatis)</b>. Hanya untuk santri yang belum punya data.</p>

            <div class="grid md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Santri</label>
                    <select v-model.number="sync.santri_id" :class="fieldCls">
                        <option :value="null">Pilih santri...</option>
                        <option v-for="s in santriSyncOpsi" :key="s.id" :value="s.id" :disabled="s.sudah_ada_data">
                            {{ s.nama }}{{ s.sudah_ada_data ? ' — sudah ada data' : '' }}
                        </option>
                    </select>
                    <p v-if="santriSudahAda" class="text-xs text-amber-600 mt-1">Santri ini sudah punya data hafalan — tidak bisa disinkron.</p>

                    <label class="block text-xs font-medium text-gray-500 mb-1 mt-4">Posisi Terakhir <span class="text-gray-300">(opsional — juz yang masih berjalan)</span></label>
                    <div class="grid grid-cols-2 gap-2">
                        <select v-model.number="sync.last_surah" :class="fieldCls">
                            <option :value="null">Surah...</option>
                            <option v-for="su in surahOpsi" :key="su.nomor" :value="su.nomor">{{ su.nomor }}. {{ su.nama }}</option>
                        </select>
                        <input v-model.number="sync.last_ayat" type="number" min="1" :max="ayatMax" :class="fieldCls" placeholder="Ayat terakhir" />
                    </div>
                    <p v-if="surahDipilih" class="text-[11px] text-gray-400 mt-1">Maks {{ ayatMax }} ayat ({{ surahDipilih.nama }}).</p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Juz Lulus <span class="text-gray-300">(centang yang sudah dihafal penuh)</span></label>
                    <div class="grid grid-cols-6 gap-1.5">
                        <button type="button" v-for="j in 30" :key="j" @click="toggleJuz(j)"
                            :class="['py-1.5 rounded-lg text-xs font-semibold border transition-colors', sync.juz_lulus.includes(j) ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50']">
                            {{ j }}
                        </button>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1.5">{{ sync.juz_lulus.length }} juz dipilih sebagai lulus tasmi.</p>
                </div>
            </div>

            <button @click="simpanSync" :disabled="!syncValid || syncing"
                class="mt-4 px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold disabled:opacity-50 transition-colors">
                {{ syncing ? 'Menyinkron...' : 'Sinkronkan Pencapaian' }}
            </button>
        </div>

        <!-- Langkah setup -->
        <div class="bg-white rounded-2xl border border-gray-200 p-5 mt-6">
            <h3 class="text-base font-semibold text-gray-900 mb-4">Langkah Penyiapan</h3>
            <div class="grid sm:grid-cols-2 gap-4">
                <SetupStep :done="setup.mapel" no="1" title="Mapel Tahfidz & Tahsin"
                    desc="Tandai tipe mapel di Mata Pelajaran." :href="route('admin.master.mata-pelajaran.index')" link-label="Buka" />
                <SetupStep :done="setup.kelas" no="2" title="Kelas Tahfidz"
                    desc="Buat kelas jenis Tahfidz + isi santri." :href="route('admin.smart-education.kelas.index')" link-label="Buka" />
                <SetupStep :done="setup.santri" no="3" title="Santri & Program"
                    desc="Atur program (tahsin/tahfidz) & level." :href="route('admin.smart-education.santri.index')" link-label="Buka" />
                <SetupStep :done="setup.jadwal" no="4" title="Jadwal Tahfidz"
                    desc="Pakai Generator di atas (1 klik)." :href="route('admin.master.jadwal-mengajar.index')" link-label="Lihat Jadwal" />
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { reactive, ref, computed, h } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    setting: { type: Object, default: () => ({}) },
    mapelTahfidz: { type: Array, default: () => [] },
    guruOpsi: { type: Array, default: () => [] },
    kelasTahfidzOpsi: { type: Array, default: () => [] },
    tahunAjaranAktif: { type: String, default: null },
    stat: { type: Object, default: () => ({}) },
    setup: { type: Object, default: () => ({}) },
    santriSyncOpsi: { type: Array, default: () => [] },
    surahOpsi: { type: Array, default: () => [] },
})

const hariList = ['senin', 'selasa', 'rabu', 'kamis', 'jumat']
const hhmm = (t) => (t ? String(t).slice(0, 5) : '')

const form = reactive({
    nilai_min: props.setting.nilai_min ?? 1,
    nilai_maks: props.setting.nilai_maks ?? 10,
    nilai_lulus: props.setting.nilai_lulus ?? 7,
    jam_pagi_mulai: hhmm(props.setting.jam_pagi_mulai) || '05:00',
    jam_pagi_selesai: hhmm(props.setting.jam_pagi_selesai) || '05:45',
    jam_sore_mulai: hhmm(props.setting.jam_sore_mulai) || '15:30',
    jam_sore_selesai: hhmm(props.setting.jam_sore_selesai) || '16:15',
    jp_per_sesi: props.setting.jp_per_sesi ?? 1,
    vakasi_tasmi: props.setting.vakasi_tasmi ?? 0,
    pola_jadwal: JSON.parse(JSON.stringify(props.setting.pola_jadwal ?? {
        senin: ['sore'], selasa: ['pagi', 'sore'], rabu: ['pagi', 'sore'], kamis: ['pagi', 'sore'], jumat: ['pagi'],
    })),
})
const saving = ref(false)

function isPola(hari, sesi) { return (form.pola_jadwal[hari] ?? []).includes(sesi) }
function togglePola(hari, sesi) {
    const arr = form.pola_jadwal[hari] ?? []
    form.pola_jadwal[hari] = arr.includes(sesi) ? arr.filter(s => s !== sesi) : [...arr, sesi]
}
function simpanSetting() {
    saving.value = true
    router.put(route('admin.smart-education.tahfidz.setting'), { ...form }, {
        preserveScroll: true, onFinish: () => saving.value = false,
    })
}

// ── Generator ───────────────────────────────────────────────────────────────
const gen = reactive({ tenaga_pendidik_id: null, kelas_id: null, mata_pelajaran_id: null })
const generating = ref(false)
const genValid = computed(() => gen.tenaga_pendidik_id && gen.kelas_id && gen.mata_pelajaran_id)
function generate() {
    if (!genValid.value) return
    generating.value = true
    router.post(route('admin.smart-education.tahfidz.generate-jadwal'), { ...gen }, {
        preserveScroll: true, onFinish: () => generating.value = false,
    })
}

// ── Sinkronisasi pencapaian awal ──────────────────────────────────────────────
const sync = reactive({ santri_id: null, juz_lulus: [], last_surah: null, last_ayat: null })
const syncing = ref(false)
function toggleJuz(j) {
    const i = sync.juz_lulus.indexOf(j)
    i >= 0 ? sync.juz_lulus.splice(i, 1) : sync.juz_lulus.push(j)
}
const surahDipilih  = computed(() => props.surahOpsi.find(s => s.nomor === sync.last_surah) || null)
const ayatMax       = computed(() => surahDipilih.value?.jumlah_ayat ?? 286)
const santriSudahAda = computed(() => props.santriSyncOpsi.find(s => s.id === sync.santri_id)?.sudah_ada_data ?? false)
const syncValid = computed(() =>
    sync.santri_id && !santriSudahAda.value &&
    (sync.juz_lulus.length > 0 || (sync.last_surah && sync.last_ayat)))
function simpanSync() {
    if (!syncValid.value) return
    syncing.value = true
    router.post(route('admin.smart-education.tahfidz.sinkronisasi'), { ...sync }, {
        preserveScroll: true,
        onSuccess: () => { sync.santri_id = null; sync.juz_lulus = []; sync.last_surah = null; sync.last_ayat = null },
        onFinish: () => syncing.value = false,
    })
}

const fieldCls = 'w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all bg-white'

const SetupStep = (p) => h('div', { class: 'flex items-start gap-3' }, [
    h('div', { class: ['w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0', p.done ? 'bg-emerald-500 text-white' : 'bg-gray-100 text-gray-400'].join(' ') }, p.done ? '✓' : p.no),
    h('div', { class: 'flex-1 min-w-0' }, [
        h('p', { class: 'text-sm font-semibold text-gray-800' }, p.title),
        h('p', { class: 'text-xs text-gray-400 mt-0.5' }, p.desc),
        h(Link, { href: p.href, class: 'text-xs text-indigo-600 hover:underline mt-1 inline-block' }, () => p.linkLabel),
    ]),
])
SetupStep.props = ['done', 'no', 'title', 'desc', 'href', 'linkLabel']
</script>
