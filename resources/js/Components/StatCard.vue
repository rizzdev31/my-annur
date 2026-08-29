<template>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 flex items-start justify-between">
        <div class="min-w-0">
            <p class="text-xs font-medium text-gray-500 truncate">{{ label }}</p>
            <p :class="['text-2xl font-bold mt-1 truncate', valueColor]">{{ value }}</p>
            <p v-if="trend" class="text-xs text-gray-400 mt-1">{{ trend }}</p>
        </div>
        <div :class="['w-10 h-10 rounded-xl flex items-center justify-center text-xl shrink-0 ml-3', iconBg]">
            {{ icon }}
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    icon: { type: String, default: '📊' },
    trend: { type: String, default: '' },
    color: { type: String, default: 'indigo' },
})

const colorMap = {
    indigo: { value: 'text-indigo-700', bg: 'bg-indigo-50' },
    emerald: { value: 'text-emerald-700', bg: 'bg-emerald-50' },
    red: { value: 'text-red-600', bg: 'bg-red-50' },
    violet: { value: 'text-violet-700', bg: 'bg-violet-50' },
    amber: { value: 'text-amber-700', bg: 'bg-amber-50' },
    blue: { value: 'text-blue-700', bg: 'bg-blue-50' },
}

const valueColor = computed(() => colorMap[props.color]?.value ?? 'text-gray-800')
const iconBg = computed(() => colorMap[props.color]?.bg ?? 'bg-gray-50')
</script>