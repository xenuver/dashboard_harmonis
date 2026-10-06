import type { Supplier } from './Models/Supplier'

export interface SupplierSearchResponse {
    current_page: number,
    data: Supplier[],
    last_page: number
    per_page: number,
    total: number
}
