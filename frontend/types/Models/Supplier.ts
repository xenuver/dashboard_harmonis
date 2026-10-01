export interface Supplier     {
    id: number,
    upload_id: number,
    kode_supp: string,
    nama_supp: string,
    gross_total: number,
    net_sales_total: number,
    created_at?: string, // Date
    updated_at?: string // Date
}