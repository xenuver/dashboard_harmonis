import formatNumber from "format-number";

export function formatDate(value: string | number): string {
  const date = new Date(value);
  const day = date.toLocaleString('default', { day: '2-digit' });
  const month = date.toLocaleString('default', { month: 'short' });
  const year = date.toLocaleString('default', { year: 'numeric' });
  return `${day} ${month} ${year}`;
}

export function formatRupiah(value: number): string {
  return formatNumber({integerSeparator:".", decimal: ",", prefix: "Rp", suffix: ",-"})(value);
}

export function formatAngka(value: number) {
  return formatNumber({integerSeparator:".", decimal: ","})(value);
}

export function formatAngkaSingkat(value: number, decimalLength?: number): string {
  const units = ["", "K", "M", "B", "T"]

  if(value > 0) {
    for(let i = 0; i < units.length; i++) {
      if(value < Math.pow(10, 3 * (i + 1)) || i === units.length - 1) {
        const shortenedNumber = value / Math.pow(10, 3 * i);
        return `${shortenedNumber.toFixed(decimalLength)}${units[i]}`;
      }
    }
  } else if (value < 0) {
    for(let i = 0; i < units.length; i++) {
      if(value > -Math.pow(10, 3 * (i + 1)) || i === units.length - 1) {
        const shortenedNumber = value / Math.pow(10, 3 * i);
        return `${shortenedNumber.toFixed(decimalLength)}${units[i]}`;
      }
    }
  }

  return formatAngkaSingkat(value);
}

export function formatRupiahSingkat(value: number): string {
  if (value < 1e3) {
    return formatRupiah(value);
  } else {
    return `Rp${formatAngkaSingkat(value)}`;
  }
  
}
