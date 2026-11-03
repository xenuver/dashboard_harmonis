export interface Laporan {
      id: number,
      jenis_laporan: string,
      periode_bulan: number,
      periode_tahun: number,
      jumlah_hari: number,
      format_data: string,
      catatan: string,
      status: string,
      created_at?: string,// Date
      updated_at?: string // Date
}