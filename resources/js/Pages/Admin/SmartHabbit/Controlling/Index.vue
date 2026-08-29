<template>
    <AdminLayout title="Smart Controlling" subtitle="Smart Habbit">

        <Head title="Smart Controlling — Pengaturan" />

        <div class="mb-5">
            <h2 class="text-xl font-semibold text-gray-900">Smart Controlling — Pengaturan</h2>
            <p class="text-sm text-gray-400 mt-0.5">Susun periode (1 bulan), kegiatan, & jadwal absen santri (scan barcode). Telat otomatis terkirim ke RamahAnak.</p>
        </div>

        <!-- Peringatan pergantian bulan: sistem otomatis pakai periode bulan berjalan -->
        <div v-if="bulanIni && !bulanIni.ada" class="mb-5 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
            <span class="text-amber-500 text-lg leading-none mt-0.5">⚠</span>
            <div class="text-sm text-amber-800">
                <b>Belum ada periode untuk {{ bulanIni.label }}.</b> Sistem scan otomatis memakai periode bulan berjalan. Buat periode {{ bulanIni.label }} (atau klik <b>Duplikat ⧉</b> pada bulan sebelumnya) agar absensi tercatat di bulan yang benar.
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-5">
            <!-- PERIODE -->
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <h3 class="text-sm font-bold text-gray-800 mb-3">Periode</h3>
                <div class="space-y-2 mb-4 max-h-56 overflow-y-auto">
                    <div v-for="p in periodeList" :key="p.id"
                        :class="['flex items-center justify-between px-3 py-2 rounded-xl border', p.id === periodeId ? 'border-indigo-300 bg-indigo-50/50' : 'border-gray-200']">
                        <button @click="lihatPeriode(p.id)" class="text-left flex-1">
                            <p class="text-sm font-semibold text-gray-800">{{ p.bulan_label }}
                                <span v-if="p.is_aktif" class="ml-1 text-[10px] bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded">AKTIF</span>
                            </p>
                            <p class="text-xs text-gray-400">{{ p.jumlah_jadwal }} jadwal</p>
                        </button>
                        <div class="flex items-center gap-1">
                            <button v-if="!p.is_aktif" @click="aktifkan(p)" class="text-[11px] text-indigo-600 hover:underline">Aktifkan</button>
                            <button @click="duplikatPeriode(p)" class="p-1 text-gray-400 hover:text-indigo-600" title="Duplikat ke bulan berikutnya">⧉</button>
                            <button @click="hapusPeriode(p)" class="p-1 text-gray-400 hover:text-red-500" title="Hapus">✕</button>
                        </div>
                    </div>
                    <p v-if="!periodeList.length" class="text-xs text-gray-400 text-center py-4">Belum ada periode.</p>
                </div>
                <div class="border-t border-gray-100 pt-3 space-y-2">
                    <div class="grid grid-cols-2 gap-2">
                        <select v-model.number="fPeriode.bulan" :class="inp">
                            <option v-for="b in bulanOpsi" :key="b.v" :value="b.v">{{ b.label }}</option>
                        </select>
                        <input v-model.number="fPeriode.tahun" type="number" min="2024" max="2100" placeholder="Tahun" :class="inp" />
                    </div>
                    <select v-model.number="fPeriode.salin_dari" :class="inp">
                        <option :value="null">Tanpa salinan (jadwal kosong)</option>
                        <option v-for="p in periodeList" :key="p.id" :value="p.id">Salin jadwal dari {{ p.bulan_label }} ({{ p.jumlah_jadwal }})</option>
                    </select>
                    <button @click="tambahPeriode" class="w-full py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">+ Periode</button>
                    <p class="text-[11px] text-gray-400 leading-snug">Pilih "Salin jadwal dari" agar kegiatan bulan sebelumnya otomatis berjalan di bulan ini — lalu tinggal tambah/kurangi.</p>
                </div>
            </div>

            <!-- KEGIATAN -->
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <h3 class="text-sm font-bold text-gray-800 mb-3">Kegiatan (master)</h3>
                <div class="space-y-2 mb-4 max-h-56 overflow-y-auto">
                    <div v-for="k in kegiatanList" :key="k.id" class="flex items-center justify-between px-3 py-2 rounded-xl border border-gray-200">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">{{ k.nama }}</p>
                            <span :class="['text-[10px] px-1.5 py-0.5 rounded', k.jenis === 'kajian' ? 'bg-violet-100 text-violet-700' : 'bg-sky-100 text-sky-700']">{{ k.jenis }}</span>
                        </div>
                        <button @click="hapusKegiatan(k)" class="p-1 text-gray-400 hover:text-red-500" title="Hapus">✕</button>
                    </div>
                    <p v-if="!kegiatanList.length" class="text-xs text-gray-400 text-center py-4">Belum ada kegiatan.</p>
                </div>
                <div class="border-t border-gray-100 pt-3 space-y-2">
                    <input v-model="fKegiatan.nama" placeholder="Nama (mis. Sholat Subuh)" :class="inp" />
                    <select v-model="fKegiatan.jenis" :class="inp">
                        <option value="harian">Harian (hadir/telat/alpha)</option>
                        <option value="kajian">Kajian (fleksibel dalam window)</option>
                    </select>
                    <button @click="tambahKegiatan" class="w-full py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">+ Kegiatan</button>
                </div>
            </div>

            <!-- JADWAL -->
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <h3 class="text-sm font-bold text-gray-800 mb-1">Jadwal Periode</h3>
                <p class="text-xs text-gray-400 mb-3">{{ periodeNama || 'Pilih/aktifkan periode dulu' }}</p>
                <div class="space-y-1.5 mb-4 max-h-56 overflow-y-auto">
                    <div v-for="j in jadwalList" :key="j.id" class="flex items-center justify-between px-3 py-1.5 rounded-lg border border-gray-100 text-sm">
                        <div>
                            <span class="capitalize font-semibold text-gray-700">{{ j.hari }}</span>
                            <span class="text-gray-500"> · {{ j.kegiatan }}</span>
                            <span class="text-gray-400 text-xs"> {{ j.jam_mulai }}–{{ j.jam_selesai }}<span v-if="j.jenis !== 'kajian'"> · telat &gt;{{ j.ambang_telat_menit }}m</span></span>
                        </div>
                        <button @click="hapusJadwal(j)" class="p-1 text-gray-400 hover:text-red-500" title="Hapus">✕</button>
                    </div>
                    <p v-if="!jadwalList.length" class="text-xs text-gray-400 text-center py-4">Belum ada jadwal.</p>
                </div>
                <div v-if="periodeId" class="border-t border-gray-100 pt-3 space-y-2">
                    <select v-model.number="fJadwal.kegiatan_id" :class="inp">
                        <option :value="null">Pilih kegiatan...</option>
                        <option v-for="k in kegiatanList" :key="k.id" :value="k.id">{{ k.nama }} ({{ k.jenis }})</option>
                    </select>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-medium text-gray-500">Hari (boleh pilih banyak)</span>
                            <button type="button" @click="pilihSemuaHari" class="text-[11px] text-indigo-600 hover:underline">
                                {{ fJadwal.hari.length === hariOpsi.length ? 'Kosongkan' : 'Setiap hari' }}
                            </button>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" v-for="h in hariOpsi" :key="h" @click="toggleHari(h)"
                                :class="['px-2.5 py-1 rounded-lg text-xs font-semibold capitalize border transition', fJadwal.hari.includes(h) ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-500 border-gray-200 hover:border-indigo-300']">{{ h }}</button>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <input v-model="fJadwal.jam_mulai" type="time" :class="inp" />
                        <input v-model="fJadwal.jam_selesai" type="time" :class="inp" />
                        <input v-model.number="fJadwal.ambang_telat_menit" type="number" min="0" placeholder="telat(m)" :class="inp" />
                    </div>
                    <button @click="tambahJadwal" class="w-full py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">
                        + Jadwal<span v-if="fJadwal.hari.length"> ({{ fJadwal.hari.length }} hari)</span>
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { reactive, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { confirm } from '@/composables/useConfirm'

const props = defineProps({
    periodeList: { type: Array, default: () => [] },
    periodeAktif: { type: Object, default: null },
    periodeId: { type: Number, default: null },
    bulanOpsi: { type: Array, default: () => [] },
    bulanIni: { type: Object, default: null },
    kegiatanList: { type: Array, default: () => [] },
    jadwalList: { type: Array, default: () => [] },
    hariOpsi: { type: Array, default: () => [] },
})

const periodeNama = computed(() => props.periodeList.find(p => p.id === props.periodeId)?.nama ?? '')

const fPeriode = reactive({ bulan: new Date().getMonth() + 1, tahun: new Date().getFullYear(), salin_dari: null })
const fKegiatan = reactive({ nama: '', jenis: 'harian' })
const fJadwal = reactive({ periode_id: props.periodeId, kegiatan_id: null, hari: [], jam_mulai: '', jam_selesai: '', ambang_telat_menit: 0 })

const opt = { preserveScroll: true }
const lihatPeriode = (id) => router.get(route('admin.smart-habbit.controlling.index'), { periode_id: id }, { preserveScroll: true, preserveState: true })

function tambahPeriode() {
    if (!fPeriode.bulan || !fPeriode.tahun) return
    router.post(route('admin.smart-habbit.controlling.periode.store'), { ...fPeriode }, { ...opt, onSuccess: () => { fPeriode.salin_dari = null } })
}
const aktifkan = (p) => router.post(route('admin.smart-habbit.controlling.periode.activate', p.id), {}, opt)
const duplikatPeriode = async (p) => { if (await confirm({ title: `Duplikat ke bulan berikutnya?`, message: `Membuat periode bulan setelah ${p.nama} dengan menyalin ${p.jumlah_jadwal} jadwal. Bisa ditambah/dikurangi setelahnya.`, confirmLabel: 'Ya, Duplikat' })) router.post(route('admin.smart-habbit.controlling.periode.duplikat', p.id), {}, opt) }
const hapusPeriode = async (p) => { if (await confirm({ title: `Hapus periode ${p.nama}?`, message: 'Jadwal & absensi periode ini ikut terhapus.', variant: 'danger', irreversible: true, confirmLabel: 'Ya, Hapus' })) router.delete(route('admin.smart-habbit.controlling.periode.destroy', p.id), opt) }

// Multi-hari untuk jadwal
function toggleHari(h) { const i = fJadwal.hari.indexOf(h); i >= 0 ? fJadwal.hari.splice(i, 1) : fJadwal.hari.push(h) }
function pilihSemuaHari() { fJadwal.hari = fJadwal.hari.length === props.hariOpsi.length ? [] : [...props.hariOpsi] }

function tambahKegiatan() {
    if (!fKegiatan.nama) return
    router.post(route('admin.smart-habbit.controlling.kegiatan.store'), { ...fKegiatan }, { ...opt, onSuccess: () => { fKegiatan.nama = '' } })
}
const hapusKegiatan = async (k) => { if (await confirm({ title: `Hapus kegiatan ${k.nama}?`, variant: 'danger', confirmLabel: 'Ya, Hapus' })) router.delete(route('admin.smart-habbit.controlling.kegiatan.destroy', k.id), opt) }

function tambahJadwal() {
    if (!fJadwal.kegiatan_id || !fJadwal.hari.length || !fJadwal.jam_mulai || !fJadwal.jam_selesai) return
    router.post(route('admin.smart-habbit.controlling.jadwal.store'), { ...fJadwal, periode_id: props.periodeId }, { ...opt, onSuccess: () => { fJadwal.kegiatan_id = null; fJadwal.hari = []; fJadwal.jam_mulai = ''; fJadwal.jam_selesai = ''; fJadwal.ambang_telat_menit = 0 } })
}
const hapusJadwal = (j) => router.delete(route('admin.smart-habbit.controlling.jadwal.destroy', j.id), opt)

const inp = 'w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 bg-white'
</script>
