export interface DashboardKpiView {
    total_sales: {
        value: string | null
    },
    total_growth: {
        value: string | null
        direction: "up" | "neutral" | "down"
    },
    jumlah_transaksi: {
        value: string | null
    },
    growth_member: {
        value: string | null
        direction: "up" | "neutral" | "down"
    }
}
