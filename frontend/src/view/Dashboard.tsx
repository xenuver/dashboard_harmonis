import { useState, useEffect } from "react";
import { CartesianGrid, Legend, Line, LineChart, Tooltip, XAxis, YAxis } from 'recharts';
import { isAxiosError, isCancel as axiosIsCancel } from "axios";

import KpiCard from "../components/dashboard/KpiCard";

import { api } from "../services/api";
import { formatAngka, formatDate, formatRupiah } from "../services/formatters";

import type { Kpi } from "../../types/Models/Kpi";
import type { TrendDataView } from "../../types/TrendDataView";
import type { TrendData } from "../../types/Models/TrendData";

export function Dashboard() {
  const [error, setError] = useState<string | null>(null);

  const [kpiTotalSales, setKpiTotalSales] = useState("-");
  const [kpiTotalGrowth, setKpiTotalGrowth] = useState("-");
  const [kpiJumlahTransaksi, setKpiJumlahTransaksi] = useState("-");
  const [kpiGrowthMember, setKpiGrowthMember] = useState("-");

  const [trendGraphViewData, setTrendGraphViewData] = useState<TrendDataView[]>();

  function consumeKpi(data: Kpi): void {
    if(typeof data?.total_sales === "number") {
      setKpiTotalSales(formatRupiah(data.total_sales));
    } else {
      console.warn(`respon total_sales dari backend tidak terduga: ${data?.total_sales}`);
      setKpiTotalSales("-");
    }

    if(typeof data?.total_growth === "string") {
      let totalGrowth;
      try {
        totalGrowth = Number(data.total_growth);

        if(totalGrowth > 0) {
          setKpiTotalGrowth(`+${formatAngka(totalGrowth)}%`);
        } else if (totalGrowth === 0) {
          setKpiTotalGrowth(`${formatAngka(totalGrowth)}%`);
        } else {
          setKpiTotalGrowth(`-${formatAngka(totalGrowth)}%`);
        }
      } catch(e) {
        console.error(e);
        console.warn(`respon total_growth dari backend tidak terduga: ${data.total_growth}`);
        setKpiTotalGrowth("-");
      }
    } else {
      console.warn(`respon total_growth dari backend tidak terduga: ${data.total_growth}`);
      setKpiTotalGrowth("-");
    }

    if(typeof data?.jumlah_transaksi === "number") {
      setKpiJumlahTransaksi(`${formatAngka(data?.jumlah_transaksi)}`);
    } else {
      console.warn(`respon jumlah_transaksi dari backend tidak terduga: ${data?.jumlah_transaksi}`);
      setKpiJumlahTransaksi("-");
    }

    if(typeof data?.growth_member === "number") {
      if(data.growth_member > 0) {
        setKpiGrowthMember(`+${formatAngka(data.growth_member)}`);
      } else if (data.growth_member === 0) {
        setKpiGrowthMember(`${formatAngka(data.growth_member)}`);
      } else {
        setKpiGrowthMember(`-${formatAngka(data.growth_member)}`);
      }
    } else {
      console.warn(`respon growth_member dari backend tidak terduga: ${formatAngka(data.growth_member)}`);
      setKpiGrowthMember("-");
    }
  }

  function consumeGraphData(input: TrendData): void {
  const chartData: TrendDataView[] = Object.entries(input).map(([tanggal, items]) => {
    const row: TrendDataView = {
      tanggal,
    };

    items.forEach((item) => {
      row[item.cabang.toLowerCase()] = item.total;
    });

    if(typeof row?.tanggal !== "string" && typeof row?.tanggal !== "number") {
      throw new Error("Field tanggal tidak sesuai!");
    }

    return row;
  });

    setTrendGraphViewData(chartData);
  };

  useEffect(() => {
    const abortController = new AbortController();

    async function fetchDashboardData() {
      try {
        setError(null);
        const response = await api.get('/api/dashboard', {
          responseType: "json",
          signal: abortController.signal,
          params: {
            periode_bulan: 8
          }
        });

        consumeKpi(response.data.kpi);
        consumeGraphData(response.data.trend)

        setError(null);
      } catch (err) {
        if (axiosIsCancel(err)) {
          return; 
        }

        if(isAxiosError(err)) {
          console.error(err);
          setError("Terjadi kesalahan dalam mendapatkan data.");
        } else {
          console.error(err);
          setError('Terjadi kesalahan tidak diketahui');
        }
      }
    }
    fetchDashboardData();

    return () => {
      abortController.abort();
    };
  }, []);

  return (
  <>
  <KpiCard title="Total Penjualan" value={kpiTotalSales} />
  <KpiCard title="Total Growth" value={kpiTotalGrowth} />
  <KpiCard title="Jumlah Transaksi" value={kpiJumlahTransaksi} />
  <KpiCard title="Growth Member" value={kpiGrowthMember} />

  <LineChart style={{ height: 300, maxWidth: 900 }} responsive data={trendGraphViewData}>
    <CartesianGrid />
    <Line dataKey="ampera" isAnimationActive={false} name="Cabang Ampera" fill="orange" stroke="orange" />
    <Line dataKey="pal" isAnimationActive={false} name="Cabang Pal" fill="green" stroke="green" />
    <XAxis dataKey="tanggal" tickFormatter={formatDate} />
    <YAxis width="auto" name="Total Penjualan" />
    <Tooltip isAnimationActive={true} labelFormatter={(value) => (typeof value === 'string' || typeof value === 'number')? formatDate(value) : ""} 
      formatter={(value) => (typeof value === 'number')? formatRupiah(value) : ""} />
    <Legend />
  </LineChart>

  <table border={1}>
    <tr>
      <td colSpan={2}>Peringkat Produk</td>
      <td colSpan={2}>Produk paling laris/Produk kurang laris</td>
    </tr>
    <tr>
      <td colSpan={2}>Nama Barang</td>
      <td>Qty Terjual</td>
      <td>Jumlah (Rp)</td>
    </tr>
    <tr>
      <td>1</td>
      <td>cerek</td>
      <td>998</td>
      <td>25.000.000</td>
    </tr>
    <tr>
      <td>2</td>
      <td>Kue</td>
      <td>877</td>
      <td>20.000.000</td>
    </tr>
    <tr>
      <td>3</td>
      <td>Shampo</td>
      <td>665</td>
      <td>15.000.000</td>
    </tr>
    <tr>
      <td>4</td>
      <td>sabun</td>
      <td>544</td>
      <td>10.000.000</td>
    </tr>
    <tr>
      <td>5</td>
      <td>Makanan</td>
      <td>332</td>
      <td>5.000.000</td>
    </tr>
    <tr>
      <td colSpan={4}>Lihat Peringkat Produk</td>
    </tr>
  </table>

  </>);
}

export default Dashboard
