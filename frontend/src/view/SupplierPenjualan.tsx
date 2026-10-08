import { useState as reactUseState, useEffect, useRef } from "react";
import { isAxiosError, isCancel as axiosIsCancel } from "axios";
import { api } from "../services/api";
import { Link } from "react-router-dom";
import { FileUp, RotateCcw, ArrowLeft, ArrowRight } from "lucide-react";
import { formatRupiah } from "../services/formatters";
import { cn } from "@/lib/utils";

import { Button, buttonVariants } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardTitle } from "@/components/ui/card";
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Skeleton } from "@/components/ui/skeleton";

import type { SupplierSearchResponse } from "../../types/SupplierSearchResponse";
import type { Supplier } from "../../types/Models/Supplier";

const ERROR_CODE_SEARCH_NO_ERROR = 0;
const ERROR_CODE_SEARCH_REPORT_NOT_AVAILABLE = 1; // request valid, tapi laporan belum ada
const ERROR_CODE_SEARCH_NO_DATA = 2;
const ERROR_CODE_SEARCH_CONNECTION_ERROR = 3;
const ERROR_CODE_SEARCH_UNKNOWN_ERROR = 4;

export function SupplierPenjualan() {
  const searchHasMounted = useRef(false);

  const [jenisLaporanSaatIni, setJenisLaporanSaatIni] = reactUseState("gabungan");
  const [kataKunciPencarian, setKataKunciPencarian] = reactUseState < string | undefined > (undefined);
  const [supplierPerPage, setSupplierPerPage] = reactUseState(10);
  const [daftarSupplier, setDaftarSupplier] = reactUseState < Supplier[] > ([]);

  const [currentPage, setCurrentPage] = reactUseState < number > (1);
  const [totalSuppliers, setTotalSuppliers] = reactUseState < number > (0);
  const [maxPage, setMaxPage] = reactUseState < number > (1);

  const [isLoaded, setIsLoaded] = reactUseState < boolean > (false);
  const [errorCode, setErrorCode] = reactUseState < number > (ERROR_CODE_SEARCH_NO_ERROR);
  const [retryNowTrigger, shouldRetryNow] = reactUseState < boolean > (false);
  const [resetSearchTrigger, shouldResetSearchNow] = reactUseState < boolean > (false);

  const yearNow = new Date().getFullYear();
  const monthNow = new Date().getMonth() + 1;

  function retrySearchNow() {
    shouldRetryNow((prev) => !prev);
  }

  function resetSearchNow() {
    shouldResetSearchNow((prev) => !prev);
  }

  useEffect(() => {
    const abortController = new AbortController();

    async function fetchData() {
      setErrorCode(ERROR_CODE_SEARCH_NO_ERROR);
      setDaftarSupplier([]);
      setTotalSuppliers(0);
      setIsLoaded(false);

      // construct query params
      const body = {
        page: currentPage,
        jenis_laporan: jenisLaporanSaatIni,
        periode_tahun: yearNow,
        periode_bulan: monthNow,
        per_page: supplierPerPage,
      }

      if(kataKunciPencarian) {
        Object.defineProperty(body, "search", {value: kataKunciPencarian, enumerable: true});
      }

      try {
        const response = await api.get('/api/supplier-penjualan', {
          responseType: "json",
          signal: abortController.signal,
          params: body
        });

        const responseData = response.data as SupplierSearchResponse;

        setDaftarSupplier(responseData.data);
        setTotalSuppliers(responseData.total);
        setMaxPage(responseData.last_page);

        if (responseData.data.length === 0) {
          setErrorCode(ERROR_CODE_SEARCH_NO_DATA);
          return;
        }
        setIsLoaded(true);
      } catch (err) {
        if (axiosIsCancel(err)) {
          return;
        }

        if (isAxiosError(err)) {
          if (err.status === 404) {
            setErrorCode(ERROR_CODE_SEARCH_REPORT_NOT_AVAILABLE);
            return;
          }
          console.error(err);
          setErrorCode(ERROR_CODE_SEARCH_CONNECTION_ERROR);
        } else {
          console.error(err);
          setErrorCode(ERROR_CODE_SEARCH_UNKNOWN_ERROR);
        }
      }
    }
    fetchData();

    return () => {
      abortController.abort();
    };
  }, [retryNowTrigger, currentPage]);

  useEffect(function() {
    // pastikan kode tidak dijalankan pada tahap mounting
    if(searchHasMounted.current) {
      setIsLoaded(false);
      setCurrentPage(1);
      retrySearchNow();
    } else {
      searchHasMounted.current = true;
    }
  }, [jenisLaporanSaatIni, supplierPerPage, resetSearchTrigger]);

    return (<>
      <h1 className="scroll-m-20 text-2xl font-semibold tracking-tight lg:text-3xl m-5">Peringkat Supplier</h1>
      <Card className="m-5">
        <CardContent>
          <FieldGroup className="gap-4">
            <Field>
              <FieldLabel htmlFor="jenislaporan">Cabang saat ini:</FieldLabel>
              <Select defaultValue="gabungan" name="jenislaporan" onValueChange={(value) => value && setJenisLaporanSaatIni(value)}>
                <SelectTrigger>
                  <SelectValue placeholder="Pilih Jenis Laporan" />
                </SelectTrigger>
                <SelectContent>
                  {
                    ["ampera", "pal", "gabungan"].map((value, index) => (
                      <SelectItem tabIndex={index + 1} value={value} key={index}>{value}</SelectItem>
                    ))
                  }
                </SelectContent>
              </Select>
            </Field>
          </FieldGroup>
          <FieldGroup className="gap-4">
            <Field className="flex flex-col gap-2 w-full">
              <FieldLabel htmlFor="cariproduk">
                Cari Kode Barang atau Nama barang:
              </FieldLabel>
              <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full">
                <Input 
                  type="text" 
                  name="cariproduk" 
                  className="w-full sm:flex-1 min-w-0"
                  onChange={(e) => setKataKunciPencarian(e.target.value)} 
                  placeholder="Masukkan kode barang atau nama barang di sini" 
                  onKeyUp={(e) => (e.key === 'Enter' || e.keyCode === 13) && resetSearchNow()} 
                />
                <Button 
                  onClick={resetSearchNow} 
                  className="w-full sm:w-auto shrink-0 px-4"
                >
                  Cari
                </Button>
              </div>
            </Field>
          </FieldGroup>
        </CardContent>
      </Card>

      { errorCode === ERROR_CODE_SEARCH_CONNECTION_ERROR && 
        <Card className="m-5">
          <CardContent className="grid place-items-center pt-5 pb-5">
            <CardTitle>Kesalahan Koneksi</CardTitle>
            <CardDescription>Terjadi kesalahan koneksi. Mohon coba lagi.</CardDescription>
            <Button onClick={retrySearchNow} className="mt-3"><RotateCcw />Coba Lagi</Button>
          </CardContent>
        </Card>
      }

      { errorCode === ERROR_CODE_SEARCH_NO_DATA && 
        <Card className="m-5">
          <CardContent className="grid place-items-center pt-5 pb-5">
            <CardTitle>Supplier Tidak Ditemukan</CardTitle>
            <CardDescription>Supplier tidak ditemukan. Pastikan kode atau nama supplier dimasukkan dengan benar.</CardDescription>
          </CardContent>
        </Card>
      }

      { errorCode === ERROR_CODE_SEARCH_REPORT_NOT_AVAILABLE && 
        <Card className="m-5">
          <CardContent className="grid place-items-center pt-5 pb-5">
            <CardTitle>Laporan Tidak Tersedia</CardTitle>
            <CardDescription>Laporan produk untuk jenis dan masa yang dimasukkan masih belum tersedia. Silahkan unggah laporan penjualan untuk jenis dan masa tersebut terlebih dahulu.</CardDescription>
            <Link to="/upload" className={cn(buttonVariants({ variant: "default" }), "mt-4")}>
              <FileUp />Menuju Halaman Upload Data
            </Link>
          </CardContent>
        </Card>
      }

      { errorCode === ERROR_CODE_SEARCH_UNKNOWN_ERROR &&
        <Card className="m-5">
          <CardContent className="grid place-items-center pt-5 pb-5">
            <CardTitle>Laporan Tidak Tersedia</CardTitle>
            <CardDescription>Terjadi kesalahan yang tidak diketahui. Mohon coba lagi setelah beberapa saat.</CardDescription>
            <Button onClick={retrySearchNow} className="mt-3"><RotateCcw />Coba Lagi</Button>
          </CardContent>
        </Card>
      }

      { errorCode === ERROR_CODE_SEARCH_NO_ERROR && 
        <Card className="m-5">
          <CardContent>
            <Table>
              <TableHeader>
                <TableHead>No</TableHead>
                <TableHead>Kode Supplier</TableHead>
                <TableHead>Nama Supplier</TableHead>
                <TableHead>Gross Total</TableHead>
                <TableHead>Net Sales Total</TableHead>
              </TableHeader>
              <TableBody>
                { isLoaded === true ?
                  daftarSupplier.map((supplier, index) => (
                    <TableRow key={index}>
                      <TableCell>{index + 1}</TableCell>
                      <TableCell>{supplier.kode_supp}</TableCell>
                      <TableCell>{supplier.nama_supp}</TableCell>
                      <TableCell className={supplier.gross_total < 0 ? "font-semibold text-purple-600" : ""}>{formatRupiah(supplier.gross_total)}</TableCell>
                      <TableCell className={supplier.net_sales_total < 0 ? "font-semibold text-purple-600" : ""}>{formatRupiah(supplier.net_sales_total)}</TableCell>
                    </TableRow>
                  ))
                :
                  Array.from({ length: supplierPerPage }).map((_, index) => (
                    <TableRow key={index}>
                      <TableCell><Skeleton className="h-5 min-w-5" /></TableCell>
                      <TableCell><Skeleton className="h-5 min-w-20" /></TableCell>
                      <TableCell><Skeleton className="h-5 min-w-30" /></TableCell>
                      <TableCell><Skeleton className="h-5 min-w-30" /></TableCell>
                      <TableCell><Skeleton className="h-5 min-w-30" /></TableCell>
                    </TableRow>
                  ))
                }
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      }

      { (errorCode === ERROR_CODE_SEARCH_NO_ERROR || errorCode === ERROR_CODE_SEARCH_NO_DATA || errorCode === ERROR_CODE_SEARCH_CONNECTION_ERROR) &&
        <Card className="m-5">
          <CardContent>
            <div>
              <div className="flex items-center justify-center gap-2">
                { currentPage - 1 > 0 && 
                  <Button onClick={() => setCurrentPage(currentPage - 1)}><ArrowLeft />Sebelumnya</Button>
                }
                { currentPage - 2 >= 1 &&
                <>
                  <Button onClick={() => setCurrentPage(1)}>{1}</Button>
                  <div>...</div>
                </>
                }
                {currentPage - 1 > 0 && <Button onClick={() => setCurrentPage(currentPage - 1)}>{currentPage - 1}</Button> }
                <Button disabled={true}>{currentPage}</Button>
                {currentPage + 1 <= maxPage && <Button onClick={() => setCurrentPage(currentPage + 1)}>{currentPage + 1}</Button>}
                { currentPage + 2 <= maxPage && 
                  <>
                    <div>...</div>
                    <Button onClick={() => setCurrentPage(maxPage)}>{maxPage}</Button>
                  </>
                }
                {currentPage + 1 <= maxPage && 
                  <Button onClick={() => setCurrentPage(currentPage + 1)}>Selanjutnya<ArrowRight /></Button>
                }
              </div>
            </div>

            <div className="flex flex-col sm:flex-row justify-between items-center gap-4">
              <div>
                {totalSuppliers > 0 
                  ? `Menampilkan produk ${supplierPerPage * (currentPage - 1) + 1}-${(supplierPerPage * (currentPage - 1)) + daftarSupplier.length} dari ${totalSuppliers}`
                  : '\u00A0'}
              </div>
              <div className="flex items-center gap-2">
                <div>Produk per halaman</div>
                <Select defaultValue={10} name="jenislaporan" onValueChange={(value) => value && setSupplierPerPage(value)}>
                  <SelectTrigger className="w-45">
                    <SelectValue placeholder="" />
                  </SelectTrigger>
                  <SelectContent>
                    {
                      [10, 20, 50].map((value, index) => (
                        <SelectItem tabIndex={index + 1} value={value} key={index}>{value}</SelectItem>
                      ))
                    }
                  </SelectContent>
                </Select>
              </div>
            </div>
          </CardContent>
        </Card>
      }
    </>);
}

export default SupplierPenjualan;
