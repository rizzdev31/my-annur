<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { tanggalLokal } from '../tanggal'
import { useRoute, useRouter } from 'vue-router'
import api from '../api'
import { kompresFoto } from '../foto'
import { toast } from '../store/toast'
import PageHeader from '../components/PageHeader.vue'
import BottomSheet from '../components/BottomSheet.vue'

const route = useRoute()
const router = useRouter()
const id = route.params.id
const loading = ref(true)
const p = ref(null)
const busy = ref(false)

const isKegiatan = computed(() => p.value?.tipe_pengerjaan === 'absen_kegiatan')
const kegiatan = computed(() => p.value?.kegiatan_list ?? [])

async function load() {
    loading.value = true
    try { p.value = (await api.get(`/tugas/${id}`)).data.data }
    catch (e) { toast.error(e.response?.data?.message || 'Gagal memuat detail.') }
    finally { loading.value = false }
}
onMounted(load)

const statusMeta = computed(() => {
    // Rentang absen_kegiatan yang belum lewat → tampil "Berlangsung" walau status 'selesai'.
    if (p.value?.rentang_aktif) return { t: 'Berlangsung', c: 'bg-amber-50 text-amber-600' }
    return ({
        belum: { t: 'Belum Dikerjakan', c: 'bg-gray-100 text-gray-500' },
        sedang: { t: 'Sedang Dikerjakan', c: 'bg-amber-50 text-amber-600' },
        selesai: { t: 'Selesai', c: 'bg-emerald-50 text-emerald-600' },
        tidak_selesai: { t: 'Tidak Selesai', c: 'bg-red-50 text-red-600' },
    }[p.value?.status_pengerjaan] || { t: p.value?.status_pengerjaan, c: 'bg-gray-100 text-gray-500' })
})

async function mulai() {
    busy.value = true
    try { await api.post(`/tugas/${id}/mulai`); toast.success('Tugas dimulai.'); await load() }
    catch (e) { toast.error(e.response?.data?.message || 'Gagal memulai.') }
    finally { busy.value = false }
}

// ── Laporan (mandiri) ────────────────────────────────────────────────────────
const lapSheet = ref(false)
const lf = reactive({ tipe: 'teks', teks: '', link: '', foto: null, preview: null, laporan: '' })
function openLaporan() { Object.assign(lf, { tipe: 'teks', teks: '', link: '', foto: null, preview: null, laporan: '' }); lapSheet.value = true }
async function lFoto(e) { let f = e.target.files?.[0]; if (f) f = await kompresFoto(f); lf.foto = f || null; lf.preview = f ? URL.createObjectURL(f) : null }
async function kirimLaporan() {
    if (lf.tipe === 'teks' && !lf.teks.trim()) return toast.warning('Isi teks bukti.')
    if (lf.tipe === 'link' && !lf.link.trim()) return toast.warning('Isi link bukti.')
    if (lf.tipe === 'foto' && !lf.foto) return toast.warning('Pilih foto bukti.')
    busy.value = true
    try {
        const fd = new FormData()
        fd.append('bukti_tipe', lf.tipe)
        if (lf.tipe === 'teks') fd.append('teks_bukti', lf.teks.trim())
        if (lf.tipe === 'link') fd.append('link_bukti', lf.link.trim())
        if (lf.tipe === 'foto') fd.append('foto', lf.foto)
        if (lf.laporan.trim()) fd.append('laporan', lf.laporan.trim())
        await api.post(`/tugas/${id}/laporan`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
        lapSheet.value = false
        toast.success('Laporan terkirim. Menunggu verifikasi.')
        await load()
    } catch (e) { toast.error(e.response?.data?.message || 'Gagal mengirim laporan.') }
    finally { busy.value = false }
}

// ── Tambah Kegiatan (absen_kegiatan) ─────────────────────────────────────────
const kegSheet = ref(false)
const kf = reactive({ nama: '', tanggal: tanggalLokal(), jam: '', lokasi: '' })
function openKeg() { Object.assign(kf, { nama: '', tanggal: tanggalLokal(), jam: '', lokasi: '' }); kegSheet.value = true }
async function buatKegiatan() {
    if (!kf.nama.trim()) return toast.warning('Nama kegiatan wajib.')
    busy.value = true
    try {
        const res = await api.post('/kegiatan', {
            sumber_tipe: 'tugas_tambahan', sumber_id: p.value.tugas_tambahan_id,
            nama_kegiatan: kf.nama.trim(), tanggal_kegiatan: kf.tanggal,
            jam_mulai: kf.jam || null, lokasi: kf.lokasi.trim() || null,
        })
        const kid = res.data.data?.id
        kegSheet.value = false
        toast.success('Kegiatan dibuat.')
        if (kid) router.push({ name: 'kegiatan', params: { id: kid } })
    } catch (e) { toast.error(e.response?.data?.message || 'Gagal membuat kegiatan.') }
    finally { busy.value = false }
}
</script>

<template>
    <div>
        <PageHeader title="Detail Tugas" back />
        <div v-if="loading" class="pt-16 flex justify-center"><div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div></div>

        <template v-else-if="p">
            <!-- Header card -->
            <div class="rounded-2xl bg-gradient-to-br from-[#0C78FF] to-[#0A5FCC] p-5 text-white mb-4">
                <div class="flex items-center gap-2 mb-2">
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-white/20">{{ isKegiatan ? 'Absen Kegiatan' : 'Mandiri' }}</span>
                    <span v-if="p.wajib_laporan" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-white/20">Wajib Bukti</span>
                </div>
                <h1 class="text-lg font-extrabold leading-tight">{{ p.judul }}</h1>
                <p v-if="p.deskripsi" class="text-[13px] text-white/80 mt-1">{{ p.deskripsi }}</p>
                <div class="flex items-center gap-4 mt-3 text-[12px] text-white/90">
                    <span v-if="p.tanggal_mulai">📅 {{ p.tanggal_mulai }}<span v-if="p.tanggal_selesai"> — {{ p.tanggal_selesai }}</span></span>
                    <span v-if="p.nominal_vakasi" class="font-bold">Berhak Vakasi</span>
                </div>
            </div>

            <!-- Status -->
            <div class="flex items-center justify-between rounded-2xl bg-white border border-gray-100 p-4 mb-4">
                <span class="text-sm font-semibold text-gray-500">Status</span>
                <span class="text-xs font-bold px-3 py-1 rounded-full" :class="statusMeta.c">{{ statusMeta.t }}</span>
            </div>

            <!-- Verifikasi -->
            <div v-if="p.status_pengerjaan === 'selesai' && !isKegiatan" class="rounded-2xl border p-4 mb-4"
                :class="p.disetujui === true ? 'bg-emerald-50 border-emerald-100' : p.disetujui === false ? 'bg-red-50 border-red-100' : 'bg-amber-50 border-amber-100'">
                <p class="text-sm font-bold" :class="p.disetujui === true ? 'text-emerald-700' : p.disetujui === false ? 'text-red-700' : 'text-amber-700'">
                    {{ p.disetujui === true ? '✓ Disetujui' : p.disetujui === false ? '✕ Ditolak' : '⏳ Menunggu verifikasi' }}
                </p>
                <p v-if="p.catatan_verifikasi" class="text-[12px] text-gray-600 mt-1">{{ p.catatan_verifikasi }}</p>
                <p v-if="p.dilaporkan_pada" class="text-[11px] text-gray-400 mt-1">Dilaporkan {{ p.dilaporkan_pada }}</p>
            </div>

            <!-- Bukti tersimpan -->
            <div v-if="p.status_pengerjaan === 'selesai' && !isKegiatan && (p.teks_bukti || p.link_bukti || p.file_laporan_url)" class="rounded-2xl bg-white border border-gray-100 p-4 mb-4">
                <p class="text-[11px] font-bold text-gray-400 mb-1.5">BUKTI</p>
                <p v-if="p.teks_bukti" class="text-sm text-gray-700">{{ p.teks_bukti }}</p>
                <a v-if="p.link_bukti" :href="p.link_bukti" target="_blank" class="text-sm text-[#0C78FF] underline break-all">{{ p.link_bukti }}</a>
                <img v-if="p.file_laporan_url" :src="p.file_laporan_url" class="w-full rounded-xl mt-2" />
            </div>

            <!-- ══ ABSEN KEGIATAN: daftar kegiatan ══ -->
            <template v-if="isKegiatan">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm font-bold text-gray-700">Kegiatan</p>
                    <button @click="openKeg" class="text-xs font-bold text-[#0C78FF]">+ Tambah</button>
                </div>
                <div v-if="!kegiatan.length" class="rounded-2xl border border-dashed border-gray-200 p-6 text-center text-sm text-gray-400 mb-4">Belum ada kegiatan. Tambah untuk mulai absen peserta.</div>
                <ul v-else class="space-y-2.5 mb-4">
                    <li v-for="k in kegiatan" :key="k.id" @click="router.push({ name: 'kegiatan', params: { id: k.id } })"
                        class="rounded-2xl bg-white border border-gray-100 p-4 active:scale-[0.99] transition flex items-center justify-between">
                        <div>
                            <p class="text-sm font-bold text-gray-800">{{ k.nama_kegiatan }}</p>
                            <p class="text-[11px] text-gray-400">{{ k.tanggal_kegiatan }}<span v-if="k.jam_mulai"> · {{ k.jam_mulai }}</span><span v-if="k.lokasi"> · {{ k.lokasi }}</span></p>
                        </div>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" :class="k.status === 'selesai' ? 'text-emerald-600 bg-emerald-50' : 'text-amber-600 bg-amber-50'">{{ k.status === 'selesai' ? 'Selesai' : 'Berlangsung' }}</span>
                    </li>
                </ul>
            </template>

            <!-- ══ MANDIRI: aksi ══ -->
            <template v-else>
                <button v-if="p.status_pengerjaan === 'belum'" @click="mulai" :disabled="busy"
                    class="w-full py-3.5 rounded-2xl bg-[#0C78FF] text-white font-bold disabled:opacity-60 mb-2">Mulai Kerjakan</button>
                <button v-if="p.status_pengerjaan === 'sedang'" @click="openLaporan" :disabled="busy"
                    class="w-full py-3.5 rounded-2xl bg-emerald-600 text-white font-bold disabled:opacity-60 mb-2">Kirim Laporan</button>
                <button v-if="p.status_pengerjaan === 'belum'" @click="openLaporan" :disabled="busy"
                    class="w-full py-3 rounded-2xl bg-gray-100 text-gray-600 font-semibold text-sm">Langsung Kirim Laporan</button>
            </template>
        </template>

        <!-- Sheet laporan -->
        <BottomSheet v-model="lapSheet" title="Kirim Laporan" subtitle="Lampirkan bukti pengerjaan">
            <div class="flex gap-1 bg-gray-100 rounded-2xl p-1 mb-3">
                <button v-for="b in ['teks','link','foto']" :key="b" @click="lf.tipe = b" class="flex-1 py-2 rounded-xl text-[12px] font-bold capitalize transition" :class="lf.tipe === b ? 'bg-white text-[#0C78FF] shadow-sm' : 'text-gray-400'">{{ b }}</button>
            </div>
            <textarea v-if="lf.tipe === 'teks'" v-model="lf.teks" rows="3" placeholder="Tuliskan bukti…" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-3"></textarea>
            <input v-else-if="lf.tipe === 'link'" v-model="lf.link" type="url" placeholder="https://…" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-3" />
            <div v-else class="mb-3">
                <img v-if="lf.preview" :src="lf.preview" class="w-full h-40 object-cover rounded-xl mb-2" />
                <input type="file" accept="image/*" capture="environment" @change="lFoto" class="block w-full text-xs text-gray-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-[#0C78FF]/10 file:text-[#0C78FF] file:text-xs file:font-semibold" />
            </div>
            <textarea v-model="lf.laporan" rows="2" placeholder="Catatan laporan (opsional)" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-4"></textarea>
            <button @click="kirimLaporan" :disabled="busy" class="w-full py-3 rounded-xl bg-[#0C78FF] text-white font-bold text-sm disabled:opacity-60">{{ busy ? 'Mengirim…' : 'Kirim' }}</button>
        </BottomSheet>

        <!-- Sheet buat kegiatan -->
        <BottomSheet v-model="kegSheet" title="Buat Kegiatan">
            <input v-model="kf.nama" type="text" placeholder="Nama kegiatan *" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-3" />
            <div class="grid grid-cols-2 gap-3 mb-3">
                <input v-model="kf.tanggal" type="date" class="px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none" />
                <input v-model="kf.jam" type="time" class="px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none" />
            </div>
            <input v-model="kf.lokasi" type="text" placeholder="Lokasi (opsional)" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-4" />
            <button @click="buatKegiatan" :disabled="busy" class="w-full py-3 rounded-xl bg-emerald-600 text-white font-bold text-sm disabled:opacity-60">{{ busy ? 'Membuat…' : 'Buat & Buka' }}</button>
        </BottomSheet>
    </div>
</template>
