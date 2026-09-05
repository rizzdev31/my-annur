<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api'
import { kompresFoto } from '../foto'
import PageHeader from '../components/PageHeader.vue'

const router = useRouter()
const list = ref([])
const kategoriOpsi = ref({})
const loading = ref(true)
const error = ref('')
const msg = ref(null)

// Form utas baru
const buka = ref(false)
const form = ref({ kategori: 'saran', judul: '', isi: '' })
const foto = ref([])
const preview = ref([])
const saving = ref(false)

const WARNA = {
    baru: 'text-sky-700 bg-sky-50',
    diproses: 'text-amber-700 bg-amber-50',
    selesai: 'text-emerald-700 bg-emerald-50',
    ditolak: 'text-gray-500 bg-gray-100',
}
const IKON = { bug: '🐞', saran: '💡', pertanyaan: '❓', lainnya: '📝' }

async function load() {
    loading.value = true; error.value = ''
    try {
        const d = (await api.get('/masukan')).data.data
        list.value = d.masukan ?? []
        kategoriOpsi.value = d.kategori ?? {}
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal memuat masukan.'
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

function reset() {
    form.value = { kategori: 'saran', judul: '', isi: '' }
    foto.value = []; preview.value = []
}

async function kirim() {
    if (!form.value.judul.trim() || !form.value.isi.trim()) {
        msg.value = { ok: false, text: 'Judul dan isi wajib diisi.' }; return
    }
    saving.value = true; msg.value = null
    try {
        const fd = new FormData()
        fd.append('kategori', form.value.kategori)
        fd.append('judul', form.value.judul.trim())
        fd.append('isi', form.value.isi.trim())
        foto.value.forEach(f => fd.append('foto[]', f))
        const res = await api.post('/masukan', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
        buka.value = false; reset()
        msg.value = { ok: true, text: res.data.message || 'Masukan terkirim.' }
        await load()
    } catch (e) {
        msg.value = { ok: false, text: e.response?.data?.message || 'Gagal mengirim masukan.' }
    } finally { saving.value = false }
}
</script>

<template>
    <div>
        <PageHeader title="Saran & Masukan" />

        <p v-if="msg" :class="msg.ok ? 'text-emerald-700 bg-emerald-50' : 'text-red-600 bg-red-50'"
            class="text-sm rounded-xl px-3 py-2 mb-3">{{ msg.text }}</p>

        <div v-if="loading" class="pt-10 flex justify-center">
            <div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div>
        </div>
        <div v-else-if="error" class="pt-8 text-center">
            <p class="text-sm text-gray-500">{{ error }}</p>
            <button @click="load" class="mt-3 px-4 py-2 rounded-xl bg-[#0C78FF] text-white text-sm font-semibold">Coba lagi</button>
        </div>

        <template v-else>
            <button @click="buka = true"
                class="w-full py-3 rounded-2xl bg-[#0C78FF] text-white font-bold text-sm mb-3 active:scale-[0.99] transition">
                + Kirim Saran / Laporkan Bug
            </button>

            <p class="text-[11px] text-gray-400 mb-3">
                Menemukan kendala atau punya usulan? Kirim di sini — bisa sertakan foto layar.
                Admin akan membalas di percakapan yang sama.
            </p>

            <div v-if="!list.length" class="pt-12 text-center text-sm text-gray-400">
                Belum ada masukan. Yang pertama dari Anda?
            </div>

            <ul v-else class="space-y-2">
                <li v-for="m in list" :key="m.id" @click="router.push('/masukan/' + m.id)"
                    class="rounded-2xl bg-white border border-gray-100 p-3 active:bg-gray-50 cursor-pointer">
                    <div class="flex items-start gap-2">
                        <span class="shrink-0 text-lg leading-none mt-0.5">{{ IKON[m.kategori] || '📝' }}</span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5">
                                <p class="text-[13px] font-bold text-gray-800 truncate">{{ m.judul }}</p>
                                <span v-if="m.belum_dibaca" class="shrink-0 w-2 h-2 rounded-full bg-red-500"></span>
                            </div>
                            <p class="text-[11px] text-gray-400 truncate">{{ m.cuplikan }}</p>
                            <p class="text-[10px] text-gray-300 mt-0.5">{{ m.waktu }}</p>
                        </div>
                        <span :class="['shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold', WARNA[m.status]]">
                            {{ m.status_label }}
                        </span>
                    </div>
                </li>
            </ul>
        </template>

        <!-- Sheet: buat masukan baru -->
        <div v-if="buka" class="fixed inset-0 z-[70] flex items-end justify-center" style="background: rgba(0,0,0,.55)">
            <div class="w-full max-w-md bg-white rounded-t-3xl p-5 pb-8 safe-b max-h-[92vh] overflow-y-auto">
                <div class="w-10 h-1 bg-gray-200 rounded-full mx-auto mb-4"></div>
                <h3 class="text-base font-extrabold text-gray-900 mb-3">Kirim Masukan</h3>

                <label class="block text-[11px] font-medium text-gray-600 mb-1">Jenis</label>
                <div class="grid grid-cols-2 gap-2 mb-3">
                    <button v-for="(label, key) in kategoriOpsi" :key="key" @click="form.kategori = key"
                        :class="['py-2 rounded-xl text-[12px] font-bold border transition',
                            form.kategori === key ? 'bg-[#0C78FF] text-white border-[#0C78FF]' : 'bg-white text-gray-600 border-gray-200']">
                        {{ IKON[key] }} {{ label }}
                    </button>
                </div>

                <label class="block text-[11px] font-medium text-gray-600 mb-1">Judul singkat <span class="text-red-500">*</span></label>
                <input v-model="form.judul" type="text" maxlength="150" placeholder="Mis. Tombol simpan tidak berfungsi"
                    class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF] mb-3" />

                <label class="block text-[11px] font-medium text-gray-600 mb-1">Penjelasan <span class="text-red-500">*</span></label>
                <textarea v-model="form.isi" rows="4" maxlength="2000"
                    placeholder="Ceritakan apa yang terjadi atau usulan Anda. Untuk bug, sebutkan halaman & langkahnya."
                    class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF] mb-3"></textarea>

                <label class="block text-[11px] font-medium text-gray-600 mb-1">Foto bukti (opsional, maks 3)</label>
                <div v-if="preview.length" class="flex gap-2 mb-2">
                    <img v-for="(p, i) in preview" :key="i" :src="p" class="w-16 h-16 object-cover rounded-lg" />
                </div>
                <input type="file" accept="image/*" multiple @change="pilihFoto"
                    class="block w-full text-xs text-gray-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-[#0C78FF]/10 file:text-[#0C78FF] file:text-xs file:font-semibold mb-4" />

                <div class="flex gap-2">
                    <button @click="buka = false" class="flex-1 py-3 rounded-xl bg-gray-100 text-gray-600 font-bold text-sm">Batal</button>
                    <button @click="kirim" :disabled="saving"
                        class="flex-1 py-3 rounded-xl bg-[#0C78FF] text-white font-bold text-sm disabled:opacity-60">
                        {{ saving ? 'Mengirim…' : 'Kirim' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
