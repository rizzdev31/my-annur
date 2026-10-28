<template>
    <AdminLayout title="Data Gaji" subtitle="Smart Payroll">

        <Head title="Data Gaji" />

        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Data Gaji</h2>
                <p class="text-sm text-gray-400 mt-0.5">Pilih periode untuk melihat dan mengelola data penggajian</p>
            </div>
            <Link :href="route('admin.smart-payroll.periode.create')"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm shadow-indigo-200">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Buat Periode
            </Link>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <Link v-for="p in periodes.data" :key="p.id" :href="route('admin.smart-payroll.penggajian.detail', p.id)"
                :class="[
                    'block bg-white rounded-2xl border transition-all hover:shadow-md',
                    p.status === 'dibayar' ? 'border-emerald-200' : p.status === 'selesai' ? 'border-amber-200' : 'border-gray-200 hover:border-indigo-200'
                ]">
                <div class="px-5 pt-5 pb-4">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ p.nama }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ p.tanggal_mulai }} – {{ p.tanggal_selesai }}</p>
                        </div>
                        <span :class="['px-2.5 py-1 rounded-lg text-xs font-semibold', badgeStatus(p.status).class]">
                            {{ badgeStatus(p.status).label }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-gray-50 rounded-xl px-3 py-2.5">
                            <p class="text-xs text-gray-400">Guru</p>
                            <p class="text-lg font-bold text-gray-800 mt-0.5">{{ p.jumlah_guru }}</p>
                        </div>
                        <div class="bg-indigo-50 rounded-xl px-3 py-2.5">
                            <p class="text-xs text-indigo-500">Total Gaji</p>
                            <p class="text-sm font-bold text-indigo-700 mt-0.5">
                                {{ p.total_gaji > 0 ? formatRp(p.total_gaji) : '—' }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="px-5 py-3 border-t border-gray-50 flex items-center justify-between">
                    <p class="text-xs text-gray-400">
                        {{ p.dikunci_pada ? `Dikunci: ${p.dikunci_pada}` : 'Belum dikunci' }}
                    </p>
                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </div>
            </Link>
        </div>

        <div v-if="!periodes.data?.length" class="bg-white rounded-2xl border border-gray-200 py-16 text-center">
            <p class="text-sm text-gray-400">Belum ada periode penggajian.</p>
            <Link :href="route('admin.smart-payroll.periode.create')"
                class="inline-block mt-3 text-sm text-indigo-600 font-medium hover:underline">
                Buat periode pertama →
            </Link>
        </div>

        <!-- Penanda tangan slip gaji -->
        <div v-if="bolehUbahTtd" class="bg-white rounded-2xl border border-gray-200 p-5 mt-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 class="text-sm font-bold text-gray-800">Tanda Tangan Slip Gaji</h3>
                    <p class="text-xs text-gray-500 mt-1 max-w-xl">
                        Nama &amp; gambar ini yang tercetak di bagian bawah setiap slip.
                        Biarkan nama <b>kosong</b> agar sistem memakai pemegang jabatan
                        {{ ttdForm.jabatan || 'Bendahara' }} yang sedang aktif — jadi saat
                        bendaharanya berganti, slip ikut berubah sendiri.
                    </p>
                </div>
                <div class="text-right shrink-0">
                    <p class="text-[11px] text-gray-400">Tercetak di slip</p>
                    <p class="text-sm font-bold text-gray-800 uppercase">
                        {{ ttd.nama_tercetak || '(belum ada nama)' }}
                    </p>
                    <span v-if="ttd.otomatis"
                        class="inline-block mt-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700">
                        otomatis dari jabatan
                    </span>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-5 mt-4">
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                            Nama penanda tangan <span class="text-gray-400">(opsional)</span>
                        </label>
                        <input v-model="ttdForm.nama" type="text" maxlength="150"
                            :placeholder="ttd.nama_tercetak || 'Kosongkan untuk otomatis'"
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                        <p class="text-[11px] text-gray-400 mt-1">
                            Isi hanya bila ejaan resminya berbeda dari nama akun.
                        </p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Label jabatan</label>
                        <input v-model="ttdForm.jabatan" type="text" maxlength="100" placeholder="Bendahara"
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                        Gambar tanda tangan <span class="text-gray-400">(PNG)</span>
                    </label>
                    <div class="rounded-xl border-2 border-dashed border-gray-200 p-4 text-center">
                        <img v-if="pratinjauTtd || ttd.gambar" :src="pratinjauTtd || ttd.gambar"
                            alt="Tanda tangan" class="h-20 mx-auto object-contain" />
                        <p v-else class="text-xs text-gray-400 py-6">Belum ada gambar tanda tangan.</p>

                        <input ref="inputTtd" type="file" accept="image/png" class="hidden"
                            @change="pilihTtd" />
                        <div class="flex items-center justify-center gap-2 mt-3">
                            <button @click="$refs.inputTtd.click()"
                                class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700">
                                {{ (pratinjauTtd || ttd.gambar) ? 'Ganti PNG' : 'Pilih PNG' }}
                            </button>
                            <button v-if="ttd.gambar && !ttdForm.berkas" @click="hapusTtd"
                                class="px-3 py-1.5 text-xs font-semibold rounded-lg text-red-600 hover:bg-red-50">
                                Hapus
                            </button>
                            <button v-if="ttdForm.berkas" @click="batalPilih"
                                class="px-3 py-1.5 text-xs font-medium rounded-lg text-gray-500 hover:bg-gray-50">
                                Batal pilih
                            </button>
                        </div>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1.5">
                        PNG berlatar transparan, maksimal 2 MB. JPG ditolak karena akan
                        mencetak kotak putih di atas garis slip.
                    </p>
                </div>
            </div>

            <p v-if="ttdGalat" class="mt-3 text-sm text-red-600">{{ ttdGalat }}</p>

            <div class="flex justify-end mt-4">
                <button @click="simpanTtd" :disabled="menyimpanTtd"
                    class="px-4 py-2 text-sm font-semibold rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-60">
                    {{ menyimpanTtd ? 'Menyimpan…' : 'Simpan Penanda Tangan' }}
                </button>
            </div>
        </div>

    </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    periodes: { type: Object, default: () => ({ data: [] }) },
    ttd: { type: Object, default: () => ({}) },
})

// ── Penanda tangan slip ─────────────────────────────────────────────────────
// Nama sengaja boleh kosong: slip lalu memakai pemegang jabatan penanda tangan
// yang aktif, jadi pergantian bendahara tidak perlu diingat di sini.
const bolehUbahTtd = computed(() => usePage().props.auth?.user?.role === 'super_admin')
const ttdForm = reactive({
    nama: props.ttd.nama_tersimpan ?? '',
    jabatan: props.ttd.jabatan ?? 'Bendahara',
    berkas: null,
})
const pratinjauTtd = ref(null)
const ttdGalat = ref('')
const menyimpanTtd = ref(false)
const inputTtd = ref(null)

function pilihTtd(e) {
    const f = e.target.files?.[0]
    if (!f) return
    // Dijaga di sini juga supaya penolakan terasa langsung, bukan setelah unggah.
    if (f.type !== 'image/png') {
        ttdGalat.value = 'Berkas harus PNG agar latarnya transparan.'
        e.target.value = ''
        return
    }
    if (f.size > 2 * 1024 * 1024) {
        ttdGalat.value = 'Berkas maksimal 2 MB.'
        e.target.value = ''
        return
    }
    ttdGalat.value = ''
    ttdForm.berkas = f
    pratinjauTtd.value = URL.createObjectURL(f)
}

function batalPilih() {
    ttdForm.berkas = null
    pratinjauTtd.value = null
    if (inputTtd.value) inputTtd.value.value = ''
}

function kirimTtd(muatan) {
    menyimpanTtd.value = true
    ttdGalat.value = ''
    router.post(route('admin.smart-payroll.penggajian.ttd'), muatan, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => batalPilih(),
        // Inertia membalas 303 + errors; tanpa onError galatnya hilang diam-diam.
        onError: (e) => { ttdGalat.value = Object.values(e)[0] ?? 'Gagal menyimpan.' },
        onFinish: () => { menyimpanTtd.value = false },
    })
}

function simpanTtd() {
    kirimTtd({
        nama_bendahara: ttdForm.nama?.trim() || null,
        jabatan_penanda_tangan: ttdForm.jabatan?.trim() || 'Bendahara',
        ttd: ttdForm.berkas,
    })
}

function hapusTtd() {
    if (!window.confirm('Hapus gambar tanda tangan dari slip?')) return
    kirimTtd({
        nama_bendahara: ttdForm.nama?.trim() || null,
        jabatan_penanda_tangan: ttdForm.jabatan?.trim() || 'Bendahara',
        hapus_ttd: true,
    })
}

function badgeStatus(s) {
    return {
        draft: { label: 'Draft', class: 'bg-gray-100 text-gray-600' },
        selesai: { label: 'Selesai', class: 'bg-amber-50 text-amber-700' },
        dibayar: { label: 'Dibayar', class: 'bg-emerald-50 text-emerald-700' },
        proses: { label: 'Proses', class: 'bg-blue-50 text-blue-700' },
    }[s] ?? { label: s, class: 'bg-gray-100 text-gray-600' }
}

function formatRp(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID') }
</script>