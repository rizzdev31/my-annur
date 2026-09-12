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
const setuju = ref(false)
const buka = ref({})
const koreksi = ref({})            // santri sudah terisi yang dibuka untuk diperbaiki

const TOTAL_AYAT_QURAN = 6236

// Urutan hafalan kelas — dipakai server untuk menurunkan juz dari posisi terakhir.
const POLA = [
    { key: 'amma_maju', label: 'Juz 30 → 29 → lalu 1, 2, 3…' },
    { key: 'belakang', label: 'Juz 30 → 29 → 28…' },
    { key: 'depan', label: 'Juz 1 → 2 → 3…' },
]
const pola = ref('amma_maju')

function urutanJuz(p) {
    if (p === 'depan') return Array.from({ length: 30 }, (_, i) => i + 1)
    if (p === 'belakang') return Array.from({ length: 30 }, (_, i) => 30 - i)
    return [30, 29, ...Array.from({ length: 28 }, (_, i) => i + 1)]
}

// Jumlah ayat tiap juz (untuk pratinjau persen — server tetap yang menghitung final).
const AYAT_JUZ = [148, 111, 125, 131, 124, 110, 149, 142, 159, 127, 151, 170, 154, 227, 185,
    269, 190, 202, 339, 171, 178, 169, 357, 175, 246, 195, 399, 137, 431, 564]

const isi = ref({})
const stel = (id) => isi.value[id]

function terapkanPola(id) {
    const s = stel(id)
    const n = Math.max(0, Math.min(30, parseInt(s.jumlah) || 0))
    s.juz = new Set(urutanJuz(pola.value).slice(0, n))
}

function toggleJuz(id, j) {
    const s = stel(id)
    s.juz.has(j) ? s.juz.delete(j) : s.juz.add(j)
    s.jumlah = s.juz.size
}

const juzTerurut = (id) => [...stel(id).juz].sort((a, b) => a - b)

/** Pratinjau persen — supaya salah ketik (mis. 30 juz) langsung kelihatan. */
function perkiraan(id) {
    const s = stel(id)
    let ayat = [...s.juz].reduce((t, j) => t + (AYAT_JUZ[j - 1] || 0), 0)
    // Posisi terakhir menurunkan juz sebelumnya menurut pola (dihitung server).
    if (!s.juz.size && s.last_surah && s.last_ayat) return null
    return { ayat, persen: Math.round(ayat / TOTAL_AYAT_QURAN * 1000) / 10 }
}

function barisTerisi(s) {
    return s.juz.size > 0 || (s.last_surah && s.last_ayat)
}

const daftarKerja = computed(() => {
    if (!d.value) return []
    const dikoreksi = d.value.sudah.filter(s => koreksi.value[s.santri_id])
    return [...d.value.belum, ...dikoreksi]
})

const adaIsi = computed(() => daftarKerja.value.filter(s => barisTerisi(stel(s.santri_id))).length)

// Peringatan: jumlah juz besar hampir selalu salah ketik "nomor juz".
const mencurigakan = computed(() =>
    daftarKerja.value.filter(s => stel(s.santri_id)?.juz.size >= 10).map(s => s.nama))

function siapkan(list) {
    for (const s of list) {
        if (!isi.value[s.santri_id]) {
            isi.value[s.santri_id] = { jumlah: 0, juz: new Set(), last_surah: '', last_ayat: '' }
        }
    }
}

async function load() {
    loading.value = true; error.value = ''
    try {
        d.value = (await api.get(`/education/tahfidz/jadwal/${route.params.jadwalId}/sinkron`)).data.data
        isi.value = {}
        siapkan(d.value.belum)
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal memuat daftar santri.'
    } finally { loading.value = false }
}
onMounted(load)

function bukaKoreksi(s) {
    koreksi.value[s.santri_id] = true
    siapkan([s])
    buka.value[s.santri_id] = true
}

async function simpan() {
    const items = daftarKerja.value.map(s => {
        const v = stel(s.santri_id)
        if (!barisTerisi(v)) return null
        return {
            santri_id: s.santri_id,
            juz_lulus: [...v.juz],
            last_surah: v.last_surah ? Number(v.last_surah) : null,
            last_ayat: v.last_ayat ? Number(v.last_ayat) : null,
            ganti: !!koreksi.value[s.santri_id],
        }
    }).filter(Boolean)

    if (!items.length) { msg.value = { ok: false, text: 'Belum ada santri yang diisi.' }; return }

    if (mencurigakan.value.length && !confirm(
        `Perhatikan: ${mencurigakan.value.join(', ')} diisi 10 juz atau lebih.\n\n`
        + 'Kolom itu diisi JUMLAH juz, bukan nomor juz. Santri yang hafal "juz 30" '
        + 'ditulis 1, bukan 30.\n\nSudah benar?'
    )) return

    if (!confirm(`Simpan pencapaian ${items.length} santri?`)) return

    saving.value = true; msg.value = null
    try {
        const res = await api.post('/education/tahfidz/sinkron', {
            jadwal_id: Number(route.params.jadwalId), pola: pola.value, items,
        })
        msg.value = { ok: true, text: res.data.message }
        setuju.value = false; koreksi.value = {}
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
                    Catat hafalan yang sudah dimiliki santri <b>sebelum tercatat di sistem</b>.
                </p>
                <p class="text-[11px] text-sky-600 mt-1">
                    {{ d.sudah.length }} sudah tercatat · <b>{{ d.belum.length }} belum</b>
                </p>

                <label class="block text-[11px] font-bold text-sky-800 mt-2 mb-1">Urutan hafalan kelas ini</label>
                <select v-model="pola" class="w-full px-2 py-2 rounded-xl border border-sky-200 bg-white text-[12px] outline-none">
                    <option v-for="p in POLA" :key="p.key" :value="p.key">{{ p.label }}</option>
                </select>
                <p class="text-[10px] text-sky-600 mt-1 leading-snug">
                    Dipakai saat Anda mengisi <b>posisi terakhir</b>: juz sebelum posisi itu
                    otomatis dihitung sudah hafal.
                </p>
            </div>

            <!-- Santri yang perlu diisi -->
            <div v-for="s in daftarKerja" :key="s.santri_id"
                class="rounded-2xl bg-white border p-3 mb-2"
                :class="koreksi[s.santri_id] ? 'border-amber-300' : 'border-gray-100'">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-[13px] font-bold text-gray-800 truncate">{{ s.nama }}</p>
                    <span v-if="koreksi[s.santri_id]"
                        class="shrink-0 px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-[10px] font-bold">Koreksi</span>
                </div>
                <p v-if="koreksi[s.santri_id]" class="text-[10px] text-amber-600 mt-0.5">
                    Data lama ({{ s.persen }}%) akan diganti dengan isian baru.
                </p>

                <div class="flex items-center gap-2 mt-2">
                    <input v-model="stel(s.santri_id).jumlah" @input="terapkanPola(s.santri_id)"
                        type="number" min="0" max="30" inputmode="numeric" placeholder="0"
                        class="w-16 shrink-0 px-2 py-2 rounded-xl border border-gray-200 text-sm text-center outline-none" />
                    <span class="text-[11px] text-gray-500 leading-tight">
                        <b>jumlah</b> juz yang<br />sudah hafal
                    </span>
                </div>
                <p class="text-[10px] text-gray-400 mt-1">
                    Hafal juz 30 saja → tulis <b>1</b>. Hafal juz 30 &amp; 29 → tulis <b>2</b>.
                </p>

                <template v-if="stel(s.santri_id).juz.size">
                    <p class="text-[11px] text-emerald-700 mt-1.5">Juz: {{ juzTerurut(s.santri_id).join(', ') }}</p>
                    <p v-if="perkiraan(s.santri_id)" class="text-[11px] mt-0.5"
                        :class="perkiraan(s.santri_id).persen >= 50 ? 'text-red-600 font-bold' : 'text-gray-400'">
                        ± {{ perkiraan(s.santri_id).ayat }} ayat · {{ perkiraan(s.santri_id).persen }}% dari Al-Qur'an
                        <span v-if="perkiraan(s.santri_id).persen >= 50"> — yakin sebanyak ini?</span>
                    </p>
                </template>
                <!-- Posisi saja: persennya baru bisa dihitung server, jadi tegaskan
                     pola yang dipakai agar salah pilih pola tidak lolos diam-diam. -->
                <p v-else-if="stel(s.santri_id).last_surah && stel(s.santri_id).last_ayat"
                    class="text-[11px] text-sky-700 bg-sky-50 rounded-lg px-2 py-1.5 mt-1.5 leading-snug">
                    Dihitung dari posisi terakhir: semua juz sebelum posisi itu menurut urutan
                    <b>{{ POLA.find(p => p.key === pola).label }}</b> akan dianggap sudah hafal.
                    Periksa persentasenya di daftar "Sudah tercatat" setelah disimpan.
                </p>
                <p v-else class="text-[11px] text-gray-300 mt-1.5">
                    Belum diisi — santri ini akan dilewati.
                </p>

                <button @click="buka[s.santri_id] = !buka[s.santri_id]"
                    class="mt-1.5 text-[11px] font-bold text-[#0C78FF]">
                    {{ buka[s.santri_id] ? 'Tutup rincian' : 'Atur juz manual / posisi terakhir' }}
                </button>

                <div v-if="buka[s.santri_id]" class="mt-2 pt-2 border-t border-gray-50">
                    <div class="grid grid-cols-10 gap-1 mb-2">
                        <button v-for="j in 30" :key="j" @click="toggleJuz(s.santri_id, j)"
                            :class="stel(s.santri_id).juz.has(j) ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-500'"
                            class="py-1.5 rounded text-[10px] font-bold">{{ j }}</button>
                    </div>

                    <p class="text-[10px] text-gray-400 mb-1 leading-snug">
                        Posisi terakhir bila sedang di tengah juz (opsional). Juz sebelumnya
                        <b>otomatis</b> dihitung menurut urutan di atas — tidak perlu dicentang satu-satu.
                    </p>
                    <div class="flex gap-2">
                        <input v-model="stel(s.santri_id).last_surah" type="number" min="1" max="114"
                            inputmode="numeric" placeholder="No. surah"
                            class="flex-1 min-w-0 px-2 py-2 rounded-xl border border-gray-200 text-[12px] outline-none" />
                        <input v-model="stel(s.santri_id).last_ayat" type="number" min="1"
                            inputmode="numeric" placeholder="Ayat"
                            class="flex-1 min-w-0 px-2 py-2 rounded-xl border border-gray-200 text-[12px] outline-none" />
                    </div>
                </div>
            </div>

            <div v-if="!daftarKerja.length" class="rounded-2xl bg-emerald-50 border border-emerald-100 p-4 text-center mb-3">
                <p class="text-sm font-bold text-emerald-700">✓ Semua santri sudah tercatat</p>
                <p class="text-[11px] text-emerald-600 mt-1">Periksa daftar di bawah bila ada yang perlu dikoreksi.</p>
            </div>

            <template v-if="daftarKerja.length">
                <div class="rounded-2xl bg-amber-50 border border-amber-200 p-3 mt-3">
                    <label class="flex items-start gap-2.5 cursor-pointer">
                        <input v-model="setuju" type="checkbox" class="mt-0.5 w-4 h-4 shrink-0 accent-amber-600" />
                        <span class="text-[11px] text-amber-800 leading-snug">
                            Saya sudah memeriksa jumlah juz dan persentasenya masuk akal untuk tiap santri.
                        </span>
                    </label>
                </div>

                <button @click="simpan" :disabled="saving || !setuju || !adaIsi"
                    class="w-full mt-3 py-3 rounded-xl bg-[#0C78FF] text-white font-bold text-sm disabled:opacity-50">
                    {{ saving ? 'Menyimpan…' : `Simpan Pencapaian (${adaIsi} santri)` }}
                </button>
            </template>

            <!-- Sudah tercatat: bisa diperiksa & dikoreksi -->
            <div v-if="d.sudah.length" class="mt-5">
                <p class="text-[12px] font-extrabold text-gray-700 mb-1.5">Sudah tercatat</p>
                <p class="text-[10px] text-gray-400 mb-2">
                    Periksa persentasenya. Bila keliru, ketuk <b>Koreksi</b> untuk menulis ulang.
                </p>
                <div v-for="s in d.sudah" :key="s.santri_id"
                    class="flex items-center gap-2 rounded-xl bg-white border border-gray-100 px-3 py-2 mb-1.5">
                    <div class="min-w-0 flex-1">
                        <p class="text-[12px] font-semibold text-gray-800 truncate">{{ s.nama }}</p>
                        <p class="text-[10px]" :class="s.persen >= 50 ? 'text-red-500 font-bold' : 'text-gray-400'">
                            {{ s.juz.length }} juz · {{ s.total_ayat }} ayat · {{ s.persen }}%
                        </p>
                    </div>
                    <button v-if="s.boleh_koreksi && !koreksi[s.santri_id]" @click="bukaKoreksi(s)"
                        class="shrink-0 px-3 py-1.5 rounded-lg bg-amber-50 text-amber-700 text-[11px] font-bold">Koreksi</button>
                    <span v-else-if="!s.boleh_koreksi"
                        class="shrink-0 text-[10px] text-gray-300">sudah ada setoran</span>
                    <span v-else class="shrink-0 text-[10px] text-amber-600 font-bold">sedang dikoreksi</span>
                </div>
            </div>
        </template>
    </div>
</template>
