<script setup>
import { ref, onMounted } from 'vue'
import api from '../api'
import { tanggalLokal } from '../tanggal'
import PageHeader from '../components/PageHeader.vue'

const stats = ref(null)
const list = ref([])
const jenis = ref([])
const loading = ref(true)
const showForm = ref(false)
const saving = ref(false)
const msg = ref(null)

const form = ref({ setting_jenis_pengajuan_id: '', tanggal_mulai: '', tanggal_selesai: '', alasan: '', dokumen: null })

const statusClass = (s) => ({
    disetujui: 'text-emerald-600 bg-emerald-50',
    pending: 'text-amber-600 bg-amber-50',
    ditolak: 'text-red-600 bg-red-50',
}[s] || 'text-gray-500 bg-gray-100')

async function load() {
    loading.value = true
    try {
        const [r, j] = await Promise.all([api.get('/izin'), api.get('/izin/jenis')])
        const d = r.data.data ?? r.data
        stats.value = d.stats ?? null
        list.value = d.riwayat ?? d.list ?? []
        jenis.value = j.data.data ?? j.data ?? []
    } catch (_) {/* diamkan */} finally { loading.value = false }
}
onMounted(load)

function pilihDokumen(e) { form.value.dokumen = e.target.files?.[0] || null }

async function ajukan() {
    msg.value = null
    const f = form.value
    if (!f.setting_jenis_pengajuan_id || !f.tanggal_mulai || !f.tanggal_selesai || f.alasan.trim().length < 10) {
        msg.value = { ok: false, text: 'Lengkapi jenis, tanggal, dan alasan (min. 10 karakter).' }
        return
    }
    saving.value = true
    try {
        const fd = new FormData()
        fd.append('setting_jenis_pengajuan_id', f.setting_jenis_pengajuan_id)
        fd.append('tanggal_mulai', f.tanggal_mulai)
        fd.append('tanggal_selesai', f.tanggal_selesai)
        fd.append('alasan', f.alasan.trim())
        if (f.dokumen) fd.append('dokumen', f.dokumen)
        await api.post('/izin', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
        msg.value = { ok: true, text: 'Pengajuan terkirim.' }
        showForm.value = false
        form.value = { setting_jenis_pengajuan_id: '', tanggal_mulai: '', tanggal_selesai: '', alasan: '', dokumen: null }
        await load()
    } catch (e) {
        const errs = e.response?.data?.errors
        msg.value = { ok: false, text: errs ? Object.values(errs)[0][0] : (e.response?.data?.message || 'Gagal mengirim pengajuan.') }
    } finally { saving.value = false }
}

// ── Izin Datang Terlambat ───────────────────────────────────────────────────
const showTelat = ref(false)
const telatForm = ref({ tanggal: '', jam: '', alasan: '' })
const telatSaving = ref(false)
async function ajukanTelat() {
    msg.value = null
    const f = telatForm.value
    if (!f.tanggal || !f.jam || f.alasan.trim().length < 3) {
        msg.value = { ok: false, text: 'Isi tanggal, jam boleh datang, dan alasan.' }; return
    }
    telatSaving.value = true
    try {
        const res = await api.post('/izin/datang-terlambat', { tanggal: f.tanggal, jam: f.jam, alasan: f.alasan.trim() })
        msg.value = { ok: true, text: res.data.message }
        showTelat.value = false
        telatForm.value = { tanggal: '', jam: '', alasan: '' }
        await load()
    } catch (e) {
        msg.value = { ok: false, text: e.response?.data?.message || 'Gagal mengajukan.' }
    } finally { telatSaving.value = false }
}

// ── Izin Sementara (partial-day) ────────────────────────────────────────────
const showSem = ref(false)
const semForm = ref({ jam_mulai: '', jam_selesai: '', alasan: '' })
const semSaving = ref(false)
const izinSemDone = ref(false)
const izinSemId = ref(null)
const semBatal = ref(false)
const sesiTerdampak = ref([])      // {jadwal_mengajar_id, mapel, kelas, jam_*, pengganti_id, pengganti_nama, assigning}
const penggantiOpsi = ref([])

function resetSem() {
    showSem.value = false; izinSemDone.value = false; sesiTerdampak.value = []
    semForm.value = { jam_mulai: '', jam_selesai: '', alasan: '' }
}

async function ajukanSementara() {
    msg.value = null
    const f = semForm.value
    if (!f.jam_mulai || !f.jam_selesai || f.alasan.trim().length < 3) {
        msg.value = { ok: false, text: 'Isi jam mulai, jam selesai, dan alasan.' }; return
    }
    semSaving.value = true
    try {
        const res = await api.post('/izin/sementara', {
            jam_mulai: f.jam_mulai, jam_selesai: f.jam_selesai, alasan: f.alasan.trim(),
        })
        const d = res.data.data ?? {}
        izinSemId.value = d.izin_id ?? null
        sesiTerdampak.value = (d.sesi_terdampak ?? []).map(s => ({ ...s, pengganti_id: '', pengganti_nama: null, assigning: false }))
        izinSemDone.value = true
        msg.value = { ok: true, text: res.data.message }
        if (!penggantiOpsi.value.length) {
            try { const o = await api.get('/absensi/mengajar/pengganti-opsi'); penggantiOpsi.value = o.data.data ?? [] } catch (_) {}
        }
        await load()
    } catch (e) {
        const errs = e.response?.data?.errors
        msg.value = { ok: false, text: errs ? Object.values(errs)[0][0] : (e.response?.data?.message || 'Gagal mengajukan izin sementara.') }
    } finally { semSaving.value = false }
}

async function batalSementara() {
    if (!izinSemId.value) return
    if (!confirm('Batalkan izin sementara ini? Penunjukan pengganti yang belum mengajar akan dibatalkan.')) return
    semBatal.value = true
    try {
        const res = await api.post(`/izin/sementara/${izinSemId.value}/batal`)
        msg.value = { ok: true, text: res.data.message || 'Izin sementara dibatalkan.' }
        resetSem(); await load()
    } catch (e) {
        msg.value = { ok: false, text: e.response?.data?.message || 'Gagal membatalkan.' }
    } finally { semBatal.value = false }
}

async function tunjukPengganti(sesi) {
    if (!sesi.pengganti_id) return
    sesi.assigning = true
    try {
        await api.post('/absensi/mengajar/tunjuk-pengganti', {
            jadwal_mengajar_id: sesi.jadwal_mengajar_id,
            pengganti_id: sesi.pengganti_id,
            tanggal: tanggalLokal(),
            keterangan: `Izin sementara ${semForm.value.jam_mulai}–${semForm.value.jam_selesai}`,
        })
        sesi.pengganti_nama = penggantiOpsi.value.find(o => o.id == sesi.pengganti_id)?.nama || 'Pengganti'
    } catch (e) {
        msg.value = { ok: false, text: e.response?.data?.message || 'Gagal menunjuk pengganti.' }
    } finally { sesi.assigning = false }
}
</script>

<template>
    <div>
        <PageHeader title="Pengajuan Izin" />

        <div v-if="loading" class="pt-10 flex justify-center">
            <div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div>
        </div>

        <template v-else>
            <!-- Ringkasan -->
            <div v-if="stats" class="grid grid-cols-3 gap-3 mb-4">
                <div class="rounded-2xl bg-white border border-gray-100 p-3 text-center">
                    <p class="text-xl font-extrabold text-amber-500">{{ stats.pending ?? 0 }}</p>
                    <p class="text-[10px] text-gray-400">Pending</p>
                </div>
                <div class="rounded-2xl bg-white border border-gray-100 p-3 text-center">
                    <p class="text-xl font-extrabold text-emerald-600">{{ stats.disetujui ?? 0 }}</p>
                    <p class="text-[10px] text-gray-400">Disetujui</p>
                </div>
                <div class="rounded-2xl bg-white border border-gray-100 p-3 text-center">
                    <p class="text-xl font-extrabold text-red-500">{{ stats.ditolak ?? 0 }}</p>
                    <p class="text-[10px] text-gray-400">Ditolak</p>
                </div>
            </div>

            <p v-if="msg" :class="msg.ok ? 'text-emerald-700 bg-emerald-50' : 'text-red-600 bg-red-50'"
                class="text-sm rounded-xl px-3 py-2 mb-3">{{ msg.text }}</p>

            <div class="grid grid-cols-2 gap-3 mb-4">
                <button @click="showForm = !showForm; showSem = false"
                    class="py-3 rounded-2xl bg-[#0C78FF] text-white font-bold text-sm active:scale-[0.99] transition">
                    {{ showForm ? 'Tutup' : '+ Ajukan Izin' }}
                </button>
                <button @click="showSem = !showSem; showForm = false; showTelat = false"
                    class="py-3 rounded-2xl bg-amber-500 text-white font-bold text-sm active:scale-[0.99] transition">
                    {{ showSem ? 'Tutup' : '⏱ Izin Sementara' }}
                </button>
            </div>
            <button @click="showTelat = !showTelat; showForm = false; showSem = false"
                class="w-full py-3 rounded-2xl bg-orange-500 text-white font-bold text-sm mb-4 active:scale-[0.99] transition">
                {{ showTelat ? 'Tutup' : '🕗 Izin Datang Terlambat' }}
            </button>

            <!-- Sheet Datang Terlambat -->
            <div v-if="showTelat" class="rounded-2xl bg-white border border-orange-100 p-4 mb-4 space-y-3">
                <p class="text-xs text-gray-500 -mt-1">Ajukan izin datang terlambat pada jam tertentu. Bila disetujui admin & kamu datang dalam batas jam itu, tetap dicatat <b>hadir</b> (bukan terlambat).</p>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Tanggal</label>
                        <input v-model="telatForm.tanggal" type="date" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-orange-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Boleh datang s/d</label>
                        <input v-model="telatForm.jam" type="time" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-orange-500" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Alasan</label>
                    <textarea v-model="telatForm.alasan" rows="2" placeholder="cth: ada keperluan pagi…" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-orange-500"></textarea>
                </div>
                <button @click="ajukanTelat" :disabled="telatSaving" class="w-full py-3 rounded-xl bg-orange-500 text-white font-bold text-sm disabled:opacity-60">
                    {{ telatSaving ? 'Mengirim…' : 'Ajukan Datang Terlambat' }}
                </button>
            </div>

            <!-- Form -->
            <div v-if="showForm" class="rounded-2xl bg-white border border-gray-100 p-4 mb-4 space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Jenis Pengajuan</label>
                    <select v-model="form.setting_jenis_pengajuan_id" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]">
                        <option value="">— pilih —</option>
                        <option v-for="j in jenis" :key="j.id" :value="j.id">{{ j.nama }}<span v-if="j.sisa_kuota != null"> (sisa {{ j.sisa_kuota }})</span></option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Mulai</label>
                        <input v-model="form.tanggal_mulai" type="date" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Selesai</label>
                        <input v-model="form.tanggal_selesai" type="date" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Alasan</label>
                    <textarea v-model="form.alasan" rows="3" placeholder="Tuliskan alasan (min. 10 karakter)…"
                        class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Dokumen (opsional, PDF/gambar)</label>
                    <input type="file" accept=".pdf,image/*" @change="pilihDokumen" class="block w-full text-xs text-gray-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-[#0C78FF]/10 file:text-[#0C78FF] file:text-xs file:font-semibold" />
                </div>
                <button @click="ajukan" :disabled="saving"
                    class="w-full py-3 rounded-xl bg-emerald-600 text-white font-bold text-sm disabled:opacity-60">
                    {{ saving ? 'Mengirim…' : 'Kirim Pengajuan' }}
                </button>
            </div>

            <!-- Sheet Izin Sementara -->
            <div v-if="showSem" class="rounded-2xl bg-white border border-amber-100 p-4 mb-4 space-y-3">
                <p class="text-xs text-gray-500 -mt-1">Izin meninggalkan tempat sebentar di tengah jam kerja (hari ini). Anda tetap tercatat <b>hadir</b>; sesi mengajar yang beririsan bisa dialihkan ke pengganti.</p>

                <!-- Langkah 1: form -->
                <template v-if="!izinSemDone">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Jam mulai</label>
                            <input v-model="semForm.jam_mulai" type="time" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-amber-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Jam selesai</label>
                            <input v-model="semForm.jam_selesai" type="time" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-amber-500" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Alasan</label>
                        <textarea v-model="semForm.alasan" rows="2" placeholder="cth: ada keperluan mendadak…"
                            class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-amber-500"></textarea>
                    </div>
                    <button @click="ajukanSementara" :disabled="semSaving"
                        class="w-full py-3 rounded-xl bg-amber-500 text-white font-bold text-sm disabled:opacity-60">
                        {{ semSaving ? 'Memproses…' : 'Ajukan Izin Sementara' }}
                    </button>
                </template>

                <!-- Langkah 2: sesi terdampak + tunjuk pengganti -->
                <template v-else>
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-bold text-gray-800">Izin sementara tercatat ✓</p>
                        <div class="flex items-center gap-3">
                            <button @click="batalSementara" :disabled="semBatal" class="text-xs font-semibold text-red-500 disabled:opacity-50">{{ semBatal ? '…' : 'Batalkan izin' }}</button>
                            <button @click="resetSem" class="text-xs font-semibold text-gray-400">Selesai</button>
                        </div>
                    </div>
                    <p v-if="!sesiTerdampak.length" class="text-sm text-gray-500 bg-gray-50 rounded-xl px-3 py-2">Tidak ada sesi mengajar yang terdampak. Aman.</p>
                    <div v-else class="space-y-2.5">
                        <p class="text-xs text-gray-500">{{ sesiTerdampak.length }} sesi mengajar beririsan — tunjuk pengganti (opsional; jika tidak, kelas kosong & JP hangus).</p>
                        <div v-for="s in sesiTerdampak" :key="s.jadwal_mengajar_id" class="rounded-xl border border-gray-100 p-3">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-semibold text-gray-800">{{ s.mapel }} <span class="text-gray-400 font-normal">· {{ s.kelas }}</span></p>
                                <span class="text-[11px] text-gray-400 tabular-nums">{{ s.jam_mulai }}–{{ s.jam_selesai }}</span>
                            </div>
                            <div v-if="s.pengganti_nama" class="mt-2 text-xs text-emerald-600 font-semibold flex items-center gap-1">
                                ✓ Pengganti: {{ s.pengganti_nama }}
                            </div>
                            <div v-else class="mt-2 flex gap-2">
                                <select v-model="s.pengganti_id" class="flex-1 px-2.5 py-2 rounded-lg border border-gray-200 text-xs outline-none focus:border-amber-500">
                                    <option value="">— pilih pengganti —</option>
                                    <option v-for="o in penggantiOpsi" :key="o.id" :value="o.id">{{ o.nama }}</option>
                                </select>
                                <button @click="tunjukPengganti(s)" :disabled="!s.pengganti_id || s.assigning"
                                    class="px-3 py-2 rounded-lg bg-[#0C78FF] text-white text-xs font-bold disabled:opacity-50">
                                    {{ s.assigning ? '…' : 'Tunjuk' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Riwayat -->
            <div v-if="!list.length" class="pt-10 text-center text-sm text-gray-400">Belum ada pengajuan.</div>
            <ul v-else class="space-y-2.5">
                <li v-for="p in list" :key="p.id" class="rounded-2xl bg-white border border-gray-100 p-3.5">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-bold text-gray-800">{{ p.jenis || p.jenis_nama || p.kategori || 'Izin' }}</p>
                        <span class="text-[10px] font-bold px-2.5 py-1 rounded-full capitalize" :class="statusClass(p.status)">{{ p.status_label || p.status }}</span>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">{{ p.tanggal_mulai }} — {{ p.tanggal_selesai }}<span v-if="p.jumlah_hari"> · {{ p.jumlah_hari }} hari</span></p>
                    <p v-if="p.alasan" class="text-xs text-gray-500 mt-1 line-clamp-2">{{ p.alasan }}</p>
                </li>
            </ul>
        </template>
    </div>
</template>
