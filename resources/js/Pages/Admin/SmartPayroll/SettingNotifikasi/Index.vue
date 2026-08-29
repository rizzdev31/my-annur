<template>
    <AdminLayout title="Setting Notifikasi" subtitle="Pengaturan">
        <Head title="Setting Notifikasi" />

        <div class="mb-5">
            <h1 class="text-xl font-bold text-gray-800">Setting Notifikasi</h1>
            <p class="text-sm text-gray-400 mt-0.5">
                Atur notifikasi per kejadian: aktif/nonaktif, kanal, pengingat & eskalasi.
                Event <b>Wajib</b> selalu aktif. WhatsApp &amp; Push menyusul (fase berikutnya).
            </p>
        </div>

        <!-- Kirim Pengumuman (broadcast manual) -->
        <div class="bg-white rounded-2xl border border-indigo-100 p-4 mb-6">
            <h2 class="font-semibold text-gray-800 mb-0.5">Kirim Pengumuman</h2>
            <p class="text-xs text-gray-400 mb-3">Broadcast pesan langsung ke lonceng guru.</p>
            <div class="grid gap-3">
                <input v-model="bc.judul" maxlength="150" placeholder="Judul pengumuman"
                    class="w-full rounded-lg border-gray-200 text-sm" />
                <textarea v-model="bc.pesan" rows="3" maxlength="1000" placeholder="Isi pesan…"
                    class="w-full rounded-lg border-gray-200 text-sm"></textarea>
                <div class="flex flex-wrap items-center gap-4 text-sm">
                    <span class="text-gray-500">Kirim ke:</span>
                    <label class="flex items-center gap-1.5"><input type="radio" value="semua" v-model="bc.target" /> Semua guru</label>
                    <label class="flex items-center gap-1.5"><input type="radio" value="jabatan" v-model="bc.target" /> Per jabatan</label>
                    <label class="flex items-center gap-1.5"><input type="radio" value="individu" v-model="bc.target" /> Per individu</label>
                </div>
                <div v-if="bc.target === 'jabatan'" class="flex flex-wrap gap-2">
                    <label v-for="j in jabatan" :key="j.id"
                        class="text-xs px-2.5 py-1 rounded-lg border cursor-pointer select-none"
                        :class="bc.jabatan_ids.includes(j.id) ? 'border-indigo-400 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-600'">
                        <input type="checkbox" :value="j.id" v-model="bc.jabatan_ids" class="hidden" /> {{ j.nama }}
                    </label>
                </div>
                <div v-if="bc.target === 'individu'" class="max-h-44 overflow-auto border border-gray-100 rounded-lg p-2 grid gap-1">
                    <label v-for="g in guru" :key="g.id" class="flex items-center gap-2 text-sm">
                        <input type="checkbox" :value="g.id" v-model="bc.tenaga_pendidik_ids" />
                        {{ g.nama }} <span class="text-xs text-gray-400">· {{ g.jabatan }}</span>
                    </label>
                </div>
                <div class="flex items-center gap-3">
                    <button @click="kirimPengumuman" :disabled="bc.processing || !bc.judul || !bc.pesan"
                        class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50">
                        {{ bc.processing ? 'Mengirim…' : 'Kirim Pengumuman' }}
                    </button>
                    <span v-if="bc.errors.target || bc.errors.jabatan_ids || bc.errors.tenaga_pendidik_ids" class="text-xs text-red-500">Pilih penerima yang valid.</span>
                </div>
            </div>
        </div>

        <div v-for="(events, kategori) in grup" :key="kategori" class="mb-6">
            <h2 class="text-xs font-bold uppercase tracking-wide text-gray-400 mb-2">{{ kategori }}</h2>
            <div class="grid gap-3">
                <div v-for="ev in events" :key="ev.id"
                    class="bg-white rounded-2xl border border-gray-100 p-4">
                    <div class="flex items-start gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="font-semibold text-gray-800">{{ ev.nama }}</h3>
                                <span v-if="ev.wajib" class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-100 text-amber-700">WAJIB</span>
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-400">{{ ev.event_kode }}</span>
                            </div>
                            <p class="text-xs text-gray-400 mt-0.5">{{ ev.deskripsi }}</p>
                            <p class="text-[11px] text-gray-400 mt-1">Penerima: {{ (ev.penerima || []).join(', ') || '—' }}</p>
                        </div>
                        <!-- Aktif toggle -->
                        <button type="button" @click="ev.wajib || (state[ev.id].aktif = !state[ev.id].aktif)"
                            :disabled="ev.wajib"
                            :class="['relative rounded-full transition-colors shrink-0', state[ev.id].aktif ? 'bg-indigo-600' : 'bg-gray-300', ev.wajib ? 'opacity-60 cursor-not-allowed' : '']"
                            style="height:24px;width:44px;" :title="ev.wajib ? 'Event wajib selalu aktif' : 'Aktif/Nonaktif'">
                            <span :class="['absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform', state[ev.id].aktif ? 'translate-x-5' : 'translate-x-0.5']"></span>
                        </button>
                    </div>

                    <div class="mt-3 flex flex-wrap items-end gap-4">
                        <!-- Kanal -->
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" v-model="state[ev.id].kanal.in_app" class="rounded" /> In-app
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-300 cursor-not-allowed" title="Segera">
                            <input type="checkbox" disabled /> WhatsApp <span class="text-[10px]">(segera)</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-300 cursor-not-allowed" title="Segera">
                            <input type="checkbox" disabled /> Push <span class="text-[10px]">(segera)</span>
                        </label>

                        <!-- Maks per hari -->
                        <div>
                            <label class="block text-[11px] text-gray-500 mb-0.5">Maks/hari</label>
                            <input v-model.number="state[ev.id].maks_per_hari" type="number" min="0" max="50"
                                class="w-20 rounded-lg border-gray-200 text-sm" placeholder="∞" />
                        </div>

                        <!-- Reminder (bila event pengingat) -->
                        <template v-if="ev.reminder">
                            <div>
                                <label class="block text-[11px] text-gray-500 mb-0.5">Sebelum (mnt)</label>
                                <input v-model.number="state[ev.id].reminder.sebelum_menit" type="number" min="0" class="w-20 rounded-lg border-gray-200 text-sm" />
                            </div>
                            <div>
                                <label class="block text-[11px] text-gray-500 mb-0.5">Ulang (mnt)</label>
                                <input v-model.number="state[ev.id].reminder.ulang_menit" type="number" min="0" class="w-20 rounded-lg border-gray-200 text-sm" />
                            </div>
                            <div>
                                <label class="block text-[11px] text-gray-500 mb-0.5">Batas (mnt)</label>
                                <input v-model.number="state[ev.id].reminder.batas_menit" type="number" min="0" class="w-20 rounded-lg border-gray-200 text-sm" />
                            </div>
                        </template>

                        <!-- Eskalasi -->
                        <div v-if="ev.eskalasi">
                            <label class="block text-[11px] text-gray-500 mb-0.5">Eskalasi setelah (mnt)</label>
                            <input v-model.number="state[ev.id].eskalasi.setelah_menit" type="number" min="0" class="w-24 rounded-lg border-gray-200 text-sm" />
                        </div>

                        <button @click="simpan(ev)" :disabled="saving === ev.id"
                            class="ml-auto px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50">
                            {{ saving === ev.id ? 'Menyimpan…' : 'Simpan' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    grup: { type: Object, default: () => ({}) },
    jabatan: { type: Array, default: () => [] },
    guru: { type: Array, default: () => [] },
})

// Broadcast pengumuman manual.
const bc = useForm({ judul: '', pesan: '', target: 'semua', jabatan_ids: [], tenaga_pendidik_ids: [], link: '' })
function kirimPengumuman() {
    bc.post(route('admin.smart-payroll.setting-notifikasi.broadcast'), {
        preserveScroll: true,
        onSuccess: () => { bc.reset('judul', 'pesan'); bc.jabatan_ids = []; bc.tenaga_pendidik_ids = [] },
    })
}

// State editable per event (id → salinan).
const state = reactive({})
for (const events of Object.values(props.grup)) {
    for (const ev of events) {
        state[ev.id] = {
            aktif: ev.aktif,
            kanal: { in_app: ev.kanal?.in_app ?? true, wa: !!ev.kanal?.wa, push: !!ev.kanal?.push },
            maks_per_hari: ev.maks_per_hari,
            reminder: ev.reminder ? { ...ev.reminder } : null,
            eskalasi: ev.eskalasi ? { ...ev.eskalasi } : null,
        }
    }
}

const saving = ref(null)
function simpan(ev) {
    saving.value = ev.id
    router.put(route('admin.smart-payroll.setting-notifikasi.update', ev.id), state[ev.id], {
        preserveScroll: true,
        onFinish: () => (saving.value = null),
    })
}
</script>
