<template>
    <div class="bg-white rounded-2xl border border-gray-200 p-3 mb-4 flex flex-wrap items-center gap-3">
        <!-- Tab status -->
        <div v-if="tabs.length" class="flex items-center gap-1 bg-gray-100 p-1 rounded-xl">
            <button v-for="t in tabs" :key="t.val" type="button"
                @click="$emit('update:status', t.val)"
                :class="['px-3 py-1.5 rounded-lg text-xs font-semibold transition inline-flex items-center gap-1.5',
                    status === t.val ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500 hover:text-gray-700']">
                {{ t.label }}
                <span v-if="t.count != null"
                    :class="['px-1.5 py-0.5 rounded-md text-[10px] tabular-nums',
                        status === t.val ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-200 text-gray-500']">
                    {{ t.count }}
                </span>
            </button>
        </div>

        <!-- Slot filter tambahan (mis. jabatan) -->
        <slot name="filters" />

        <!-- Search -->
        <div v-if="searchable" class="relative flex-1 min-w-[180px]">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                <MonitorIcon name="search" size="sm" />
            </span>
            <input :value="search" @input="$emit('update:search', $event.target.value)" type="text"
                :placeholder="placeholder"
                class="w-full pl-9 pr-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-400 focus:ring-1 focus:ring-indigo-200" />
        </div>
    </div>
</template>

<script setup>
import MonitorIcon from './MonitorIcon.vue'

defineEmits(['update:status', 'update:search'])
defineProps({
    tabs:        { type: Array, default: () => [] }, // [{ val, label, count? }]
    status:      { type: String, default: '' },
    search:      { type: String, default: '' },
    searchable:  { type: Boolean, default: true },
    placeholder: { type: String, default: 'Cari…' },
})
</script>
