<template>
    <ConfirmDialog
        :show="dlg.show"
        :title="dlg.title"
        :message="dlg.message"
        :details="dlg.details"
        :variant="dlg.variant"
        :confirm-label="dlg.confirmLabel"
        :cancel-label="dlg.cancelLabel"
        :irreversible="dlg.irreversible"
        :loading="busy"
        @confirm="run"
        @cancel="cancel" />
</template>

<script setup>
import { ref } from 'vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'

const _default = () => ({
    show: false, title: '', message: '', details: [],
    variant: 'danger', confirmLabel: 'Ya, Lanjutkan',
    cancelLabel: 'Batal', irreversible: false,
})

const dlg  = ref(_default())
const busy = ref(false)
let action = null

/**
 * Buka dialog konfirmasi.
 * @param {Object}   cfg  konfigurasi tampilan (title, message, variant, dst)
 * @param {Function} fn   callback aksi; menerima `done()` untuk menutup dialog.
 */
function ask(cfg, fn) {
    dlg.value = { ..._default(), ...cfg, show: true }
    busy.value = false
    action = fn
}

function run() {
    if (!action) return
    busy.value = true
    action(() => { busy.value = false; dlg.value.show = false })
}

function cancel() { dlg.value.show = false }

defineExpose({ ask })
</script>
