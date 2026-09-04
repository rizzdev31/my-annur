<template>
    <AdminLayout title="Monitoring Pimpinan" subtitle="Pengawas">

        <Head title="Monitoring Pimpinan" />

        <div class="flex items-start justify-between gap-4 mb-5">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Pengawas Monitoring</h2>
                <p class="text-sm text-gray-400 mt-0.5">
                    Tunjuk guru/pimpinan agar dapat memantau aktivitas guru lain <b>langsung dari PWA</b> — tanpa akun admin kedua.
                </p>
            </div>
            <button @click="bukaTambah"
                class="shrink-0 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">
                + Tunjuk Pengawas
            </button>
        </div>

        <div class="rounded-2xl bg-amber-50 border border-amber-200 px-4 py-3 mb-5 text-xs text-amber-800 leading-relaxed">
            <b>Perhatian data pribadi.</b> Pengawas dapat melihat data rekan kerja (absensi, izin — termasuk alasan sakit, kinerja).
            Berikan hanya seperlunya. Pengawas <b>tidak pernah</b> bisa memantau atau menyetujui izin dirinya sendiri.
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead><tr class="bg-gray-50/60 text-xs text-gray-400 uppercase">
                    <th class="px-4 py-2.5 text-left">Pengawas</th>
                    <th class="px-3 py-2.5 text-left">Modul</th>
                    <th class="px-3 py-2.5 text-left">Cakupan</th>
                    <th class="px-3 py-2.5 text-center">Setujui Izin</th>
                    <th class="px-3 py-2.5 text-center">Status</th>
                    <th class="px-4 py-2.5 text-right">Aksi</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="p in pengawas" :key="p.id" :class="!p.is_aktif ? 'opacity-60' : ''">
                        <td class="px-4 py-3">
                            <p class="font-semibold text-gray-800">{{ p.nama }}</p>
                            <p class="text-xs text-gray-400">{{ p.jabatan }}</p>
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex flex-wrap gap-1">
                                <span v-for="m in p.modul" :key="m"
                                    class="px-2 py-0.5 rounded-lg bg-indigo-50 text-indigo-700 text-[11px] font-medium">
                                    {{ modulOpsi[m] ?? m }}
                                </span>
                                <span v-if="!p.modul.length" class="text-xs text-gray-400">— belum ada —</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-xs text-gray-600">
                            <span v-if="p.cakupan === 'semua'" class="font-semibold text-gray-700">Semua guru</span>
                            <span v-else>{{ p.jumlah_guru }} guru terpilih</span>
                        </td>
                        <td class="px-3 py-3 text-center">
                            <span :class="['px-2 py-0.5 rounded-lg text-[11px] font-semibold',
                                p.boleh_setujui_izin ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500']">
                                {{ p.boleh_setujui_izin ? 'Ya (final)' : 'Tidak' }}
                            </span>
                        </td>
                        <td class="px-3 py-3 text-center">
                            <button @click="toggle(p)" :class="['px-2 py-0.5 rounded-lg text-[11px] font-semibold',
                                p.is_aktif ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500']">
                                {{ p.is_aktif ? 'Aktif' : 'Nonaktif' }}
                            </button>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button @click="bukaEdit(p)" class="text-xs font-semibold text-indigo-600 hover:underline">Edit</button>
                            <button @click="hapus(p)" class="ml-3 text-xs font-semibold text-red-500 hover:underline">Hapus</button>
                        </td>
                    </tr>
                    <tr v-if="!pengawas.length">
                        <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-400">Belum ada pengawas ditunjuk.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal -->
        <div v-if="form" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,.45)">
            <div class="w-full max-w-2xl bg-white rounded-2xl p-6 max-h-[90vh] overflow-y-auto">
                <h3 class="text-base font-semibold text-gray-900 mb-4">
                    {{ editId ? 'Edit Pengawas' : 'Tunjuk Pengawas Baru' }}
                </h3>

                <label class="block text-xs font-medium text-gray-500 mb-1">Guru / Pimpinan</label>
                <select v-model.number="form.tenaga_pendidik_id" :disabled="!!editId" :class="[inp, 'w-full mb-4 disabled:bg-gray-50']">
                    <option :value="null">Pilih guru…</option>
                    <option v-for="g in guruOpsi" :key="g.id" :value="g.id">{{ g.nama }} — {{ g.jabatan }}</option>
                </select>

                <label class="block text-xs font-medium text-gray-500 mb-1.5">Modul yang boleh dipantau</label>
                <div class="grid sm:grid-cols-2 gap-2 mb-4">
                    <label v-for="(label, kunci) in modulOpsi" :key="kunci"
                        class="flex items-center gap-2 px-3 py-2 rounded-xl border border-gray-200 cursor-pointer hover:bg-gray-50">
                        <input type="checkbox" :value="kunci" v-model="form.modul" class="rounded text-indigo-600" />
                        <span class="text-sm text-gray-700">{{ label }}</span>
                    </label>
                </div>

                <label class="block text-xs font-medium text-gray-500 mb-1.5">Cakupan (guru yang dipantau)</label>
                <div class="flex gap-2 mb-3">
                    <label v-for="c in [['pilih','Pilih guru tertentu'],['semua','Semua guru']]" :key="c[0]"
                        :class="['flex-1 px-3 py-2 rounded-xl border cursor-pointer text-sm text-center',
                            form.cakupan === c[0] ? 'border-indigo-400 bg-indigo-50 text-indigo-700 font-semibold' : 'border-gray-200 text-gray-600']">
                        <input type="radio" :value="c[0]" v-model="form.cakupan" class="hidden" />{{ c[1] }}
                    </label>
                </div>

                <div v-if="form.cakupan === 'pilih'" class="mb-4">
                    <div class="flex items-center gap-2 mb-2">
                        <input v-model="cariGuru" placeholder="Cari guru…" :class="[inp, 'flex-1']" />
                        <span class="text-xs text-gray-500 shrink-0">{{ form.guru_ids.length }} dipilih</span>
                    </div>
                    <div class="max-h-52 overflow-y-auto rounded-xl border border-gray-200 divide-y divide-gray-50">
                        <label v-for="g in guruTersaring" :key="g.id"
                            class="flex items-center gap-3 px-3 py-2 cursor-pointer hover:bg-gray-50">
                            <input type="checkbox" :value="g.id" v-model="form.guru_ids"
                                :disabled="g.id === form.tenaga_pendidik_id" class="rounded text-indigo-600 disabled:opacity-40" />
                            <span class="text-sm text-gray-700">{{ g.nama }}</span>
                            <span class="text-xs text-gray-400 ml-auto">{{ g.jabatan }}</span>
                        </label>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">Diri sendiri otomatis tidak bisa dipilih.</p>
                </div>

                <label class="flex items-start gap-2 mb-4 cursor-pointer">
                    <input type="checkbox" v-model="form.boleh_setujui_izin" class="mt-0.5 rounded text-emerald-600" />
                    <span class="text-xs text-gray-600 leading-snug">
                        <b class="text-emerald-700">Boleh menyetujui izin guru (final)</b> — keputusannya langsung sah seperti admin.
                        Hanya berlaku bila modul <b>Perizinan Guru</b> dicentang. Setiap keputusan tercatat.
                    </span>
                </label>

                <label class="block text-xs font-medium text-gray-500 mb-1">Catatan (opsional)</label>
                <input v-model="form.catatan" placeholder="mis. Kepala Sekolah SMP" :class="[inp, 'w-full mb-5']" />

                <div class="flex gap-3">
                    <button @click="form = null" class="flex-1 py-2.5 rounded-xl bg-gray-100 text-gray-600 font-semibold text-sm">Batal</button>
                    <button @click="simpan" :disabled="!valid"
                        class="flex-1 py-2.5 rounded-xl bg-indigo-600 text-white font-bold text-sm disabled:opacity-50">Simpan</button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { confirm } from '@/composables/useConfirm'

const props = defineProps({
    pengawas:  { type: Array,  default: () => [] },
    modulOpsi: { type: Object, default: () => ({}) },
    guruOpsi:  { type: Array,  default: () => [] },
})

const form = ref(null)
const editId = ref(null)
const cariGuru = ref('')

const guruTersaring = computed(() => {
    const q = cariGuru.value.trim().toLowerCase()
    return q ? props.guruOpsi.filter(g => g.nama.toLowerCase().includes(q)) : props.guruOpsi
})
const valid = computed(() => form.value?.tenaga_pendidik_id && (form.value?.modul?.length > 0))

function kosong() {
    return { tenaga_pendidik_id: null, modul: [], cakupan: 'pilih', boleh_setujui_izin: false, catatan: '', guru_ids: [] }
}
function bukaTambah() { editId.value = null; cariGuru.value = ''; form.value = kosong() }
function bukaEdit(p) {
    editId.value = p.id; cariGuru.value = ''
    form.value = {
        tenaga_pendidik_id: p.tenaga_pendidik_id,
        modul: [...p.modul],
        cakupan: p.cakupan,
        boleh_setujui_izin: !!p.boleh_setujui_izin,
        catatan: p.catatan ?? '',
        guru_ids: [...(p.guru_ids ?? [])],
    }
}
function simpan() {
    if (!valid.value) return
    const opts = { preserveScroll: true, onSuccess: () => { form.value = null } }
    if (editId.value) router.put(route('admin.pengawas.update', editId.value), form.value, opts)
    else router.post(route('admin.pengawas.store'), form.value, opts)
}
function toggle(p) { router.patch(route('admin.pengawas.toggle', p.id), {}, { preserveScroll: true }) }
async function hapus(p) {
    if (!(await confirm({ title: 'Hapus pengawas?', message: `${p.nama} akan kehilangan akses monitoring.`, variant: 'danger', confirmLabel: 'Ya, Hapus' }))) return
    router.delete(route('admin.pengawas.destroy', p.id), { preserveScroll: true })
}

const inp = 'px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white'
</script>
