<template>
    <Teleport to="body">
        <div class="fixed top-4 right-4 z-[70] flex flex-col gap-2.5 w-[min(92vw,370px)] print:hidden">
            <TransitionGroup name="toast">
                <div v-for="t in toasts" :key="t.id"
                    class="flex items-start gap-3 p-3.5 rounded-2xl border bg-white shadow-lg shadow-black/5"
                    :class="cfg[t.type].border">
                    <span class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0" :class="cfg[t.type].iconBg">
                        <svg class="w-4.5 h-4.5" style="width:18px;height:18px" :class="cfg[t.type].iconText"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="cfg[t.type].icon" />
                        </svg>
                    </span>
                    <p class="flex-1 text-sm text-gray-700 leading-snug pt-1">{{ t.message }}</p>
                    <button @click="remove(t.id)" aria-label="Tutup"
                        class="shrink-0 text-gray-300 hover:text-gray-500 transition-colors -mt-0.5 -mr-0.5 p-1">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const toasts = ref([])
let seq = 0
let lastRef = null

const cfg = {
    success: {
        border: 'border-emerald-100', iconBg: 'bg-emerald-50', iconText: 'text-emerald-600',
        icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    },
    error: {
        border: 'border-red-100', iconBg: 'bg-red-50', iconText: 'text-red-600',
        icon: 'M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    },
    warning: {
        border: 'border-amber-100', iconBg: 'bg-amber-50', iconText: 'text-amber-600',
        icon: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    },
    info: {
        border: 'border-blue-100', iconBg: 'bg-blue-50', iconText: 'text-blue-600',
        icon: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    },
}

function add(type, message) {
    const id = ++seq
    toasts.value.push({ id, type, message })
    setTimeout(() => remove(id), 5000)
}
function remove(id) {
    toasts.value = toasts.value.filter(t => t.id !== id)
}

// Baca flash server (redirect setelah aksi). Dedup via referensi objek flash
// agar pesan sama pada 2 aksi berbeda tetap muncul, tapi tak dobel per-response.
function checkFlash() {
    const f = page.props.flash
    if (!f || f === lastRef) return
    lastRef = f
    if (f.success)      add('success', f.success)
    else if (f.error)   add('error', f.error)
    else if (f.warning) add('warning', f.warning)
    else if (f.info)    add('info', f.info)
}

onMounted(checkFlash)
watch(() => page.props.flash, checkFlash, { deep: true })
</script>

<style scoped>
.toast-enter-active {
    transition: all 0.3s cubic-bezier(0.22, 1, 0.36, 1);
}
.toast-leave-active {
    transition: all 0.2s ease;
    position: absolute;
    right: 0;
    width: 100%;
}
.toast-enter-from {
    opacity: 0;
    transform: translateX(24px) scale(0.98);
}
.toast-leave-to {
    opacity: 0;
    transform: translateX(24px);
}
.toast-move {
    transition: transform 0.25s ease;
}

@media (prefers-reduced-motion: reduce) {
    .toast-enter-active,
    .toast-leave-active,
    .toast-move {
        transition: opacity 0.2s ease;
    }
    .toast-enter-from,
    .toast-leave-to {
        transform: none;
    }
}
</style>
