<template>
    <Transition name="modal">
        <div v-if="show" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="!loading && $emit('cancel')" />

            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                <!-- Accent strip -->
                <div :class="['h-1.5', accent.bar]" />

                <div class="px-6 pt-6 pb-5">
                    <!-- Icon + judul -->
                    <div class="flex items-start gap-4">
                        <div :class="['w-12 h-12 rounded-2xl flex items-center justify-center shrink-0', accent.iconBg]">
                            <svg class="w-6 h-6" :class="accent.iconText" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path v-if="variant === 'danger'" stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                <path v-else-if="variant === 'success'" stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                <path v-else stroke-linecap="round" stroke-linejoin="round"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="flex-1 pt-0.5">
                            <h3 class="text-lg font-bold text-gray-900 leading-snug">{{ title }}</h3>
                            <p v-if="message" class="text-sm text-gray-500 mt-1 leading-relaxed">{{ message }}</p>
                        </div>
                    </div>

                    <!-- Daftar konsekuensi -->
                    <div v-if="details.length" class="mt-4 rounded-xl bg-gray-50 border border-gray-100 p-3.5 space-y-2.5">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Yang akan terjadi</p>
                        <div v-for="(d, i) in details" :key="i" class="flex items-start gap-2.5">
                            <span :class="['mt-1.5 w-1.5 h-1.5 rounded-full shrink-0', accent.dot]" />
                            <span class="text-[13px] text-gray-600 leading-relaxed">{{ d }}</span>
                        </div>
                    </div>

                    <!-- Peringatan tidak bisa dibatalkan -->
                    <div v-if="irreversible" class="mt-3 flex items-start gap-2 p-3 bg-amber-50 border border-amber-100 rounded-xl">
                        <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <p class="text-xs text-amber-700 leading-relaxed">
                            <strong>Tindakan ini tidak dapat dibatalkan.</strong> Pastikan seluruh data sudah benar.
                        </p>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-3">
                    <button type="button" :disabled="loading" @click="$emit('cancel')"
                        class="px-4 py-2.5 rounded-xl text-sm font-semibold text-gray-600 hover:bg-gray-100 transition disabled:opacity-50">
                        {{ cancelLabel }}
                    </button>
                    <button type="button" :disabled="loading" @click="$emit('confirm')"
                        :class="['px-5 py-2.5 rounded-xl text-sm font-semibold text-white shadow-sm transition flex items-center gap-2 disabled:opacity-60', accent.btn]">
                        <svg v-if="loading" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        {{ confirmLabel }}
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    show:         { type: Boolean, default: false },
    title:        { type: String,  required: true },
    message:      { type: String,  default: '' },
    details:      { type: Array,   default: () => [] },
    confirmLabel: { type: String,  default: 'Ya, Lanjutkan' },
    cancelLabel:  { type: String,  default: 'Batal' },
    variant:      { type: String,  default: 'primary' }, // primary | success | danger
    irreversible: { type: Boolean, default: false },
    loading:      { type: Boolean, default: false },
})

defineEmits(['confirm', 'cancel'])

const accent = computed(() => ({
    primary: {
        bar: 'bg-gradient-to-r from-indigo-500 to-violet-500',
        iconBg: 'bg-indigo-50', iconText: 'text-indigo-600',
        dot: 'bg-indigo-400', btn: 'bg-indigo-600 hover:bg-indigo-700',
    },
    success: {
        bar: 'bg-gradient-to-r from-emerald-500 to-teal-500',
        iconBg: 'bg-emerald-50', iconText: 'text-emerald-600',
        dot: 'bg-emerald-400', btn: 'bg-emerald-600 hover:bg-emerald-700',
    },
    danger: {
        bar: 'bg-gradient-to-r from-red-500 to-rose-500',
        iconBg: 'bg-red-50', iconText: 'text-red-600',
        dot: 'bg-red-400', btn: 'bg-red-600 hover:bg-red-700',
    },
}[props.variant] ?? {}))
</script>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity .2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-active .relative, .modal-leave-active .relative { transition: transform .2s ease; }
.modal-enter-from .relative { transform: scale(.95) translateY(8px); }
</style>
