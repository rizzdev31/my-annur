<template>
    <ConfirmDialog
        :show="state.show"
        :title="state.title"
        :message="state.message"
        :details="state.details"
        :variant="state.variant"
        :confirm-label="state.confirmLabel"
        :cancel-label="state.cancelLabel"
        :irreversible="state.irreversible"
        @confirm="resolveConfirm(true)"
        @cancel="resolveConfirm(false)" />
</template>

<script setup>
// Host tunggal untuk global confirm — pasang sekali di AdminLayout.
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import { useConfirmState, resolveConfirm } from '@/composables/useConfirm'

const state = useConfirmState()
</script>
