import type { Kpi } from "./Models/Kpi"
import type { Product } from "./Models/Product"
import type { Supplier } from "./Models/Supplier"
import type { TrendData } from "./Models/TrendData"

export interface DashboardResponseData {
    kpi: Kpi,
    trend: TrendData,
    top_produk_terlaris: Array<Product & {
        kategori: "tertinggi"
    }>,
    top_produk_terendah: Array<Product & {
        kategori: "terendah"
    }>,
    top_supplier: Array<Supplier>
}