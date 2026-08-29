<template>
    <AdminLayout>
        <div class="flex items-center gap-3 mb-5">
            <Link :href="route('admin.smart-payroll.lembur.index')"
                class="p-2 rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </Link>
            <h1 class="text-lg font-bold text-gray-800 flex-1">{{ lembur.judul }}</h1>
            <span class="px-2.5 py-1 rounded-lg text-xs font-bold border" :class="statusCls(lembur.status)">{{ statusLabel(lembur.status) }}</span>
        </div>

        <div class="grid lg:grid-cols-3 gap-4">
            <!-- DETAIL -->
            <div class="lg:col-span-1 space-y-4">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 text-sm">
                    <Row label="Jabatan" :value="lembur.jabatan" />
                    <Row label="Tanggal" :value="lembur.tanggal_label" />
                    <Row label="Jam" :value="lembur.jam_mulai ? `${lembur.jam_mulai} – ${lembur.jam_selesai} (${lembur.durasi_menit}m)` : '—'" />
                    <Row label="Sumber" :value="lembur.sumber_input === 'kolektif' ? 'Kolektif' : 'Mandiri (pengajuan)'" />
                    <Row label="Tarif" :value="lembur.nominal ? rupiah(lembur.nominal) : '—'" />
                    <Row label="Setting" :value="lembur.setting_nama || '—'" />
                    <Row v-if="lembur.diajukan_oleh" label="Diajukan" :value="lembur.diajukan_oleh" />
                    <Row v-if="lembur.disetujui_oleh" label="Disetujui" :value="lembur.disetujui_oleh" />
                    <Row v-if="lembur.deskripsi" label="Deskripsi" :value="lembur.deskripsi" />
                    <Row v-if="lembur.catatan" label="Catatan" :value="lembur.catatan" />
                </div>

                <!-- APPROVE PANEL (pengajuan mandiri) -->
                <div v-if="lembur.status === 'diajukan'" class="bg-white rounded-2xl border border-amber-200 shadow-sm p-5 space-y-3">
                    <h3 class="font-semibold text-gray-800 text-sm">Setujui Pengajuan</h3>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Setting Vakasi Lembur</label>
                        <select v-model.number="ap.setting_vakasi_id" class="w-full px-3 py-2 rounded-xl text-sm border border-gray-200">
                            <option :value="null" disabled>Pilih tarif</option>
                            <option v-for="s in settingLembur" :key="s.id" :value="s.id">
                                {{ s.nama }} — {{ rupiah(s.nominal) }} (min {{ s.min_durasi_menit || 0 }}m)
                            </option>
                        </select>
                    </div>
                    <!-- Jam & durasi sudah ditentukan oleh pengaju — admin hanya pilih tarif -->
                    <div class="text-xs text-gray-500 bg-gray-50 rounded-lg px-3 py-2 leading-relaxed">
                        Diajukan mulai <b>{{ lembur.jam_mulai || '—' }}</b> selama
                        <b>{{ lembur.durasi_menit || 0 }} menit</b> (selesai ±{{ lembur.jam_selesai || '—' }}).
                        Jam tidak diubah saat approve.
                    </div>
                    <p v-if="minWarn" class="text-xs text-red-600">
                        ⚠ Durasi pengajuan ({{ lembur.durasi_menit }}m) di bawah minimal tarif ini. Pilih tarif lain.
                    </p>
                    <div class="flex gap-2">
                        <button @click="setujui" :disabled="!apValid"
                            class="flex-1 px-3 py-2 rounded-xl text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50">Setujui</button>
                        <button @click="tolak" class="px-3 py-2 rounded-xl text-sm font-semibold text-red-600 border border-red-200 hover:bg-red-50">Tolak</button>
                    </div>
                </div>

                <button v-if="lembur.status === 'disetujui'" @click="batalkan"
                    class="w-full px-3 py-2.5 rounded-xl text-sm font-semibold text-red-600 border border-red-200 hover:bg-red-50">
                    Batalkan Lembur
                </button>
            </div>

            <!-- PESERTA -->
            <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 font-semibold text-gray-800 text-sm">
                    Peserta ({{ peserta.length }})
                </div>
                <div class="divide-y divide-gray-50">
                    <div v-for="p in peserta" :key="p.id" class="p-4">
                        <div class="flex items-center gap-3">
                            <img v-if="p.guru_foto" :src="p.guru_foto" class="w-9 h-9 rounded-full object-cover" />
                            <div v-else class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600">
                                {{ p.guru_nama?.charAt(0) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-gray-800 text-sm">{{ p.guru_nama }}</div>
                                <div class="text-[11px] text-gray-400">
                                    {{ p.nominal_vakasi ? rupiah(p.nominal_vakasi) : '—' }}
                                    <span v-if="p.metode === 'manual_admin'" class="text-amber-600">· disahkan admin</span>
                                </div>
                            </div>
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold border" :class="pStatusCls(p)">
                                {{ p.terlewat ? 'Terlewat' : pStatusLabel(p.status) }}
                            </span>
                        </div>

                        <!-- Bukti -->
                        <div v-if="p.sudah_upload" class="mt-3 flex gap-3">
                            <a :href="p.foto_bukti_url" target="_blank" class="shrink-0">
                                <img :src="p.foto_bukti_url" class="w-20 h-20 rounded-xl object-cover border border-gray-200" />
                            </a>
                            <div class="text-xs text-gray-500 space-y-1">
                                <p v-if="p.deskripsi" class="text-gray-700">{{ p.deskripsi }}</p>
                                <p>Diunggah: {{ p.diunggah_pada }}</p>
                                <p>Lokasi:
                                    <span :class="p.validasi_lokasi && p.validasi_lokasi.startsWith('valid') ? 'text-emerald-600' : 'text-gray-500'">
                                        {{ lokasiLabel(p.validasi_lokasi) }}</span>
                                    <span v-if="p.jarak_meter"> · {{ Math.round(p.jarak_meter) }}m</span>
                                    <a v-if="p.latitude" :href="`https://www.google.com/maps?q=${p.latitude},${p.longitude}`" target="_blank"
                                        class="text-indigo-600 ml-1">peta ↗</a>
                                </p>
                            </div>
                        </div>

                        <!-- Aksi terlewat (lupa bukti) -->
                        <div v-if="p.terlewat" class="mt-3 flex gap-2">
                            <button @click="sahkanPeserta(p)" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700">Sahkan Manual</button>
                            <button @click="batalkanPeserta(p)" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-red-600 border border-red-200 hover:bg-red-50">Batalkan</button>
                        </div>
                    </div>
                    <div v-if="!peserta.length" class="p-10 text-center text-gray-400 text-sm">Belum ada peserta.</div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed, h } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { confirm } from '@/composables/useConfirm'

const props = defineProps({
    lembur: { type: Object, required: true },
    peserta: { type: Array, default: () => [] },
    settingLembur: { type: Array, default: () => [] },
})

const ap = ref({ setting_vakasi_id: null, catatan: '' })

const selSetting = computed(() => props.settingLembur.find(s => s.id === ap.value.setting_vakasi_id))
const minWarn = computed(() => {
    const min = selSetting.value?.min_durasi_menit
    const dur = props.lembur.durasi_menit
    return !!(min && dur && dur < min)
})
const apValid = computed(() => !!ap.value.setting_vakasi_id && !minWarn.value)

function setujui() {
    router.post(route('admin.smart-payroll.lembur.setujui', props.lembur.id), ap.value)
}
function tolak() {
    const c = window.prompt('Alasan menolak (opsional):') ?? ''
    router.post(route('admin.smart-payroll.lembur.tolak', props.lembur.id), { catatan: c })
}
async function batalkan() {
    if (!(await confirm({ title: 'Batalkan lembur ini?', variant: 'danger', confirmLabel: 'Ya, Batalkan' }))) return
    const c = window.prompt('Alasan pembatalan (opsional):') ?? ''
    router.post(route('admin.smart-payroll.lembur.batalkan', props.lembur.id), { catatan: c })
}
function sahkanPeserta(p) {
    const a = window.prompt(`Alasan sahkan manual untuk ${p.guru_nama} (wajib):`)
    if (!a) return
    router.post(route('admin.smart-payroll.lembur.peserta.sahkan', p.id), { alasan: a })
}
async function batalkanPeserta(p) {
    if (!(await confirm({ title: `Batalkan peserta ${p.guru_nama}?`, variant: 'danger', confirmLabel: 'Ya, Batalkan' }))) return
    const a = window.prompt('Alasan (opsional):') ?? ''
    router.post(route('admin.smart-payroll.lembur.peserta.batalkan', p.id), { alasan: a })
}

function rupiah(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID') }
function statusLabel(s) { return { diajukan: 'Diajukan', disetujui: 'Disetujui', ditolak: 'Ditolak', dibatalkan: 'Dibatalkan' }[s] ?? s }
function statusCls(s) {
    return { diajukan: 'bg-amber-50 text-amber-700 border-amber-200', disetujui: 'bg-indigo-50 text-indigo-700 border-indigo-200',
        ditolak: 'bg-red-50 text-red-700 border-red-200', dibatalkan: 'bg-gray-50 text-gray-500 border-gray-200' }[s] ?? 'bg-gray-50 text-gray-500 border-gray-200'
}
function pStatusLabel(s) { return { menunggu_bukti: 'Menunggu Bukti', sah: 'Sah', ditolak: 'Ditolak', dibatalkan: 'Dibatalkan' }[s] ?? s }
function pStatusCls(p) {
    if (p.terlewat) return 'bg-red-50 text-red-700 border-red-200'
    return { menunggu_bukti: 'bg-amber-50 text-amber-700 border-amber-200', sah: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        ditolak: 'bg-red-50 text-red-700 border-red-200', dibatalkan: 'bg-gray-50 text-gray-500 border-gray-200' }[p.status] ?? 'bg-gray-50 text-gray-500 border-gray-200'
}
function lokasiLabel(v) {
    return { valid_koordinat: 'GPS Valid', valid_wifi: 'WiFi Valid', invalid: 'Di luar area', bypass_admin: 'Input admin', tidak_diperiksa: 'Tanpa GPS' }[v] ?? (v || '—')
}

const Row = (p) => h('div', { class: 'flex justify-between gap-3 py-1.5 border-b border-gray-50 last:border-0' }, [
    h('span', { class: 'text-gray-400 shrink-0' }, p.label),
    h('span', { class: 'text-gray-800 font-medium text-right' }, p.value),
])
Row.props = ['label', 'value']
</script>
