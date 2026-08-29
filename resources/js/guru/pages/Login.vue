<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { auth } from '../store/auth'

const router = useRouter()
const username = ref('')
const password = ref('')
const showPass = ref(false)
const error = ref('')

async function submit() {
    error.value = ''
    if (!username.value || !password.value) {
        error.value = 'Isi username dan sandi.'
        return
    }
    const res = await auth.login(username.value.trim(), password.value)
    if (res.ok) router.replace({ name: 'beranda' })
    else error.value = res.message
}
</script>

<template>
    <div class="relative min-h-dvh bg-white flex flex-col overflow-hidden">
        <!-- Dekorasi gradient lembut -->
        <div class="pointer-events-none absolute -top-24 -right-20 w-72 h-72 rounded-full bg-gradient-to-br from-[#0C78FF]/20 to-[#06346B]/10 blur-2xl"></div>
        <div class="pointer-events-none absolute -bottom-24 -left-16 w-64 h-64 rounded-full bg-gradient-to-tr from-[#0C78FF]/10 to-transparent blur-2xl"></div>

        <div class="safe-t relative flex-1 flex flex-col justify-center px-6 py-10 w-full max-w-md mx-auto">
            <!-- Header: logo + versi -->
            <div class="flex items-start justify-between mb-9">
                <div class="w-16 h-16 rounded-2xl bg-[#0C78FF]/10 grid place-items-center shadow-sm shadow-[#0C78FF]/10">
                    <img src="/logo.png" alt="An-Nur" class="w-10 h-10 object-contain" />
                </div>
                <span class="mt-2 text-[10px] font-semibold text-gray-300">An-Nur Smart · v1.0</span>
            </div>

            <!-- Judul -->
            <h1 class="text-[26px] leading-tight font-extrabold text-gray-900">Selamat Datang 👋</h1>
            <p class="text-sm text-gray-400 mt-1.5 mb-8">Masuk ke Portal Tenaga Pendidik<br class="hidden sm:block" /> PP An-Nur Sidoarjo.</p>

            <!-- Form -->
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Email / Username</label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-300">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </span>
                        <input v-model="username" type="text" autocomplete="username" placeholder="username atau email"
                            class="w-full pl-11 pr-4 py-3.5 rounded-2xl bg-gray-50 border border-gray-200 text-sm text-gray-800 placeholder:text-gray-300 focus:bg-white focus:border-[#0C78FF] focus:ring-4 focus:ring-[#0C78FF]/10 outline-none transition" />
                    </div>
                </div>

                <div>
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Sandi</label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-300">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </span>
                        <input v-model="password" :type="showPass ? 'text' : 'password'" autocomplete="current-password" placeholder="••••••••"
                            class="w-full pl-11 pr-12 py-3.5 rounded-2xl bg-gray-50 border border-gray-200 text-sm text-gray-800 placeholder:text-gray-300 focus:bg-white focus:border-[#0C78FF] focus:ring-4 focus:ring-[#0C78FF]/10 outline-none transition" />
                        <button type="button" @click="showPass = !showPass" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-500">
                            <svg v-if="!showPass" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                </div>

                <Transition name="fade">
                    <p v-if="error" class="text-sm text-red-600 bg-red-50 rounded-xl px-3.5 py-2.5 flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                        {{ error }}
                    </p>
                </Transition>

                <button type="submit" :disabled="auth.loading"
                    class="w-full py-4 rounded-2xl bg-gradient-to-r from-[#0C78FF] to-[#0958c9] text-white font-bold text-[15px] shadow-lg shadow-[#0C78FF]/25 disabled:opacity-60 active:scale-[0.99] transition">
                    <span v-if="!auth.loading">Masuk</span>
                    <span v-else class="inline-flex items-center gap-2">
                        <span class="w-4 h-4 border-2 border-white/50 border-t-white rounded-full animate-spin"></span> Memproses…
                    </span>
                </button>
            </form>

            <p class="text-center text-xs text-gray-400 mt-7">
                Lupa sandi? <span class="text-gray-500 font-semibold">Hubungi admin sekolah.</span>
            </p>
        </div>

        <p class="safe-b relative text-center text-[11px] text-gray-300 pb-4">© 2026 An-Nur Smart System</p>
    </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
