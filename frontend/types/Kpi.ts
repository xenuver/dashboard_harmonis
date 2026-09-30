export interface Kpi {
    id: number,
    upload_id: number,
    total_sales: number,
    total_growth: string, // ?????
    jumlah_transaksi: number,
    growth_member: number,
    created_at?: string,
    updated_at?: string
}