<template>
    <AdminLayout :title="isEdit ? 'Edit Setting Potongan' : 'Tambah Setting Potongan'" subtitle="Smart Payroll">

        <Head :title="isEdit ? 'Edit Potongan' : 'Tambah Potongan'" />

        <div class="flex items-center gap-4 mb-6">
            <Link :href="route('admin.smart-payroll.setting-potongan.index')"
                class="p-2 rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </Link>
            <div>
                <h2 class="text-xl font-semibold text-gray-900">
                    {{ isEdit ? 'Edit Setting Potongan' : 'Tambah Setting Potongan' }}
                </h2>
                <p class="text-sm text-gray-400 mt-0.5">Potongan akan otomatis dihitung saat generate gaji</p>
            </div>
        </div>

        <div class="max-w-2xl space-y-4">

            <!-- ── Identitas ─────────────────────────────────────────────── -->
            <div class="bg-white rounded-2xl border border-gray-200 p-5 space-y-4">
                <h3 class="text-sm font-semibold text-gray-800">Identitas Potongan</h3>

                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2 md:col-span-1">
                        <label class="label">Nama Potongan <span class="text-red-500">*</span></label>
                        <input v-model="form.nama" type="text" placeholder="cth: Potongan BPJS Ketenagakerjaan"
                            :class="inputCls(form.errors.nama)" />
                        <ErrMsg :e="form.errors.nama" />
                    </div>
                    <div>
                        <label class="label">Kode <span class="text-red-500">*</span></label>
                        <input v-model="form.kode" type="text" placeholder="BPJS_TK" :class="inputCls(form.errors.kode)"
                            style="text-transform:uppercase" @input="form.kode = form.kode.toUpperCase()" />
                        <p class="text-xs text-gray-400 mt-1">Unik, maks 20 karakter</p>
                        <ErrMsg :e="form.errors.kode" />
                    </div>
                </div>

                <div>
                    <label class="label">Deskripsi</label>
                    <textarea v-model="form.deskripsi" rows="2" placeholder="Keterangan tambahan..."
                        :class="inputCls() + ` resize-none`" />
                </div>

                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl cursor-pointer"
                    @click="form.tampil_di_slip = !form.tampil_di_slip">
                    <div>
                        <p class="text-sm font-medium text-gray-800">Tampil di Slip Gaji</p>
                        <p class="text-xs text-gray-400 mt-0.5">Jika dimatikan, potongan tetap dihitung tapi tidak
                            terlihat di slip</p>
                    </div>
                    <div :class="[`relative rounded-full transition-colors shrink-0`,
                        form.tampil_di_slip ? `bg-indigo-600` : `bg-gray-300`]" style="height:22px;width:40px">
                        <span :class="[`absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform duration-200`,
                            form.tampil_di_slip ? `translate-x-5` : `translate-x-0.5`]" />
                    </div>
                </div>
            </div>

            <!-- ── Kategori ─────────────────────────────────────────────── -->
            <div class="bg-white rounded-2xl border border-gray-200 p-5 space-y-4">
                <h3 class="text-sm font-semibold text-gray-800">Kategori & Pemicu</h3>

                <!-- Kategori -->
                <div>
                    <label class="label">Kategori <span class="text-red-500">*</span></label>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                        <button v-for="k in kategoriOpts" :key="k.value" type="button"
                            @click="form.kategori = k.value; syncPemicu()" :class="[
                                'flex flex-col gap-1 px-3 py-3 rounded-xl border-2 text-left transition-all',
                                form.kategori === k.value
                                    ? `border-${k.color}-400 bg-${k.color}-50`
                                    : 'border-gray-200 hover:border-gray-300'
                            ]">
                            <span class="text-xl leading-none">{{ k.icon }}</span>
                            <span class="text-xs font-semibold"
                                :class="form.kategori === k.value ? `text-${k.color}-700` : `text-gray-700`">
                                {{ k.label }}
                            </span>
                            <span class="text-xs leading-snug"
                                :class="form.kategori === k.value ? `text-${k.color}-500` : `text-gray-400`">
                                {{ k.desc }}
                            </span>
                        </button>
                    </div>
                    <ErrMsg :e="form.errors.kategori" />
                </div>

                <!-- Pemicu -->
                <div>
                    <label class="label">Tipe Pemicu <span class="text-red-500">*</span></label>
                    <div class="space-y-2">
                        <label v-for="p in pemicuFiltered" :key="p.value" :class="[
                            'flex items-start gap-3 px-4 py-3 rounded-xl border-2 cursor-pointer transition-all',
                            form.tipe_pemicu === p.value
                                ? 'border-indigo-400 bg-indigo-50'
                                : 'border-gray-200 hover:border-gray-300'
                        ]">
                            <input type="radio" :value="p.value" v-model="form.tipe_pemicu"
                                class="mt-0.5 text-indigo-600 shrink-0" />
                            <div>
                                <p class="text-sm font-medium"
                                    :class="form.tipe_pemicu === p.value ? `text-indigo-700` : `text-gray-700`">
                                    {{ p.label }}
                                </p>
                                <p class="text-xs mt-0.5"
                                    :class="form.tipe_pemicu === p.value ? `text-indigo-500` : `text-gray-400`">
                                    {{ p.desc }}
                                </p>
                            </div>
                        </label>
                    </div>
                    <ErrMsg :e="form.errors.tipe_pemicu" />
                </div>
            </div>

            <!-- ── Nominal ─────────────────────────────────────────────── -->
            <div class="bg-white rounded-2xl border border-gray-200 p-5 space-y-4">
                <h3 class="text-sm font-semibold text-gray-800">Nominal Potongan</h3>

                <div class="grid grid-cols-2 gap-3">
                    <button type="button" @click="form.tipe_nominal = 'nominal'" :class="[
                        'py-3 px-4 rounded-xl border-2 text-left transition-all',
                        form.tipe_nominal === 'nominal'
                            ? 'border-indigo-500 bg-indigo-50'
                            : 'border-gray-200 hover:border-gray-300'
                    ]">
                        <p class="text-sm font-semibold"
                            :class="form.tipe_nominal === `nominal` ? `text-indigo-700` : `text-gray-700`">
                            Nominal (Rp)
                        </p>
                        <p class="text-xs mt-0.5"
                            :class="form.tipe_nominal === `nominal` ? `text-indigo-500` : `text-gray-400`">
                            Jumlah tetap dalam rupiah
                        </p>
                    </button>
                    <button type="button" @click="form.tipe_nominal = 'persen'" :class="[
                        'py-3 px-4 rounded-xl border-2 text-left transition-all',
                        form.tipe_nominal === 'persen'
                            ? 'border-indigo-500 bg-indigo-50'
                            : 'border-gray-200 hover:border-gray-300'
                    ]">
                        <p class="text-sm font-semibold"
                            :class="form.tipe_nominal === `persen` ? `text-indigo-700` : `text-gray-700`">
                            Persentase (%)
                        </p>
                        <p class="text-xs mt-0.5"
                            :class="form.tipe_nominal === `persen` ? `text-indigo-500` : `text-gray-400`">
                            % dari gaji pokok
                        </p>
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="label">
                            {{ form.tipe_nominal === 'persen' ? 'Persentase (%)' : 'Nominal (Rp)' }}
                            <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-gray-400">
                                {{ form.tipe_nominal === 'persen' ? '%' : 'Rp' }}
                            </span>
                            <input v-model.number="form.nominal" type="number" min="0"
                                :step="form.tipe_nominal === 'persen' ? '0.01' : '1000'"
                                :class="inputCls(form.errors.nominal) + ` pl-10`" />
                        </div>
                        <p v-if="form.tipe_nominal === 'nominal' && form.nominal > 0"
                            class="text-xs text-indigo-600 mt-1">
                            {{ formatRp(form.nominal) }}
                            <template v-if="form.tipe_pemicu === 'per_keterlambatan'"> per kejadian terlambat</template>
                            <template v-else-if="form.tipe_pemicu === 'per_alfa'"> per hari alfa</template>
                            <template v-else-if="form.tipe_pemicu === 'per_menit_keterlambatan'"> per menit terlambat</template>
                            <template v-else-if="form.tipe_pemicu === 'per_sesi_tidak_mengajar'"> per sesi tidak mengajar</template>
                            <template v-else> per bulan</template>
                        </p>
                        <ErrMsg :e="form.errors.nominal" />
                    </div>
                    <div v-if="form.tipe_nominal === 'persen'">
                        <label class="label">Batas Maksimal (Rp)</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-gray-400">Rp</span>
                            <input v-model.number="form.nominal_maksimal" type="number" min="0"
                                placeholder="Kosongkan = tidak ada batas" :class="inputCls() + ` pl-10`" />
                        </div>
                    </div>
                </div>

                <!-- Preview kalkulasi -->
                <div v-if="form.nominal > 0"
                    class="p-3 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-600">
                    <p class="font-semibold mb-1">📊 Preview:</p>
                    <p v-if="form.tipe_pemicu === 'per_keterlambatan'">
                        Contoh: 3× terlambat → potongan <strong>{{ formatRp(form.nominal * 3) }}</strong>
                    </p>
                    <p v-else-if="form.tipe_pemicu === 'per_alfa'">
                        Contoh: 2 hari alfa → potongan <strong>{{ formatRp(form.nominal * 2) }}</strong>
                    </p>
                    <p v-else-if="form.tipe_pemicu === 'per_menit_keterlambatan'">
                        Contoh: 45 menit terlambat kumulatif → potongan <strong>{{ formatRp(form.nominal * 45) }}</strong>
                    </p>
                    <p v-else-if="form.tipe_pemicu === 'persen_gaji'">
                        Contoh: gaji pokok Rp 3.000.000 → potongan
                        <strong>{{ formatRp(3000000 * form.nominal / 100) }}</strong>
                        ({{ form.nominal }}%)
                    </p>
                    <p v-else>
                        Dipotong <strong>{{ formatRp(form.nominal) }}</strong> setiap bulan
                    </p>
                </div>
            </div>

            <!-- ── Lingkup ──────────────────────────────────────────────── -->
            <div class="bg-white rounded-2xl border border-gray-200 p-5 space-y-4">
                <h3 class="text-sm font-semibold text-gray-800">Lingkup Berlaku</h3>

                <div class="grid grid-cols-2 gap-2">
                    <button v-for="l in lingkupOpts" :key="l.value" type="button" @click="form.lingkup = l.value"
                        :class="[
                            'py-2.5 px-4 rounded-xl border-2 text-sm font-medium text-left transition-all',
                            form.lingkup === l.value
                                ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                : 'border-gray-200 text-gray-600 hover:border-gray-300'
                        ]">
                        {{ l.label }}
                    </button>
                </div>

                <!-- Pilih Jabatan -->
                <Transition name="slide">
                    <div v-if="form.lingkup === 'per_jabatan' || form.lingkup === 'custom'">
                        <label class="label">Pilih Jabatan</label>
                        <div class="grid grid-cols-2 gap-1.5 max-h-40 overflow-y-auto">
                            <label v-for="j in jabatan" :key="j.id" :class="[
                                'flex items-center gap-2.5 px-3 py-2 rounded-xl border cursor-pointer transition-all text-sm',
                                form.jabatan_ids.includes(j.id)
                                    ? 'border-indigo-400 bg-indigo-50'
                                    : 'border-gray-200 hover:border-gray-300'
                            ]">
                                <input type="checkbox" :value="j.id" v-model="form.jabatan_ids"
                                    class="rounded text-indigo-600 shrink-0" />
                                <span class="truncate text-gray-700">{{ j.nama_jabatan }}</span>
                            </label>
                        </div>
                    </div>
                </Transition>

                <!-- Pilih Individu -->
                <Transition name="slide">
                    <div v-if="form.lingkup === 'per_individu' || form.lingkup === 'custom'">
                        <label class="label">Pilih Individu</label>
                        <input v-model="searchGuru" type="text" placeholder="Cari nama guru..."
                            class="w-full px-4 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 mb-2 bg-white" />
                        <div class="max-h-40 overflow-y-auto space-y-1.5">
                            <label v-for="g in guruFiltered" :key="g.id" :class="[
                                'flex items-center gap-2.5 px-3 py-2 rounded-xl border cursor-pointer transition-all text-sm',
                                form.tenaga_pendidik_ids.includes(g.id)
                                    ? 'border-indigo-400 bg-indigo-50'
                                    : 'border-gray-200 hover:border-gray-300'
                            ]">
                                <input type="checkbox" :value="g.id" v-model="form.tenaga_pendidik_ids"
                                    class="rounded text-indigo-600 shrink-0" />
                                <div class="min-w-0">
                                    <p class="text-sm text-gray-700 truncate">{{ g.nama }}</p>
                                    <p class="text-xs text-gray-400">{{ g.jabatan }}</p>
                                </div>
                            </label>
                        </div>
                        <p class="text-xs text-gray-400 mt-1.5">
                            {{ form.tenaga_pendidik_ids.length }} individu dipilih
                        </p>
                    </div>
                </Transition>

                <p v-if="form.lingkup === 'semua'" class="text-sm text-emerald-600 flex items-center gap-1.5">
                    <span class="w-4 h-4 rounded-full bg-emerald-100 flex items-center justify-center text-xs">✓</span>
                    Berlaku untuk semua tenaga pendidik aktif
                </p>
            </div>

            <!-- Submit -->
            <div class="flex gap-3">
                <Link :href="route('admin.smart-payroll.setting-potongan.index')"
                    class="flex-1 py-2.5 text-center rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50">
                    Batal
                </Link>
                <button @click="submit" :disabled="form.processing"
                    class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-60 transition-colors">
                    {{ form.processing ? 'Menyimpan...' : (isEdit ? 'Simpan' : 'Tambah Potongan') }}
                </button>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

// ── Inline components ─────────────────────────────────────────────────────────
// ErrMsg: tampil pesan error validasi
const ErrMsg = {
    props: { e: String },
    template: `<p v-if="e" class="mt-1 text-xs text-red-500">{{ e }}</p>`,
}


const props = defineProps({
    potongan: { type: Object, default: null },
    jabatan: { type: Array, default: () => [] },
    guru: { type: Array, default: () => [] },
    kategoriOpts: { type: Array, default: () => [] },
    pemicuOpts: { type: Array, default: () => [] },
    lingkupOpts: { type: Array, default: () => [] },
})

const isEdit = computed(() => !!props.potongan)
const searchGuru = ref('')

const form = useForm({
    nama: props.potongan?.nama ?? '',
    kode: props.potongan?.kode ?? '',
    kategori: props.potongan?.kategori ?? 'lainnya',
    tipe_pemicu: props.potongan?.tipe_pemicu ?? 'per_bulan',
    tipe_nominal: props.potongan?.tipe_nominal ?? 'nominal',
    nominal: props.potongan?.nominal ?? 0,
    nominal_maksimal: props.potongan?.nominal_maksimal ?? null,
    lingkup: props.potongan?.lingkup ?? 'semua',
    jabatan_ids: props.potongan?.jabatan_ids ?? [],
    tenaga_pendidik_ids: props.potongan?.tenaga_pendidik_ids ?? [],
    deskripsi: props.potongan?.deskripsi ?? '',
    tampil_di_slip: props.potongan?.tampil_di_slip ?? true,
})

// Sinkronkan pemicu default dengan kategori
function syncPemicu() {
    const defaultPemicu = {
        absensi: 'per_keterlambatan',
        wajib: 'per_bulan',
        simpanan: 'per_bulan',
        pinjaman: 'per_bulan',
        lainnya: 'per_bulan',
    }
    form.tipe_pemicu = defaultPemicu[form.kategori] ?? 'per_bulan'
}

// Filter pemicu yang relevan per kategori
const pemicuFiltered = computed(() => {
    if (form.kategori === 'absensi') {
        return props.pemicuOpts.filter(p =>
            ['per_keterlambatan', 'per_alfa', 'per_menit_keterlambatan', 'per_sesi_tidak_mengajar', 'per_bulan'].includes(p.value)
        )
    }
    return props.pemicuOpts.filter(p =>
        !['per_keterlambatan', 'per_alfa', 'per_menit_keterlambatan'].includes(p.value)
    )
})

const guruFiltered = computed(() => {
    if (!searchGuru.value) return props.guru
    const s = searchGuru.value.toLowerCase()
    return props.guru.filter(g =>
        g.nama.toLowerCase().includes(s) || g.jabatan.toLowerCase().includes(s)
    )
})

function inputCls(e) {
    const b = 'w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 transition-all bg-white'
    return e ? `${b} border-red-300 focus:ring-red-100` : `${b} border-gray-200 focus:border-indigo-500 focus:ring-indigo-100`
}

function formatRp(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID') }

function submit() {
    isEdit.value
        ? form.put(route('admin.smart-payroll.setting-potongan.update', props.potongan.id))
        : form.post(route('admin.smart-payroll.setting-potongan.store'))
}
</script>


<style scoped>
.label {
    @apply block text-sm font-medium text-gray-700 mb-1.5;
}

.slide-enter-active,
.slide-leave-active {
    transition: all 0.2s ease;
    overflow: hidden;
}

.slide-enter-from,
.slide-leave-to {
    opacity: 0;
    max-height: 0;
}

.slide-enter-to,
.slide-leave-from {
    max-height: 600px;
}
</style>