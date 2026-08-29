<template>
    <div class="min-h-screen flex">

        <Head title="Login" />

        <!-- ══ SPLASH SAAT LOGIN (branded) ══════════════════════════════════ -->
        <Transition name="splash">
            <div v-if="submitting"
                class="fixed inset-0 z-[100] flex flex-col items-center justify-center bg-gradient-to-br from-indigo-900 via-indigo-800 to-violet-900">
                <!-- dekorasi lembut -->
                <div class="absolute -top-24 -left-24 w-80 h-80 rounded-full bg-white/5"></div>
                <div class="absolute -bottom-28 -right-20 w-96 h-96 rounded-full bg-violet-500/10 blur-2xl"></div>

                <div class="relative flex flex-col items-center gap-5">
                    <div class="splash-logo w-20 h-20 rounded-2xl bg-white/95 p-2.5 shadow-2xl shadow-black/30 flex items-center justify-center">
                        <img src="/logo.png" alt="An Nur Smart System" class="w-full h-full object-contain" />
                    </div>
                    <div class="text-center">
                        <p class="text-white font-bold text-lg tracking-tight leading-none">An Nur Smart System</p>
                        <p class="text-indigo-200 text-xs mt-2">Menyiapkan dashboard…</p>
                    </div>
                    <svg class="w-6 h-6 text-white/80 animate-spin mt-1" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                </div>
            </div>
        </Transition>

        <div
            class="hidden lg:flex lg:w-1/2 relative overflow-hidden bg-gradient-to-br from-indigo-900 via-indigo-800 to-violet-900">

            <div class="absolute inset-0 opacity-10">
                <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                            <path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="1" />
                        </pattern>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#grid)" />
                </svg>
            </div>

            <div class="absolute -top-20 -left-20 w-80 h-80 rounded-full bg-white opacity-5"></div>
            <div class="absolute -bottom-32 -right-16 w-96 h-96 rounded-full bg-violet-500 opacity-10"></div>
            <div
                class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-64 h-64 rounded-full bg-indigo-500 opacity-10 blur-3xl">
            </div>

            <div class="relative z-10 flex flex-col justify-between p-12 w-full">

                <div class="flex items-center gap-3">
                    <div
                        class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur p-1 flex items-center justify-center overflow-hidden">
                        <img src="/logo.png" alt="Logo An Nur" class="w-full h-full object-contain" />
                    </div>
                    <div>
                        <p class="text-white font-semibold text-lg leading-tight">An Nur</p>
                        <p class="text-indigo-300 text-xs">Smart System</p>
                    </div>
                </div>

                <div>
                    <div class="mb-6">
                        <span
                            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/10 text-indigo-200 text-xs font-medium backdrop-blur mb-6">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            Smart Payroll System
                        </span>
                    </div>
                    <h1 class="text-4xl font-bold text-white leading-tight mb-4">
                        Kelola Penggajian<br />
                        <span class="text-indigo-300">Lebih Cerdas</span>
                    </h1>
                    <p class="text-indigo-200 text-base leading-relaxed max-w-sm">
                        Sistem manajemen terpadu untuk pondok pesantren. Absensi, pembelajaran, dan penggajian dalam
                        satu platform.
                    </p>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div v-for="feature in features" :key="feature.label"
                        class="p-3 rounded-xl bg-white/10 backdrop-blur border border-white/10">
                        <span class="text-2xl mb-2 block">{{ feature.icon }}</span>
                        <p class="text-white text-xs font-medium">{{ feature.label }}</p>
                        <p class="text-indigo-300 text-xs mt-0.5">{{ feature.desc }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-10 bg-gray-50">
            <div class="w-full max-w-md">

                <div class="flex items-center gap-3 mb-8 lg:hidden">
                    <div
                        class="w-12 h-12 rounded-xl bg-white shadow-sm border border-gray-100 p-1 flex items-center justify-center overflow-hidden">
                        <img src="/logo.png" alt="Logo An Nur" class="w-full h-full object-contain" />
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900 text-lg">An Nur Smart System</p>
                        <p class="text-gray-500 text-xs">Pondok Pesantren</p>
                    </div>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-1">Selamat Datang</h2>
                    <p class="text-gray-500 text-sm">Masuk ke panel administrasi An Nur</p>
                </div>

                <div v-if="$page.props.flash?.error || errors.general"
                    class="mb-5 flex items-start gap-3 p-4 rounded-xl bg-red-50 border border-red-100">
                    <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-sm text-red-700">{{ $page.props.flash?.error || errors.general }}</p>
                </div>

                <form @submit.prevent="submit" class="space-y-5">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Email atau Username
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <input v-model="form.login" type="text" placeholder="email@annur.sch.id atau username"
                                autocomplete="username" :class="[
                                    'w-full pl-10 pr-4 py-2.5 rounded-xl border text-sm transition-all outline-none',
                                    errors.login
                                        ? 'border-red-300 bg-red-50 focus:border-red-500 focus:ring-2 focus:ring-red-100'
                                        : 'border-gray-200 bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100'
                                ]" />
                        </div>
                        <p v-if="errors.login" class="mt-1.5 text-xs text-red-600">{{ errors.login }}</p>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-sm font-medium text-gray-700">Password</label>
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <input v-model="form.password" :type="showPassword ? 'text' : 'password'"
                                placeholder="Masukkan password" autocomplete="current-password" :class="[
                                    'w-full pl-10 pr-12 py-2.5 rounded-xl border text-sm transition-all outline-none',
                                    errors.password
                                        ? 'border-red-300 bg-red-50 focus:border-red-500 focus:ring-2 focus:ring-red-100'
                                        : 'border-gray-200 bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100'
                                ]" />
                            <button type="button" @click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-gray-400 hover:text-gray-600 transition-colors">
                                <svg v-if="!showPassword" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                </svg>
                            </button>
                        </div>
                        <p v-if="errors.password" class="mt-1.5 text-xs text-red-600">{{ errors.password }}</p>
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <div class="relative">
                                <input v-model="form.remember" type="checkbox" class="sr-only peer" />
                                <div
                                    class="w-9 h-5 rounded-full border-2 border-gray-200 bg-gray-100 peer-checked:bg-indigo-600 peer-checked:border-indigo-600 transition-all">
                                </div>
                                <div
                                    class="absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white shadow transition-all peer-checked:translate-x-4">
                                </div>
                            </div>
                            <span class="text-sm text-gray-600">Ingat saya</span>
                        </label>
                    </div>

                    <button type="submit" :disabled="form.processing" class="w-full flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl
                               bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800
                               text-white text-sm font-semibold
                               transition-all focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2
                               disabled:opacity-60 disabled:cursor-not-allowed">
                        <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        <span>{{ form.processing ? 'Memproses...' : 'Masuk' }}</span>
                    </button>

                </form>

                <div class="mt-8 pt-6 border-t border-gray-200">
                    <p class="text-center text-xs text-gray-400">
                        Hanya untuk administrator & staf resmi<br />
                        Pondok Pesantren An Nur
                    </p>
                </div>

            </div>
        </div>

    </div>
</template>

<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'

// Data fitur untuk card di branding kiri
const features = [
    { icon: '🕐', label: 'Absensi Digital', desc: 'Real-time tracking' },
    { icon: '💰', label: 'Smart Payroll', desc: 'Auto kalkulasi' },
    { icon: '📋', label: 'Manajemen Tugas', desc: 'Terstruktur rapi' },
]

const showPassword = ref(false)
// Splash tampil sejak submit → tetap tampil menembus transisi ke dashboard
// (Inertia menahan halaman Login sampai dashboard siap). Reset hanya bila gagal.
const submitting = ref(false)

// Form Inertia — otomatis handle loading state & error
const form = useForm({
    login: '',
    password: '',
    remember: false,
})

// Props error dari server (validasi Laravel)
defineProps({
    errors: {
        type: Object,
        default: () => ({}),
    },
})

function submit() {
    submitting.value = true
    form.post(route('login.post'), {
        onError: () => {
            // Gagal → sembunyikan splash & bersihkan password
            submitting.value = false
            form.reset('password')
        },
        // Sukses → biarkan splash tampil sampai dashboard menggantikan halaman
    })
}
</script>

<style scoped>
.splash-enter-active,
.splash-leave-active {
    transition: opacity 0.35s ease;
}

.splash-enter-from,
.splash-leave-to {
    opacity: 0;
}

.splash-logo {
    animation: splashPulse 1.6s ease-in-out infinite;
}

@keyframes splashPulse {
    0%, 100% { transform: scale(1); }
    50%      { transform: scale(1.06); }
}

@media (prefers-reduced-motion: reduce) {
    .splash-logo { animation: none; }
}
</style>