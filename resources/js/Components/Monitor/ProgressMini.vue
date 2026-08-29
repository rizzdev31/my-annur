<template>
    <div class="min-w-[110px]">
        <div class="flex items-center justify-between mb-1">
            <span class="text-xs font-semibold text-gray-700 tabular-nums">{{ done }}<span class="text-gray-400">/{{ total }}</span></span>
            <span v-if="total > 0" class="text-[10px] text-gray-400 tabular-nums">{{ pct }}%</span>
        </div>
        <div class="h-1.5 w-full rounded-full bg-gray-100 overflow-hidden">
            <div :class="['h-full rounded-full transition-all', barCls]" :style="{ width: pct + '%' }"></div>
        </div>
        <p v-if="label" class="text-[10px] text-gray-400 mt-1">{{ label }}</p>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    done:  { type: Number, default: 0 },
    total: { type: Number, default: 0 },
    label: { type: String, default: '' },
    tone:  { type: String, default: 'emerald' }, // emerald | amber | indigo | blue
})

const pct = computed(() => props.total > 0 ? Math.round((props.done / props.total) * 100) : 0)
const barCls = computed(() => ({
    emerald: 'bg-emerald-500',
    amber:   'bg-amber-500',
    indigo:  'bg-indigo-500',
    blue:    'bg-blue-500',
}[props.tone] ?? 'bg-emerald-500'))
</script>
