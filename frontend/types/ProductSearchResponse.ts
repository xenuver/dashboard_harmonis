import type { Product } from './Models/Product'

export interface ProductSearchResponse {
    current_page: number,
    data: Product[],
    last_page: number
    per_page: number,
    total: number
}
