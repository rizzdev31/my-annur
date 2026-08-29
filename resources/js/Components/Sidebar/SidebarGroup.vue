<template>
    <div>
        <button @click="toggle" :class="[
            'w-full flex items-center gap-3 px-3 py-2 rounded-xl text-sm transition-all duration-150 group relative',
            active ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
        ]">
            <!-- Accent bar kiri saat aktif -->
            <span v-if="active && !collapsed"
                class="absolute left-0 top-1.5 bottom-1.5 w-1 rounded-r-full bg-indigo-600"></span>

            <!-- Icon (sumber bersama) -->
            <span class="w-5 h-5 shrink-0 flex items-center justify-center">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                    :class="['w-[18px] h-[18px] transition-colors', active ? 'text-indigo-600' : 'text-gray-400 group-hover:text-gray-600']">
                    <path v-for="(d, i) in iconPaths" :key="i" stroke-linecap="round" stroke-linejoin="round" :d="d" />
                </svg>
            </span>

            <Transition name="label">
                <span v-if="!collapsed" class="flex-1 text-left truncate">{{ label }}</span>
            </Transition>

            <Transition name="label">
                <svg v-if="!collapsed" class="w-3.5 h-3.5 text-gray-400 transition-transform shrink-0"
                    :class="isOpen ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </Transition>

            <!-- Tooltip collapsed -->
            <div v-if="collapsed" class="absolute left-14 px-3 py-1.5 bg-gray-900 text-white text-xs rounded-lg
                       whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none
                       transition-opacity z-50 shadow-xl">
                {{ label }}
            </div>
        </button>

        <!-- Submenu -->
        <Transition name="submenu">
            <div v-if="isOpen && !collapsed" class="mt-0.5 ml-8 space-y-0.5">
                <slot />
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { sidebarIcons } from './icons.js'

const props = defineProps({
    icon: { type: String, default: 'settings' },
    label: { type: String, required: true },
    active: { type: Boolean, default: false },
    collapsed: { type: Boolean, default: false },
})

const iconPaths = computed(() => sidebarIcons[props.icon] ?? sidebarIcons.settings)
const isOpen = ref(props.active)
function toggle() { if (!props.collapsed) isOpen.value = !isOpen.value }
watch(() => props.active, (v) => { if (v) isOpen.value = true })
</script>

<style scoped>
.label-enter-active,
.label-leave-active {
    transition: opacity 0.15s;
}

.label-enter-from,
.label-leave-to {
    opacity: 0;
}

.submenu-enter-active {
    transition: all 0.2s ease;
}

.submenu-leave-active {
    transition: all 0.15s ease;
}

.submenu-enter-from {
    opacity: 0;
    transform: translateY(-4px);
}

.submenu-leave-to {
    opacity: 0;
}
</style>