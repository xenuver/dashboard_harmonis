import { useState as reactUseState, useEffect, useRef } from "react";
import { isAxiosError, isCancel as axiosIsCancel } from "axios";
import { api } from "../services/api";
import { formatAngka, formatRupiah } from "../services/formatters";
import { ArrowLeft, ArrowRight } from "lucide-react";

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Skeleton } from "@/components/ui/skeleton";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { ToggleGroup, ToggleGroupItem } from "@/components/ui/toggle-group";
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { ReportUnavailableCard } from "@/components/errorcards/ReportUnavailableCard";
import { NoConnectionCard } from "@/components/errorcards/NoConnectionCard";
import { UnknownErrorCard } from "@/components/errorcards/UnknownErrorCard";
import { NoDataFoundCard } from "@/components/errorcards/NoDataFoundCard";

import type { ProductSearchResponse } from "../../types/ProductSearchResponse";
import type { Product } from "../../types/Models/Product";

const ERROR_CODE_SEARCH_NO_ERROR = 0;
const ERROR_CODE_SEARCH_REPORT_NOT_AVAILABLE = 1; // request valid, tapi laporan belum ada
const ERROR_CODE_SEARCH_NO_DATA = 2;
const ERROR_CODE_SEARCH_CONNECTION_ERROR = 3;
const ERROR_CODE_SEARCH_UNKNOWN_ERROR = 4;

export function ProdukPenjualan() {
  const searchHasMounted = useRef(false);

  const [jenisLaporanSaatIni, setJenisLaporanSaatIni] = reactUseState("gabungan");
  const [kataKunciPencarian, setKataKunciPencarian] = reactUseState < string | undefined > (undefined);
  const [sortirTingkatProduk, setSortirTingkatProduk] = reactUseState("tertinggi");
  const [productPerPage, setProductPerPage] = reactUseState(10);
  const [daftarProduk, setDaftarProduk] = reactUseState < Product[] > ([]);

  const [currentPage, setCurrentPage] = reactUseState < number > (1);
  const [totalProducts, setTotalProducts] = reactUseState < number > (0);
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
      setDaftarProduk([]);
      setTotalProducts(0);
      setIsLoaded(false);

      // construct query params
      const body = {
        page: currentPage,
        jenis_laporan: jenisLaporanSaatIni,
        periode_tahun: yearNow,
        periode_bulan: monthNow,
        per_page: productPerPage,
        kategori: sortirTingkatProduk
      }

      if(kataKunciPencarian) {
        Object.defineProperty(body, "search", {value: kataKunciPencarian, enumerable: true});
      }

      try {
        const response = await api.get('/api/produk-penjualan', {
          responseType: "json",
          signal: abortController.signal,
          params: body
        });

        const responseData = response.data as ProductSearchResponse;

        setDaftarProduk(responseData.data);
        setTotalProducts(responseData.total);
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
      setMaxPage(1);
      retrySearchNow();
    } else {
      searchHasMounted.current = true;
    }
  }, [jenisLaporanSaatIni, productPerPage, sortirTingkatProduk, resetSearchTrigger]);

    return (<>
      <h1 className="scroll-m-20 text-2xl font-semibold tracking-tight lg:text-3xl m-5">Peringkat Produk</h1>
      <Card className="m-5">
        <CardContent>
          <FieldGroup className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 gap-4">
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
            <Field>
              <FieldLabel htmlFor="kategori">Kategori Laporan:</FieldLabel>
              <ToggleGroup defaultValue={["tertinggi"]} variant="outline" onValueChange={(e) => setSortirTingkatProduk(e[0])} spacing={0}>
                <ToggleGroupItem value="tertinggi">Tertinggi</ToggleGroupItem>
                <ToggleGroupItem value="terendah">Terendah</ToggleGroupItem>
              </ToggleGroup>
            </Field>
          </FieldGroup>
          <FieldGroup className="gap-4 mt-4">
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
        <NoConnectionCard className="m-5" retryTrigger={retrySearchNow} />
      }

      { errorCode === ERROR_CODE_SEARCH_NO_DATA && 
        <NoDataFoundCard className="m-5" />
      }

      { errorCode === ERROR_CODE_SEARCH_REPORT_NOT_AVAILABLE && 
        <ReportUnavailableCard className="m-5" />
      }

      { errorCode === ERROR_CODE_SEARCH_UNKNOWN_ERROR &&
        <UnknownErrorCard className="m-5" retryTrigger={retrySearchNow} />
      }

      { errorCode === ERROR_CODE_SEARCH_NO_ERROR && 
        <Card className="m-5">
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>No</TableHead>
                  <TableHead>Barcode</TableHead>
                  <TableHead>Kode Barang</TableHead>
                  <TableHead>Nama barang</TableHead>
                  <TableHead>Satuan</TableHead>
                  <TableHead>Harga Jual</TableHead>
                  <TableHead>Jumlah (Omzet)</TableHead>
                  <TableHead>Qty (terjual)</TableHead>
                  <TableHead>Sisa Stok</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                { isLoaded === true ?
                  daftarProduk.map((produk, index) => (
                    <TableRow key={index}>
                      <TableCell>{(productPerPage * (currentPage - 1) + 1) + index}</TableCell>
                      <TableCell>{produk.barcode}</TableCell>
                      <TableCell>{produk.kode_brg}</TableCell>
                      <TableCell>{produk.nama_brg}</TableCell>
                      <TableCell>{produk.satuan}</TableCell>
                      <TableCell>{formatRupiah(produk.harga_jual)}</TableCell>
                      <TableCell className={produk.jumlah < 0 ? "font-semibold text-purple-600" : ""}>{formatRupiah(produk.jumlah)}</TableCell>
                      <TableCell className={produk.qty < 0 ? "font-semibold text-purple-600" : ""}>{formatAngka(produk.qty)}</TableCell>
                      <TableCell className={produk.stok <= 0 ? "font-semibold text-purple-600" : ""}>{formatAngka(produk.stok)}</TableCell>
                    </TableRow>
                  ))
                :
                  Array.from({ length: productPerPage }).map((_, index) => (
                    <TableRow key={index}>
                      <TableCell><Skeleton className="h-5 min-w-5" /></TableCell>
                      <TableCell><Skeleton className="h-5 min-w-20" /></TableCell>
                      <TableCell><Skeleton className="h-5 min-w-20" /></TableCell>
                      <TableCell><Skeleton className="h-5 min-w-30" /></TableCell>
                      <TableCell><Skeleton className="h-5 min-w-10" /></TableCell>
                      <TableCell><Skeleton className="h-5 min-w-30" /></TableCell>
                      <TableCell><Skeleton className="h-5 min-w-30" /></TableCell>
                      <TableCell><Skeleton className="h-5 min-w-20" /></TableCell>
                      <TableCell><Skeleton className="h-5 min-w-20" /></TableCell>
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
                {totalProducts > 0 
                  ? `Menampilkan produk ${productPerPage * (currentPage - 1) + 1}-${(productPerPage * (currentPage - 1)) + daftarProduk.length} dari ${totalProducts}`
                  : '\u00A0'}
              </div>
              <div className="flex items-center gap-2">
                <div>Produk per halaman</div>
                <Select defaultValue={10} name="jenislaporan" onValueChange={(value) => value && setProductPerPage(value)}>
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

export default ProdukPenjualan;
