<template>
    <span :class="['inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold', cls.badge]">
        <span :class="['w-1.5 h-1.5 rounded-full', cls.dot]"></span>
        {{ cls.label }}
    </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    status: { type: String, default: '' },
    // Optional override map: { key: { label, tone } }
    map: { type: Object, default: () => ({}) },
})

// Tone → kelas badge + dot (status tak hanya dibedakan warna: selalu ada label).
const TONE = {
    gray:    { badge: 'bg-gray-100 text-gray-600',     dot: 'bg-gray-400' },
    emerald: { badge: 'bg-emerald-50 text-emerald-700', dot: 'bg-emerald-500' },
    blue:    { badge: 'bg-blue-50 text-blue-700',       dot: 'bg-blue-500' },
    amber:   { badge: 'bg-amber-50 text-amber-700',     dot: 'bg-amber-500' },
    red:     { badge: 'bg-red-50 text-red-600',         dot: 'bg-red-400' },
    violet:  { badge: 'bg-violet-50 text-violet-700',   dot: 'bg-violet-500' },
}

// Default status untuk seluruh modul monitoring.
const DEFAULTS = {
    draft:       { label: 'Draft',       tone: 'gray' },
    aktif:       { label: 'Aktif',       tone: 'emerald' },
    berlangsung: { label: 'Berlangsung', tone: 'emerald' },
    selesai:     { label: 'Selesai',     tone: 'blue' },
    dibatalkan:  { label: 'Dibatalkan',  tone: 'red' },
}

const cls = computed(() => {
    const def = { ...DEFAULTS, ...props.map }[props.status]
        ?? { label: props.status || '—', tone: 'gray' }
    return { label: def.label, ...(TONE[def.tone] ?? TONE.gray) }
})
</script>
