<template>
    <AdminLayout title="Sinkron Hafalan" subtitle="Smart Tahfidz">

        <Head title="Sinkron Hafalan Tahfidz" />

        <div class="mb-6">
            <h2 class="text-xl font-semibold text-gray-900">Sinkron Hafalan Santri</h2>
            <p class="text-sm text-gray-400 mt-0.5">
                Catat pencapaian awal santri, koreksi bila keliru, atau reset agar bisa disinkronkan ulang.
            </p>
        </div>

        <!-- Ringkasan kondisi -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-xs text-gray-400">Santri Aktif</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ santriSyncOpsi.length }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-xs text-gray-400">Sudah Tercatat</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1">{{ jmlSudah }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-xs text-gray-400">Belum Tercatat</p>
                <p class="text-2xl font-bold text-amber-600 mt-1">{{ santriSyncOpsi.length - jmlSudah }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-xs text-gray-400">Punya Setoran</p>
                <p class="text-2xl font-bold text-sky-600 mt-1">{{ jmlSetoran }}</p>
            </div>
        </div>

        <div class="rounded-2xl bg-sky-50 border border-sky-100 px-4 py-3 mb-6 text-xs text-sky-800 leading-relaxed">
            <b>Jurnal setoran tidak pernah dihapus.</b> Reset hanya mengosongkan hasil hitungan hafalan
            supaya santri muncul lagi di daftar sinkronisasi. Begitu disinkronkan ulang, setoran yang
            sudah lulus dihitung kembali otomatis di atas data baru.
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
                        <option v-for="s in santriSyncOpsi" :key="s.id" :value="s.id">
                            {{ s.nama }}{{ s.sudah_ada_data ? ` — ${s.persen}% (${s.juz.length} juz)` : '' }}
                        </option>
                    </select>
                    <p v-if="santriTerpilih?.sudah_ada_data" class="text-xs mt-1"
                        :class="modeKoreksi ? 'text-amber-600' : 'text-gray-500'">
                        Sudah ada data: {{ santriTerpilih.total_ayat }} ayat ({{ santriTerpilih.persen }}%),
                        juz {{ santriTerpilih.juz.join(', ') || '—' }}.
                        <span v-if="santriTerpilih.ada_ziyadah"> Sudah ada setoran ziyadah lulus.</span>
                    </p>

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

            <div class="mt-4 pt-4 border-t border-gray-100">
                <label class="block text-xs font-medium text-gray-500 mb-1">Urutan hafalan (dipakai bila mengisi posisi terakhir)</label>
                <select v-model="sync.pola" :class="fieldCls" class="max-w-sm">
                    <option value="amma_maju">Juz 30 → 29 → lalu 1, 2, 3…</option>
                    <option value="belakang">Juz 30 → 29 → 28…</option>
                    <option value="depan">Juz 1 → 2 → 3…</option>
                </select>
                <p class="text-[11px] text-gray-400 mt-1">
                    Juz sebelum posisi terakhir otomatis dihitung sudah hafal menurut urutan ini.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2 mt-4">
                <button @click="simpanSync" :disabled="!syncValid || syncing || modeKoreksi"
                    class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold disabled:opacity-50 transition-colors">
                    {{ syncing ? 'Menyinkron...' : 'Sinkronkan Pencapaian' }}
                </button>

                <template v-if="modeKoreksi">
                    <button @click="koreksi(true)" :disabled="!koreksiValid || syncing"
                        class="px-5 py-2.5 rounded-xl bg-white border border-amber-300 text-amber-700 text-sm font-semibold disabled:opacity-50">
                        Pratinjau Koreksi
                    </button>
                    <button @click="koreksi(false)" :disabled="!koreksiValid || syncing"
                        class="px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold disabled:opacity-50">
                        {{ syncing ? 'Memproses...' : 'Terapkan Koreksi' }}
                    </button>
                </template>
            </div>

            <!-- Reset massal: jalan cepat agar santri bisa disinkron ulang -->
            <div class="mt-6 pt-5 border-t border-gray-100">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h4 class="text-sm font-semibold text-gray-900">Reset Pencapaian (massal)</h4>
                        <p class="text-xs text-gray-400 mt-0.5 max-w-2xl leading-relaxed">
                            Mengosongkan hasil hitungan hafalan supaya santri muncul lagi di daftar sinkronisasi.
                            <b class="text-gray-600">Jurnal setoran &amp; absensi tidak dihapus</b> — setelah disinkronkan ulang,
                            setoran yang lulus otomatis dihitung kembali di atas data baru.
                        </p>
                    </div>
                    <button @click="bukaReset = !bukaReset" type="button"
                        class="shrink-0 px-4 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">
                        {{ bukaReset ? 'Tutup' : 'Pilih Santri' }}
                    </button>
                </div>

                <div v-if="bukaReset" class="mt-3">
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        <input v-model="cariReset" type="text" placeholder="Cari nama santri…"
                            class="px-3 py-2 rounded-xl border border-gray-200 text-sm outline-none focus:border-indigo-500 w-64" />
                        <button @click="pilihSemuaReset" type="button"
                            class="px-3 py-2 rounded-xl bg-gray-100 text-gray-600 text-xs font-semibold">
                            Pilih semua yang tampil ({{ santriReset.length }})
                        </button>
                        <button @click="resetPilihan = []" type="button"
                            class="px-3 py-2 rounded-xl bg-gray-100 text-gray-600 text-xs font-semibold">Bersihkan</button>
                        <span class="text-xs text-gray-400 ml-auto">{{ resetPilihan.length }} dipilih</span>
                    </div>

                    <div class="max-h-64 overflow-y-auto rounded-xl border border-gray-200 divide-y divide-gray-50">
                        <label v-for="s in santriReset" :key="s.id"
                            class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" :value="s.id" v-model="resetPilihan" class="w-4 h-4 accent-red-600" />
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm text-gray-800 truncate">{{ s.nama }}</span>
                                <span class="block text-[11px]" :class="s.persen >= 50 ? 'text-red-500' : 'text-gray-400'">
                                    {{ s.juz.length }} juz · {{ s.total_ayat }} ayat · {{ s.persen }}%
                                    <span v-if="s.ada_ziyadah" class="text-emerald-600"> · ada setoran (akan dihitung ulang)</span>
                                </span>
                            </span>
                        </label>
                        <p v-if="!santriReset.length" class="px-3 py-6 text-center text-sm text-gray-400">
                            Tidak ada santri yang cocok.
                        </p>
                    </div>

                    <button @click="jalankanReset" :disabled="!resetPilihan.length || syncing"
                        class="mt-3 px-5 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-semibold disabled:opacity-50">
                        {{ syncing ? 'Memproses…' : `Reset ${resetPilihan.length} Santri` }}
                    </button>
                </div>
            </div>

            <div v-if="modeKoreksi" class="mt-3 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3">
                <p class="text-xs text-amber-800 leading-relaxed">
                    <b>Mode koreksi.</b> Santri ini sudah punya data, jadi tombol sinkron biasa dimatikan.
                    Koreksi menulis ulang pencapaian dari baseline di atas, lalu
                    <b>memutar ulang seluruh setoran ziyadah yang lulus</b> di atasnya —
                    riwayat setoran tidak dihapus dan tidak mungkin terhitung ganda.
                    Jalankan <b>Pratinjau</b> dulu untuk melihat sebelum → sesudah.
                </p>
            </div>
        </div>

    </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    santriSyncOpsi: { type: Array, default: () => [] },
    surahOpsi: { type: Array, default: () => [] },
})

const jmlSudah   = computed(() => props.santriSyncOpsi.filter(s => s.sudah_ada_data).length)
const jmlSetoran = computed(() => props.santriSyncOpsi.filter(s => s.ada_ziyadah).length)

const sync = reactive({ santri_id: null, juz_lulus: [], last_surah: null, last_ayat: null, pola: 'amma_maju' })
const syncing = ref(false)
function toggleJuz(j) {
    const i = sync.juz_lulus.indexOf(j)
    i >= 0 ? sync.juz_lulus.splice(i, 1) : sync.juz_lulus.push(j)
}
const surahDipilih  = computed(() => props.surahOpsi.find(s => s.nomor === sync.last_surah) || null)
const ayatMax       = computed(() => surahDipilih.value?.jumlah_ayat ?? 286)
const santriTerpilih = computed(() => props.santriSyncOpsi.find(s => s.id === sync.santri_id) || null)
const santriSudahAda = computed(() => santriTerpilih.value?.sudah_ada_data ?? false)
// Santri yang sudah punya data hanya bisa lewat Koreksi (bangun ulang), bukan seed biasa.
const modeKoreksi = computed(() => !!santriTerpilih.value && santriSudahAda.value)
const isiValid = computed(() => sync.juz_lulus.length > 0 || (sync.last_surah && sync.last_ayat))
const syncValid = computed(() => sync.santri_id && !santriSudahAda.value && isiValid.value)
const koreksiValid = computed(() => sync.santri_id && isiValid.value)

// ── Reset pencapaian (massal) ────────────────────────────────────────────
const bukaReset    = ref(false)
const cariReset    = ref('')
const resetPilihan = ref([])

// Hanya santri yang PUNYA data yang perlu direset; yang kosong tak ada gunanya.
const santriReset = computed(() => {
    const q = cariReset.value.trim().toLowerCase()
    return props.santriSyncOpsi
        .filter(s => s.sudah_ada_data)
        .filter(s => !q || s.nama.toLowerCase().includes(q))
})

const pilihSemuaReset = () => { resetPilihan.value = santriReset.value.map(s => s.id) }

function jalankanReset() {
    if (!resetPilihan.value.length) return
    if (!confirm(
        `Reset pencapaian ${resetPilihan.value.length} santri? `
        + 'Jurnal setoran dan absensi TIDAK dihapus. Yang dikosongkan hanya hasil '
        + 'hitungan hafalan, supaya santri bisa disinkronkan ulang. Setelah disinkron, '
        + 'setoran yang lulus dihitung kembali otomatis. Lanjutkan?'
    )) return

    syncing.value = true
    router.post(route('admin.smart-education.tahfidz.reset-pencapaian'),
        { santri_ids: resetPilihan.value },
        { preserveScroll: true, onSuccess: () => { resetPilihan.value = []; bukaReset.value = false },
          onFinish: () => syncing.value = false })
}

function koreksi(simulasi) {
    if (!koreksiValid.value) return
    if (!simulasi && !confirm(
        'Terapkan koreksi? Pencapaian santri ini akan ditulis ulang dari baseline di atas, '
        + 'lalu seluruh setoran ziyadah yang lulus diputar ulang di atasnya. '
        + 'Riwayat setoran tidak dihapus. Lanjutkan?'
    )) return

    syncing.value = true
    router.post(route('admin.smart-education.tahfidz.koreksi-pencapaian'),
        { ...sync, simulasi }, { preserveScroll: true, onFinish: () => syncing.value = false })
}
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
</script>
