import { reactive } from 'vue'

/**
 * Global confirm — dialog konfirmasi tunggal (promise-based).
 * Dipakai untuk aksi immediate/destruktif (hapus, ubah status, dll)
 * agar konsisten & sesuai brand, menggantikan window.confirm().
 *
 * Pemakaian di halaman mana pun:
 *   import { confirm } from '@/composables/useConfirm'
 *   if (!(await confirm({ title: 'Hapus data?', variant: 'danger' }))) return
 *   router.delete(...)   // logika aksi TIDAK berubah
 *
 * Host <ConfirmHost /> cukup dipasang SEKALI di AdminLayout.
 */

const state = reactive({
    show: false,
    title: 'Konfirmasi',
    message: '',
    details: [],
    variant: 'danger',       // primary | success | danger
    confirmLabel: 'Ya, Lanjutkan',
    cancelLabel: 'Batal',
    irreversible: false,
})

let resolver = null

export function confirm(opts = {}) {
    state.title        = opts.title ?? 'Konfirmasi'
    state.message      = opts.message ?? ''
    state.details      = opts.details ?? []
    state.variant      = opts.variant ?? 'danger'
    state.confirmLabel = opts.confirmLabel ?? 'Ya, Lanjutkan'
    state.cancelLabel  = opts.cancelLabel ?? 'Batal'
    state.irreversible = opts.irreversible ?? false
    state.show         = true

    return new Promise((resolve) => { resolver = resolve })
}

/** Dipanggil host saat user memutuskan (true = konfirmasi, false = batal). */
export function resolveConfirm(value) {
    state.show = false
    if (resolver) { resolver(value); resolver = null }
}

export function useConfirmState() {
    return state
}
