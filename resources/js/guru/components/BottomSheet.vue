<script setup>
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue'])
function close() { emit('update:modelValue', false) }
</script>

<template>
    <Transition name="sheet">
        <div v-if="modelValue" class="fixed inset-0 z-[85] flex items-end justify-center">
            <div class="sheet-backdrop absolute inset-0" @click="close"></div>
            <div class="sheet-panel relative w-full max-w-md bg-white rounded-t-[28px] px-5 pt-3 pb-8 safe-b max-h-[90vh] overflow-y-auto shadow-2xl" @click.stop>
                <div class="w-11 h-1.5 bg-gray-200 rounded-full mx-auto mb-4"></div>
                <div v-if="title" class="mb-4">
                    <h3 class="text-lg font-extrabold text-gray-900 leading-tight">{{ title }}</h3>
                    <p v-if="subtitle" class="text-xs text-gray-400 mt-0.5">{{ subtitle }}</p>
                </div>
                <slot />
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.sheet-backdrop { background: rgba(10, 15, 30, 0.5); backdrop-filter: blur(2px); }

.sheet-enter-active .sheet-panel { transition: transform .38s cubic-bezier(.32, .72, 0, 1); }
.sheet-leave-active .sheet-panel { transition: transform .28s cubic-bezier(.4, 0, 1, 1); }
.sheet-enter-from .sheet-panel,
.sheet-leave-to .sheet-panel { transform: translateY(100%); }

.sheet-enter-active .sheet-backdrop,
.sheet-leave-active .sheet-backdrop { transition: opacity .34s ease; }
.sheet-enter-from .sheet-backdrop,
.sheet-leave-to .sheet-backdrop { opacity: 0; }
</style>
