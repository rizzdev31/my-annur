<template>
    <AdminLayout :title="santri.nama" subtitle="Monitoring Tahfidz">

        <Head :title="`Tahfidz — ${santri.nama}`" />

        <div class="flex items-center gap-3 mb-5">
            <Link :href="route('admin.smart-education.tahfidz-monitoring.index')"
                class="p-2 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </Link>
            <div>
                <h2 class="text-xl font-semibold text-gray-900">{{ santri.nama }}</h2>
                <p class="text-sm text-gray-400">NIP {{ santri.nip }}
                    <span v-if="santri.tahsin_level"> · Tahsin Lv {{ santri.tahsin_level }}</span>
                </p>
            </div>
        </div>

        <!-- Ringkasan hafalan -->
        <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-5">
            <div class="flex items-end justify-between mb-2">
                <div>
                    <p class="text-xs text-gray-400">Total Hafalan</p>
                    <p class="text-3xl font-bold text-gray-900">{{ status.total_ayat }}
                        <span class="text-base font-medium text-gray-400">/ {{ status.total_ayat_quran }} ayat</span>
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-2xl font-bold text-emerald-600">{{ status.persen }}%</p>
                    <p class="text-xs text-gray-400">{{ status.juz_selesai_total }} juz selesai</p>
                </div>
            </div>
            <div class="w-full h-2.5 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full bg-gradient-to-r from-emerald-500 to-teal-500 rounded-full"
                    :style="{ width: Math.min(100, status.persen) + '%' }"></div>
            </div>
            <div class="flex flex-wrap gap-2 mt-3">
                <span v-if="status.perlu_murojaah" class="px-2.5 py-1 rounded-lg bg-amber-50 text-amber-700 text-xs font-medium">⚠️ Perlu murojaah wajib</span>
                <span v-if="status.juz_perlu_tasmi.length" class="px-2.5 py-1 rounded-lg bg-red-50 text-red-700 text-xs font-medium">🔴 Menunggu tasmi' juz {{ status.juz_perlu_tasmi.join(', ') }}</span>
                <span v-if="status.last" class="px-2.5 py-1 rounded-lg bg-gray-100 text-gray-600 text-xs font-medium">
                    Terakhir: juz {{ status.last.juz }}
                </span>
            </div>
        </div>

        <!-- Grid 30 juz -->
        <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-5">
            <h3 class="text-base font-semibold text-gray-900 mb-3">Progres per Juz</h3>
            <div class="grid grid-cols-6 sm:grid-cols-10 gap-2">
                <button v-for="j in juzGrid" :key="j.juz" type="button" @click="bukaJuz(j)"
                    :title="`Juz ${j.juz}: cakupan ${j.nyata}/${j.total} ayat — klik untuk rincian`" :class="[
                    'relative aspect-square rounded-lg flex flex-col items-center justify-center text-xs font-bold border transition hover:ring-2 hover:ring-indigo-300',
                    juzCls(j.status)
                ]">
                    <span>{{ j.juz }}</span>
                    <span v-if="j.status !== 'belum'" class="text-[9px] font-medium opacity-80">{{ pct(j) }}%</span>
                    <!-- Gerbang tasmi' tidak sejalan dengan cakupan nyata -->
                    <span v-if="j.perlu_koreksi"
                        class="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-rose-500 text-white text-[9px] flex items-center justify-center">!</span>
                    <span v-if="j.dikunci" class="absolute -bottom-1 -right-1 text-[9px]">🔒</span>
                </button>
            </div>
            <div class="flex flex-wrap gap-3 mt-4 text-xs text-gray-500">
                <span class="flex items-center gap-1.5"><i class="w-3 h-3 rounded bg-gray-100 border border-gray-200"></i> Belum</span>
                <span class="flex items-center gap-1.5"><i class="w-3 h-3 rounded bg-sky-100 border border-sky-300"></i> Berjalan</span>
                <span class="flex items-center gap-1.5"><i class="w-3 h-3 rounded bg-amber-100 border border-amber-300"></i> Selesai (perlu tasmi')</span>
                <span class="flex items-center gap-1.5"><i class="w-3 h-3 rounded bg-emerald-100 border border-emerald-400"></i> Tasmi' lulus</span>
                <span class="flex items-center gap-1.5"><i class="w-3 h-3 rounded-full bg-rose-500"></i> Perlu dibenarkan</span>
                <span class="flex items-center gap-1.5">🔒 Status dikunci admin</span>
            </div>

            <!-- Pembenaran: hitung ulang dari riwayat setoran. Dibutuhkan karena
                 penghitung lama bisa terisi ganda dari setoran yang tumpang tindih. -->
            <div class="mt-4 pt-4 border-t border-gray-100 flex flex-wrap items-center gap-3">
                <button @click="hitungUlang" :disabled="sibuk"
                    class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-semibold disabled:opacity-50">
                    {{ sibuk ? 'Menghitung…' : 'Hitung Ulang dari Riwayat Setoran' }}
                </button>
                <p v-if="perluKoreksi.length" class="text-xs text-rose-600">
                    {{ perluKoreksi.length }} juz tidak sejalan dengan cakupan nyata:
                    juz {{ perluKoreksi.map(j => j.juz).join(', ') }}
                </p>
                <p v-else class="text-xs text-gray-400">Gerbang tasmi' sejalan dengan cakupan nyata.</p>
            </div>
        </div>

        <!-- Penunjukan penguji tasmi' -->
        <div v-if="tugasTasmi.length" class="bg-white rounded-2xl border border-gray-200 p-5 mb-5">
            <h3 class="text-base font-semibold text-gray-900 mb-3">Penunjukan Penguji Tasmi'</h3>
            <div class="space-y-2">
                <div v-for="t in tugasTasmi" :key="t.id"
                    class="flex flex-wrap items-center gap-3 px-3.5 py-2.5 rounded-xl bg-gray-50">
                    <span class="px-2 py-0.5 rounded bg-white border border-gray-200 text-xs font-bold">Juz {{ t.juz }}</span>
                    <span :class="['px-2 py-0.5 rounded text-[11px] font-semibold', tugasCls(t.status)]">{{ t.status }}</span>
                    <span class="text-xs text-gray-600">Penguji: {{ t.penguji }}</span>
                    <span v-if="t.nilai" class="text-xs text-gray-500">nilai {{ t.nilai }}{{ t.lulus ? ' (lulus)' : '' }}</span>
                    <span class="text-[11px] text-gray-400">{{ t.dibuat }}</span>
                    <span v-if="t.alasan_batal" class="text-[11px] text-rose-500">{{ t.alasan_batal }}</span>
                    <button v-if="t.status !== 'dibatalkan'" @click="bukaBatal(t)"
                        class="ml-auto px-3 py-1.5 rounded-lg bg-rose-50 text-rose-700 text-xs font-semibold">
                        Batalkan
                    </button>
                </div>
            </div>
        </div>

        <!-- Jejak koreksi -->
        <div v-if="jejak.length" class="bg-white rounded-2xl border border-gray-200 p-5 mb-5">
            <h3 class="text-base font-semibold text-gray-900 mb-3">Jejak Koreksi Pencapaian</h3>
            <div class="space-y-1.5">
                <div v-for="(k, i) in jejak" :key="i" class="text-xs text-gray-600">
                    <span class="text-gray-400">{{ k.waktu }}</span> ·
                    <span class="font-semibold">{{ k.juz ? 'Juz ' + k.juz : 'Level ' + k.level }}</span>
                    {{ k.field }}: {{ k.lama }} → <span class="font-semibold">{{ k.baru }}</span>
                    — {{ k.alasan }} <span class="text-gray-400">({{ k.oleh }})</span>
                </div>
            </div>
        </div>

        <!-- Rincian + override satu juz -->
        <div v-if="juzAktif" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/50">
            <div class="bg-white rounded-2xl w-full max-w-lg p-6 max-h-[92vh] overflow-y-auto">
                <h3 class="text-base font-semibold text-gray-900">Juz {{ juzAktif.juz }}</h3>
                <p class="text-xs text-gray-400 mt-0.5 mb-4">
                    Cakupan nyata <strong>{{ juzAktif.nyata }}</strong> dari {{ juzAktif.total }} ayat<span
                        v-if="juzAktif.kurang"> · kurang {{ juzAktif.kurang }} ayat</span>
                    · penghitung tersimpan {{ juzAktif.ayat }}
                </p>

                <div v-if="juzAktif.alasan_tasmi" class="mb-4 rounded-xl bg-amber-50 border border-amber-200 px-3.5 py-2.5">
                    <p class="text-xs text-amber-800">{{ juzAktif.alasan_tasmi }}</p>
                </div>
                <div v-else class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 px-3.5 py-2.5">
                    <p class="text-xs text-emerald-800">Juz ini sah untuk ditasmi'.</p>
                </div>

                <div class="mb-3">
                    <p class="text-xs font-semibold text-gray-500 mb-1">Sudah dihafal</p>
                    <p v-if="!juzAktif.sudah_hafal.length" class="text-xs text-gray-400">— belum ada</p>
                    <ul v-else class="space-y-0.5">
                        <li v-for="(t, i) in juzAktif.sudah_hafal" :key="i" class="text-xs text-emerald-700">✓ {{ t }}</li>
                    </ul>
                </div>
                <div class="mb-4">
                    <p class="text-xs font-semibold text-gray-500 mb-1">Belum dihafal</p>
                    <p v-if="!juzAktif.belum_hafal.length" class="text-xs text-emerald-700">✓ seluruh juz sudah terhafal</p>
                    <ul v-else class="space-y-0.5">
                        <li v-for="(t, i) in juzAktif.belum_hafal" :key="i" class="text-xs text-rose-600">• {{ t }}</li>
                    </ul>
                </div>

                <div class="pt-4 border-t border-gray-100">
                    <p class="text-xs font-semibold text-gray-700 mb-2">Pembenaran manual</p>
                    <select v-model="fJuz.status" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm mb-2">
                        <option value="berjalan">Berjalan (belum selesai)</option>
                        <option value="selesai">Selesai — wajib tasmi' (munculkan tombol)</option>
                        <option value="tasmi_lulus">Tasmi' lulus</option>
                    </select>
                    <input v-model="fJuz.alasan" type="text" placeholder="Alasan koreksi (wajib)"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm mb-2" />
                    <label class="flex items-start gap-2 text-[11px] text-gray-500 mb-3">
                        <input v-model="fJuz.kunci" type="checkbox" class="mt-0.5" />
                        <span>Kunci status ini — hitungan otomatis tidak akan menimpanya lagi.
                            Perlu dicentang bila santri sudah hafal tetapi riwayat setorannya tidak lengkap.</span>
                    </label>
                    <p v-if="galat" class="mb-3 rounded-xl bg-red-50 border border-red-200 px-3.5 py-2 text-xs text-red-700">{{ galat }}</p>
                    <div class="flex flex-wrap gap-2">
                        <button @click="juzAktif = null" class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold">Tutup</button>
                        <button v-if="juzAktif.dikunci" @click="lepasKunci" :disabled="sibuk"
                            class="px-4 py-2.5 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold disabled:opacity-50">Lepas Kunci</button>
                        <button @click="simpanStatus" :disabled="sibuk"
                            class="flex-1 py-2.5 rounded-xl bg-amber-600 text-white text-sm font-semibold disabled:opacity-50">
                            {{ sibuk ? 'Menyimpan…' : 'Simpan Status' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Batalkan penunjukan -->
        <div v-if="batalTarget" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/50">
            <div class="bg-white rounded-2xl w-full max-w-sm p-6">
                <h3 class="text-base font-semibold text-gray-900">Batalkan penunjukan tasmi'?</h3>
                <p class="text-xs text-gray-500 mt-1 mb-3">
                    Juz {{ batalTarget.juz }} · penguji {{ batalTarget.penguji }}.
                    Tugas tambahannya ikut dibatalkan sehingga <strong>vakasi penguji tidak terbayar</strong>.
                    <template v-if="batalTarget.status === 'selesai'">
                        Status juz dikembalikan menjadi "selesai" agar tasmi' bisa diulang.
                    </template>
                </p>
                <input v-model="alasanBatal" type="text" placeholder="Alasan pembatalan (wajib)"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm mb-2" />
                <p v-if="galat" class="mb-2 rounded-xl bg-red-50 border border-red-200 px-3.5 py-2 text-xs text-red-700">{{ galat }}</p>
                <div class="flex gap-2 mt-2">
                    <button @click="batalTarget = null" class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold">Tidak</button>
                    <button @click="kirimBatal" :disabled="sibuk"
                        class="flex-1 py-2.5 rounded-xl bg-rose-600 text-white text-sm font-semibold disabled:opacity-50">
                        {{ sibuk ? 'Membatalkan…' : 'Ya, Batalkan' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Riwayat setoran -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-100">
                <h3 class="text-base font-semibold text-gray-900">Riwayat Setoran</h3>
            </div>
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Tanggal</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Jenis</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Rentang</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-400 uppercase tracking-wide">Ayat</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-400 uppercase tracking-wide">Juz</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-400 uppercase tracking-wide">Nilai</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide hidden md:table-cell">Guru</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="r in riwayat" :key="r.id" class="hover:bg-gray-50/40">
                        <td class="px-5 py-3 text-sm text-gray-700 whitespace-nowrap">{{ r.tanggal }}</td>
                        <td class="px-5 py-3">
                            <span :class="['px-2 py-0.5 rounded-lg text-xs font-semibold', jenisCls(r.jenis)]">{{ jenisLabel(r.jenis) }}</span>
                        </td>
                        <td class="px-5 py-3 text-sm text-gray-700">{{ r.rentang }}</td>
                        <td class="px-5 py-3 text-center text-sm font-semibold text-gray-700">{{ r.jumlah_ayat }}</td>
                        <td class="px-5 py-3 text-center text-sm text-gray-600">{{ r.juz }}</td>
                        <td class="px-5 py-3 text-center">
                            <span v-if="r.nilai != null" :class="['text-sm font-semibold', r.lulus ? 'text-emerald-600' : 'text-red-600']">{{ r.nilai }}</span>
                            <span v-else class="text-xs text-gray-300">—</span>
                        </td>
                        <td class="px-5 py-3 text-sm text-gray-600 hidden md:table-cell">{{ r.guru }}</td>
                    </tr>
                    <tr v-if="!riwayat.length">
                        <td colspan="7" class="py-12 text-center text-sm text-gray-400">Belum ada setoran.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    santri: { type: Object, default: () => ({}) },
    status: { type: Object, default: () => ({}) },
    juzGrid: { type: Array, default: () => [] },
    riwayat: { type: Array, default: () => [] },
    tugasTasmi: { type: Array, default: () => [] },
    jejak: { type: Array, default: () => [] },
})

// Persentase memakai CAKUPAN NYATA, bukan penghitung tersimpan — penghitung bisa
// terisi ganda dari setoran yang tumpang tindih sehingga menunjukkan 100% untuk
// juz yang belum khatam.
function pct(j) {
    return j.total > 0 ? Math.round((j.nyata ?? j.ayat) / j.total * 100) : 0
}
function juzCls(status) {
    return {
        belum: 'bg-gray-50 text-gray-400 border-gray-200',
        berjalan: 'bg-sky-50 text-sky-700 border-sky-300',
        selesai: 'bg-amber-50 text-amber-700 border-amber-300',
        tasmi_lulus: 'bg-emerald-50 text-emerald-700 border-emerald-400',
    }[status] ?? 'bg-gray-50 text-gray-400 border-gray-200'
}
function jenisLabel(j) {
    return { ziyadah: 'Ziyadah', murojaah_wajib: 'Murojaah Wajib', murojaah_tambahan: 'Murojaah', tasmi: "Tasmi'" }[j] ?? j
}
function jenisCls(j) {
    return {
        ziyadah: 'bg-emerald-50 text-emerald-700',
        murojaah_wajib: 'bg-amber-50 text-amber-700',
        murojaah_tambahan: 'bg-sky-50 text-sky-700',
        tasmi: 'bg-violet-50 text-violet-700',
    }[j] ?? 'bg-gray-100 text-gray-600'
}
function tugasCls(s) {
    return {
        ditugaskan: 'bg-amber-50 text-amber-700',
        selesai: 'bg-emerald-50 text-emerald-700',
        dibatalkan: 'bg-gray-100 text-gray-500 line-through',
    }[s] ?? 'bg-gray-100 text-gray-600'
}

// ── Override superadmin ─────────────────────────────────────────────────────
const sibuk = ref(false)
const galat = ref(null)
const juzAktif = ref(null)
const batalTarget = ref(null)
const alasanBatal = ref('')
const fJuz = reactive({ status: 'selesai', alasan: '', kunci: true })

const perluKoreksi = computed(() => props.juzGrid.filter(j => j.perlu_koreksi))

function bukaJuz(j) {
    if (j.status === 'belum') return      // belum ada catatan apa pun
    juzAktif.value = j
    galat.value = null
    Object.assign(fJuz, { status: j.status, alasan: '', kunci: true })
}

function bukaBatal(t) {
    batalTarget.value = t
    alasanBatal.value = ''
    galat.value = null
}

function kirim(url, data, onOk) {
    sibuk.value = true
    galat.value = null
    router.post(url, data, {
        preserveScroll: true,
        onSuccess: onOk,
        // Tanpa ini galat validasi tidak terlihat dan tombol seolah tak bekerja.
        onError: (e) => { galat.value = Object.values(e).join(' · ') },
        onFinish: () => sibuk.value = false,
    })
}

function hitungUlang() {
    kirim(route('admin.smart-education.tahfidz-monitoring.hitung-ulang', props.santri.id), {}, () => {})
}

function simpanStatus() {
    if (!fJuz.alasan.trim()) {
        galat.value = 'Alasan koreksi wajib diisi.'
        return
    }
    kirim(route('admin.smart-education.tahfidz-monitoring.status-juz', props.santri.id),
        { juz: juzAktif.value.juz, status: fJuz.status, alasan: fJuz.alasan, kunci: fJuz.kunci },
        () => { juzAktif.value = null })
}

function lepasKunci() {
    kirim(route('admin.smart-education.tahfidz-monitoring.lepas-kunci', props.santri.id),
        { juz: juzAktif.value.juz, alasan: fJuz.alasan || null },
        () => { juzAktif.value = null })
}

function kirimBatal() {
    if (!alasanBatal.value.trim()) {
        galat.value = 'Alasan pembatalan wajib diisi.'
        return
    }
    kirim(route('admin.smart-education.tahfidz-monitoring.tasmi-batal', batalTarget.value.id),
        { alasan: alasanBatal.value }, () => { batalTarget.value = null })
}
</script>
