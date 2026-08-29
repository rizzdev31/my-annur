<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api'
import { auth } from '../store/auth'
import { toast } from '../store/toast'

const router = useRouter()
const mode = ref('login')          // 'login' | 'aktivasi' | 'reset'
const resetStep = ref('nis')
const busy = ref(false)
const showPw = ref(false)
const f = reactive({ nis: '', password: '', konfirmasi: '', tanggal_lahir: '', kode: '' })
const waMask = ref('')

function masuk(res) { auth.setToken(res.data.data.token, res.data.data.santri); router.replace({ name: 'beranda' }) }

async function login() {
    if (!f.nis.trim() || !f.password) return toast.warning('Isi NIS & password.')
    busy.value = true
    try { masuk(await api.post('/auth/login', { nis: f.nis.trim(), password: f.password })) }
    catch (e) {
        const c = e.response?.data
        if (c?.code === 'BELUM_AKTIVASI') { mode.value = 'aktivasi'; toast.info('Akun belum aktif. Buat password dulu.') }
        else toast.error(c?.message || 'Gagal masuk.')
    } finally { busy.value = false }
}
async function aktivasi() {
    if (!f.nis.trim() || !f.tanggal_lahir) return toast.warning('Isi NIS & tanggal lahir.')
    if (f.password.length < 6) return toast.warning('Password minimal 6 karakter.')
    if (f.password !== f.konfirmasi) return toast.warning('Konfirmasi password tidak cocok.')
    busy.value = true
    try { masuk(await api.post('/auth/aktivasi', { nis: f.nis.trim(), tanggal_lahir: f.tanggal_lahir, password: f.password })); toast.success('Password dibuat.') }
    catch (e) { toast.error(e.response?.data?.message || 'Aktivasi gagal.') }
    finally { busy.value = false }
}
async function mintaOtp() {
    if (!f.nis.trim()) return toast.warning('Masukkan NIS.')
    busy.value = true
    try {
        const res = await api.post('/auth/minta-otp', { nis: f.nis.trim() })
        waMask.value = res.data.data?.wa || ''; resetStep.value = 'kode'
        toast.success(res.data.message || 'OTP dikirim.')
    } catch (e) { toast.error(e.response?.data?.message || 'Gagal mengirim OTP.') }
    finally { busy.value = false }
}
async function reset() {
    if (f.kode.length !== 6) return toast.warning('Kode OTP 6 digit.')
    if (f.password.length < 6) return toast.warning('Password minimal 6 karakter.')
    if (f.password !== f.konfirmasi) return toast.warning('Konfirmasi tidak cocok.')
    busy.value = true
    try { masuk(await api.post('/auth/reset-password', { nis: f.nis.trim(), kode: f.kode, password: f.password })); toast.success('Password diperbarui.') }
    catch (e) { toast.error(e.response?.data?.message || 'Reset gagal.') }
    finally { busy.value = false }
}
function ganti(m) { mode.value = m; resetStep.value = 'nis'; f.password = ''; f.konfirmasi = ''; f.kode = '' }

const field = 'w-full pl-11 pr-4 py-3.5 rounded-2xl bg-gray-50 border border-gray-200 text-sm text-gray-800 placeholder:text-gray-300 focus:bg-white focus:border-[#0C78FF] focus:ring-4 focus:ring-[#0C78FF]/10 outline-none transition'
</script>

<template>
    <div class="relative min-h-dvh bg-white flex flex-col overflow-hidden">
        <!-- Dekorasi gradient lembut (senada app guru) -->
        <div class="pointer-events-none absolute -top-24 -right-20 w-72 h-72 rounded-full bg-gradient-to-br from-[#0C78FF]/20 to-[#06346B]/10 blur-2xl"></div>
        <div class="pointer-events-none absolute -bottom-24 -left-16 w-64 h-64 rounded-full bg-gradient-to-tr from-[#0C78FF]/10 to-transparent blur-2xl"></div>

        <div class="safe-t relative flex-1 flex flex-col justify-center px-6 py-10 w-full max-w-md mx-auto">
            <!-- Header logo -->
            <div class="flex items-start justify-between mb-8">
                <div class="w-16 h-16 rounded-2xl bg-[#0C78FF]/10 grid place-items-center shadow-sm shadow-[#0C78FF]/10">
                    <img src="/logo.png" alt="An-Nur" class="w-10 h-10 object-contain" />
                </div>
                <span class="mt-2 text-[10px] font-semibold text-gray-300">Portal Santri · v1.0</span>
            </div>

            <h1 class="text-[26px] leading-tight font-extrabold text-gray-900">
                {{ mode === 'login' ? 'Assalamu\'alaikum 👋' : mode === 'aktivasi' ? 'Aktivasi Akun' : 'Lupa Password' }}
            </h1>
            <p class="text-sm text-gray-400 mt-1.5 mb-8">
                {{ mode === 'login' ? 'Masuk untuk memantau perkembangan ananda di PP An-Nur Sidoarjo.' : mode === 'aktivasi' ? 'Buat password dengan verifikasi tanggal lahir santri.' : 'Reset password lewat OTP WhatsApp wali.' }}
            </p>

            <!-- LOGIN -->
            <div v-if="mode === 'login'" class="space-y-4">
                <div>
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">NIS Santri</label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-300"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v11a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 012-2h0a2 2 0 012 2v1m-4 0h4" /></svg></span>
                        <input v-model="f.nis" inputmode="numeric" placeholder="Nomor Induk Santri" :class="field" />
                    </div>
                </div>
                <div>
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Password</label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-300"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg></span>
                        <input v-model="f.password" :type="showPw ? 'text' : 'password'" placeholder="Password" @keyup.enter="login" :class="field" class="!pr-16" />
                        <button @click="showPw = !showPw" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-[11px] font-bold text-gray-400">{{ showPw ? 'Sembunyi' : 'Lihat' }}</button>
                    </div>
                </div>
                <button @click="login" :disabled="busy" class="w-full py-3.5 rounded-2xl bg-[#0C78FF] text-white font-bold shadow-lg shadow-[#0C78FF]/25 disabled:opacity-60 active:scale-[0.99] transition">{{ busy ? 'Masuk…' : 'Masuk' }}</button>
                <div class="flex items-center justify-between pt-1 text-[12px] font-bold text-[#0C78FF]">
                    <button @click="ganti('aktivasi')">Aktivasi akun</button>
                    <button @click="ganti('reset')">Lupa password?</button>
                </div>
            </div>

            <!-- AKTIVASI -->
            <div v-else-if="mode === 'aktivasi'" class="space-y-3.5">
                <div class="relative"><span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-300"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v11a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5" /></svg></span><input v-model="f.nis" inputmode="numeric" placeholder="NIS Santri" :class="field" /></div>
                <div><label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Tanggal Lahir Santri</label><input v-model="f.tanggal_lahir" type="date" class="w-full px-4 py-3.5 rounded-2xl bg-gray-50 border border-gray-200 text-sm outline-none focus:bg-white focus:border-[#0C78FF] focus:ring-4 focus:ring-[#0C78FF]/10" /></div>
                <input v-model="f.password" :type="showPw ? 'text' : 'password'" placeholder="Password baru (min 6)" class="w-full px-4 py-3.5 rounded-2xl bg-gray-50 border border-gray-200 text-sm outline-none focus:bg-white focus:border-[#0C78FF] focus:ring-4 focus:ring-[#0C78FF]/10" />
                <input v-model="f.konfirmasi" :type="showPw ? 'text' : 'password'" placeholder="Ulangi password" class="w-full px-4 py-3.5 rounded-2xl bg-gray-50 border border-gray-200 text-sm outline-none focus:bg-white focus:border-[#0C78FF] focus:ring-4 focus:ring-[#0C78FF]/10" />
                <button @click="aktivasi" :disabled="busy" class="w-full py-3.5 rounded-2xl bg-[#0C78FF] text-white font-bold shadow-lg shadow-[#0C78FF]/25 disabled:opacity-60">{{ busy ? 'Menyimpan…' : 'Buat Password & Masuk' }}</button>
                <button @click="ganti('login')" class="w-full text-[12px] font-bold text-gray-400">← Sudah punya password? Masuk</button>
            </div>

            <!-- RESET -->
            <div v-else class="space-y-3.5">
                <template v-if="resetStep === 'nis'">
                    <div class="relative"><span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-300"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v11a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5" /></svg></span><input v-model="f.nis" inputmode="numeric" placeholder="NIS Santri" :class="field" /></div>
                    <button @click="mintaOtp" :disabled="busy" class="w-full py-3.5 rounded-2xl bg-[#0C78FF] text-white font-bold shadow-lg shadow-[#0C78FF]/25 disabled:opacity-60">{{ busy ? 'Mengirim…' : 'Kirim OTP ke WhatsApp' }}</button>
                </template>
                <template v-else>
                    <p class="text-[12px] text-gray-500">Kode dikirim ke WhatsApp <b>{{ waMask }}</b></p>
                    <input v-model="f.kode" inputmode="numeric" maxlength="6" placeholder="6 digit OTP" class="w-full px-4 py-3.5 rounded-2xl bg-gray-50 border border-gray-200 text-center tracking-[0.4em] font-bold outline-none focus:bg-white focus:border-[#0C78FF] focus:ring-4 focus:ring-[#0C78FF]/10" />
                    <input v-model="f.password" :type="showPw ? 'text' : 'password'" placeholder="Password baru (min 6)" class="w-full px-4 py-3.5 rounded-2xl bg-gray-50 border border-gray-200 text-sm outline-none focus:bg-white focus:border-[#0C78FF] focus:ring-4 focus:ring-[#0C78FF]/10" />
                    <input v-model="f.konfirmasi" :type="showPw ? 'text' : 'password'" placeholder="Ulangi password" class="w-full px-4 py-3.5 rounded-2xl bg-gray-50 border border-gray-200 text-sm outline-none focus:bg-white focus:border-[#0C78FF] focus:ring-4 focus:ring-[#0C78FF]/10" />
                    <button @click="reset" :disabled="busy" class="w-full py-3.5 rounded-2xl bg-[#0C78FF] text-white font-bold shadow-lg shadow-[#0C78FF]/25 disabled:opacity-60">{{ busy ? 'Menyimpan…' : 'Simpan & Masuk' }}</button>
                    <button @click="resetStep = 'nis'; f.kode = ''" class="w-full text-[12px] text-gray-400 font-semibold">← Ganti NIS / kirim ulang</button>
                </template>
                <button @click="ganti('login')" class="w-full text-[12px] font-bold text-gray-400">← Kembali ke Masuk</button>
            </div>

            <p class="text-center text-gray-300 text-[11px] mt-8">Hanya menampilkan data ananda · read-only</p>
        </div>
    </div>
</template>
