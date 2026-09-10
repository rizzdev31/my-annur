<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../api'
import PageHeader from '../components/PageHeader.vue'

const route = useRoute()
const router = useRouter()

const d = ref(null)
const loading = ref(true)
const error = ref('')
const msg = ref(null)
const saving = ref(false)
const setuju = ref(false)          // toggle konfirmasi — wajib sebelum simpan
const buka = ref({})               // santri yang panel juz-nya dibentangkan

// isi[santri_id] = { jumlah, pola, juz:Set, last_surah, last_ayat }
const isi = ref({})

// Pola pengisian cepat. Diambil dari kebiasaan nyata di data: mayoritas santri
// memulai dari juz 30 lalu 29, baru lanjut juz 1 ke depan.
const POLA = [
    { key: 'belakang', label: '30 → 29 → 28…' },
    { key: 'amma_maju', label: '30, 29, lalu 1 → 2…' },
    { key: 'depan', label: '1 → 2 → 3…' },
]

function urutanJuz(pola) {
    if (pola === 'depan') return Array.from({ length: 30 }, (_, i) => i + 1)
    if (pola === 'amma_maju') return [30, 29, ...Array.from({ length: 28 }, (_, i) => i + 1)]
    return Array.from({ length: 30 }, (_, i) => 30 - i)
}

function terapkanPola(id) {
    const s = isi.value[id]
    const n = Math.max(0, Math.min(30, parseInt(s.jumlah) || 0))
    s.juz = new Set(urutanJuz(s.pola).slice(0, n))
}

function toggleJuz(id, j) {
    const s = isi.value[id]
    s.juz.has(j) ? s.juz.delete(j) : s.juz.add(j)
    s.jumlah = s.juz.size          // jumlah mengikuti centang manual
}

const juzTerurut = (id) => [...isi.value[id].juz].sort((a, b) => a - b)

const adaIsi = computed(() =>
    Object.values(isi.value).filter(s => s.juz.size > 0 || (s.last_surah && s.last_ayat)).length)

async function load() {
    loading.value = true; error.value = ''
    try {
        d.value = (await api.get(`/education/tahfidz/jadwal/${route.params.jadwalId}/sinkron`)).data.data
        const o = {}
        for (const s of d.value.belum) {
            o[s.santri_id] = { jumlah: 0, pola: 'amma_maju', juz: new Set(), last_surah: '', last_ayat: '' }
        }
        isi.value = o
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal memuat daftar santri.'
    } finally { loading.value = false }
}
onMounted(load)

async function simpan() {
    const items = d.value.belum
        .map(s => {
            const v = isi.value[s.santri_id]
            const juz = [...v.juz]
            if (!juz.length && !(v.last_surah && v.last_ayat)) return null
            return {
                santri_id: s.santri_id,
                juz_lulus: juz,
                last_surah: v.last_surah ? Number(v.last_surah) : null,
                last_ayat: v.last_ayat ? Number(v.last_ayat) : null,
            }
        })
        .filter(Boolean)

    if (!items.length) { msg.value = { ok: false, text: 'Belum ada santri yang diisi.' }; return }
    if (!confirm(
        `Simpan pencapaian awal ${items.length} santri?\n\n`
        + 'Data ini hanya bisa diisi SEKALI per santri. Setelah tersimpan, perubahan '
        + 'harus lewat admin.\n\nLanjutkan?'
    )) return

    saving.value = true; msg.value = null
    try {
        const res = await api.post('/education/tahfidz/sinkron', {
            jadwal_id: Number(route.params.jadwalId), items,
        })
        msg.value = { ok: true, text: res.data.message }
        setuju.value = false
        await load()
    } catch (e) {
        msg.value = { ok: false, text: e.response?.data?.message || 'Gagal menyimpan.' }
    } finally { saving.value = false }
}
</script>

<template>
    <div>
        <PageHeader title="Sinkron Hafalan Awal" />

        <div v-if="loading" class="pt-16 flex justify-center">
            <div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div>
        </div>
        <div v-else-if="!d" class="pt-8 text-center text-sm text-gray-500">{{ error }}</div>

        <template v-else>
            <p v-if="msg" :class="msg.ok ? 'text-emerald-700 bg-emerald-50' : 'text-red-600 bg-red-50'"
                class="text-sm rounded-xl px-3 py-2 mb-3">{{ msg.text }}</p>

            <div class="rounded-2xl bg-sky-50 border border-sky-100 p-3 mb-3">
                <p class="text-[12px] font-extrabold text-sky-800">{{ d.kelas }}</p>
                <p class="text-[11px] text-sky-700 leading-snug mt-0.5">
                    Catat hafalan santri yang <b>sudah dimiliki sebelum tercatat di sistem</b>.
                    Cukup <b>sekali</b> — setelah tersimpan, santri hilang dari daftar ini dan
                    pertambahan berikutnya dihitung dari setoran harian.
                </p>
                <p class="text-[11px] text-sky-600 mt-1">
                    {{ d.sudah.length }} dari {{ d.total_santri }} santri sudah tercatat ·
                    <b>{{ d.belum.length }} belum</b>
                </p>
            </div>

            <div v-if="!d.belum.length" class="rounded-2xl bg-emerald-50 border border-emerald-100 p-4 text-center">
                <p class="text-sm font-bold text-emerald-700">✓ Semua santri sudah tercatat</p>
                <p class="text-[11px] text-emerald-600 mt-1">Tidak ada yang perlu diisi di kelas ini.</p>
                <button @click="router.back()" class="mt-3 px-4 py-2 rounded-xl bg-white text-emerald-700 text-xs font-bold">Kembali</button>
            </div>

            <template v-else>
                <div v-for="s in d.belum" :key="s.santri_id"
                    class="rounded-2xl bg-white border border-gray-100 p-3 mb-2">
                    <p class="text-[13px] font-bold text-gray-800 truncate">{{ s.nama }}</p>

                    <!-- Isian cepat: jumlah juz + pola urutan -->
                    <div class="flex items-center gap-2 mt-2">
                        <input v-model="isi[s.santri_id].jumlah" @input="terapkanPola(s.santri_id)"
                            type="number" min="0" max="30" inputmode="numeric" placeholder="0"
                            class="w-16 shrink-0 px-2 py-2 rounded-xl border border-gray-200 text-sm text-center outline-none" />
                        <span class="text-[11px] text-gray-400 shrink-0">juz</span>
                        <select v-model="isi[s.santri_id].pola" @change="terapkanPola(s.santri_id)"
                            class="flex-1 min-w-0 px-2 py-2 rounded-xl border border-gray-200 text-[12px] outline-none">
                            <option v-for="p in POLA" :key="p.key" :value="p.key">{{ p.label }}</option>
                        </select>
                    </div>

                    <p v-if="isi[s.santri_id].juz.size" class="text-[11px] text-emerald-700 mt-1.5">
                        Juz: {{ juzTerurut(s.santri_id).join(', ') }}
                    </p>
                    <p v-else class="text-[11px] text-gray-300 mt-1.5">Belum diisi — santri ini akan dilewati.</p>

                    <button @click="buka[s.santri_id] = !buka[s.santri_id]"
                        class="mt-1.5 text-[11px] font-bold text-[#0C78FF]">
                        {{ buka[s.santri_id] ? 'Tutup rincian' : 'Atur juz manual / posisi terakhir' }}
                    </button>

                    <div v-if="buka[s.santri_id]" class="mt-2 pt-2 border-t border-gray-50">
                        <!-- Grid juz: untuk santri yang hafalannya tidak berurutan -->
                        <div class="grid grid-cols-10 gap-1 mb-2">
                            <button v-for="j in 30" :key="j" @click="toggleJuz(s.santri_id, j)"
                                :class="isi[s.santri_id].juz.has(j)
                                    ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-500'"
                                class="py-1.5 rounded text-[10px] font-bold">{{ j }}</button>
                        </div>

                        <p class="text-[10px] text-gray-400 mb-1">
                            Posisi terakhir bila sedang di tengah juz (opsional) — jangan isi juz yang sudah dicentang.
                        </p>
                        <div class="flex gap-2">
                            <input v-model="isi[s.santri_id].last_surah" type="number" min="1" max="114"
                                inputmode="numeric" placeholder="No. surah"
                                class="flex-1 min-w-0 px-2 py-2 rounded-xl border border-gray-200 text-[12px] outline-none" />
                            <input v-model="isi[s.santri_id].last_ayat" type="number" min="1"
                                inputmode="numeric" placeholder="Ayat"
                                class="flex-1 min-w-0 px-2 py-2 rounded-xl border border-gray-200 text-[12px] outline-none" />
                        </div>
                    </div>
                </div>

                <!-- Konfirmasi wajib sebelum simpan -->
                <div class="rounded-2xl bg-amber-50 border border-amber-200 p-3 mt-3">
                    <label class="flex items-start gap-2.5 cursor-pointer">
                        <input v-model="setuju" type="checkbox" class="mt-0.5 w-4 h-4 shrink-0 accent-amber-600" />
                        <span class="text-[11px] text-amber-800 leading-snug">
                            Saya sudah memastikan jumlah juz di atas benar. Saya paham data ini
                            <b>hanya bisa diisi sekali</b> dan menjadi dasar perhitungan pencapaian santri.
                        </span>
                    </label>
                </div>

                <button @click="simpan" :disabled="saving || !setuju || !adaIsi"
                    class="w-full mt-3 py-3 rounded-xl bg-[#0C78FF] text-white font-bold text-sm disabled:opacity-50">
                    {{ saving ? 'Menyimpan…' : `Simpan Pencapaian (${adaIsi} santri)` }}
                </button>
            </template>
        </template>
    </div>
</template>
