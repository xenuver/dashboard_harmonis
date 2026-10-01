import formatNumber from "format-number";

export function formatDate(value: string | number): string {
    const date = new Date(value);
    const day = date.toLocaleString('default', { day: '2-digit' });
    const month = date.toLocaleString('default', { month: 'short' });
    const year = date.toLocaleString('default', { year: 'numeric' });
    return `${day} ${month} ${year}`;
}

export function formatRupiah(value: number): string {
    return formatNumber({integerSeparator:".", decimal: ",", prefix: "Rp ", suffix: " ,-"})(value)
}

export function formatAngka(value: number) {
    return formatNumber({integerSeparator:".", decimal: ","})(value);
}