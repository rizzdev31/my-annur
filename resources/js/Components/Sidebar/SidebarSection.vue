<template>
    <div class="pt-4 pb-1 px-3">
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest">{{ label }}</p>
    </div>
</template>

<script setup>
defineProps({ label: { type: String, required: true } })
</script>