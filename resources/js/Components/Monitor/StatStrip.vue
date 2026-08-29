<template>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <component :is="card.filter !== undefined ? 'button' : 'div'" v-for="card in cards" :key="card.label"
            type="button"
            @click="card.filter !== undefined && $emit('select', card.filter)"
            :class="[
                'rounded-xl border px-4 py-3 flex items-center gap-3 text-left transition-colors',
                card.filter !== undefined ? 'hover:border-indigo-300 cursor-pointer' : '',
                card.active ? 'border-indigo-400 ring-1 ring-indigo-200 bg-indigo-50/40' : 'bg-white border-gray-200',
            ]">
            <div :class="['w-9 h-9 rounded-lg flex items-center justify-center shrink-0', tone(card.tone).bg, tone(card.tone).text]">
                <MonitorIcon :name="card.icon" size="sm" />
            </div>
            <div class="min-w-0">
                <p class="text-xl font-bold text-gray-900 leading-none tabular-nums">{{ card.value }}</p>
                <p class="text-xs text-gray-500 mt-0.5 truncate">{{ card.label }}</p>
            </div>
        </component>
    </div>
</template>

<script setup>
import MonitorIcon from './MonitorIcon.vue'

defineEmits(['select'])
defineProps({
    // [{ label, value, icon, tone: 'gray|emerald|blue|amber|indigo|violet', filter?, active? }]
    cards: { type: Array, default: () => [] },
})

function tone(t) {
    return {
        gray:    { bg: 'bg-gray-100',    text: 'text-gray-600' },
        emerald: { bg: 'bg-emerald-50',  text: 'text-emerald-600' },
        blue:    { bg: 'bg-blue-50',     text: 'text-blue-600' },
        amber:   { bg: 'bg-amber-50',    text: 'text-amber-600' },
        indigo:  { bg: 'bg-indigo-50',   text: 'text-indigo-600' },
        violet:  { bg: 'bg-violet-50',   text: 'text-violet-600' },
    }[t] ?? { bg: 'bg-gray-100', text: 'text-gray-600' }
}
</script>
