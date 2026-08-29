<template>
    <div class="flex items-center gap-2">
        <a :href="templateUrl"
            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" /></svg>
            Template
        </a>
        <label
            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold cursor-pointer"
            :class="{ 'opacity-60 pointer-events-none': loading }">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M17 8l-5-5-5 5M12 3v12" /></svg>
            {{ loading ? 'Mengimpor…' : (label || 'Import Excel') }}
            <input type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="onFile" :disabled="loading" />
        </label>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    templateUrl: { type: String, required: true },
    importUrl: { type: String, required: true },
    label: { type: String, default: '' },
})

const loading = ref(false)

function onFile(e) {
    const file = e.target.files?.[0]
    if (!file) return
    loading.value = true
    router.post(props.importUrl, { file }, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => { loading.value = false; e.target.value = '' },
    })
}
</script>
