import { useState, useEffect } from "react";
// import { CartesianGrid, Legend, Line, LineChart, Tooltip, XAxis, YAxis } from 'recharts';
import { isAxiosError, isCancel as axiosIsCancel } from "axios";

import KpiCard from "../components/dashboard/KpiCard";

import { api } from "../services/api";

import type { Kpi } from "../../types/Kpi";

export function Dashboard() {
  const [error, setError] = useState<string | null>(null);

  const [kpiTotalSales, setKpiTotalSales] = useState("-");
  const [kpiTotalGrowth, setKpiTotalGrowth] = useState("-");
  const [kpiJumlahTransaksi, setKpiJumlahTransaksi] = useState("-");
  const [kpiGrowthMember, setKpiGrowthMember] = useState("-");

  function parseKpi(data: Kpi) {
    if(typeof data?.total_sales === "number") {
      setKpiTotalSales(`Rp ${data.total_sales}`);
    } else {
      console.warn(`respon total_sales dari backend tidak terduga: ${data?.total_sales}`);
      setKpiTotalSales("-");
    }

    if(typeof data?.total_growth === "string") {
      let totalGrowth;
      try {
        totalGrowth = parseFloat(data.total_growth);

        if(totalGrowth > 0) {
          setKpiTotalGrowth(`+${data.total_growth}%`);
        } else if (totalGrowth === 0) {
          setKpiTotalGrowth(`${data.total_growth}%`);
        } else {
          setKpiTotalGrowth(`-${data.total_growth}%`);
        }
      } catch(e) {
        console.error(e);
        console.warn(`respon total_growth dari backend tidak terduga: ${data?.total_sales}`);
        setKpiTotalGrowth("-");
      }
    } else {
      console.warn(`respon total_growth dari backend tidak terduga: ${data?.total_sales}`);
      setKpiTotalGrowth("-");
    }

    if(typeof data?.jumlah_transaksi === "number") {
      setKpiJumlahTransaksi(`Rp ${data?.jumlah_transaksi}`);
    } else {
      console.warn(`respon jumlah_transaksi dari backend tidak terduga: ${data?.total_sales}`);
      setKpiJumlahTransaksi("-");
    }

    if(typeof data?.growth_member === "number") {
      if(data.growth_member > 0) {
        setKpiGrowthMember(`+${data.growth_member}`);
      } else if (data.growth_member === 0) {
        setKpiGrowthMember(`${data.growth_member}`);
      } else {
        setKpiGrowthMember(`-${data.growth_member}`);
      }
    } else {
      console.warn(`respon growth_member dari backend tidak terduga: ${data?.growth_member}`);
      setKpiGrowthMember("-");
    }
  }

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

        parseKpi(response.data.kpi);
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

  {/* <LineChart style={{ width: '100%', aspectRatio: 1.618, maxWidth: 600 }} responsive data={data}>
    <CartesianGrid />
    <Line dataKey="Ampera" isAnimationActive={false} />
    <Line dataKey="Pal" isAnimationActive={false} />
    <XAxis dataKey="name" />
    <YAxis />
    <Tooltip isAnimationActive={false} />
    <Legend />
  </LineChart> */}

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
