<template>
    <AdminLayout :title="isEdit ? 'Edit Jadwal Mengajar' : 'Tambah Jadwal Mengajar'" subtitle="Master Data">

        <Head :title="isEdit ? 'Edit Jadwal Mengajar' : 'Tambah Jadwal Mengajar'" />

        <div class="flex items-center gap-4 mb-6">
            <Link :href="route('admin.master.jadwal-mengajar.index')"
                class="p-2 rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </Link>
            <div>
                <h2 class="text-xl font-semibold text-gray-900">
                    {{ isEdit ? 'Edit Jadwal Mengajar' : 'Tambah Jadwal Mengajar' }}
                </h2>
                <p class="text-sm text-gray-400 mt-0.5">
                    Setiap guru per mata pelajaran akan mendapat vakasi JP sesuai setting vakasi mengajar
                </p>
            </div>
        </div>

        <div class="max-w-2xl">
            <form @submit.prevent="submit" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">

                <!-- Tahun Ajaran -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Tahun Ajaran <span class="text-red-500">*</span>
                    </label>
                    <select v-model="form.tahun_ajaran_id" :class="inputCls(form.errors.tahun_ajaran_id)">
                        <option value="">-- Pilih Tahun Ajaran --</option>
                        <option v-for="t in tahunAjaran" :key="t.id" :value="t.id">
                            {{ t.nama }} — {{ t.semester }}
                        </option>
                    </select>
                    <ErrMsg :e="form.errors.tahun_ajaran_id" />
                </div>

                <!-- Guru -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Tenaga Pendidik <span class="text-red-500">*</span>
                    </label>
                    <select v-model="form.tenaga_pendidik_id" :class="inputCls(form.errors.tenaga_pendidik_id)">
                        <option value="">-- Pilih Guru --</option>
                        <option v-for="g in guru" :key="g.id" :value="g.id">
                            {{ g.nama }}{{ g.jabatan ? ` — ${g.jabatan}` : '' }}
                        </option>
                    </select>
                    <ErrMsg :e="form.errors.tenaga_pendidik_id" />
                </div>

                <!-- Mata Pelajaran -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Mata Pelajaran <span class="text-red-500">*</span>
                    </label>
                    <select v-model="form.mata_pelajaran_id" :class="inputCls(form.errors.mata_pelajaran_id)">
                        <option value="">-- Pilih Mata Pelajaran --</option>
                        <optgroup v-for="(items, tingkat) in mapelByTingkat" :key="tingkat"
                            :label="'Tingkat ' + tingkat">
                            <option v-for="m in items" :key="m.id" :value="m.id">
                                [{{ m.kode }}] {{ m.nama }}
                            </option>
                        </optgroup>
                    </select>
                    <ErrMsg :e="form.errors.mata_pelajaran_id" />
                </div>

                <!-- Hari -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Hari <span class="text-red-500">*</span>
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="h in hariAll" :key="h.value" type="button" @click="form.hari = h.value" :class="[
                            'px-4 py-2 rounded-xl text-sm font-medium border-2 transition-all',
                            form.hari === h.value
                                ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                : 'border-gray-200 text-gray-600 hover:border-gray-300'
                        ]">
                            {{ h.label }}
                        </button>
                    </div>
                    <ErrMsg :e="form.errors.hari" />
                </div>

                <!-- Jam Mulai & Selesai -->
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Jam Mulai <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.jam_mulai" type="time" :class="inputCls(form.errors.jam_mulai)" />
                        <ErrMsg :e="form.errors.jam_mulai" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Jam Selesai <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.jam_selesai" type="time" :class="inputCls(form.errors.jam_selesai)" />
                        <ErrMsg :e="form.errors.jam_selesai" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Jumlah JP <span class="text-red-500">*</span>
                        </label>
                        <input v-model.number="form.jumlah_jp" type="number" min="1" max="20"
                            :class="inputCls(form.errors.jumlah_jp)" />
                        <ErrMsg :e="form.errors.jumlah_jp" />
                    </div>
                </div>

                <!-- Info vakasi JP -->
                <div v-if="form.jumlah_jp > 0"
                    class="flex items-center gap-2 px-4 py-2.5 bg-amber-50 border border-amber-100 rounded-xl text-xs text-amber-700">
                    <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Guru akan mendapat vakasi mengajar untuk <strong>{{ form.jumlah_jp }} JP</strong> ini
                    setiap kali mengajar sesuai setting vakasi mengajar yang aktif.
                </div>

                <!-- Kelas & Ruangan -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Kelas <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.kelas" type="text" placeholder="cth: VII A, X IPA 1"
                            :class="inputCls(form.errors.kelas)" />
                        <ErrMsg :e="form.errors.kelas" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Ruangan</label>
                        <input v-model="form.ruangan" type="text" placeholder="cth: Lab IPA, Aula"
                            :class="inputCls()" />
                    </div>
                </div>

                <!-- Tombol -->
                <div class="flex gap-3 pt-2">
                    <Link :href="route('admin.master.jadwal-mengajar.index')"
                        class="flex-1 py-2.5 text-center rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50">
                        Batal
                    </Link>
                    <button type="submit" :disabled="form.processing"
                        class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-60 transition-colors">
                        {{ form.processing ? 'Menyimpan...' : (isEdit ? 'Simpan Perubahan' : 'Tambah Jadwal') }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

// ── Inline component ──────────────────────────────────────────────────────────
const ErrMsg = {
    props: { e: String },
    template: `<p v-if="e" class="mt-1 text-xs text-red-500">{{ e }}</p>`,
}

const props = defineProps({
    jadwal: { type: Object, default: null },
    guru: { type: Array, default: () => [] },
    mapel: { type: Array, default: () => [] },
    tahunAjaran: { type: Array, default: () => [] },
})

const isEdit = computed(() => !!props.jadwal)

const form = useForm({
    tahun_ajaran_id: props.jadwal?.tahun_ajaran_id ?? (props.tahunAjaran[0]?.id ?? ''),
    tenaga_pendidik_id: props.jadwal?.tenaga_pendidik_id ?? '',
    mata_pelajaran_id: props.jadwal?.mata_pelajaran_id ?? '',
    hari: props.jadwal?.hari ?? '',
    jam_mulai: props.jadwal?.jam_mulai ?? '07:30',
    jam_selesai: props.jadwal?.jam_selesai ?? '09:00',
    jumlah_jp: props.jadwal?.jumlah_jp ?? 2,
    kelas: props.jadwal?.kelas ?? '',
    ruangan: props.jadwal?.ruangan ?? '',
})

const hariAll = [
    { value: 'senin', label: 'Senin' },
    { value: 'selasa', label: 'Selasa' },
    { value: 'rabu', label: 'Rabu' },
    { value: 'kamis', label: 'Kamis' },
    { value: 'jumat', label: "Jum'at" },
    { value: 'sabtu', label: 'Sabtu' },
    { value: 'ahad', label: 'Ahad' },
]

const mapelByTingkat = computed(() => {
    const g = {}
    props.mapel.forEach(m => {
        const k = m.tingkat || 'Umum'
        if (!g[k]) g[k] = []
        g[k].push(m)
    })
    return g
})

function inputCls(e) {
    const b = 'w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 transition-all bg-white'
    return e ? `${b} border-red-300 focus:ring-red-100` : `${b} border-gray-200 focus:border-indigo-500 focus:ring-indigo-100`
}

function submit() {
    isEdit.value
        ? form.put(route('admin.master.jadwal-mengajar.update', props.jadwal.id))
        : form.post(route('admin.master.jadwal-mengajar.store'))
}
</script>