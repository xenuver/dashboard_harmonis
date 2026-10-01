interface TrendDataView1 {
  tanggal: string;
}

export type TrendDataView = TrendDataView1 & Record<string, string | number>;