<template>
    <AdminLayout title="Saran & Masukan" subtitle="Detail">

        <Head :title="masukan.judul" />

        <Link :href="route('admin.masukan.index')" class="text-sm text-gray-400 hover:text-gray-600">← Kembali ke daftar</Link>

        <div class="flex items-start justify-between gap-4 mt-3 mb-5">
            <div class="min-w-0">
                <h2 class="text-xl font-semibold text-gray-900">{{ masukan.judul }}</h2>
                <p class="text-sm text-gray-400 mt-0.5">
                    {{ masukan.kategori_label }} · dari <b>{{ masukan.pelapor }}</b> · {{ masukan.dibuat }}
                    <span v-if="masukan.modul"> · modul {{ masukan.modul }}</span>
                </p>
            </div>
            <span :class="['shrink-0 px-3 py-1 rounded-full text-xs font-semibold', warnaStatus(masukan.status)]">
                {{ opsi.status[masukan.status] }}
            </span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

            <!-- Percakapan -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl border border-gray-200 p-4 space-y-3 max-h-[60vh] overflow-y-auto">
                    <template v-for="p in masukan.pesan" :key="p.id">
                        <p v-if="p.sistem" class="text-center text-xs text-gray-400 py-1 whitespace-pre-line">
                            {{ p.isi }} · {{ p.waktu }}
                        </p>

                        <div v-else :class="['flex', p.tipe === 'guru' ? 'justify-start' : 'justify-end']">
                            <div :class="['max-w-[80%] rounded-2xl px-3.5 py-2.5',
                                p.tipe === 'guru' ? 'bg-gray-100' : (p.tipe === 'bot' ? 'bg-violet-50 border border-violet-100' : 'bg-indigo-600 text-white')]">
                                <p class="text-[11px] font-semibold mb-0.5"
                                    :class="p.tipe === 'guru' ? 'text-gray-500' : (p.tipe === 'bot' ? 'text-violet-600' : 'text-white/80')">
                                    {{ p.tipe === 'bot' ? '🤖 ' : '' }}{{ p.nama }}
                                </p>
                                <p class="text-sm whitespace-pre-line break-words"
                                    :class="p.tipe === 'admin' ? 'text-white' : 'text-gray-800'">{{ p.isi }}</p>

                                <div v-if="p.lampiran.length" class="flex flex-wrap gap-2 mt-2">
                                    <a v-for="(u, i) in p.lampiran" :key="i" :href="u" target="_blank">
                                        <img :src="u" class="w-24 h-24 object-cover rounded-lg border border-black/5" />
                                    </a>
                                </div>

                                <p class="text-[10px] mt-1" :class="p.tipe === 'admin' ? 'text-white/60' : 'text-gray-400'">
                                    {{ p.waktu }}
                                </p>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Balas -->
                <div v-if="masukan.ditutup" class="mt-3 rounded-2xl bg-gray-50 border border-gray-200 px-4 py-3 text-sm text-gray-500">
                    Utas ini sudah ditutup. Ubah status kembali ke <b>Diproses</b> bila ingin melanjutkan percakapan.
                </div>

                <form v-else @submit.prevent="kirim" class="mt-3 bg-white rounded-2xl border border-gray-200 p-4">
                    <textarea v-model="formBalas.isi" rows="3" maxlength="2000" placeholder="Tulis balasan untuk pelapor…"
                        class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-indigo-500"></textarea>
                    <p v-if="formBalas.errors.isi" class="text-xs text-red-500 mt-1">{{ formBalas.errors.isi }}</p>

                    <div class="flex items-center gap-3 mt-3">
                        <input type="file" accept="image/*" multiple @input="formBalas.foto = Array.from($event.target.files)"
                            class="text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-600 file:text-xs file:font-semibold" />
                        <button type="submit" :disabled="formBalas.processing || !formBalas.isi.trim()"
                            class="ml-auto px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-50">
                            {{ formBalas.processing ? 'Mengirim…' : 'Kirim Balasan' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Panel tindakan -->
            <div class="space-y-4">
                <div class="bg-white rounded-2xl border border-gray-200 p-4">
                    <h3 class="text-sm font-semibold text-gray-800 mb-3">Tindak Lanjut</h3>

                    <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                    <select v-model="formStatus.status"
                        class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm outline-none focus:border-indigo-500 mb-3">
                        <option v-for="(l, k) in opsi.status" :key="k" :value="k">{{ l }}</option>
                    </select>

                    <label class="block text-xs font-medium text-gray-600 mb-1">Catatan tindak lanjut (opsional)</label>
                    <textarea v-model="formStatus.catatan" rows="3" maxlength="1000"
                        placeholder="Mis. sudah diperbaiki pada rilis hari ini."
                        class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm outline-none focus:border-indigo-500 mb-3"></textarea>

                    <button @click="simpanStatus" :disabled="formStatus.processing"
                        class="w-full py-2.5 rounded-xl bg-gray-900 hover:bg-gray-800 text-white text-sm font-semibold disabled:opacity-50">
                        {{ formStatus.processing ? 'Menyimpan…' : 'Simpan Status' }}
                    </button>

                    <p class="text-[11px] text-gray-400 mt-2">
                        Perubahan status ikut tercatat di percakapan dan diberitahukan ke pelapor.
                    </p>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 p-4 text-sm">
                    <h3 class="text-sm font-semibold text-gray-800 mb-2">Informasi</h3>
                    <dl class="space-y-1.5 text-xs">
                        <div class="flex justify-between gap-2">
                            <dt class="text-gray-400">Prioritas</dt><dd class="font-medium text-gray-700 capitalize">{{ masukan.prioritas }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-gray-400">Ditangani</dt><dd class="font-medium text-gray-700">{{ masukan.ditangani_oleh || '—' }}</dd>
                        </div>
                        <div v-if="masukan.catatan_admin" class="pt-1">
                            <dt class="text-gray-400 mb-0.5">Catatan</dt>
                            <dd class="text-gray-700 whitespace-pre-line">{{ masukan.catatan_admin }}</dd>
                        </div>
                    </dl>
                </div>

                <button @click="hapus" class="w-full py-2.5 rounded-xl bg-red-50 hover:bg-red-100 text-red-600 text-sm font-semibold">
                    Hapus Masukan
                </button>
            </div>
        </div>

    </AdminLayout>
</template>

<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ masukan: Object, opsi: Object })

const formBalas = useForm({ isi: '', foto: [] })
const formStatus = useForm({ status: props.masukan.status, catatan: '' })

function kirim() {
    formBalas.post(route('admin.masukan.balas', props.masukan.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => formBalas.reset(),
    })
}

function simpanStatus() {
    formStatus.patch(route('admin.masukan.status', props.masukan.id), { preserveScroll: true })
}

function hapus() {
    if (!confirm('Hapus masukan ini beserta seluruh percakapannya? Tindakan ini tidak bisa dibatalkan.')) return
    router.delete(route('admin.masukan.destroy', props.masukan.id))
}

const warnaStatus = (s) => ({
    baru: 'bg-sky-50 text-sky-700',
    diproses: 'bg-amber-50 text-amber-700',
    selesai: 'bg-emerald-50 text-emerald-700',
    ditolak: 'bg-gray-100 text-gray-500',
}[s] ?? 'bg-gray-100 text-gray-500')
</script>
