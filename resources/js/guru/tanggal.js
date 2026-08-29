// Tanggal kalender LOKAL perangkat (Y-m-d), BUKAN UTC.
//
// PENTING: `new Date().toISOString().slice(0,10)` memakai UTC. Di WIB (UTC+7)
// sebelum pukul 07:00 pagi, UTC masih di tanggal kemarin → device_date mundur
// satu hari → absensi salah tanggal ("Absensi selesai" padahal belum absen).
// Gunakan getter lokal agar sesuai jam dinding pengguna.
export function tanggalLokal(date = new Date()) {
    const y = date.getFullYear()
    const m = String(date.getMonth() + 1).padStart(2, '0')
    const d = String(date.getDate()).padStart(2, '0')
    return `${y}-${m}-${d}`
}

export default tanggalLokal
