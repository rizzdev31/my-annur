<template>
    <div :class="$attrs.class">
        <label v-if="label" class="block text-sm font-medium text-gray-700 mb-1.5">
            {{ label }}
            <span v-if="required" class="text-red-500 ml-0.5">*</span>
        </label>
        <slot />
        <p v-if="error" class="mt-1.5 text-xs text-red-500 flex items-center gap-1">
            <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd"
                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                    clip-rule="evenodd" />
            </svg>
            {{ error }}
        </p>
    </div>
</template>

<script setup>
defineProps({
    label: { type: String, default: '' },
    required: { type: Boolean, default: false },
    error: { type: String, default: '' },
})
</script>