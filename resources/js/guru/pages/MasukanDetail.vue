<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import api from '../api'
import { kompresFoto } from '../foto'
import PageHeader from '../components/PageHeader.vue'

const route = useRoute()
const d = ref(null)
const loading = ref(true)
const error = ref('')

const isi = ref('')
const foto = ref([])
const preview = ref([])
const saving = ref(false)
const bawah = ref(null)

const WARNA = {
    baru: 'text-sky-700 bg-sky-50',
    diproses: 'text-amber-700 bg-amber-50',
    selesai: 'text-emerald-700 bg-emerald-50',
    ditolak: 'text-gray-500 bg-gray-100',
}

async function load() {
    loading.value = true; error.value = ''
    try {
        d.value = (await api.get('/masukan/' + route.params.id)).data.data
        await nextTick(); bawah.value?.scrollIntoView({ block: 'end' })
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal memuat percakapan.'
    } finally { loading.value = false }
}
onMounted(load)

async function pilihFoto(e) {
    const files = Array.from(e.target.files || []).slice(0, 3)
    foto.value = []; preview.value = []
    for (const f of files) {
        const k = await kompresFoto(f)
        foto.value.push(k); preview.value.push(URL.createObjectURL(k))
    }
}

async function kirim() {
    if (!isi.value.trim()) return
    saving.value = true; error.value = ''
    try {
        const fd = new FormData()
        fd.append('isi', isi.value.trim())
        foto.value.forEach(f => fd.append('foto[]', f))
        const res = await api.post(`/masukan/${route.params.id}/balas`, fd,
            { headers: { 'Content-Type': 'multipart/form-data' } })
        d.value = res.data.data
        isi.value = ''; foto.value = []; preview.value = []
        await nextTick(); bawah.value?.scrollIntoView({ behavior: 'smooth', block: 'end' })
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal mengirim pesan.'
    } finally { saving.value = false }
}
</script>

<template>
    <div>
        <PageHeader title="Masukan" />

        <div v-if="loading" class="pt-10 flex justify-center">
            <div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div>
        </div>
        <div v-else-if="!d" class="pt-8 text-center text-sm text-gray-500">{{ error }}</div>

        <template v-else>
            <!-- Kepala utas -->
            <div class="rounded-2xl bg-white border border-gray-100 p-3 mb-3">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-[13px] font-extrabold text-gray-800">{{ d.judul }}</p>
                        <p class="text-[10px] text-gray-400">{{ d.kategori_label }} · {{ d.dibuat }}</p>
                    </div>
                    <span :class="['shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold', WARNA[d.status]]">
                        {{ d.status_label }}
                    </span>
                </div>
            </div>

            <p v-if="error" class="text-sm text-red-600 bg-red-50 rounded-xl px-3 py-2 mb-3">{{ error }}</p>

            <!-- Percakapan -->
            <div class="space-y-2 mb-4">
                <template v-for="p in d.pesan" :key="p.id">
                    <!-- Catatan sistem (perubahan status) tampil di tengah, bukan gelembung -->
                    <p v-if="p.sistem" class="text-center text-[10px] text-gray-400 py-1 whitespace-pre-line">
                        {{ p.isi }} · {{ p.waktu }}
                    </p>

                    <div v-else :class="['flex', p.tipe === 'guru' ? 'justify-end' : 'justify-start']">
                        <div :class="['max-w-[82%] rounded-2xl px-3 py-2',
                            p.tipe === 'guru' ? 'bg-[#0C78FF] text-white' : 'bg-white border border-gray-100']">
                            <p v-if="p.tipe !== 'guru'" class="text-[10px] font-bold mb-0.5"
                                :class="p.tipe === 'bot' ? 'text-violet-600' : 'text-gray-500'">
                                {{ p.tipe === 'bot' ? '🤖 ' : '' }}{{ p.nama }}
                            </p>
                            <p class="text-[13px] whitespace-pre-line break-words"
                                :class="p.tipe === 'guru' ? 'text-white' : 'text-gray-800'">{{ p.isi }}</p>

                            <div v-if="p.lampiran.length" class="flex flex-wrap gap-1.5 mt-2">
                                <a v-for="(u, i) in p.lampiran" :key="i" :href="u" target="_blank">
                                    <img :src="u" class="w-20 h-20 object-cover rounded-lg" />
                                </a>
                            </div>

                            <p class="text-[9px] mt-1" :class="p.tipe === 'guru' ? 'text-white/70' : 'text-gray-300'">
                                {{ p.waktu }}
                            </p>
                        </div>
                    </div>
                </template>
                <div ref="bawah"></div>
            </div>

            <!-- Kotak balas -->
            <div v-if="d.ditutup" class="rounded-2xl bg-gray-50 border border-gray-100 p-3 text-center">
                <p class="text-[11px] text-gray-500">
                    Masukan ini sudah <b>{{ d.status_label.toLowerCase() }}</b>.
                    Bila masih ada kendala, silakan kirim masukan baru.
                </p>
            </div>

            <div v-else class="rounded-2xl bg-white border border-gray-100 p-3">
                <div v-if="preview.length" class="flex gap-2 mb-2">
                    <img v-for="(p, i) in preview" :key="i" :src="p" class="w-14 h-14 object-cover rounded-lg" />
                </div>
                <textarea v-model="isi" rows="2" maxlength="2000" placeholder="Tulis pesan…"
                    class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF] mb-2"></textarea>
                <div class="flex items-center gap-2">
                    <label class="shrink-0 px-3 py-2 rounded-xl bg-gray-100 text-gray-600 text-xs font-bold cursor-pointer">
                        📎 Foto
                        <input type="file" accept="image/*" multiple class="hidden" @change="pilihFoto" />
                    </label>
                    <button @click="kirim" :disabled="saving || !isi.trim()"
                        class="flex-1 min-w-0 py-2 rounded-xl bg-[#0C78FF] text-white text-sm font-bold disabled:opacity-50">
                        {{ saving ? 'Mengirim…' : 'Kirim' }}
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>
