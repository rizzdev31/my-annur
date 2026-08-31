<script setup>
import { ref, reactive, onMounted } from 'vue'
import { tanggalLokal } from '../tanggal'
import { RouterLink, useRouter } from 'vue-router'
import api from '../api'
import { kompresFoto } from '../foto'
import { toast } from '../store/toast'
import BottomSheet from '../components/BottomSheet.vue'

const router = useRouter()
const tab = ref('tambahan')
const loading = ref(true)
const tambahan = ref([])
const jabatan = ref([])

const statusClass = (s) => ({ selesai: 'text-emerald-600 bg-emerald-50', sedang: 'text-amber-600 bg-amber-50', tidak_selesai: 'text-red-600 bg-red-50' }[s] || 'text-gray-500 bg-gray-100')
const statusLabel = (s) => ({ selesai: 'Selesai', sedang: 'Dikerjakan', tidak_selesai: 'Tidak Selesai' }[s] || 'Belum')

async function load() {
    loading.value = true
    try {
        const [a, j] = await Promise.all([api.get('/tugas/aktif'), api.get('/tugas/jabatan/list')])
        tambahan.value = a.data.data ?? []
        jabatan.value = j.data.data ?? []
    } catch (e) { toast.error(e.response?.data?.message || 'Gagal memuat tugas.') }
    finally { loading.value = false }
}
onMounted(load)

// ── Tugas Jabatan: realisasi (mandiri) / kegiatan (absen_kegiatan) ───────────
const jab = ref(null)
const jbukti = reactive({ tipe: 'teks', teks: '', link: '', foto: null, preview: null, keterangan: '' })
const jSaving = ref(false)
function bukaJabatan(t) {
    jab.value = t
    Object.assign(jbukti, { tipe: 'teks', teks: '', link: '', foto: null, preview: null, keterangan: '' })
}
async function jFoto(e) { let f = e.target.files?.[0]; if (f) f = await kompresFoto(f); jbukti.foto = f || null; jbukti.preview = f ? URL.createObjectURL(f) : null }

async function kirimRealisasi() {
    if (jbukti.tipe === 'teks' && !jbukti.teks.trim()) return toast.warning('Isi keterangan bukti.')
    if (jbukti.tipe === 'link' && !jbukti.link.trim()) return toast.warning('Isi link bukti.')
    if (jbukti.tipe === 'foto' && !jbukti.foto) return toast.warning('Pilih foto bukti.')
    jSaving.value = true
    try {
        const fd = new FormData()
        fd.append('bukti_tipe', jbukti.tipe)
        if (jbukti.tipe === 'teks') fd.append('teks_bukti', jbukti.teks.trim())
        if (jbukti.tipe === 'link') fd.append('link_bukti', jbukti.link.trim())
        if (jbukti.tipe === 'foto') fd.append('foto', jbukti.foto)
        if (jbukti.keterangan.trim()) fd.append('keterangan', jbukti.keterangan.trim())
        await api.post(`/tugas/jabatan/${jab.value.id}/realisasi`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
        jab.value = null
        toast.success('Realisasi tugas jabatan terkirim.')
        await load()
    } catch (e) { toast.error(e.response?.data?.message || 'Gagal mengirim.') }
    finally { jSaving.value = false }
}

// Jabatan tipe absen_kegiatan: buat kegiatan → buka Kegiatan
const buatSheet = ref(false)
const kf = reactive({ nama: '', tanggal: tanggalLokal(), jam: '', lokasi: '' })
const kSaving = ref(false)
function bukaBuat() { Object.assign(kf, { nama: '', tanggal: tanggalLokal(), jam: '', lokasi: '' }); buatSheet.value = true }
async function buatKegiatan(sumberTipe, sumberId) {
    if (!kf.nama.trim()) return toast.warning('Nama kegiatan wajib.')
    kSaving.value = true
    try {
        const res = await api.post('/kegiatan', {
            sumber_tipe: sumberTipe, sumber_id: sumberId, nama_kegiatan: kf.nama.trim(),
            tanggal_kegiatan: kf.tanggal, jam_mulai: kf.jam || null, lokasi: kf.lokasi.trim() || null,
        })
        const id = res.data.data?.id
        buatSheet.value = false; jab.value = null
        toast.success('Kegiatan dibuat.')
        if (id) router.push({ name: 'kegiatan', params: { id } })
    } catch (e) { toast.error(e.response?.data?.message || 'Gagal membuat kegiatan.') }
    finally { kSaving.value = false }
}
</script>

<template>
    <div>
        <h1 class="text-xl font-extrabold text-gray-900 mb-4">Tugas & Kegiatan</h1>

        <div class="flex gap-2 mb-4 bg-gray-100 rounded-2xl p-1">
            <button @click="tab = 'tambahan'" class="flex-1 py-2 rounded-xl text-sm font-bold" :class="tab === 'tambahan' ? 'bg-white text-[#0C78FF] shadow-sm' : 'text-gray-400'">Tambahan</button>
            <button @click="tab = 'jabatan'" class="flex-1 py-2 rounded-xl text-sm font-bold" :class="tab === 'jabatan' ? 'bg-white text-[#0C78FF] shadow-sm' : 'text-gray-400'">Jabatan</button>
        </div>

        <div v-if="loading" class="pt-10 flex justify-center"><div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div></div>

        <!-- ══ TAMBAHAN ══ -->
        <template v-else-if="tab === 'tambahan'">
            <div v-if="!tambahan.length" class="pt-16 text-center text-sm text-gray-400">Belum ada tugas tambahan aktif.</div>
            <ul v-else class="space-y-3">
                <RouterLink v-for="t in tambahan" :key="t.id" :to="{ name: 'tugas-detail', params: { id: t.id } }"
                    class="block rounded-2xl bg-white border border-gray-100 p-4 active:scale-[0.99] transition">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-xl bg-[#0C78FF]/10 grid place-items-center shrink-0">
                            <svg class="w-5 h-5 text-[#0C78FF]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-gray-800">{{ t.judul }}</p>
                            <p v-if="t.deskripsi" class="text-[11px] text-gray-400 line-clamp-1">{{ t.deskripsi }}</p>
                            <div class="flex items-center gap-2 mt-1.5">
                                <span v-if="t.rentang_aktif" class="text-[10px] font-bold px-2 py-0.5 rounded-full text-amber-600 bg-amber-50">Berlangsung</span>
                                <span v-else class="text-[10px] font-bold px-2 py-0.5 rounded-full" :class="statusClass(t.status_pengerjaan)">{{ statusLabel(t.status_pengerjaan) }}</span>
                                <span v-if="t.tipe_pengerjaan === 'absen_kegiatan'" class="text-[10px] font-bold text-violet-600 bg-violet-50 px-2 py-0.5 rounded-full">Kegiatan</span>
                                <span v-if="t.disetujui === true" class="text-[10px] text-emerald-600">✓ disetujui</span>
                                <span v-else-if="t.disetujui === false" class="text-[10px] text-red-500">ditolak</span>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-gray-300 mt-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </div>
                </RouterLink>
            </ul>
        </template>

        <!-- ══ JABATAN ══ -->
        <template v-else>
            <div v-if="!jabatan.length" class="pt-16 text-center text-sm text-gray-400">Belum ada tugas jabatan.</div>
            <ul v-else class="space-y-3">
                <li v-for="t in jabatan" :key="t.id" @click="bukaJabatan(t)"
                    class="rounded-2xl bg-white border border-gray-100 p-4 active:scale-[0.99] transition">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 grid place-items-center shrink-0">
                            <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-gray-800">{{ t.nama_tugas }}</p>
                            <p class="text-[11px] text-gray-400">{{ t.frekuensi_label }} · {{ t.periode_label }}<span v-if="t.tipe_pengerjaan === 'absen_kegiatan'"> · Kegiatan</span></p>
                            <div class="mt-1.5">
                                <span v-if="t.sudah_realisasi" class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">✓ {{ t.realisasi_status === 'disetujui' ? 'Disetujui' : 'Sudah lapor' }}</span>
                                <span v-else class="text-[10px] font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full">Belum realisasi</span>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-gray-300 mt-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </div>
                </li>
            </ul>
        </template>

        <!-- Sheet jabatan -->
        <BottomSheet :model-value="!!jab" @update:model-value="jab = null" :title="jab?.nama_tugas" :subtitle="jab ? `${jab.frekuensi_label} · ${jab.periode_label}` : ''">
            <template v-if="jab">
                <p v-if="jab.deskripsi" class="text-[12px] text-gray-500 mb-4">{{ jab.deskripsi }}</p>

                <!-- absen_kegiatan → daftar kegiatan bulan ini + buat -->
                <template v-if="jab.tipe_pengerjaan === 'absen_kegiatan'">
                    <p class="text-[11px] font-bold text-gray-500 mb-2">Kegiatan bulan ini</p>
                    <div v-if="!jab.kegiatan_bulan_ini?.length" class="text-[12px] text-gray-400 mb-3">Belum ada kegiatan.</div>
                    <ul v-else class="space-y-2 mb-3">
                        <li v-for="k in jab.kegiatan_bulan_ini" :key="k.id" @click="jab = null; router.push({ name: 'kegiatan', params: { id: k.id } })"
                            class="flex items-center justify-between bg-gray-50 rounded-xl px-3 py-2.5 active:bg-gray-100">
                            <div><p class="text-sm font-semibold text-gray-800">{{ k.nama_kegiatan }}</p><p class="text-[10px] text-gray-400">{{ k.tanggal_kegiatan }}</p></div>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" :class="k.status === 'selesai' ? 'text-emerald-600 bg-emerald-50' : 'text-amber-600 bg-amber-50'">{{ k.status === 'selesai' ? 'Selesai' : 'Berlangsung' }}</span>
                        </li>
                    </ul>
                    <button @click="bukaBuat" class="w-full py-3 rounded-xl bg-gradient-to-r from-emerald-500 to-emerald-600 text-white font-bold text-sm active:scale-[0.98] transition">+ Buat Kegiatan</button>
                </template>

                <!-- mandiri → realisasi bukti -->
                <template v-else>
                    <div v-if="jab.sudah_realisasi" class="rounded-xl bg-emerald-50 p-3 text-[12px] text-emerald-700 mb-3">Sudah direalisasi periode ini ✓</div>
                    <template v-else>
                        <div class="flex gap-1 bg-gray-100 rounded-2xl p-1 mb-3">
                            <button v-for="b in ['teks','link','foto']" :key="b" @click="jbukti.tipe = b" class="flex-1 py-2 rounded-xl text-[12px] font-bold capitalize transition" :class="jbukti.tipe === b ? 'bg-white text-[#0C78FF] shadow-sm' : 'text-gray-400'">{{ b }}</button>
                        </div>
                        <textarea v-if="jbukti.tipe === 'teks'" v-model="jbukti.teks" rows="3" placeholder="Tuliskan bukti/laporan…" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-3"></textarea>
                        <input v-else-if="jbukti.tipe === 'link'" v-model="jbukti.link" type="url" placeholder="https://…" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-3" />
                        <div v-else class="mb-3">
                            <img v-if="jbukti.preview" :src="jbukti.preview" class="w-full h-40 object-cover rounded-xl mb-2" />
                            <input type="file" accept="image/*" capture="environment" @change="jFoto" class="block w-full text-xs text-gray-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-[#0C78FF]/10 file:text-[#0C78FF] file:text-xs file:font-semibold" />
                        </div>
                        <input v-model="jbukti.keterangan" type="text" placeholder="Keterangan (opsional)" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-4" />
                        <button @click="kirimRealisasi" :disabled="jSaving" class="w-full py-3 rounded-xl bg-[#0C78FF] text-white font-bold text-sm disabled:opacity-60">{{ jSaving ? 'Mengirim…' : 'Kirim Realisasi' }}</button>
                    </template>
                </template>
            </template>
        </BottomSheet>

        <!-- Sheet buat kegiatan (jabatan) -->
        <BottomSheet v-model="buatSheet" title="Buat Kegiatan">
            <input v-model="kf.nama" type="text" placeholder="Nama kegiatan *" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-3" />
            <div class="grid grid-cols-2 gap-3 mb-3">
                <input v-model="kf.tanggal" type="date" class="px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none" />
                <input v-model="kf.jam" type="time" class="px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none" />
            </div>
            <input v-model="kf.lokasi" type="text" placeholder="Lokasi (opsional)" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-4" />
            <button @click="buatKegiatan('tugas_jabatan', jab?.id)" :disabled="kSaving" class="w-full py-3 rounded-xl bg-emerald-600 text-white font-bold text-sm disabled:opacity-60">{{ kSaving ? 'Membuat…' : 'Buat & Buka' }}</button>
        </BottomSheet>
    </div>
</template>
