<template>
    <div :class="$attrs.class">
        <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-0.5">{{ label }}</p>
        <p :class="['text-sm text-gray-800 font-medium', mono ? 'font-mono' : '']">
            {{ value || '—' }}
        </p>
    </div>
</template>

<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], default: '—' },
    mono: { type: Boolean, default: false },
})
</script>