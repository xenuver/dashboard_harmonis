import { useState as reactUseState, useEffect, useRef } from "react";
import { isAxiosError, isCancel as axiosIsCancel } from "axios";
import { api } from "../services/api";
import { Link } from "react-router-dom";
import { formatAngka, formatRupiah } from "../services/formatters";
import { FileUp } from "lucide-react";

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Skeleton } from "@/components/ui/skeleton";
import { Card, CardContent, CardDescription, CardTitle } from "@/components/ui/card";
import { Button, buttonVariants } from "@/components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { ToggleGroup, ToggleGroupItem } from "@/components/ui/toggle-group";
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";

import type { ProductSearchResponse } from "../../types/ProductSearchResponse";
import type { Product } from "../../types/Models/Product";

const ERROR_CODE_SEARCH_NO_ERROR = 0;
const ERROR_CODE_SEARCH_REPORT_NOT_AVAILABLE = 1; // request valid, tapi laporan belum ada
const ERROR_CODE_SEARCH_NO_DATA = 2;
const ERROR_CODE_SEARCH_CONNECTION_ERROR = 3;
const ERROR_CODE_SEARCH_UNKNOWN_ERROR = 4;

export function ProdukPenjualan() {
  const searchHasMounted = useRef(false);

  const [jenisLaporanSaatIni, setJenisLaporanSaatIni] = reactUseState("ampera");
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
      retrySearchNow();
    } else {
      searchHasMounted.current = true;
    }
  }, [jenisLaporanSaatIni, productPerPage, sortirTingkatProduk, resetSearchTrigger]);

    return (<>
      <h1 className="scroll-m-20 text-2xl font-semibold tracking-tight lg:text-3xl m-5">Peringkat Produk</h1>
      <Card className="m-5">
        <CardContent>
          <FieldGroup className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <Field>
              <FieldLabel htmlFor="jenislaporan">Cabang saat ini:</FieldLabel>
              <Select defaultValue="gabungan" name="jenislaporan" onValueChange={(value) => value && setJenisLaporanSaatIni(value)}>
                <SelectTrigger className="w-45">
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
          <FieldGroup className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 gap-4">
            <Field>
              <FieldLabel htmlFor="cariproduk">Barcode atau Kata Kunci Produk:</FieldLabel>
              <Input type="text" name="cariproduk" onChange={(e) => setKataKunciPencarian(e.target.value)} placeholder="Masukkan kata kunci di sini" onKeyUp={(e) => e.keyCode === 13 && resetSearchNow()} />
              <Button onClick={resetSearchNow}>Cari</Button>
            </Field>
          </FieldGroup>
        </CardContent>
      </Card>

      { errorCode === ERROR_CODE_SEARCH_CONNECTION_ERROR && 
        <Card className="m-5">
          <CardContent className="grid place-items-center pt-5 pb-5">
            <CardTitle>Kesalahan Koneksi</CardTitle>
            <CardDescription>Terjadi kesalahan koneksi. Mohon coba lagi.</CardDescription>
            <Button onClick={retrySearchNow} className="mt-3">Coba Lagi</Button>
          </CardContent>
        </Card>
      }

      { errorCode === ERROR_CODE_SEARCH_NO_DATA && 
        <Card className="m-5">
          <CardContent className="grid place-items-center pt-5 pb-5">
            <CardTitle>Produk Tidak Ditemukan</CardTitle>
            <CardDescription>Produk tidak ditemukan. Pastikan kode atau nama produk dimasukkan dengan benar.</CardDescription>
          </CardContent>
        </Card>
      }

      { errorCode === ERROR_CODE_SEARCH_REPORT_NOT_AVAILABLE && 
        <Card className="m-5">
          <CardContent className="grid place-items-center pt-5 pb-5">
            <CardTitle>Laporan Tidak Tersedia</CardTitle>
            <CardDescription>Laporan produk untuk jenis dan masa yang dimasukkan masih belum tersedia. Silahkan unggah laporan penjualan untuk jenis dan masa tersebut terlebih dahulu.</CardDescription>
            <Link to="/upload" className={buttonVariants({ variant: "default" })}>
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
            <Button onClick={retrySearchNow} className="mt-3">Coba Lagi</Button>
          </CardContent>
        </Card>
      }

      { errorCode === ERROR_CODE_SEARCH_NO_ERROR && 
        <Card className="m-5">
          <CardContent>
            <Table>
              <TableHeader>
                <TableHead>No</TableHead>
                <TableHead>Barcode</TableHead>
                <TableHead>Kode Barang</TableHead>
                <TableHead>Nama barang</TableHead>
                <TableHead>Satuan</TableHead>
                <TableHead>Harga Jual</TableHead>
                <TableHead>Jumlah (Omzet)</TableHead>
                <TableHead>Qty (terjual)</TableHead>
                <TableHead>Sisa Stok</TableHead>
              </TableHeader>
              <TableBody>
                { isLoaded === true ?
                  daftarProduk.map((produk, index) => (
                    <TableRow key={index}>
                      <TableCell>{index + 1}</TableCell>
                      <TableCell>{produk.barcode}</TableCell>
                      <TableCell>{produk.kode_brg}</TableCell>
                      <TableCell>{produk.nama_brg}</TableCell>
                      <TableCell>{produk.satuan}</TableCell>
                      <TableCell>{formatRupiah(produk.harga_jual)}</TableCell>
                      <TableCell>{formatRupiah(produk.jumlah)}</TableCell>
                      <TableCell className={produk.qty < 0 ? "font-semibold text-purple-600" : ""}>{formatAngka(produk.qty)}</TableCell>
                      <TableCell>{formatAngka(produk.stok)}</TableCell>
                    </TableRow>
                  ))
                :
                  Array.from({ length: productPerPage }).map((_, index) => (
                    <TableRow key={index}>
                      <TableCell><Skeleton className="h-4 w-10" /></TableCell>
                      <TableCell><Skeleton className="h-4 w-30" /></TableCell>
                      <TableCell><Skeleton className="h-4 w-30" /></TableCell>
                      <TableCell><Skeleton className="h-4 w-50" /></TableCell>
                      <TableCell><Skeleton className="h-4 w-20" /></TableCell>
                      <TableCell><Skeleton className="h-4 w-50" /></TableCell>
                      <TableCell><Skeleton className="h-4 w-50" /></TableCell>
                      <TableCell><Skeleton className="h-4 w-20" /></TableCell>
                      <TableCell><Skeleton className="h-4 w-20" /></TableCell>
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
              <div>Halaman...</div>
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                { currentPage - 2 >= 1 && <div onClick={() => setCurrentPage(1)}>{1}</div> }
                {currentPage - 1 > 0 && <div onClick={() => setCurrentPage(currentPage - 1)}>{currentPage - 1}</div> }
                <div>{currentPage}</div>
                {currentPage + 1 <= maxPage && <div onClick={() => setCurrentPage(currentPage + 1)}>{currentPage + 1}</div>}
                { currentPage + 2 <= maxPage && <div onClick={() => setCurrentPage(maxPage)}>{maxPage}</div> }
              </div>
            </div>
            <div>{`Menampilkan produk ${productPerPage * (currentPage - 1)}-${(productPerPage * (currentPage - 1)) + daftarProduk.length} dari ${totalProducts}`}</div>
            <div>
              <div>Produk per halaman</div>
              <select onChange={(e) => setProductPerPage(parseInt(e.target.value))} defaultValue={10}>
              {
                [10,20,50].map((value, index) => (
                  <option tabIndex={index + 1} value={value} key={index}>{value}</option>
                ))
              }
              </select>
            </div>
          </CardContent>
        </Card>
      }
    </>);
}

export default ProdukPenjualan;
