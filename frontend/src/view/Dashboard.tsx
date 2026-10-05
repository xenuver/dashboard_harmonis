import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { CartesianGrid, Legend, Line, LineChart, Tooltip, XAxis, YAxis, ResponsiveContainer } from 'recharts';
import { isAxiosError, isCancel as axiosIsCancel } from "axios";

import KpiCard from "../components/dashboard/KpiCard";

import { api } from "../services/api";
import { formatAngka, formatAngkaSingkat, formatDate, formatRupiah, formatRupiahSingkat } from "../services/formatters";

import type { DashboardResponseData } from "../../types/DashboardResponseData";
import type { Kpi } from "../../types/Models/Kpi";
import type { Product } from "../../types/Models/Product";
import type { TrendDataView } from "../../types/TrendDataView";
import type { TrendData } from "../../types/Models/TrendData";
import type { Supplier } from "../../types/Models/Supplier";

import '../styles/common.css';

const ERROR_CODE_DASHBOARD_NO_ERROR = 0;
const ERROR_CODE_DASHBOARD_NO_DATA = 1;
const ERROR_CODE_DASHBOARD_CONNECTION_ERROR = 2;
const ERROR_CODE_DASHBOARD_UNKNOWN_ERROR = 3;

export function Dashboard() {
  const navigate = useNavigate();

  const [isLoaded, setIsLoaded] = useState<boolean>(false);
  const [errorCode, setErrorCode] = useState<number>(ERROR_CODE_DASHBOARD_NO_ERROR);
  const [errorMessage, setErrorMessage] = useState<string| null>(null);
  const [retryNowTrigger, shouldRetryNow] = useState<boolean>(false);

  // views
  const [kpiTotalSales, setKpiTotalSales] = useState<string>("-");
  const [kpiTotalSalesToolTip, setKpiTotalSalesToolTip] = useState<string | undefined>();
  const [kpiTotalGrowth, setKpiTotalGrowth] = useState<string>("-");
  const [kpiTotalGrowthToolTip, setKpiTotalGrowthToolTip] = useState<string | undefined>();
  const [kpiJumlahTransaksi, setKpiJumlahTransaksi] = useState<string>("-");
  const [kpiJumlahTransaksiToolTip, setKpiJumlahTransaksiToolTip] = useState<string | undefined>();
  const [kpiGrowthMember, setKpiGrowthMember] = useState<string>("-");
  const [kpiGrowthMemberToolTip, setKpiGrowthMemberToolTip] = useState<string | undefined>();

  const [trendGraphViewData, setTrendGraphViewData] = useState<TrendDataView[]>([]);
  const [topProductsList, setTopProductsList] = useState<Product[]>([]);
  const [worstProductsList, setWorstProductsList] = useState<Product[]>([]);
  const [topSuppliersList, setTopSuppliersList] = useState<Supplier[]>([]);

  function consumeKpi(data: Kpi): void {
    if(typeof data?.total_sales === "number") {
      setKpiTotalSales(formatRupiahSingkat(data.total_sales));
      setKpiTotalSalesToolTip(`Total penjualan: ${formatRupiah(data.total_sales)}`);
    } else {
      console.warn(`respon total_sales dari backend tidak terduga: ${data?.total_sales}`);
      setKpiTotalSales("-");
      setKpiTotalSalesToolTip(undefined);
    }

    if(typeof data?.total_growth === "string") {
      let totalGrowth;
      try {
        totalGrowth = Number(data.total_growth);

        if(totalGrowth > 0) {
          setKpiTotalGrowth(`+${formatAngkaSingkat(totalGrowth, 3)}%`);
          setKpiTotalGrowthToolTip(`Total growth: +${totalGrowth}%`);
        } else {
          setKpiTotalGrowth(`${formatAngkaSingkat(totalGrowth, 3)}%`);
          setKpiTotalGrowthToolTip(`Total growth: ${totalGrowth}%`);
        }
      } catch(e) {
        console.error(e);
        console.warn(`respon total_growth dari backend tidak terduga: ${data.total_growth}`);
        setKpiTotalGrowth("-");
        setKpiTotalGrowthToolTip(undefined);
      }
    } else {
      console.warn(`respon total_growth dari backend tidak terduga: ${data.total_growth}`);
      setKpiTotalGrowth("-");
      setKpiTotalGrowthToolTip(undefined);
    }

    if(typeof data?.jumlah_transaksi === "number") {
      setKpiJumlahTransaksi(`${formatAngka(data?.jumlah_transaksi)}`);
      setKpiJumlahTransaksiToolTip(`Jumlah transaksi: ${formatAngka(data?.jumlah_transaksi)}`);
    } else {
      console.warn(`respon jumlah_transaksi dari backend tidak terduga: ${data?.jumlah_transaksi}`);
      setKpiJumlahTransaksi("-");
      setKpiJumlahTransaksiToolTip(undefined);
    }

    if(typeof data?.growth_member === "number") {
      if(data.growth_member > 0) {
        if(data.growth_member >= 100000) {
          setKpiGrowthMember(`+${formatAngkaSingkat(data.growth_member)}`);
          setKpiGrowthMemberToolTip(`Growth member: ${formatAngkaSingkat(data.growth_member)}`);
        } else {
          setKpiGrowthMember(`+${formatAngka(data.growth_member)}`);
          setKpiGrowthMemberToolTip(`Growth member: ${formatAngka(data.growth_member)}`);
        }
      } else if (data.growth_member === 0) {
        setKpiGrowthMember(`${formatAngka(data.growth_member)}`);
        setKpiGrowthMemberToolTip(`Growth member: ${formatAngka(data.growth_member)}`);
      } else {
        if(data.growth_member <= -100000) {
          setKpiGrowthMember(`${formatAngkaSingkat(data.growth_member)}`);
          setKpiGrowthMemberToolTip(`Growth member: ${formatAngkaSingkat(data.growth_member)}`);
        } else {
          setKpiGrowthMember(`${formatAngka(data.growth_member)}`);
          setKpiGrowthMemberToolTip(`Growth member: ${formatAngka(data.growth_member)}`);
        }
      }
    } else {
      console.warn(`respon growth_member dari backend tidak terduga: ${formatAngka(data.growth_member)}`);
      setKpiGrowthMember("-");
      setKpiGrowthMemberToolTip(undefined);
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

  function handleRetryApi() {
    shouldRetryNow((prev) => !prev);
  }

  useEffect(() => {
    const abortController = new AbortController();

    async function fetchDashboardData() {
      try {
        setErrorCode(ERROR_CODE_DASHBOARD_NO_ERROR);
        setErrorMessage(null);
        setIsLoaded(false);
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

        setIsLoaded(true);
      } catch (err) {
        if (axiosIsCancel(err)) {
          return; 
        }

        if(isAxiosError(err)) {
          if(err.status === 404) {
            setErrorCode(ERROR_CODE_DASHBOARD_NO_DATA);
            return;
          }
          console.error(err);
          setErrorCode(ERROR_CODE_DASHBOARD_CONNECTION_ERROR);
        } else {
          console.error(err);
          setErrorCode(ERROR_CODE_DASHBOARD_UNKNOWN_ERROR);
        }
      }
    }
    fetchDashboardData();

    return () => {
      abortController.abort();
    };
  }, [retryNowTrigger]);

  return (
    <>
      <h1>Dashboard</h1>
      {errorMessage && <div style={{ color: "red"}}>{errorMessage}</div>}

      { errorCode === ERROR_CODE_DASHBOARD_NO_DATA &&
        <>
        <h2>Data Tidak Ditemukan</h2>
        <p>Data dashboard untuk periode ini belum tersedia. Silakan unggah data terlebih dahulu.</p>
        <button onClick={() => navigate("/upload")}>Menuju Halaman Upload Data</button>
        </>
      }

      { errorCode === ERROR_CODE_DASHBOARD_CONNECTION_ERROR &&
        <>
        <h2>Terjadi Kesalahan Koneksi</h2>
        <p>Data dashboard untuk periode ini tidak dapat dimuat karena kesalahan koneksi. Mohon coba lagi setelah beberapa saat.</p>
        <button onClick={handleRetryApi}>Coba Lagi</button>
        </>
      }

      { errorCode === ERROR_CODE_DASHBOARD_UNKNOWN_ERROR &&
        <>
        <h2>Terjadi Kesalahan Tidak Diketahui</h2>
        <p>Terjadi kesalahan yang tidak diketahui. Mohon coba lagi setelah beberapa saat.</p>
        <button onClick={handleRetryApi}>Coba Lagi</button>
        </>
      }

      {errorCode === ERROR_CODE_DASHBOARD_NO_ERROR && 
        <>
          <KpiCard title="Total Penjualan" value={kpiTotalSales} valueToolTip={kpiTotalSalesToolTip} isLoading={!isLoaded} />
          <KpiCard title="Total Growth" value={kpiTotalGrowth} valueToolTip={kpiTotalGrowthToolTip} isLoading={!isLoaded} />
          <KpiCard title="Jumlah Transaksi" value={kpiJumlahTransaksi} valueToolTip={kpiJumlahTransaksiToolTip} isLoading={!isLoaded} />
          <KpiCard title="Growth Member" value={kpiGrowthMember} valueToolTip={kpiGrowthMemberToolTip} isLoading={!isLoaded} />

          { isLoaded === true ? 
          <ResponsiveContainer width="75%" aspect={1.67} maxHeight={300}>
            <LineChart responsive data={trendGraphViewData}>
              <CartesianGrid />
              <Line dataKey="ampera" isAnimationActive={false} name="Cabang Ampera" fill="orange" stroke="orange" />
              <Line dataKey="pal" isAnimationActive={false} name="Cabang Pal" fill="green" stroke="green" />
              <XAxis dataKey="tanggal" tickFormatter={formatDate} />
              <YAxis width="auto" name="Total Penjualan" tickFormatter={formatRupiahSingkat} />
              <Tooltip isAnimationActive={true} labelFormatter={(value) => (typeof value === 'string' || typeof value === 'number')? formatDate(value) : value} 
                formatter={(value) => (typeof value === 'number')? formatRupiah(value) : ""} />
              <Legend />
            </LineChart>
          </ResponsiveContainer>
            :
          <ResponsiveContainer width="75%" aspect={1.67} maxHeight={300}>
            <LineChart responsive data={[]}>
              <CartesianGrid />
              <Line isAnimationActive={false} name="Memuat..." fill="black" stroke="black" />
              <XAxis dataKey="tanggal" />
              <YAxis width="auto" name="Total Penjualan" />
              <Legend />
            </LineChart>
          </ResponsiveContainer>
          }

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

            { isLoaded === true ?
              topProductsList.length > 0 ?
                topProductsList.map((produk, index) => (
                  <tr key={index}>
                    <td>{index + 1}</td>
                    <td>{produk.nama_brg}</td>
                    <td>{formatAngka(produk.qty)}</td>
                    <td>{formatRupiah(produk.jumlah)}</td>
                  </tr>
                ))
              :
                // Fallback row if the list is empty
                <tr>
                  <td colSpan={4} rowSpan={5} style={{ textAlign: 'center' }}>
                    Tidak ada data
                  </td>
                </tr>
            :
              Array.from({ length: 10 }).map((_, index) => (
                <tr key={index}>
                  <td className="bg-gray-200 animate-pulse loading-skeleton">&nbsp;</td>
                  <td className="bg-gray-200 animate-pulse loading-skeleton">&nbsp;</td>
                  <td className="bg-gray-200 animate-pulse loading-skeleton">&nbsp;</td>
                  <td className="bg-gray-200 animate-pulse loading-skeleton">&nbsp;</td>
                </tr>
              ))
            }
            
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

            { isLoaded === true ?
              worstProductsList.length > 0 ?
                worstProductsList.map((produk, index) => (
                  <tr key={produk.id ?? index}>
                    <td>{index + 1}</td>
                    <td>{produk.nama_brg}</td>
                    <td>{formatAngka(produk.qty)}</td>
                    <td>{formatRupiah(produk.jumlah)}</td>
                  </tr>
                ))
              :
                // Fallback row if the list is empty
                <tr>
                  <td colSpan={4} rowSpan={5} style={{ textAlign: 'center' }}>
                    Tidak ada data
                  </td>
                </tr>
            :
               Array.from({ length: 10 }).map(() => (
                  <tr>
                    <td className="bg-gray-200 animate-pulse loading-skeleton">&nbsp;</td>
                    <td className="bg-gray-200 animate-pulse loading-skeleton">&nbsp;</td>
                    <td className="bg-gray-200 animate-pulse loading-skeleton">&nbsp;</td>
                    <td className="bg-gray-200 animate-pulse loading-skeleton">&nbsp;</td>
                  </tr>
                ))
            }
            
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

            { isLoaded === true ?
              topSuppliersList.length > 0 ?
                topSuppliersList.map((supplier, index) => (
                  <tr key={supplier.id ?? index}>
                    <td>{index + 1}</td>
                    <td>{supplier.nama_supp}</td>
                    <td>{formatRupiah(supplier.gross_total)}</td>
                    <td>{formatRupiah(supplier.net_sales_total)}</td>
                  </tr>
                ))
              :
                // Fallback row if the list is empty
                <tr>
                  <td colSpan={4} rowSpan={5} style={{ textAlign: 'center' }}>
                    Tidak ada data
                  </td>
                </tr>
            :
               Array.from({ length: 10 }).map(() => (
                  <tr>
                    <td className="bg-gray-200 animate-pulse loading-skeleton">&nbsp;</td>
                    <td className="bg-gray-200 animate-pulse loading-skeleton">&nbsp;</td>
                    <td className="bg-gray-200 animate-pulse loading-skeleton">&nbsp;</td>
                    <td className="bg-gray-200 animate-pulse loading-skeleton">&nbsp;</td>
                  </tr>
                ))
            }
            
            <tr>
              <td colSpan={4}><a href="">Lihat Peringkat Supplier</a></td>
            </tr>
          </table>
        </>
      }

  </>);
}

export default Dashboard
