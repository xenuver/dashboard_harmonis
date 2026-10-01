import { useState, useEffect } from "react";
import { CartesianGrid, Legend, Line, LineChart, Tooltip, XAxis, YAxis } from 'recharts';
import { isAxiosError, isCancel as axiosIsCancel } from "axios";

import KpiCard from "../components/dashboard/KpiCard";

import { api } from "../services/api";
import { formatAngka, formatDate, formatRupiah } from "../services/formatters";

import type { DashboardResponseData } from "../../types/DashboardResponseData";
import type { Kpi } from "../../types/Models/Kpi";
import type { Product } from "../../types/Models/Product";
import type { TrendDataView } from "../../types/TrendDataView";
import type { TrendData } from "../../types/Models/TrendData";
import type { Supplier } from "../../types/Models/Supplier";

export function Dashboard() {
  const [error, setError] = useState<string | null>(null);

  const [kpiTotalSales, setKpiTotalSales] = useState("-");
  const [kpiTotalGrowth, setKpiTotalGrowth] = useState("-");
  const [kpiJumlahTransaksi, setKpiJumlahTransaksi] = useState("-");
  const [kpiGrowthMember, setKpiGrowthMember] = useState("-");

  const [trendGraphViewData, setTrendGraphViewData] = useState<TrendDataView[]>([]);
  const [topProductsList, setTopProductsList] = useState<Product[]>([]);
  const [worstProductsList, setWorstProductsList] = useState<Product[]>([]);
  const [topSuppliersList, setTopSuppliersList] = useState<Supplier[]>([]);

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
        const responseData = response.data as DashboardResponseData;

        consumeKpi(responseData.kpi);
        consumeGraphData(responseData.trend);
        setTopProductsList(responseData.top_produk_terlaris);
        setWorstProductsList(responseData.top_produk_terendah);
        setTopSuppliersList(responseData.top_supplier);

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
      <td colSpan={2}>Produk paling laris</td>
    </tr>
    <tr>
      <td colSpan={2}>Nama Barang</td>
      <td>Qty Terjual</td>
      <td>Jumlah (Rp)</td>
    </tr>

    {topProductsList.map((produk, index) => (
      <tr key={produk.id ?? index}>
        <td>{index + 1}</td>
        <td>{produk.nama_brg}</td>
        <td>{formatAngka(produk.qty)}</td>
        <td>{formatRupiah(produk.jumlah)}</td>
      </tr>
    ))}

    {/* Fallback row if the list is empty */}
    {topProductsList.length === 0 && (
      <tr>
        <td colSpan={4} style={{ textAlign: 'center' }}>
          Tidak ada data
        </td>
      </tr>
    )}
    
    <tr>
      <td colSpan={4}><a href="">Lihat Peringkat Produk</a></td>
    </tr>
  </table>

  <table border={1}>
    <tr>
      <td colSpan={2}>Peringkat Produk</td>
      <td colSpan={2}>Produk kurang laris</td>
    </tr>
    <tr>
      <td colSpan={2}>Nama Barang</td>
      <td>Qty Terjual</td>
      <td>Jumlah (Rp)</td>
    </tr>

    {worstProductsList.map((produk, index) => (
      <tr key={produk.id ?? index}>
        <td>{index + 1}</td>
        <td>{produk.nama_brg}</td>
        <td>{formatAngka(produk.qty)}</td>
        <td>{formatRupiah(produk.jumlah)}</td>
      </tr>
    ))}

    {/* Fallback row if the list is empty */}
    {worstProductsList.length === 0 && (
      <tr>
        <td colSpan={4} style={{ textAlign: 'center' }}>
          Tidak ada data
        </td>
      </tr>
    )}
    
    <tr>
      <td colSpan={4}><a href="">Lihat Peringkat Produk</a></td>
    </tr>
  </table>

  <table border={1}>
    <tr>
      <td colSpan={4}>Peringkat Supplier</td>
    </tr>
    <tr>
      <td colSpan={2}>Nama Supplier</td>
      <td>Gross Total</td>
      <td>Net Sales Total</td>
    </tr>

    {topSuppliersList.map((supplier, index) => (
      <tr key={supplier.id ?? index}>
        <td>{index + 1}</td>
        <td>{supplier.nama_supp}</td>
        <td>{formatRupiah(supplier.gross_total)}</td>
        <td>{formatRupiah(supplier.net_sales_total)}</td>
      </tr>
    ))}

    {/* Fallback row if the list is empty */}
    {topSuppliersList.length === 0 && (
      <tr>
        <td colSpan={4} style={{ textAlign: 'center' }}>
          Tidak ada data
        </td>
      </tr>
    )}
    
    <tr>
      <td colSpan={4}><a href="">Lihat Peringkat Supplier</a></td>
    </tr>
  </table>

  </>);
}

export default Dashboard
