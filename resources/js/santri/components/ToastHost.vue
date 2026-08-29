<script setup>
import { toastState } from '../store/toast'
const cls = { success: 'bg-emerald-600', error: 'bg-red-600', warning: 'bg-amber-500', info: 'bg-gray-800' }
</script>
<template>
    <div class="fixed top-3 inset-x-0 z-[100] flex flex-col items-center gap-2 px-4 pointer-events-none">
        <TransitionGroup name="toast">
            <div v-for="t in toastState.items" :key="t.id" class="max-w-sm w-full text-white text-sm font-semibold rounded-2xl px-4 py-3 shadow-lg" :class="cls[t.type] || cls.info">
                {{ t.text }}
            </div>
        </TransitionGroup>
    </div>
</template>
<style scoped>
.toast-enter-active, .toast-leave-active { transition: all .3s cubic-bezier(.32,.72,0,1); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(-12px); }
</style>
