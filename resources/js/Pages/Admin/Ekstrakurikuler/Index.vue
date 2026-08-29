<template>
    <AdminLayout title="Ekstrakurikuler" subtitle="Kegiatan & Vakasi">
        <Head title="Ekstrakurikuler" />

        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Ekstrakurikuler</h2>
                <p class="text-sm text-gray-400 mt-0.5">Kelola ekskul, pembina, kelompok & vakasi (flat per pertemuan).</p>
            </div>
            <button @click="openCreate" class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl shadow-sm shadow-indigo-200">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                Tambah Ekskul
            </button>
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-3 gap-4 mb-5">
            <div class="bg-white rounded-2xl border border-gray-200 p-4"><p class="text-2xl font-bold text-gray-900">{{ summary.total }}</p><p class="text-xs text-gray-400">Total Ekskul</p></div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4"><p class="text-2xl font-bold text-emerald-600">{{ summary.aktif }}</p><p class="text-xs text-gray-400">Aktif</p></div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4"><p class="text-2xl font-bold text-indigo-600">{{ summary.anggota }}</p><p class="text-xs text-gray-400">Santri Terdaftar</p></div>
        </div>
        <p class="text-xs text-gray-400 mb-3">Vakasi default (Setting Vakasi tipe <b>ekstrakurikuler</b>): <b>Rp {{ rupiah(vakasiDefault) }}</b> / pertemuan. Bisa di-override per ekskul.</p>

        <!-- Tabel -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-[11px] uppercase tracking-wide text-gray-400 bg-gray-50/70">
                        <th class="px-5 py-3 font-semibold">Ekskul</th>
                        <th class="px-4 py-3 font-semibold">Pembina</th>
                        <th class="px-4 py-3 font-semibold whitespace-nowrap">Jadwal</th>
                        <th class="px-4 py-3 font-semibold text-center">Anggota</th>
                        <th class="px-4 py-3 font-semibold whitespace-nowrap">Vakasi/Pertemuan</th>
                        <th class="px-4 py-3 font-semibold text-center">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="e in list" :key="e.id" class="hover:bg-gray-50/50" :class="!e.is_aktif ? 'opacity-60' : ''">
                            <td class="px-5 py-3.5">
                                <p class="font-semibold text-gray-800">{{ e.nama }}</p>
                                <p v-if="e.lokasi" class="text-[11px] text-gray-400">📍 {{ e.lokasi }}<span v-if="e.tahun_ajaran"> · {{ e.tahun_ajaran }}</span></p>
                            </td>
                            <td class="px-4 py-3.5 text-gray-700">{{ e.pembina }}</td>
                            <td class="px-4 py-3.5 whitespace-nowrap text-gray-600">
                                <span v-if="e.hari" class="capitalize">{{ e.hari }}</span><span v-else class="text-gray-300">—</span>
                                <span v-if="e.jam_mulai" class="text-[11px] text-gray-400"> {{ e.jam_mulai }}–{{ e.jam_selesai }}</span>
                            </td>
                            <td class="px-4 py-3.5 text-center"><span class="inline-flex px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 text-xs font-bold">{{ e.anggota }}</span></td>
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                <span class="font-semibold text-gray-700">Rp {{ rupiah(e.vakasi_efektif) }}</span>
                                <span v-if="e.nominal_vakasi != null" class="ml-1 text-[10px] text-amber-500">(override)</span>
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <span :class="['inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium', e.is_aktif ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500']">
                                    <span :class="['w-1.5 h-1.5 rounded-full', e.is_aktif ? 'bg-emerald-500' : 'bg-gray-400']"></span>{{ e.is_aktif ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex justify-end gap-1">
                                    <button @click="openAnggota(e)" title="Kelola Anggota" class="p-2 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4z" /></svg></button>
                                    <button @click="openMonitoring(e)" title="Monitoring" class="p-2 rounded-lg text-gray-400 hover:text-teal-600 hover:bg-teal-50"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg></button>
                                    <button @click="openEdit(e)" title="Edit" class="p-2 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg></button>
                                    <button @click="hapus(e)" title="Nonaktifkan" class="p-2 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636" /></svg></button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!list.length"><td colspan="7" class="px-5 py-16 text-center text-sm text-gray-400">Belum ada ekstrakurikuler.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal Tambah/Edit -->
        <Transition name="modal">
            <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="showForm = false">
                <div class="absolute inset-0 bg-black/40"></div>
                <div class="relative bg-white rounded-2xl w-full max-w-lg p-6 shadow-xl max-h-[90vh] overflow-y-auto">
                    <h3 class="text-base font-semibold text-gray-900 mb-4">{{ editTarget ? 'Edit' : 'Tambah' }} Ekstrakurikuler</h3>
                    <div class="space-y-3">
                        <div><label :class="lab">Nama *</label><input v-model="form.nama" :class="inp" placeholder="cth: Pramuka, Futsal" /></div>
                        <div><label :class="lab">Deskripsi</label><input v-model="form.deskripsi" :class="inp" /></div>
                        <div class="grid grid-cols-2 gap-3">
                            <div><label :class="lab">Pembina</label><select v-model="form.pembina_id" :class="inp"><option :value="null">—</option><option v-for="g in guru" :key="g.id" :value="g.id">{{ g.nama }}</option></select></div>
                            <div><label :class="lab">Tahun Ajaran</label><select v-model="form.tahun_ajaran_id" :class="inp"><option :value="null">—</option><option v-for="t in tahunAjaran" :key="t.id" :value="t.id">{{ t.nama }}</option></select></div>
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div><label :class="lab">Hari</label><select v-model="form.hari" :class="inp"><option :value="null">—</option><option v-for="h in hariOpsi" :key="h" :value="h" class="capitalize">{{ h }}</option></select></div>
                            <div><label :class="lab">Jam Mulai</label><input v-model="form.jam_mulai" type="time" :class="inp" /></div>
                            <div><label :class="lab">Jam Selesai</label><input v-model="form.jam_selesai" type="time" :class="inp" /></div>
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div><label :class="lab">Lokasi</label><input v-model="form.lokasi" :class="inp" /></div>
                            <div><label :class="lab">Kuota</label><input v-model.number="form.kuota" type="number" min="1" :class="inp" placeholder="opsional" /></div>
                            <div><label :class="lab">Batas isi (hari)</label><input v-model.number="form.batas_isi_hari" type="number" min="0" :class="inp" placeholder="bebas" /></div>
                        </div>
                        <div>
                            <label :class="lab">Vakasi/Pertemuan (override)</label>
                            <input v-model.number="form.nominal_vakasi" type="number" min="0" :class="inp" :placeholder="`kosong = default Rp ${rupiah(vakasiDefault)}`" />
                        </div>
                    </div>
                    <div class="flex gap-2 mt-5">
                        <button @click="showForm = false" class="flex-1 py-2.5 rounded-xl bg-gray-100 text-gray-600 text-sm font-semibold">Batal</button>
                        <button @click="submit" :disabled="!form.nama || saving" class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-50">{{ saving ? 'Menyimpan…' : 'Simpan' }}</button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Modal Anggota -->
        <Transition name="modal">
            <div v-if="anggotaTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="anggotaTarget = null">
                <div class="absolute inset-0 bg-black/40"></div>
                <div class="relative bg-white rounded-2xl w-full max-w-2xl p-6 shadow-xl max-h-[90vh] overflow-y-auto">
                    <h3 class="text-base font-semibold text-gray-900">Kelola Anggota — {{ anggotaTarget.nama }}</h3>
                    <p class="text-xs text-gray-400 mb-4">Anggota lintas kelas. Keluarkan tidak menghapus histori.</p>
                    <div v-if="anggotaLoading" class="py-8 text-center text-sm text-gray-400">Memuat…</div>
                    <div v-else class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs font-semibold text-gray-500 mb-1">Anggota ({{ anggotaAktif.length }})</p>
                            <input v-model="cariAnggota" placeholder="Cari…" :class="inp" class="mb-2 !py-2" />
                            <div class="border border-gray-100 rounded-xl divide-y divide-gray-50 max-h-72 overflow-y-auto">
                                <label v-for="s in anggotaFiltered" :key="s.id" class="flex items-center gap-2 px-3 py-2 hover:bg-red-50/40 cursor-pointer">
                                    <input type="checkbox" v-model="keluarkan" :value="s.id" class="rounded text-red-500" />
                                    <span class="text-sm text-gray-700 truncate">{{ s.nama }}</span>
                                </label>
                                <p v-if="!anggotaAktif.length" class="px-3 py-4 text-xs text-gray-400 text-center">Belum ada anggota.</p>
                            </div>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 mb-1">Tambah santri</p>
                            <input v-model="cariKandidat" placeholder="Cari nama/NIS…" :class="inp" class="mb-2 !py-2" />
                            <div class="border border-gray-100 rounded-xl divide-y divide-gray-50 max-h-72 overflow-y-auto">
                                <label v-for="s in kandidatFiltered" :key="s.id" class="flex items-center gap-2 px-3 py-2 hover:bg-indigo-50/40 cursor-pointer">
                                    <input type="checkbox" v-model="tambah" :value="s.id" class="rounded text-indigo-500" />
                                    <span class="text-sm text-gray-700 truncate">{{ s.nama }}</span><span v-if="s.nip" class="ml-auto text-[11px] text-gray-400">{{ s.nip }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2 mt-5">
                        <button @click="anggotaTarget = null" class="flex-1 py-2.5 rounded-xl bg-gray-100 text-gray-600 text-sm font-semibold">Tutup</button>
                        <button @click="simpanAnggota" :disabled="(!tambah.length && !keluarkan.length) || anggotaSaving" class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-50">
                            {{ anggotaSaving ? 'Menyimpan…' : `Simpan (${tambah.length} tambah, ${keluarkan.length} keluar)` }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Modal Monitoring -->
        <Transition name="modal">
            <div v-if="monitor" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="monitor = null">
                <div class="absolute inset-0 bg-black/40"></div>
                <div class="relative bg-white rounded-2xl w-full max-w-2xl p-6 shadow-xl max-h-[90vh] overflow-y-auto">
                    <h3 class="text-base font-semibold text-gray-900">Monitoring — {{ monitor.nama }}</h3>
                    <p class="text-xs text-gray-400 mb-4">{{ monitor.total_pertemuan }} pertemuan. Nilai: A Sangat Baik · B Baik · C Cukup.</p>
                    <div class="overflow-x-auto border border-gray-100 rounded-xl">
                        <table class="w-full text-sm">
                            <thead><tr class="text-left text-[11px] uppercase text-gray-400 bg-gray-50"><th class="px-4 py-2">Santri</th><th class="px-4 py-2 text-center">Kehadiran</th><th class="px-3 py-2 text-center">Keaktifan</th><th class="px-3 py-2 text-center">Perkembangan</th></tr></thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr v-for="(r, i) in monitor.rows" :key="i">
                                    <td class="px-4 py-2.5 text-gray-700">{{ r.nama }}</td>
                                    <td class="px-4 py-2.5 text-center"><span class="font-semibold">{{ r.hadir }}/{{ r.total }}</span> <span class="text-[11px] text-gray-400">({{ r.persen }}%)</span></td>
                                    <td class="px-3 py-2.5 text-center"><span v-if="r.keaktifan" :class="['inline-flex w-6 h-6 items-center justify-center rounded-lg text-xs font-bold', gradeCls(r.keaktifan)]">{{ r.keaktifan }}</span><span v-else class="text-gray-300">—</span></td>
                                    <td class="px-3 py-2.5 text-center"><span v-if="r.perkembangan" :class="['inline-flex w-6 h-6 items-center justify-center rounded-lg text-xs font-bold', gradeCls(r.perkembangan)]">{{ r.perkembangan }}</span><span v-else class="text-gray-300">—</span></td>
                                </tr>
                                <tr v-if="!monitor.rows.length"><td colspan="4" class="px-4 py-8 text-center text-sm text-gray-400">Belum ada anggota.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <button @click="monitor = null" class="w-full mt-4 py-2 text-sm text-gray-500 font-medium">Tutup</button>
                </div>
            </div>
        </Transition>
    </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { confirm } from '@/composables/useConfirm'

const props = defineProps({
    list: { type: Array, default: () => [] },
    guru: { type: Array, default: () => [] },
    tahunAjaran: { type: Array, default: () => [] },
    vakasiDefault: { type: Number, default: 0 },
    hariOpsi: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
})

const inp = 'w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 bg-white'
const lab = 'block text-xs font-medium text-gray-600 mb-1'
const rupiah = (n) => Number(n || 0).toLocaleString('id-ID')
const gradeCls = (g) => ({ A: 'bg-emerald-100 text-emerald-700', B: 'bg-blue-100 text-blue-700', C: 'bg-amber-100 text-amber-700' }[g] || '')

// Form
const showForm = ref(false)
const editTarget = ref(null)
const saving = ref(false)
const blank = () => ({ nama: '', deskripsi: '', pembina_id: null, hari: null, jam_mulai: '', jam_selesai: '', lokasi: '', tahun_ajaran_id: null, kuota: null, nominal_vakasi: null, batas_isi_hari: null })
const form = reactive(blank())
function openCreate() { Object.assign(form, blank()); editTarget.value = null; showForm.value = true }
function openEdit(e) {
    editTarget.value = e
    Object.assign(form, { nama: e.nama, deskripsi: e.deskripsi ?? '', pembina_id: e.pembina_id, hari: e.hari, jam_mulai: e.jam_mulai ?? '', jam_selesai: e.jam_selesai ?? '', lokasi: e.lokasi ?? '', tahun_ajaran_id: e.tahun_ajaran_id, kuota: e.kuota, nominal_vakasi: e.nominal_vakasi, batas_isi_hari: e.batas_isi_hari })
    showForm.value = true
}
function submit() {
    saving.value = true
    const opts = { preserveScroll: true, onFinish: () => { saving.value = false }, onSuccess: () => { showForm.value = false } }
    if (editTarget.value) router.put(route('admin.smart-education.ekstrakurikuler.update', editTarget.value.id), { ...form }, opts)
    else router.post(route('admin.smart-education.ekstrakurikuler.store'), { ...form }, opts)
}
async function hapus(e) {
    if (!(await confirm({ title: `Nonaktifkan "${e.nama}"?`, variant: 'danger', confirmLabel: 'Ya' }))) return
    router.delete(route('admin.smart-education.ekstrakurikuler.destroy', e.id), { preserveScroll: true })
}

// Anggota
const anggotaTarget = ref(null)
const anggotaLoading = ref(false)
const anggotaSaving = ref(false)
const anggotaAktif = ref([])
const kandidat = ref([])
const tambah = ref([])
const keluarkan = ref([])
const cariAnggota = ref('')
const cariKandidat = ref('')
const anggotaFiltered = computed(() => { const q = cariAnggota.value.toLowerCase(); return anggotaAktif.value.filter(s => !q || s.nama.toLowerCase().includes(q)) })
const kandidatFiltered = computed(() => { const q = cariKandidat.value.toLowerCase(); return q ? kandidat.value.filter(s => s.nama.toLowerCase().includes(q) || String(s.nip || '').includes(q)) : kandidat.value.slice(0, 50) })
async function openAnggota(e) {
    anggotaTarget.value = e; tambah.value = []; keluarkan.value = []; cariAnggota.value = ''; cariKandidat.value = ''; anggotaLoading.value = true
    try { const d = (await (await fetch(route('admin.smart-education.ekstrakurikuler.anggota', e.id), { headers: { Accept: 'application/json' } })).json()).data; anggotaAktif.value = d.anggota; kandidat.value = d.kandidat } catch (_) {} finally { anggotaLoading.value = false }
}
function simpanAnggota() {
    anggotaSaving.value = true
    router.post(route('admin.smart-education.ekstrakurikuler.anggota.simpan', anggotaTarget.value.id), { tambah: tambah.value, keluarkan: keluarkan.value },
        { preserveScroll: true, onFinish: () => { anggotaSaving.value = false }, onSuccess: () => { anggotaTarget.value = null } })
}

// Monitoring
const monitor = ref(null)
async function openMonitoring(e) {
    try { monitor.value = (await (await fetch(route('admin.smart-education.ekstrakurikuler.monitoring', e.id), { headers: { Accept: 'application/json' } })).json()).data } catch (_) {}
}
</script>
