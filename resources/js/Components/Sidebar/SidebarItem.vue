<template>
    <Link :href="href" :class="[
        'flex items-center gap-3 px-3 py-2 rounded-xl text-sm transition-all duration-150 group relative',
        active
            ? 'bg-indigo-50 text-indigo-700 font-semibold'
            : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
    ]">
        <!-- Accent bar kiri saat aktif -->
        <span v-if="active && !collapsed"
            class="absolute left-0 top-1.5 bottom-1.5 w-1 rounded-r-full bg-indigo-600"></span>

        <!-- Icon -->
        <span class="w-5 h-5 shrink-0 flex items-center justify-center">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                :class="['w-[18px] h-[18px] transition-colors', active ? 'text-indigo-600' : 'text-gray-400 group-hover:text-gray-600']">
                <path v-for="(d, i) in iconPaths" :key="i" stroke-linecap="round" stroke-linejoin="round" :d="d" />
            </svg>
        </span>

        <!-- Label -->
        <Transition name="label">
            <span v-if="!collapsed" class="flex-1 truncate">{{ label }}</span>
        </Transition>

        <!-- Badge (notif count) -->
        <Transition name="label">
            <span v-if="!collapsed && badge"
                class="ml-auto shrink-0 min-w-[20px] h-5 px-1.5 rounded-full bg-red-500 text-white text-xs font-bold flex items-center justify-center">
                {{ badge > 99 ? '99+' : badge }}
            </span>
            <span v-else-if="!collapsed && active"
                class="ml-auto w-1.5 h-1.5 rounded-full bg-indigo-500 shrink-0"></span>
        </Transition>

        <!-- Tooltip saat collapsed -->
        <div v-if="collapsed" class="absolute left-[52px] px-3 py-1.5 bg-gray-900 text-white text-xs rounded-lg
                   whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none
                   transition-opacity duration-150 shadow-xl z-50 flex items-center gap-2">
            {{ label }}
            <span v-if="badge"
                class="min-w-[18px] h-4 px-1 rounded-full bg-red-500 text-white text-xs font-bold flex items-center justify-center">
                {{ badge > 99 ? '99+' : badge }}
            </span>
            <div class="absolute right-full top-1/2 -translate-y-1/2 border-4 border-transparent border-r-gray-900">
            </div>
        </div>
    </Link>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { sidebarIcons } from './icons.js'

const props = defineProps({
    href: { type: String, required: true },
    icon: { type: String, default: 'grid' },
    label: { type: String, required: true },
    active: { type: Boolean, default: false },
    collapsed: { type: Boolean, default: false },
    badge: { type: [Number, null], default: null },
})

const iconPaths = computed(() => sidebarIcons[props.icon] ?? sidebarIcons.grid)
</script>

<style scoped>
.label-enter-active,
.label-leave-active {
    transition: opacity 0.12s ease;
}

.label-enter-from,
.label-leave-to {
    opacity: 0;
}
</style>
