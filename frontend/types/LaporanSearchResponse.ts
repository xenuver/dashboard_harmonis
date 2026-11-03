import type { Laporan } from './Models/Laporan'

export interface LaporanSearchResponse {
    current_page: number,
    data: Laporan[],
    last_page: number
    per_page: number,
    total: number
}
