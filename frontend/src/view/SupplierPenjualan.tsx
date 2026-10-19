import { useState as reactUseState, useEffect, useRef } from "react";
import { isAxiosError, isCancel as axiosIsCancel } from "axios";
import { api } from "../services/api";
import { ArrowLeft, ArrowRight, Search } from "lucide-react";
import { formatRupiah } from "../services/formatters";

import { Button, } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { NativeSelect, NativeSelectOption } from "@/components/ui/native-select"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Skeleton } from "@/components/ui/skeleton";
import { ReportUnavailableCard } from "@/components/errorcards/ReportUnavailableCard";
import { NoConnectionCard } from "@/components/errorcards/NoConnectionCard";
import { UnknownErrorCard } from "@/components/errorcards/UnknownErrorCard";
import { NoDataFoundCard } from "@/components/errorcards/NoDataFoundCard";

import type { SupplierSearchResponse } from "../../types/SupplierSearchResponse";
import type { Supplier } from "../../types/Models/Supplier";

const months = [
  "Januari", "Februari", "Maret", "April", "Mei", "Juni",
  "Juli", "Agustus", "September", "Oktober", "November", "Desember"
];

const ERROR_CODE_SEARCH_NO_ERROR = 0;
const ERROR_CODE_SEARCH_REPORT_NOT_AVAILABLE = 1; // request valid, tapi laporan belum ada
const ERROR_CODE_SEARCH_NO_DATA = 2;
const ERROR_CODE_SEARCH_CONNECTION_ERROR = 3;
const ERROR_CODE_SEARCH_UNKNOWN_ERROR = 4;

export function SupplierPenjualan() {
  const searchHasMounted = useRef(false);

  const yearNow = new Date().getFullYear();
  const monthNow = new Date().getMonth() + 1;

  const yearOptions: number[] = [];

  for (let i = 0; i < 100; i++) {
    const calcYear = yearNow - i;
    if(calcYear > 2155) {
      continue;
    }
    if(calcYear < 1901) {
      break;
    }
    yearOptions.push(calcYear);
  }

  const [jenisLaporanSaatIni, setJenisLaporanSaatIni] = reactUseState("gabungan");
  const [pickedMonth, setPickedMonth] = reactUseState<number>(monthNow);
  const [pickedYear, setPickedYear] = reactUseState<number>(yearNow);
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
        periode_tahun: pickedYear,
        periode_bulan: pickedMonth,
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
  }, [jenisLaporanSaatIni, supplierPerPage, resetSearchTrigger, pickedMonth, pickedYear]);

    return (<>
      <h1 className="scroll-m-20 text-2xl font-semibold tracking-tight lg:text-3xl m-5">Peringkat Supplier</h1>
      <Card className="m-5">
        <CardContent>
          <FieldGroup className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <Field>
              <FieldLabel htmlFor="jenislaporan">Jenis laporan:</FieldLabel>
              <NativeSelect required={true} name="jenis_laporan" defaultValue={jenisLaporanSaatIni} onChange={(event) => setJenisLaporanSaatIni(event.target.value)}>
                  {
                    ["ampera", "pal", "gabungan"].map((value, index) => (
                      <NativeSelectOption tabIndex={index + 1} value={value} key={index}>{value}</NativeSelectOption>
                    ))
                  }
              </NativeSelect>
            </Field>
            <Field>
              <FieldLabel>Bulan Laporan:</FieldLabel>
              <NativeSelect required={true} name="periode_bulan" defaultValue={monthNow} onChange={(event) => setPickedMonth(parseInt(event.target.value))}>
                {months.map((value, index) => (
                  <NativeSelectOption value={(index + 1).toString()} key={index}>{value}</NativeSelectOption>
                ))}
              </NativeSelect>
            </Field>
            <Field>
              <FieldLabel>Tahun Laporan:</FieldLabel>
              <NativeSelect required={true} name="periode_tahun" defaultValue={yearNow} onChange={(event) => setPickedYear(parseInt(event.target.value))}>
                {
                  yearOptions.map((value, index) => (
                    <NativeSelectOption tabIndex={index + 1} value={value} key={index}>{value}</NativeSelectOption>
                  ))
                }
              </NativeSelect>
            </Field>
          </FieldGroup>
          <FieldGroup className="gap-4 mt-4">
            <Field className="flex flex-col gap-2 w-full">
              <FieldLabel htmlFor="carisupplier">
                Cari Kode Supplier atau Nama supplier:
              </FieldLabel>
              <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full">
                <Input 
                  type="text" 
                  name="carisupplier" 
                  className="w-full sm:flex-1 min-w-0"
                  onChange={(e) => setKataKunciPencarian(e.target.value)} 
                  placeholder="Masukkan kode supplier atau nama supplier di sini" 
                  onKeyUp={(e) => (e.key === 'Enter' || e.keyCode === 13) && resetSearchNow()} 
                />
                <Button 
                  onClick={resetSearchNow} 
                  className="w-full sm:w-auto shrink-0 px-4"
                >
                  <Search className="w-5 h-5" />
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
                  <TableHead>Kode Supplier</TableHead>
                  <TableHead>Nama Supplier</TableHead>
                  <TableHead>Gross Total</TableHead>
                  <TableHead>Net Sales Total</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                { isLoaded === true ?
                  daftarSupplier.map((supplier, index) => (
                    <TableRow key={index}>
                      <TableCell>{(supplierPerPage * (currentPage - 1) + 1) + index}</TableCell>
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
            { (errorCode !== ERROR_CODE_SEARCH_NO_DATA || totalSuppliers > 0) &&
              <div>
                <div className="flex flex-col sm:flex-row items-center justify-center gap-2 w-full max-w-full px-4 box-border">

                  {currentPage - 1 > 0 && (
                    <Button onClick={() => setCurrentPage(currentPage - 1)} className="w-full sm:w-auto max-w-xs flex items-center justify-center gap-2">
                      <ArrowLeft />
                      <span>Sebelumnya</span>
                    </Button>
                  )}

                  <div className="flex flex-wrap items-center justify-center gap-1.5 max-w-full">
                    {currentPage - 2 >= 1 && (
                      <Button onClick={() => setCurrentPage(1)}>{1}</Button>
                    )}
                    {currentPage - 3 >= 1 && <div className="px-1">...</div>}
                    {currentPage - 1 > 0 && (
                      <Button onClick={() => setCurrentPage(currentPage - 1)}>
                        {currentPage - 1}
                      </Button>
                    )}

                    <Button disabled={true}>{currentPage}</Button>

                    {currentPage + 1 <= maxPage && (
                      <Button onClick={() => setCurrentPage(currentPage + 1)}>
                        {currentPage + 1}
                      </Button>
                    )}
                    {currentPage + 3 <= maxPage && <div className="px-1">...</div>}
                    {currentPage + 2 <= maxPage && (
                      <Button onClick={() => setCurrentPage(maxPage)}>{maxPage}</Button>
                    )}
                  </div>

                  {currentPage + 1 <= maxPage && (
                    <Button onClick={() => setCurrentPage(currentPage + 1)} className="w-full sm:w-auto max-w-xs flex items-center justify-center gap-2">
                      <span>Selanjutnya</span>
                      <ArrowRight />
                    </Button>
                  )}
                </div>
              </div>
            }

            <div className="flex flex-col sm:flex-row justify-between items-center gap-4">
              <div>
                { (errorCode !== ERROR_CODE_SEARCH_NO_DATA && totalSuppliers > 0)
                  ? `Menampilkan supplier ${supplierPerPage * (currentPage - 1) + 1}-${(supplierPerPage * (currentPage - 1)) + daftarSupplier.length} dari ${totalSuppliers}`
                  : '\u00A0'}
              </div>
              <div className="flex items-center gap-2">
                <div>Supplier per halaman</div>
                <NativeSelect defaultValue={10} name="jenislaporan" onChange={(event) => setSupplierPerPage(parseInt(event.target.value)) }>
                  {
                    [10, 20, 50].map((value, index) => (
                      <NativeSelectOption tabIndex={index + 1} value={value} key={index}>{value}</NativeSelectOption>
                    ))
                  }
                </NativeSelect>
              </div>
            </div>
          </CardContent>
        </Card>
      }
    </>);
}

export default SupplierPenjualan;
