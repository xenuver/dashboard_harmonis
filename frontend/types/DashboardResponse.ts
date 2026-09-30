import type { Kpi } from "./Kpi"
import type { Product } from "./Product"
import type { Supplier } from "./Supplier"

export interface DashboardResponseData {
    kpi: Kpi,
    trend: Record<string, Array<{
        cabang: string, 
        total: number
    }>>,
    top_produk_terlaris: Array<Product & {
        kategori: "tertinggi"
    }>,
    top_produk_terendah: Array<Product & {
        kategori: "terendah"
    }>,
    top_supplier: Array<Supplier>
}