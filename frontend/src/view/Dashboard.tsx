import { useState, useEffect } from "react";
import { Link } from "react-router-dom";
import { CartesianGrid, Legend, Line, LineChart, Tooltip, XAxis, YAxis, ResponsiveContainer } from 'recharts';
import { isAxiosError, isCancel as axiosIsCancel } from "axios";
import { Landmark, ChartNoAxesCombined, Receipt, IdCard, ArrowRight, FileUp, RotateCcw } from "lucide-react";
import { cn } from "@/lib/utils";

import KpiCard from "../components/dashboard/KpiCard";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableFooter } from "@/components/ui/table";
import { Skeleton } from "@/components/ui/skeleton";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { buttonVariants, Button } from "@/components/ui/button";

import { api } from "../services/api";
import { formatAngka, formatAngkaSingkat, formatDate, formatRupiah, formatRupiahSingkat } from "../services/formatters";

import type { DashboardResponseData } from "../../types/DashboardResponseData";
import type { Kpi } from "../../types/Models/Kpi";
import type { Product } from "../../types/Models/Product";
import type { TrendDataView } from "../../types/TrendDataView";
import type { TrendData } from "../../types/Models/TrendData";
import type { Supplier } from "../../types/Models/Supplier";

import '../styles/dashboard.css';

const ERROR_CODE_DASHBOARD_NO_ERROR = 0;
const ERROR_CODE_DASHBOARD_NO_DATA = 1;
const ERROR_CODE_DASHBOARD_CONNECTION_ERROR = 2;
const ERROR_CODE_DASHBOARD_UNKNOWN_ERROR = 3;

export function Dashboard() {
  const [isLoaded, setIsLoaded] = useState<boolean>(false);
  const [errorCode, setErrorCode] = useState<number>(ERROR_CODE_DASHBOARD_NO_ERROR);
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

  const yearNow = new Date().getFullYear();
  const monthNow = new Date().getMonth() + 1;

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
          setKpiGrowthMemberToolTip(`Growth member: +${formatAngkaSingkat(data.growth_member)}`);
        } else {
          setKpiGrowthMember(`+${formatAngka(data.growth_member)}`);
          setKpiGrowthMemberToolTip(`Growth member: +${formatAngka(data.growth_member)}`);
        }
      } else if (data.growth_member === 0) {
        setKpiGrowthMember(`${formatAngka(data.growth_member)}`);
        setKpiGrowthMemberToolTip(`Growth member: ${formatAngka(data.growth_member)}`);
      } else {
        if(data.growth_member <= -100000) {
          setKpiGrowthMember(`${formatAngkaSingkat(data.growth_member)}`);
          setKpiGrowthMemberToolTip(`Growth member: -${formatAngkaSingkat(data.growth_member)}`);
        } else {
          setKpiGrowthMember(`${formatAngka(data.growth_member)}`);
          setKpiGrowthMemberToolTip(`Growth member: -${formatAngka(data.growth_member)}`);
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
        setIsLoaded(false);
        const response = await api.get('/api/dashboard', {
          responseType: "json",
          signal: abortController.signal,
          params: {
            periode_bulan: monthNow,
            periode_tahun: yearNow,
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
      <h1 className="scroll-m-20 text-2xl font-semibold tracking-tight lg:text-3xl m-5">Dashboard</h1>

      { errorCode === ERROR_CODE_DASHBOARD_NO_DATA &&
        <Card className="m-5">
          <CardContent className="grid place-items-center gap-2 pt-5 pb-5">
            <CardTitle>Data Tidak Tersedia</CardTitle>
            <CardDescription>Data dashboard untuk periode ini belum tersedia. Silakan unggah data terlebih dahulu.</CardDescription>
            <Link to="/upload" className={cn(buttonVariants({ variant: "default" }), "mt-4")}>
              <FileUp />Menuju Halaman Upload Data
            </Link>
          </CardContent>
        </Card>
      }

      { errorCode === ERROR_CODE_DASHBOARD_CONNECTION_ERROR &&
        <Card className="m-5">
          <CardContent className="grid place-items-center pt-5 pb-5">
            <CardTitle>Kesalahan Koneksi</CardTitle>
            <CardDescription>Data dashboard tidak dapat dimuat karena kesalahan koneksi. Periksa koneksi anda dan coba lagi.</CardDescription>
            <Button onClick={handleRetryApi} className="mt-3"><RotateCcw />Coba Lagi</Button>
          </CardContent>
        </Card>
      }

      { errorCode === ERROR_CODE_DASHBOARD_UNKNOWN_ERROR &&
        <Card className="m-5">
          <CardContent>
            <CardTitle className="text-center">Kesalahan Tidak Diketahui</CardTitle>
            <CardDescription className="text-center">Terjadi kesalahan yang tidak diketahui. Mohon coba lagi setelah beberapa saat.</CardDescription>
            <Button onClick={handleRetryApi}><RotateCcw />Coba Lagi</Button>
          </CardContent>
        </Card>
      }

      {errorCode === ERROR_CODE_DASHBOARD_NO_ERROR && 
        <>
          <div className="pt-6 pb-6 m-5">
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              <KpiCard title="Total Penjualan" value={kpiTotalSales} tooltip={kpiTotalSalesToolTip} isLoading={!isLoaded} icon={Landmark} />
              <KpiCard title="Total Growth" value={kpiTotalGrowth} tooltip={kpiTotalGrowthToolTip} isLoading={!isLoaded} icon={ChartNoAxesCombined} />
              <KpiCard title="Jumlah Transaksi" value={kpiJumlahTransaksi} tooltip={kpiJumlahTransaksiToolTip} isLoading={!isLoaded} icon={Receipt} />
              <KpiCard title="Growth Member" value={kpiGrowthMember} tooltip={kpiGrowthMemberToolTip} isLoading={!isLoaded} icon={IdCard} />
            </div>
          </div>

          <Card className="pb-6 m-5">
            <CardHeader>
              <CardTitle>Grafik Penjualan</CardTitle>
            </CardHeader>
            <CardContent>
              { isLoaded === true ? 
                <div className="w-full h-80">
                  <ResponsiveContainer width="100%" height="100%">
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
                </div>
                :
                <div className="w-full h-80">
                  <ResponsiveContainer width="100%" height="100%">
                    <LineChart responsive data={[]}>
                      <CartesianGrid />
                      <Line isAnimationActive={false} name="Memuat..." fill="black" stroke="black" />
                      <XAxis dataKey="tanggal" />
                      <YAxis width="auto" name="Total Penjualan" />
                      <Legend />
                    </LineChart>
                  </ResponsiveContainer>
                </div>
              }
            </CardContent>
          </Card>

          <Card className="m-5">
            <CardHeader>
              <CardTitle>Produk Terlaris</CardTitle>
            </CardHeader>
            <CardContent>
              <Table className="border">
                <TableHeader>
                  <TableRow>
                    <TableHead>No</TableHead>
                    <TableHead>Nama Barang</TableHead>
                    <TableHead>Qty Terjual</TableHead>
                    <TableHead>Jumlah (Rp)</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  { isLoaded === true ?
                    topProductsList.length > 0 ?
                      topProductsList.map((produk, index) => (
                        <TableRow key={index}>
                          <TableCell>{index + 1}</TableCell>
                          <TableCell>{produk.nama_brg}</TableCell>
                          <TableCell className={produk.qty < 0? "text-purple-700" : undefined}>{formatAngka(produk.qty)}</TableCell>
                          <TableCell className={produk.qty < 0? "text-purple-700" : undefined}>{formatAngka(produk.jumlah)}</TableCell>
                        </TableRow>
                      ))
                    :
                      // Fallback row if the list is empty
                      <TableRow>
                        <TableCell  colSpan={4} rowSpan={10} style={{ textAlign: 'center' }}>
                          Tidak ada data
                        </TableCell >
                      </TableRow>
                  :
                    Array.from({ length: 10 }).map((_, index) => (
                      <TableRow key={index}>
                        <TableCell><Skeleton className="h-4 min-w-10" /></TableCell>
                        <TableCell><Skeleton className="h-4 min-w-30" /></TableCell>
                        <TableCell><Skeleton className="h-4 min-w-20" /></TableCell>
                        <TableCell><Skeleton className="h-4 min-w-30" /></TableCell>
                      </TableRow>
                    ))
                  }
                </TableBody>

                <TableFooter>
                  <TableRow>
                    <TableCell colSpan={4}>
                      <Link to="/produk-penjualan" className="inline-flex items-center gap-2">
                        <span className="text-blue-500">Lihat Peringkat Produk</span>
                        <ArrowRight className="w-4 h-4 stroke-blue-500" />
                      </Link>
                    </TableCell>
                  </TableRow>
                </TableFooter>
              </Table>
            </CardContent>
          </Card>

          <Card className="m-5">
            <CardHeader>
              <CardTitle>Produk Kurang Laris</CardTitle>
            </CardHeader>
            <CardContent>
              <Table className="border">
                <TableHeader>
                  <TableRow>
                    <TableHead>No</TableHead>
                    <TableHead>Nama Barang</TableHead>
                    <TableHead>Qty Terjual</TableHead>
                    <TableHead>Jumlah (Rp)</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  { isLoaded === true ?
                    worstProductsList.length > 0 ?
                      worstProductsList.map((produk, index) => (
                        <TableRow key={index}>
                          <TableCell>{index + 1}</TableCell>
                          <TableCell>{produk.nama_brg}</TableCell>
                          <TableCell className={produk.qty < 0? "text-purple-700" : undefined} >{formatAngka(produk.qty)}</TableCell>
                          <TableCell className={produk.qty < 0? "text-purple-700" : undefined}>{formatAngka(produk.jumlah)}</TableCell>
                        </TableRow>
                      ))
                    :
                      // Fallback row if the list is empty
                      <TableRow>
                        <TableCell  colSpan={4} rowSpan={10} style={{ textAlign: 'center' }}>
                          Tidak ada data
                        </TableCell >
                      </TableRow>
                  :
                    Array.from({ length: 10 }).map((_, index) => (
                      <TableRow key={index}>
                        <TableCell><Skeleton className="h-4 min-w-10" /></TableCell>
                        <TableCell><Skeleton className="h-4 min-w-30" /></TableCell>
                        <TableCell><Skeleton className="h-4 min-w-20" /></TableCell>
                        <TableCell><Skeleton className="h-4 min-w-30" /></TableCell>
                      </TableRow>
                    ))
                  }
                </TableBody>

                <TableFooter>
                  <TableRow>
                    <TableCell colSpan={4}>
                      <Link to="/produk-penjualan" className="inline-flex items-center gap-2">
                        <span className="text-blue-500">Lihat Peringkat Produk</span>
                        <ArrowRight className="w-4 h-4 stroke-blue-500" />
                      </Link>
                    </TableCell>
                  </TableRow>
                </TableFooter>
              </Table>
            </CardContent>
          </Card>

          <Card className="m-5">
            <CardHeader>
              <CardTitle>Supplier Teratas</CardTitle>
            </CardHeader>
            <CardContent>
              <Table className="border">
                <TableHeader>
                  <TableRow>
                    <TableHead>No</TableHead>
                    <TableHead>Nama Supplier</TableHead>
                    <TableHead>Gross Total</TableHead>
                    <TableHead>Net Sales Total</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  { isLoaded === true ?
                    topSuppliersList.length > 0 ?
                      topSuppliersList.map((supplier, index) => (
                        <TableRow key={index}>
                          <TableCell>{index + 1}</TableCell>
                          <TableCell>{supplier.nama_supp}</TableCell>
                          <TableCell>{formatRupiah(supplier.gross_total)}</TableCell>
                          <TableCell>{formatRupiah(supplier.net_sales_total)}</TableCell>
                        </TableRow>
                      ))
                    :
                      // Fallback row if the list is empty
                      <TableRow>
                        <TableCell  colSpan={4} rowSpan={10} style={{ textAlign: 'center' }}>
                          Tidak ada data
                        </TableCell >
                      </TableRow>
                  :
                    Array.from({ length: 10 }).map((_, index) => (
                      <TableRow key={index}>
                        <TableCell><Skeleton className="h-4 min-w-10" /></TableCell>
                        <TableCell><Skeleton className="h-4 min-w-30" /></TableCell>
                        <TableCell><Skeleton className="h-4 min-w-30" /></TableCell>
                        <TableCell><Skeleton className="h-4 min-w-30" /></TableCell>
                      </TableRow>
                    ))
                  }

                </TableBody>
                <TableFooter>
                  <TableRow>
                    <TableCell colSpan={4}>
                      <Link to="/produk-penjualan" className="inline-flex items-center gap-2">
                        <span className="text-blue-500">Lihat Peringkat Supplier</span>
                        <ArrowRight className="w-4 h-4 stroke-blue-500" />
                      </Link>
                    </TableCell>
                  </TableRow>
                </TableFooter>
              </Table>
            </CardContent>
          </Card>
        </>
      }
  </>);
}

export default Dashboard
