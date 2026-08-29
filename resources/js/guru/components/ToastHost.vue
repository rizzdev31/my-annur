<script setup>
import { toasts, dismiss } from '../store/toast'

const style = {
    success: { bar: 'bg-emerald-500', ic: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', tone: 'text-emerald-600' },
    error:   { bar: 'bg-red-500',     ic: 'M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z', tone: 'text-red-500' },
    warning: { bar: 'bg-amber-500',   ic: 'M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0L3.16 16.25A2 2 0 005 19z', tone: 'text-amber-500' },
    info:    { bar: 'bg-[#0C78FF]',   ic: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z', tone: 'text-[#0C78FF]' },
}
const S = (t) => style[t] || style.info
</script>

<template>
    <div class="fixed top-0 inset-x-0 z-[95] flex flex-col items-center px-4 pointer-events-none safe-t">
        <TransitionGroup name="toast" tag="div" class="w-full max-w-sm mt-2 space-y-2">
            <div v-for="t in toasts" :key="t.id" @click="dismiss(t.id)"
                class="pointer-events-auto flex items-center gap-3 bg-white rounded-2xl shadow-xl shadow-black/10 border border-gray-100 pl-1 pr-3 py-2.5 cursor-pointer active:scale-[0.99] transition">
                <span class="w-8 h-8 rounded-xl grid place-items-center shrink-0" :class="S(t.type).bar">
                    <svg class="w-[18px] h-[18px] text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" :d="S(t.type).ic" /></svg>
                </span>
                <p class="flex-1 text-[13px] font-semibold text-gray-700 leading-snug py-0.5">{{ t.text }}</p>
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.toast-enter-active { transition: all .35s cubic-bezier(.34, 1.56, .64, 1); }
.toast-leave-active { transition: all .25s ease; position: absolute; width: 100%; }
.toast-enter-from   { opacity: 0; transform: translateY(-16px) scale(.96); }
.toast-leave-to     { opacity: 0; transform: translateY(-8px) scale(.98); }
.toast-move         { transition: transform .3s ease; }
</style>
