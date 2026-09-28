<template>
    <div>
        <PageHeader title="Absen Kegiatan" />

        <div v-if="loading" class="pt-16 flex justify-center">
            <div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div>
        </div>

        <template v-else>
            <p v-if="msg" :class="msg.ok ? 'text-emerald-700 bg-emerald-50' : 'text-red-600 bg-red-50'" class="text-sm rounded-xl px-3 py-2 mb-3">{{ msg.text }}</p>

            <div v-if="!isPiket" class="rounded-2xl bg-amber-50 border border-amber-200 p-4 text-sm text-amber-700 mb-4">
                Anda bukan guru piket hari ini — hanya bisa melihat, tidak bisa mencatat.
            </div>

            <!-- Daftar kegiatan -->
            <div v-if="!sel">
                <p class="text-xs text-gray-400 mb-2">{{ tanggal }}</p>
                <div v-if="!list.length" class="text-center text-gray-400 py-12">Tidak ada kegiatan aktif hari ini.</div>
                <button v-for="k in list" :key="k.id" @click="pilih(k)"
                    class="w-full text-left bg-white rounded-2xl border border-gray-100 p-4 mb-3 flex items-center gap-3">
                    <div class="w-11 h-11 rounded-xl bg-[#0C78FF]/10 grid place-items-center text-[#0C78FF] font-bold text-sm shrink-0">{{ k.jam }}</div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-gray-800 truncate">{{ k.nama }}</p>
                        <p class="text-xs text-gray-400">
                            {{ labelSasaran(k.sasaran) }} · {{ k.sudah_catat }}/{{ k.total }} ditandai
                            <span v-if="k.sudah_catat"> · {{ k.sudah_hadir }} hadir</span>
                        </p>
                    </div>
                    <!-- Penanda kelengkapan: kegiatan yang separuh jalan paling
                         mudah terlewat, jadi dibuat terlihat dari daftar. -->
                    <span v-if="k.lengkap" class="shrink-0 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[10px] font-bold">Lengkap</span>
                    <span v-else-if="k.total" class="shrink-0 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 text-[10px] font-bold">{{ k.belum }} belum</span>
                    <svg class="w-4 h-4 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>

            <!-- Detail peserta -->
            <div v-else>
                <button @click="sel = null" class="text-sm text-[#0C78FF] font-semibold mb-3">← Kembali</button>
                <h2 class="text-base font-extrabold text-gray-900">{{ sel.nama }} <span class="text-sm font-medium text-gray-400">· {{ sel.jam }}</span></h2>
                <p class="text-xs text-gray-400 mb-2">
                    Tandai sesuai kenyataan di lapangan. Absen harian tidak memengaruhi apa pun di sini —
                    guru shift sore/asrama tetap bisa ditandai <b>hadir</b>.
                </p>

                <div v-if="isPiket" class="flex items-center gap-2 mb-2">
                    <button @click="tandaiSemua('hadir')"
                        class="px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 text-xs font-bold">Semua hadir</button>
                    <button @click="tandaiSemua(null)"
                        class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-500 text-xs font-bold">Bersihkan</button>
                    <span class="ml-auto text-[11px]" :class="belumDitandai ? 'text-amber-600 font-semibold' : 'text-gray-400'">
                        {{ belumDitandai ? belumDitandai + ' belum ditandai' : 'Semua sudah ditandai' }}
                    </span>
                </div>

                <div class="divide-y divide-gray-50 bg-white rounded-2xl border border-gray-100 overflow-hidden">
                    <div v-for="p in peserta" :key="p.tenaga_pendidik_id" class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-800 truncate">
                                    {{ p.nama }}
                                    <span v-if="p.tambahan" class="ml-1 text-[9px] font-bold text-violet-700 bg-violet-50 px-1.5 py-0.5 rounded">tambahan</span>
                                    <span v-if="p.adhoc" class="ml-1 text-[9px] font-bold text-[#0C78FF] bg-blue-50 px-1.5 py-0.5 rounded">ikut hadir</span>
                                </p>
                                <p class="text-[11px] text-gray-400">
                                    {{ p.jenis_guru }}
                                    <!-- Keterangan saja, bukan larangan: guru shift bisa saja
                                         belum absen harian tapi memang sedang bertugas. -->
                                    <span v-if="!p.hadir_kerja" class="text-gray-300"> · belum tercatat masuk kerja</span>
                                </p>
                                <!-- Izin resmi: statusnya terkunci, piket tidak perlu menandai -->
                                <p v-if="p.terkunci" class="text-[11px] text-amber-600 mt-0.5">
                                    Izin sudah disetujui — dihitung netral. {{ p.keterangan }}
                                </p>
                            </div>
                            <div class="flex gap-1 shrink-0">
                                <button @click="isPiket && (p.status = 'hadir')" :disabled="!isPiket"
                                    :class="p.status === 'hadir' ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-500'"
                                    class="px-2.5 py-1.5 rounded-lg text-xs font-semibold disabled:opacity-40">Hadir</button>
                                <button @click="isPiket && setIzin(p)" :disabled="!isPiket || p.adhoc"
                                    :class="p.status === 'izin' ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-500'"
                                    class="px-2.5 py-1.5 rounded-lg text-xs font-semibold disabled:opacity-40">Izin</button>
                                <button @click="isPiket && !p.terkunci && (p.status = 'tidak_hadir')"
                                    :disabled="!isPiket || p.terkunci || p.adhoc"
                                    :class="p.status === 'tidak_hadir' ? 'bg-red-500 text-white' : 'bg-gray-100 text-gray-500'"
                                    class="px-2.5 py-1.5 rounded-lg text-xs font-semibold disabled:opacity-40">Tidak</button>
                            </div>
                        </div>
                        <!-- Alasan izin: wajib agar ada jejak yang bisa ditelusuri -->
                        <input v-if="p.status === 'izin' && !p.terkunci && isPiket" v-model="p.keterangan"
                            type="text" maxlength="200" placeholder="Alasan diizinkan (mis. mengajar di jam yang sama)"
                            class="mt-2 w-full px-3 py-2 rounded-lg border border-amber-200 text-[12px] focus:outline-none focus:border-amber-400" />
                    </div>
                </div>

                <!-- Peserta ad-hoc: guru di luar daftar wajib yang ikut hadir -->
                <div v-if="isPiket" class="mt-3">
                    <button v-if="!bukaTambah" @click="bukaTambah = true"
                        class="w-full py-2.5 rounded-xl border border-dashed border-gray-300 text-xs font-semibold text-gray-500">
                        + Tambah guru yang ikut hadir
                    </button>
                    <div v-else class="rounded-xl bg-white border border-gray-100 p-3">
                        <p class="text-[11px] text-gray-500 mb-2">
                            Untuk guru di luar daftar wajib — mis. guru mukim yang ikut kegiatan non-mukim.
                            Hanya dicatat <b>hadir</b>, tidak menciptakan kewajiban baru.
                        </p>
                        <input v-model="cariKandidat" type="text" placeholder="Cari nama guru…"
                            class="w-full px-3 py-2 rounded-lg border border-gray-200 text-[12px] mb-2 focus:outline-none focus:border-[#0C78FF]" />
                        <div class="max-h-44 overflow-y-auto divide-y divide-gray-50">
                            <button v-for="k in kandidatTersaring" :key="k.tenaga_pendidik_id" @click="tambahAdHoc(k)"
                                class="w-full text-left px-2 py-2 flex items-center justify-between">
                                <span class="text-[12px] text-gray-700">{{ k.nama }}</span>
                                <span class="text-[10px] text-gray-400">{{ k.jenis_guru }}</span>
                            </button>
                            <p v-if="!kandidatTersaring.length" class="text-[11px] text-gray-400 py-2">Tidak ada guru yang cocok.</p>
                        </div>
                        <button @click="bukaTambah = false" class="mt-2 w-full py-2 rounded-lg bg-gray-100 text-xs font-semibold text-gray-600">Tutup</button>
                    </div>
                </div>

                <button v-if="isPiket" @click="simpan" :disabled="busy"
                    class="w-full mt-4 py-3 rounded-xl bg-[#0C78FF] text-white font-semibold text-sm disabled:opacity-50">
                    {{ busy ? 'Menyimpan…' : 'Simpan Kehadiran' }}
                </button>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../api'
import PageHeader from '../components/PageHeader.vue'

const loading = ref(true)
const busy = ref(false)
const isPiket = ref(false)
const tanggal = ref('')
const list = ref([])
const sel = ref(null)
const peserta = ref([])
const msg = ref(null)
const kandidat = ref([])
const bukaTambah = ref(false)
const cariKandidat = ref('')

const kandidatTersaring = computed(() => {
    const q = cariKandidat.value.trim().toLowerCase()
    const sudah = new Set(peserta.value.map(p => p.tenaga_pendidik_id))
    return kandidat.value
        .filter(k => !sudah.has(k.tenaga_pendidik_id))
        .filter(k => !q || k.nama.toLowerCase().includes(q))
        .slice(0, 30)
})

// Izin = berhalangan yang diperbolehkan. Netral bagi kinerja, tapi wajib
// berketerangan supaya alasannya bisa ditelusuri.
function setIzin(p) {
    if (p.terkunci) return
    p.status = 'izin'
    if (!p.keterangan) p.keterangan = ''
}

function tambahAdHoc(k) {
    peserta.value.unshift({
        ...k, status: 'hadir', adhoc: true, terkunci: false,
        hadir_kerja: true, keterangan: null, tercatat: false,
    })
    cariKandidat.value = ''
    bukaTambah.value = false
}

function labelSasaran(s) { return { semua: 'Semua', mukim: 'Mukim', non_mukim: 'Non-mukim' }[s] ?? s }

async function load() {
    loading.value = true
    try {
        const res = await api.get('/piket/kegiatan')
        isPiket.value = res.data.is_piket
        tanggal.value = res.data.tanggal
        list.value = res.data.data ?? []
    } catch (e) { msg.value = { ok: false, text: 'Gagal memuat kegiatan.' } }
    finally { loading.value = false }
}

async function pilih(k) {
    try {
        const res = await api.get(`/piket/kegiatan/${k.id}/peserta`)
        peserta.value = (res.data.data ?? []).map(p => ({ ...p }))
        kandidat.value = res.data.kandidat ?? []
        bukaTambah.value = false
        cariKandidat.value = ''
        sel.value = k
    } catch (e) { msg.value = { ok: false, text: 'Gagal memuat peserta.' } }
}

const belumDitandai = computed(() =>
    peserta.value.filter(p => !['hadir', 'tidak_hadir', 'izin'].includes(p.status)).length)

function tandaiSemua(status) {
    // Jangan sentuh yang izinnya sudah disetujui — statusnya terkunci netral.
    peserta.value.forEach(p => { if (!p.terkunci) p.status = status })
}

async function simpan() {
    // Hanya kirim yang BENAR-BENAR ditandai. Dulu semua yang belum ditandai
    // ikut tersimpan sebagai 'tidak_hadir' — memotong poin kinerja guru yang
    // sebenarnya hadir, hanya karena piket belum sempat menyentuh barisnya.
    const items = peserta.value
        .filter(p => ['hadir', 'tidak_hadir', 'izin'].includes(p.status))
        .map(p => ({
            tenaga_pendidik_id: p.tenaga_pendidik_id,
            status: p.status,
            keterangan: p.status === 'izin' ? (p.keterangan || null) : null,
        }))

    if (!items.length) { msg.value = { ok: false, text: 'Belum ada yang ditandai.' }; return }

    // Izin harus punya alasan — tanpa itu catatannya tidak bisa dipertanggungjawabkan.
    const izinTanpaAlasan = peserta.value.filter(
        p => p.status === 'izin' && !p.terkunci && !(p.keterangan || '').trim())
    if (izinTanpaAlasan.length) {
        msg.value = { ok: false, text: `Isi alasan izin untuk ${izinTanpaAlasan[0].nama}.` }
        return
    }

    // Yang belum ditandai tidak tercatat sama sekali — pastikan piket sadar,
    // bukan menemukannya nanti saat laporan sudah telanjur timpang.
    if (belumDitandai.value && !confirm(
        `${belumDitandai.value} guru belum ditandai.\n\n`
        + 'Mereka tidak akan tercatat hadir maupun tidak hadir untuk kegiatan ini.\n\nTetap simpan?'
    )) return

    busy.value = true; msg.value = null
    try {
        const res = await api.post(`/piket/kegiatan/${sel.value.id}/simpan`, { items })
        msg.value = { ok: true, text: res.data.message || 'Tersimpan.' }
        sel.value = null
        await load()
    } catch (e) { msg.value = { ok: false, text: e.response?.data?.message || 'Gagal menyimpan.' } }
    finally { busy.value = false }
}

onMounted(load)
</script>
