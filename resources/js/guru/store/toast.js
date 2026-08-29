import { reactive } from 'vue'

// Antrean toast global (dibaca oleh ToastHost).
export const toasts = reactive([])
let seq = 0

export function dismiss(id) {
    const i = toasts.findIndex((t) => t.id === id)
    if (i >= 0) toasts.splice(i, 1)
}

/** Tampilkan toast. type: success | error | info | warning */
export function toast(text, type = 'info', ms) {
    const id = ++seq
    const dur = ms ?? (type === 'error' ? 4200 : 3000)
    toasts.push({ id, text, type })
    if (dur > 0) setTimeout(() => dismiss(id), dur)
    return id
}
toast.success = (t, ms) => toast(t, 'success', ms)
toast.error   = (t, ms) => toast(t, 'error', ms)
toast.info    = (t, ms) => toast(t, 'info', ms)
toast.warning = (t, ms) => toast(t, 'warning', ms)
