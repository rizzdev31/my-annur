<template>
    <AdminLayout title="Template WhatsApp" subtitle="Bot Notifikasi Wali">
        <Head title="Template Bot WhatsApp" />

        <div class="mb-5">
            <h2 class="text-xl font-semibold text-gray-900">Template Bot WhatsApp</h2>
            <p class="text-sm text-gray-400 mt-0.5">Atur nama bot, salam, footer, dan isi pesan per jenis notifikasi.</p>
        </div>

        <!-- Status Fonnte -->
        <div :class="['mb-5 rounded-2xl border p-4 text-sm flex items-center gap-3',
            fonnte.aktif && fonnte.token_ada ? 'bg-emerald-50 border-emerald-100 text-emerald-700' : 'bg-amber-50 border-amber-100 text-amber-700']">
            <span class="text-lg">{{ fonnte.aktif && fonnte.token_ada ? '✅' : '⚠️' }}</span>
            <span v-if="fonnte.aktif && fonnte.token_ada">Fonnte aktif — token tersambung. Notifikasi WA akan terkirim (pastikan worker jalan).</span>
            <span v-else>Fonnte belum aktif / token kosong. Isi <code>FONNTE_TOKEN</code> & <code>FONNTE_ENABLED=true</code> di .env.</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <!-- Form -->
            <div class="lg:col-span-2 space-y-4">
                <!-- Identitas -->
                <div class="bg-white border border-gray-200 rounded-2xl p-5">
                    <h3 class="text-sm font-bold text-gray-800 mb-3">Identitas Bot</h3>
                    <label class="text-xs text-gray-500 block mb-3">Nama Bot / Instansi (header pesan)
                        <input v-model="form.nama_bot" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-200 text-sm" />
                    </label>
                    <label class="text-xs text-gray-500 block mb-3">Footer
                        <input v-model="form.footer" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-200 text-sm" />
                    </label>
                    <label class="flex items-center gap-2 text-xs text-gray-600 cursor-pointer">
                        <input type="checkbox" v-model="form.pakai_salam" class="w-4 h-4 rounded text-indigo-600" />
                        Sertakan salam pembuka ("Assalamu'alaikum … Yth. Wali Santri")
                    </label>
                </div>

                <!-- Placeholder legend -->
                <div class="bg-indigo-50 border border-indigo-100 rounded-2xl p-4 text-xs text-indigo-700">
                    <p class="font-semibold mb-1">Placeholder yang tersedia (akan diganti otomatis):</p>
                    <p>Umum: <code v-for="p in placeholder.umum" :key="p" class="mr-1">{{ p }}</code></p>
                    <p>Controlling: <code v-for="p in placeholder.controlling" :key="p" class="mr-1">{{ p }}</code></p>
                    <p>Mengajar: <code v-for="p in placeholder.mengajar" :key="p" class="mr-1">{{ p }}</code></p>
                    <p>Eksekusi: <code v-for="p in placeholder.eksekusi" :key="p" class="mr-1">{{ p }}</code></p>
                </div>

                <!-- Templates -->
                <div v-for="t in templates" :key="t.key" class="bg-white border border-gray-200 rounded-2xl p-5">
                    <h3 class="text-sm font-bold text-gray-800 mb-1">{{ t.label }}</h3>
                    <p class="text-xs text-gray-400 mb-2">{{ t.desc }}</p>
                    <textarea v-model="form[t.key]" rows="6"
                        class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm font-mono leading-relaxed"></textarea>
                </div>

                <div class="flex justify-end">
                    <button @click="simpan" :disabled="saving"
                        class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-semibold">
                        {{ saving ? 'Menyimpan…' : 'Simpan Template' }}
                    </button>
                </div>
            </div>

            <!-- Preview + test -->
            <div class="space-y-4">
                <div class="bg-white border border-gray-200 rounded-2xl p-4 sticky top-4">
                    <h3 class="text-sm font-bold text-gray-800 mb-1">Pratinjau</h3>
                    <p class="text-[11px] text-gray-400 mb-3">Contoh data · simpan untuk memperbarui.</p>
                    <div class="flex gap-1 mb-3 flex-wrap">
                        <button v-for="t in templates" :key="t.key" @click="tab = t.previewKey"
                            :class="['px-2.5 py-1 rounded-lg text-[11px] font-semibold',
                                tab === t.previewKey ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-500']">
                            {{ t.short }}
                        </button>
                    </div>
                    <div class="rounded-xl bg-[#e5ddd5] p-3">
                        <div class="bg-[#dcf8c6] rounded-lg rounded-tr-none p-2.5 text-[12px] text-gray-800 whitespace-pre-wrap shadow-sm">{{ preview[tab] }}</div>
                    </div>
                </div>

                <div class="bg-white border border-gray-200 rounded-2xl p-4">
                    <h3 class="text-sm font-bold text-gray-800 mb-2">Uji Kirim</h3>
                    <div class="flex gap-2">
                        <input v-model="nomorTes" placeholder="08xxxxxxxxxx" class="flex-1 px-3 py-2 rounded-lg border border-gray-200 text-sm" />
                        <button @click="kirimTes" :disabled="!nomorTes || testing"
                            class="px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white text-sm font-semibold">
                            {{ testing ? '…' : 'Kirim' }}
                        </button>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-2">Kirim 1 pesan contoh ke nomor ini untuk cek koneksi Fonnte.</p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    setting:     { type: Object, default: () => ({}) },
    preview:     { type: Object, default: () => ({}) },
    fonnte:      { type: Object, default: () => ({}) },
    placeholder: { type: Object, default: () => ({}) },
})

const templates = [
    { key: 'tpl_controlling', previewKey: 'controlling', label: 'Absensi Kegiatan (Smart Controlling)', short: 'Kegiatan', desc: 'Dikirim saat santri hadir/telat/alfa di kegiatan.' },
    { key: 'tpl_mengajar',    previewKey: 'mengajar',    label: 'Absensi Pembelajaran (Absen Mengajar)', short: 'Belajar', desc: 'Dikirim saat absen santri di kelas/tahfidz/tahsin.' },
    { key: 'tpl_pelanggaran', previewKey: 'pelanggaran', label: 'Pelanggaran (Smart Eksekusi)', short: 'Pelanggaran', desc: 'Dikirim saat guru melaporkan pelanggaran.' },
    { key: 'tpl_apresiasi',   previewKey: 'apresiasi',   label: 'Apresiasi (Smart Eksekusi)', short: 'Apresiasi', desc: 'Dikirim saat guru memberi apresiasi.' },
    { key: 'tpl_konselor',    previewKey: 'konselor',    label: 'Konseling (Smart Eksekusi)', short: 'Konselor', desc: 'Dikirim saat laporan konselor.' },
]

const form = reactive({ ...props.setting })
const tab = ref('controlling')
const nomorTes = ref('')
const saving = ref(false)
const testing = ref(false)

function simpan() {
    saving.value = true
    router.patch(route('admin.smart-payroll.setting-wa.update'), { ...form }, {
        preserveScroll: true, onFinish: () => (saving.value = false),
    })
}
function kirimTes() {
    testing.value = true
    router.post(route('admin.smart-payroll.setting-wa.test'), { nomor: nomorTes.value }, {
        preserveScroll: true, onFinish: () => (testing.value = false),
    })
}
</script>
