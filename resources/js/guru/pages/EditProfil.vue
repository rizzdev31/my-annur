<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api'
import { auth } from '../store/auth'
import { toast } from '../store/toast'
import PageHeader from '../components/PageHeader.vue'
import BottomSheet from '../components/BottomSheet.vue'

const router = useRouter()
const loading = ref(true)
const saving = ref(false)
const fotoBusy = ref(false)

const identitas = reactive({ nip: '', nik: '', jabatan: '' })
const foto = ref(null)
const f = reactive({
    name: '', email: '', no_hp: '', alamat: '', tempat_lahir: '', tanggal_lahir: '',
    jenis_kelamin: '', pendidikan_terakhir: '', jurusan: '',
    no_rekening: '', nama_bank: '', nama_rekening: '',
})
const PENDIDIKAN = ['SMA', 'D3', 'S1', 'S2', 'S3', 'Pesantren']
const initials = () => (f.name || 'AN').split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase()

async function load() {
    loading.value = true
    try {
        const p = (await api.get('/profile')).data.data ?? {}
        Object.keys(f).forEach(k => { f[k] = p[k] ?? '' })
        identitas.nip = p.nip || '—'; identitas.nik = p.nik || '—'; identitas.jabatan = p.jabatan_display || p.jabatan || '—'
        foto.value = p.foto || null
    } catch (e) { toast.error(e.response?.data?.message || 'Gagal memuat profil.') }
    finally { loading.value = false }
}
onMounted(load)

async function pilihFoto(e) {
    const file = e.target.files?.[0]
    if (!file) return
    if (file.size > 2 * 1024 * 1024) return toast.warning('Ukuran foto maksimal 2MB.')
    fotoBusy.value = true
    try {
        const fd = new FormData(); fd.append('foto', file)
        const res = await api.post('/profile/foto', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
        foto.value = res.data.data?.foto || foto.value
        if (auth.user) auth.user.foto = foto.value
        toast.success('Foto profil diperbarui.')
    } catch (er) { toast.error(er.response?.data?.message || 'Gagal mengunggah foto.') }
    finally { fotoBusy.value = false; e.target.value = '' }
}

async function simpan() {
    if (!f.name.trim()) return toast.warning('Nama wajib diisi.')
    if (!f.email.trim()) return toast.warning('Email wajib diisi.')
    saving.value = true
    try {
        const nn = (v) => { const s = (v ?? '').toString().trim(); return s === '' ? null : s }
        const payload = {
            name: f.name.trim(), email: f.email.trim(),
            no_hp: nn(f.no_hp), alamat: nn(f.alamat), tempat_lahir: nn(f.tempat_lahir),
            tanggal_lahir: nn(f.tanggal_lahir), jenis_kelamin: nn(f.jenis_kelamin),
            pendidikan_terakhir: nn(f.pendidikan_terakhir), jurusan: nn(f.jurusan),
            no_rekening: nn(f.no_rekening), nama_bank: nn(f.nama_bank), nama_rekening: nn(f.nama_rekening),
        }
        const res = await api.put('/profile', payload)
        auth.user = res.data.data ?? auth.user
        toast.success(res.data.message || 'Profil diperbarui.')
        router.back()
    } catch (e) {
        const err = e.response?.data
        toast.error(err?.errors ? Object.values(err.errors)[0][0] : (err?.message || 'Gagal menyimpan.'))
    } finally { saving.value = false }
}

// ── Ganti password ───────────────────────────────────────────────────────────
const pwSheet = ref(false)
const pwBusy = ref(false)
const pw = reactive({ lama: '', baru: '', konfirmasi: '' })
const show = reactive({ lama: false, baru: false })
function openPw() { Object.assign(pw, { lama: '', baru: '', konfirmasi: '' }); Object.assign(show, { lama: false, baru: false }); pwSheet.value = true }
async function gantiPassword() {
    if (!pw.lama) return toast.warning('Isi password lama.')
    if (pw.baru.length < 8) return toast.warning('Password baru minimal 8 karakter.')
    if (pw.baru !== pw.konfirmasi) return toast.warning('Konfirmasi password tidak cocok.')
    pwBusy.value = true
    try {
        await api.put('/profile/password', { password_lama: pw.lama, password_baru: pw.baru, password_baru_confirmation: pw.konfirmasi })
        pwSheet.value = false
        toast.success('Password berhasil diperbarui.')
    } catch (e) { toast.error(e.response?.data?.message || 'Gagal mengganti password.') }
    finally { pwBusy.value = false }
}
</script>

<template>
    <div class="pb-4">
        <PageHeader title="Edit Profil" />
        <div v-if="loading" class="pt-16 flex justify-center"><div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div></div>

        <template v-else>
            <!-- Foto -->
            <div class="flex flex-col items-center mb-5">
                <div class="relative">
                    <div class="w-24 h-24 rounded-full bg-[#0C78FF]/10 text-[#0C78FF] ring-4 ring-[#0C78FF]/15 overflow-hidden grid place-items-center text-2xl font-extrabold">
                        <img v-if="foto" :src="foto" class="w-full h-full object-cover" @error="foto = null" />
                        <span v-else>{{ initials() }}</span>
                        <div v-if="fotoBusy" class="absolute inset-0 bg-black/40 grid place-items-center"><div class="w-6 h-6 border-2 border-white border-t-transparent rounded-full animate-spin"></div></div>
                    </div>
                    <label class="absolute bottom-0 right-0 w-8 h-8 rounded-full bg-[#0C78FF] grid place-items-center shadow-lg cursor-pointer active:scale-95 transition">
                        <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5l4 4M4 20h4l10.5-10.5a2.83 2.83 0 10-4-4L4 16v4z"/></svg>
                        <input type="file" accept="image/*" class="hidden" @change="pilihFoto" />
                    </label>
                </div>
                <p class="mt-2 text-[11px] text-gray-400">Ketuk ikon untuk ganti foto (maks 2MB)</p>
            </div>

            <!-- Identitas (read-only) -->
            <div class="rounded-2xl bg-gray-50 border border-gray-100 px-4 py-3 mb-5 grid grid-cols-2 gap-3">
                <div><p class="text-[10px] text-gray-400">NIP</p><p class="text-sm font-semibold text-gray-700">{{ identitas.nip }}</p></div>
                <div><p class="text-[10px] text-gray-400">Jabatan</p><p class="text-sm font-semibold text-gray-700 truncate">{{ identitas.jabatan }}</p></div>
                <div class="col-span-2"><p class="text-[10px] text-gray-400">NIK</p><p class="text-sm font-semibold text-gray-700">{{ identitas.nik }}</p></div>
            </div>

            <!-- Akun -->
            <p class="text-[11px] font-extrabold uppercase tracking-wider text-gray-400 px-1 mb-2">Akun</p>
            <div class="space-y-3 mb-5">
                <div><label class="text-xs font-semibold text-gray-500">Nama Lengkap</label><input v-model="f.name" type="text" class="mt-1 w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]" /></div>
                <div><label class="text-xs font-semibold text-gray-500">Email</label><input v-model="f.email" type="email" class="mt-1 w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]" /></div>
                <div><label class="text-xs font-semibold text-gray-500">No. HP</label><input v-model="f.no_hp" type="tel" class="mt-1 w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]" /></div>
                <div><label class="text-xs font-semibold text-gray-500">Alamat</label><textarea v-model="f.alamat" rows="2" class="mt-1 w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]"></textarea></div>
            </div>

            <!-- Data Diri -->
            <p class="text-[11px] font-extrabold uppercase tracking-wider text-gray-400 px-1 mb-2">Data Diri</p>
            <div class="space-y-3 mb-5">
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="text-xs font-semibold text-gray-500">Tempat Lahir</label><input v-model="f.tempat_lahir" type="text" class="mt-1 w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]" /></div>
                    <div><label class="text-xs font-semibold text-gray-500">Tgl Lahir</label><input v-model="f.tanggal_lahir" type="date" class="mt-1 w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]" /></div>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-500">Jenis Kelamin</label>
                    <div class="mt-1 flex gap-2">
                        <button v-for="opt in [['L','Laki-laki'],['P','Perempuan']]" :key="opt[0]" @click="f.jenis_kelamin = opt[0]"
                            class="flex-1 py-2.5 rounded-xl text-sm font-bold border transition" :class="f.jenis_kelamin === opt[0] ? 'bg-[#0C78FF] text-white border-[#0C78FF]' : 'bg-white text-gray-500 border-gray-200'">{{ opt[1] }}</button>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="text-xs font-semibold text-gray-500">Pendidikan</label>
                        <select v-model="f.pendidikan_terakhir" class="mt-1 w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF] bg-white">
                            <option value="">—</option><option v-for="p in PENDIDIKAN" :key="p" :value="p">{{ p }}</option>
                        </select>
                    </div>
                    <div><label class="text-xs font-semibold text-gray-500">Jurusan</label><input v-model="f.jurusan" type="text" class="mt-1 w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]" /></div>
                </div>
            </div>

            <!-- Rekening -->
            <p class="text-[11px] font-extrabold uppercase tracking-wider text-gray-400 px-1 mb-2">Rekening Gaji</p>
            <div class="space-y-3 mb-6">
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="text-xs font-semibold text-gray-500">Bank</label><input v-model="f.nama_bank" type="text" class="mt-1 w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]" /></div>
                    <div><label class="text-xs font-semibold text-gray-500">No. Rekening</label><input v-model="f.no_rekening" type="text" class="mt-1 w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]" /></div>
                </div>
                <div><label class="text-xs font-semibold text-gray-500">Atas Nama</label><input v-model="f.nama_rekening" type="text" class="mt-1 w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]" /></div>
            </div>

            <button @click="simpan" :disabled="saving" class="w-full py-3.5 rounded-2xl bg-[#0C78FF] text-white font-bold disabled:opacity-60 mb-3">{{ saving ? 'Menyimpan…' : 'Simpan Perubahan' }}</button>
            <button @click="openPw" class="w-full py-3 rounded-2xl bg-gray-100 text-gray-700 font-bold text-sm flex items-center justify-center gap-2">
                <svg class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                Ganti Password
            </button>
        </template>

        <!-- Sheet ganti password -->
        <BottomSheet v-model="pwSheet" title="Ganti Password" subtitle="Minimal 8 karakter">
            <div class="space-y-3 mb-4">
                <div>
                    <label class="text-xs font-semibold text-gray-500">Password Lama</label>
                    <div class="mt-1 relative">
                        <input v-model="pw.lama" :type="show.lama ? 'text' : 'password'" class="w-full px-3 py-2.5 pr-10 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]" />
                        <button @click="show.lama = !show.lama" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs font-bold">{{ show.lama ? 'Sembunyi' : 'Lihat' }}</button>
                    </div>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-500">Password Baru</label>
                    <div class="mt-1 relative">
                        <input v-model="pw.baru" :type="show.baru ? 'text' : 'password'" class="w-full px-3 py-2.5 pr-10 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]" />
                        <button @click="show.baru = !show.baru" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs font-bold">{{ show.baru ? 'Sembunyi' : 'Lihat' }}</button>
                    </div>
                </div>
                <div><label class="text-xs font-semibold text-gray-500">Konfirmasi Password Baru</label><input v-model="pw.konfirmasi" :type="show.baru ? 'text' : 'password'" class="mt-1 w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]" /></div>
            </div>
            <button @click="gantiPassword" :disabled="pwBusy" class="w-full py-3 rounded-xl bg-[#0C78FF] text-white font-bold text-sm disabled:opacity-60">{{ pwBusy ? 'Menyimpan…' : 'Perbarui Password' }}</button>
        </BottomSheet>
    </div>
</template>
