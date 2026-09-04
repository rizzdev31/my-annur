import { reactive } from 'vue'
import api from '../api'

// Hak monitoring pimpinan (diberikan superadmin). Dipakai untuk menampilkan
// menu "Monitoring" & membatasi tab modul di halaman monitoring.
export const monitoring = reactive({
    dimuat: false,
    is_pengawas: false,
    modul: [],
    boleh_setujui_izin: false,
    jumlah_guru: 0,
})

export function bolehModul(kunci) {
    return monitoring.is_pengawas && monitoring.modul.includes(kunci)
}

export async function muatMonitoring() {
    try {
        const d = (await api.get('/monitoring/status')).data.data ?? {}
        monitoring.is_pengawas = !!d.is_pengawas
        monitoring.modul = d.modul ?? []
        monitoring.boleh_setujui_izin = !!d.boleh_setujui_izin
        monitoring.jumlah_guru = d.jumlah_guru ?? 0
    } catch (_) {
        monitoring.is_pengawas = false
        monitoring.modul = []
    } finally {
        monitoring.dimuat = true
    }
}
