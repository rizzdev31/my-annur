<template>
    <AdminLayout title="Perombakan Kelas" subtitle="Smart Education">

        <Head title="Perombakan Kelas" />

        <div class="flex items-start justify-between mb-5 gap-4">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Perombakan Kelas</h2>
                <p class="text-sm text-gray-400 mt-0.5">
                    Tetapkan kelas tujuan tiap santri, lalu simpan sekali. Hanya santri yang
                    Anda tetapkan yang berpindah — tidak ada yang dikeluarkan.
                </p>
            </div>
            <Link :href="route('admin.smart-education.kelas.index')"
                class="shrink-0 px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-sm text-gray-600 hover:bg-gray-50">
                ← Daftar Kelas
            </Link>
        </div>

        <!-- Kelompok kelas -->
        <div class="flex gap-2 mb-4">
            <button v-for="s in slotOpts" :key="s.val" @click="gantiSlot(s.val)" :class="[
                'px-3.5 py-1.5 rounded-xl text-sm font-medium transition-colors',
                slot === s.val ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-200'
                               : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'
            ]">{{ s.label }}</button>
        </div>

        <!-- Batas kewenangan papan ini: PINDAH, bukan naik kelas. -->
        <p v-if="slot === 'quran'" class="text-xs text-emerald-800 bg-emerald-50 border border-emerald-100 rounded-xl px-4 py-2.5 mb-3">
            Papan ini memindahkan santri <strong>di dalam pembelajaran yang sama</strong> —
            tahsin ke tahsin, tahfidz ke tahfidz. Perpindahan <strong>tahsin → tahfidz adalah
            naik kelas</strong> (ganti program) dan dikerjakan dari
            <Link :href="route('admin.smart-education.kelas.index')" class="font-semibold underline">Daftar Kelas</Link>
            lewat tombol Naik Kelas pada kelas tahsinnya.
        </p>

        <!-- Pencapaian tidak berubah: alasan utama admin ragu memindahkan santri. -->
        <p class="text-xs text-indigo-700 bg-indigo-50 border border-indigo-100 rounded-xl px-4 py-2.5 mb-5">
            Hafalan, juz, setoran, dan hasil tasmi' melekat pada santri — pindah halaqoh
            <strong>tidak mengubah pencapaiannya</strong>. Riwayat kelas lama tetap tersimpan,
            dan pengampu kelas asal maupun tujuan otomatis dikabari.
        </p>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
            <!-- ── Daftar santri ─────────────────────────────────────────── -->
            <div class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="p-4 border-b border-gray-100 space-y-3">
                    <div class="flex flex-wrap gap-2">
                        <input v-model="cari" type="search" placeholder="Cari nama atau NIS…"
                            class="flex-1 min-w-[180px] px-3.5 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100" />
                        <select v-model="filterKelas"
                            class="px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500">
                            <option value="">Semua kelas</option>
                            <option value="__tanpa__">Belum punya kelas</option>
                            <option v-for="k in kelas" :key="k.id" :value="k.id">{{ k.nama }}</option>
                        </select>
                        <select v-model="filterJk"
                            class="px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500">
                            <option value="">L & P</option>
                            <option value="L">Putra</option>
                            <option value="P">Putri</option>
                        </select>
                    </div>

                    <!-- Penetapan massal: pilih beberapa santri → satu tujuan -->
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <label class="inline-flex items-center gap-2 text-gray-600">
                            <input type="checkbox" :checked="semuaTampilTerpilih" @change="togglePilihSemua"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            Pilih semua ({{ tampil.length }})
                        </label>
                        <span v-if="terpilih.length" class="text-gray-400">· {{ terpilih.length }} dipilih</span>
                        <select v-model="tujuanMassal" :disabled="!terpilih.length"
                            class="px-3 py-1.5 rounded-xl border border-gray-200 text-sm disabled:bg-gray-50 disabled:text-gray-400">
                            <option value="">— tetapkan tujuan untuk yang dipilih —</option>
                            <option v-for="k in kelasAktif" :key="k.id" :value="k.id">{{ k.nama }} ({{ k.jenis }})</option>
                        </select>
                        <button @click="terapkanMassal" :disabled="!terpilih.length || !tujuanMassal"
                            class="px-3 py-1.5 rounded-xl bg-indigo-600 text-white text-sm font-semibold disabled:opacity-40">
                            Terapkan
                        </button>
                        <button v-if="terpilih.length" @click="terpilih = []"
                            class="px-3 py-1.5 rounded-xl border border-gray-200 text-sm text-gray-500">Bersihkan</button>
                        <span v-if="pesanMassal" class="text-[11px] text-amber-700">{{ pesanMassal }}</span>
                    </div>
                </div>

                <div class="overflow-x-auto max-h-[62vh] overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 sticky top-0 z-10">
                            <tr class="text-left text-xs text-gray-500">
                                <th class="px-4 py-2.5 w-10"></th>
                                <th class="px-3 py-2.5">Santri</th>
                                <th class="px-3 py-2.5 hidden md:table-cell">Pencapaian</th>
                                <th class="px-3 py-2.5">Kelas sekarang</th>
                                <th class="px-3 py-2.5">Pindah ke</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="s in tampil" :key="s.id"
                                :class="tujuan[s.id] ? 'bg-emerald-50/40' : ''">
                                <td class="px-4 py-2.5">
                                    <input type="checkbox" :value="s.id" v-model="terpilih"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                </td>
                                <td class="px-3 py-2.5">
                                    <p class="font-medium text-gray-800 leading-tight">{{ s.nama }}</p>
                                    <p class="text-xs text-gray-400">
                                        {{ s.nip || '—' }} · {{ s.jenis_kelamin === 'P' ? 'Putri' : 'Putra' }}
                                    </p>
                                </td>
                                <td class="px-3 py-2.5 hidden md:table-cell text-xs text-gray-500">
                                    <span v-if="slot === 'quran'">
                                        {{ s.hafalan_ayat.toLocaleString('id-ID') }} ayat
                                        <span v-if="s.juz_selesai"> · {{ s.juz_selesai }} juz</span>
                                        <span v-if="s.tahsin_level"> · tahsin L{{ s.tahsin_level }}</span>
                                    </span>
                                    <span v-else>—</span>
                                </td>
                                <td class="px-3 py-2.5">
                                    <span v-if="s.kelas_nama" class="text-gray-700">{{ s.kelas_nama }}</span>
                                    <span v-else class="text-xs text-amber-600 bg-amber-50 px-2 py-0.5 rounded">belum ada</span>
                                </td>
                                <td class="px-3 py-2.5">
                                    <select :value="tujuan[s.id] || ''" @change="setTujuan(s, $event.target.value)"
                                        class="w-full min-w-[150px] px-2.5 py-1.5 rounded-xl border text-sm focus:outline-none"
                                        :class="tujuan[s.id] ? 'border-emerald-300 bg-white text-emerald-800 font-medium'
                                                             : 'border-gray-200 text-gray-500'">
                                        <option value="">— tetap —</option>
                                        <!-- Hanya kelas sejenis: lintas program ditolak servicenya,
                                             jadi jangan ditawarkan lalu gagal saat disimpan. -->
                                        <option v-for="k in kelasUntuk(s)" :key="k.id" :value="k.id"
                                            :disabled="k.id === s.kelas_id">
                                            {{ k.nama }}{{ k.id === s.kelas_id ? ' (sekarang)' : '' }}
                                        </option>
                                    </select>
                                    <p v-if="s.kelas_jenis && !kelasUntuk(s).some(k => k.id !== s.kelas_id)"
                                        class="text-[11px] text-gray-400 mt-1">
                                        Tidak ada kelas {{ s.kelas_jenis }} lain.
                                    </p>
                                </td>
                            </tr>
                            <tr v-if="!tampil.length">
                                <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-400">
                                    Tidak ada santri yang cocok dengan filter.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── Ringkasan & simpan ────────────────────────────────────── -->
            <div class="space-y-5">
                <div class="bg-white rounded-2xl border border-gray-200 p-4">
                    <h3 class="text-sm font-semibold text-gray-800 mb-1">Rencana perpindahan</h3>
                    <p class="text-xs text-gray-400 mb-3">
                        {{ rencana.length }} santri akan dipindah
                        <span v-if="rencana.length"> ke {{ kelasTujuanDipakai }} kelas</span>.
                    </p>

                    <div v-if="!rencana.length" class="text-sm text-gray-400 py-6 text-center">
                        Belum ada yang ditetapkan.
                    </div>

                    <div v-else class="space-y-3 max-h-[38vh] overflow-y-auto">
                        <div v-for="g in rencanaPerTujuan" :key="g.kelas_id"
                            class="rounded-xl border border-gray-100 bg-gray-50/60 px-3 py-2.5">
                            <p class="text-xs font-bold text-gray-700 mb-1.5">
                                → {{ g.nama }} <span class="text-gray-400 font-normal">({{ g.items.length }})</span>
                            </p>
                            <ul class="space-y-1">
                                <li v-for="it in g.items" :key="it.id"
                                    class="flex items-center justify-between gap-2 text-xs">
                                    <span class="text-gray-700 truncate">{{ it.nama }}</span>
                                    <span class="shrink-0 text-gray-400">
                                        dari {{ it.kelas_nama || '—' }}
                                        <button @click="setTujuan(it, '')" class="ml-1.5 text-red-500 font-bold">✕</button>
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 p-4 space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Tanggal berlaku</label>
                        <input v-model="form.tanggal" type="date"
                            class="w-full px-3.5 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500" />
                        <p class="text-[11px] text-gray-400 mt-1">
                            Dipakai sebagai tanggal keluar kelas lama & masuk kelas baru.
                        </p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Keterangan (opsional)</label>
                        <input v-model="form.keterangan" type="text" maxlength="150"
                            placeholder="mis. Perombakan halaqoh semester 2"
                            class="w-full px-3.5 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500" />
                    </div>

                    <button @click="konfirmasi = true" :disabled="!rencana.length || loading"
                        class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-40">
                        Simpan perombakan ({{ rencana.length }})
                    </button>
                    <p v-if="err" class="text-xs text-red-600">{{ err }}</p>
                </div>
            </div>
        </div>

        <!-- Konfirmasi: perpindahan menyentuh halaqoh orang lain, jadi tidak sunyi. -->
        <div v-if="konfirmasi" class="fixed inset-0 z-50 flex items-center justify-center p-4"
            style="background: rgba(0,0,0,.45)">
            <div class="w-full max-w-md bg-white rounded-2xl p-5">
                <h3 class="text-base font-semibold text-gray-900">Pindahkan {{ rencana.length }} santri?</h3>
                <p class="text-xs text-gray-500 mt-1 mb-4">
                    Keanggotaan kelas lama ditutup per {{ form.tanggal || 'hari ini' }} dan riwayatnya
                    tetap tersimpan. Pencapaian hafalan tidak berubah. Pengampu kelas asal & tujuan
                    akan menerima pemberitahuan.
                </p>
                <ul class="text-xs text-gray-600 space-y-1 mb-4 max-h-40 overflow-y-auto">
                    <li v-for="g in rencanaPerTujuan" :key="g.kelas_id">
                        <b>{{ g.items.length }}</b> → {{ g.nama }}
                    </li>
                </ul>
                <div class="flex gap-2">
                    <button @click="konfirmasi = false"
                        class="flex-1 py-2.5 rounded-xl bg-gray-100 text-gray-600 text-sm font-semibold">Batal</button>
                    <button @click="simpan" :disabled="loading"
                        class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-50">
                        {{ loading ? 'Memproses…' : 'Ya, pindahkan' }}
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    slot: { type: String, default: 'quran' },
    kelas: { type: Array, default: () => [] },
    santri: { type: Array, default: () => [] },
})

const slotOpts = [
    { val: 'quran', label: 'Tahfidz & Tahsin' },
    { val: 'sekolah', label: 'Sekolah' },
    { val: 'pesantren', label: 'Pesantren' },
]

const cari = ref('')
const filterKelas = ref('')
const filterJk = ref('')
const terpilih = ref([])
const tujuanMassal = ref('')
const konfirmasi = ref(false)
const loading = ref(false)
const err = ref('')

// santri_id → kelas_id tujuan. Hanya isian di sini yang dikirim ke server.
const tujuan = reactive({})

const form = reactive({ tanggal: new Date().toISOString().slice(0, 10), keterangan: '' })

const kelasAktif = computed(() => props.kelas.filter((k) => k.is_aktif))
const pesanMassal = ref('')

// Tujuan yang SAH untuk satu santri: hanya kelas sejenis dengan kelasnya
// sekarang (pindah di dalam pembelajaran yang sama). Santri yang belum punya
// kelas di slot ini boleh ditempatkan ke mana saja — itu penempatan, bukan
// pindah, dan tidak melompati program apa pun.
function kelasUntuk(s) {
    if (!s.kelas_jenis) return kelasAktif.value
    return kelasAktif.value.filter((k) => k.jenis === s.kelas_jenis)
}

const tampil = computed(() => {
    const q = cari.value.trim().toLowerCase()
    return props.santri.filter((s) => {
        if (q && !`${s.nama} ${s.nip || ''}`.toLowerCase().includes(q)) return false
        if (filterJk.value && s.jenis_kelamin !== filterJk.value) return false
        if (filterKelas.value === '__tanpa__' && s.kelas_id) return false
        if (filterKelas.value && filterKelas.value !== '__tanpa__'
            && Number(filterKelas.value) !== s.kelas_id) return false
        return true
    })
})

const semuaTampilTerpilih = computed(() =>
    tampil.value.length > 0 && tampil.value.every((s) => terpilih.value.includes(s.id)))

function togglePilihSemua() {
    terpilih.value = semuaTampilTerpilih.value ? [] : tampil.value.map((s) => s.id)
}

function setTujuan(s, kelasId) {
    const id = Number(kelasId)
    // "tetap" atau memilih kelasnya sendiri = tidak ada perpindahan.
    if (!id || id === s.kelas_id) { delete tujuan[s.id]; return false }
    // Lintas program ditolak servicenya; cegah di sini supaya tidak terkumpul
    // lalu menggagalkan seluruh perombakan saat disimpan.
    if (!kelasUntuk(s).some((k) => k.id === id)) return false
    tujuan[s.id] = id
    return true
}

function terapkanMassal() {
    const id = Number(tujuanMassal.value)
    if (!id) return
    const dipilih = props.santri.filter((s) => terpilih.value.includes(s.id))
    let kena = 0
    dipilih.forEach((s) => { if (setTujuan(s, id)) kena++ })

    const lewat = dipilih.length - kena
    const kelas = props.kelas.find((k) => k.id === id)
    pesanMassal.value = lewat
        ? `${kena} ditetapkan, ${lewat} dilewati (program berbeda dari ${kelas?.jenis ?? 'kelas tujuan'}` +
          ` — tahsin → tahfidz lewat Naik Kelas).`
        : ''
    terpilih.value = []
    tujuanMassal.value = ''
}

const rencana = computed(() =>
    props.santri.filter((s) => tujuan[s.id]).map((s) => ({ ...s, tujuan_id: tujuan[s.id] })))

const rencanaPerTujuan = computed(() => {
    const peta = new Map()
    rencana.value.forEach((s) => {
        if (!peta.has(s.tujuan_id)) {
            peta.set(s.tujuan_id, {
                kelas_id: s.tujuan_id,
                nama: props.kelas.find((k) => k.id === s.tujuan_id)?.nama || `Kelas #${s.tujuan_id}`,
                items: [],
            })
        }
        peta.get(s.tujuan_id).items.push(s)
    })
    return [...peta.values()]
})

const kelasTujuanDipakai = computed(() => rencanaPerTujuan.value.length)

function gantiSlot(val) {
    if (val === props.slot) return
    if (rencana.value.length
        && !confirm('Rencana perpindahan yang belum disimpan akan hilang. Lanjut?')) return
    router.get(route('admin.smart-education.kelas.perombakan'), { slot: val },
        { preserveScroll: true })
}

function simpan() {
    loading.value = true
    err.value = ''
    router.post(route('admin.smart-education.kelas.perombakan.simpan'), {
        pindah: rencana.value.map((s) => ({ santri_id: s.id, kelas_id: s.tujuan_id })),
        tanggal: form.tanggal || null,
        keterangan: form.keterangan || null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            Object.keys(tujuan).forEach((k) => delete tujuan[k])
            terpilih.value = []
            konfirmasi.value = false
        },
        // Inertia mengembalikan 303 + errors; tanpa ini kegagalan tidak terlihat.
        onError: (e) => {
            err.value = e.pindah || Object.values(e)[0] || 'Gagal menyimpan perombakan.'
            konfirmasi.value = false
        },
        onFinish: () => { loading.value = false },
    })
}
</script>
