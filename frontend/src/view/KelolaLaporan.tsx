import { useState as reactUseState, useEffect, useRef } from "react";
import { isAxiosError, isCancel as axiosIsCancel } from "axios";
import { api } from "../services/api";
import { ArrowLeft, ArrowRight } from "lucide-react";

import { Button, } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field";
import { NativeSelect, NativeSelectOption } from "@/components/ui/native-select"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Skeleton } from "@/components/ui/skeleton";
import { NoConnectionCard } from "@/components/errorcards/NoConnectionCard";
import { UnknownErrorCard } from "@/components/errorcards/UnknownErrorCard";
import { NoDataFoundCard } from "@/components/errorcards/NoDataFoundCard";

import type { LaporanSearchResponse } from "../../types/LaporanSearchResponse";
import type { Laporan } from "../../types/Models/Laporan";

import { DeleteReportDialogFlow } from "@/components/managereports/DeleteReportDialogFlow";
import { CatatanPopupDialogFlow } from "@/components/managereports/CatatanPopupDialogFlow";

const months = [
  "Januari", "Februari", "Maret", "April", "Mei", "Juni",
  "Juli", "Agustus", "September", "Oktober", "November", "Desember"
];

const ERROR_CODE_SEARCH_NO_ERROR = 0;
const ERROR_CODE_SEARCH_NO_DATA = 1;
const ERROR_CODE_SEARCH_CONNECTION_ERROR = 2;
const ERROR_CODE_SEARCH_UNKNOWN_ERROR = 3;

export function KelolaLaporan() {
  const searchHasMounted = useRef(false);

  const yearNow = new Date().getFullYear();

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

  const [pickedJenisLaporan, setPickedJenisLaporan] = reactUseState<string>(""); 
  const [pickedMonth, setPickedMonth] = reactUseState<number | "">("");
  const [pickedYear, setPickedYear] = reactUseState<number | "">("");

  const [reportsPerPage, setReportsPerPage] = reactUseState(10);
  const [daftarLaporan, setDaftarLaporan] = reactUseState < Laporan[] > ([]);

  const [currentPage, setCurrentPage] = reactUseState < number > (1);
  const [totalReports, setTotalReports] = reactUseState < number > (0);
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
      setDaftarLaporan([]);
      setTotalReports(0);
      setIsLoaded(false);

      // construct query params
      const body = {
        page: currentPage,
        per_page: reportsPerPage,
      }

      if(pickedJenisLaporan) {
        Object.defineProperty(body, "jenis_laporan", {value: pickedJenisLaporan, enumerable: true});
      }

      if(pickedMonth) {
        Object.defineProperty(body, "periode_bulan", {value: pickedMonth, enumerable: true});
      }

      if(pickedYear) {
        Object.defineProperty(body, "periode_tahun", {value: pickedYear, enumerable: true});
      }

      try {
        const response = await api.get('/api/list-reports', {
          responseType: "json",
          signal: abortController.signal,
          params: body
        });

        const responseData = response.data as LaporanSearchResponse;

        setDaftarLaporan(responseData.data);
        setTotalReports(responseData.total);
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
  }, [reportsPerPage, resetSearchTrigger]);

    return (<>
      <h1 className="scroll-m-20 text-2xl font-semibold tracking-tight lg:text-3xl m-5">Kelola Laporan</h1>
      <Card className="m-5">
        <CardContent>
          <FieldGroup className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <Field>
              <FieldLabel htmlFor="jenislaporan">Jenis laporan:</FieldLabel>
              <NativeSelect required={true} name="jenis_laporan" defaultValue="Apapun" onChange={(event) => {setPickedJenisLaporan(event.target.value); resetSearchNow();}}>
                <NativeSelectOption tabIndex={0} value="" key={0}>Apapun</NativeSelectOption>
                {
                  ["ampera", "pal", "gabungan"].map((value, index) => (
                    <NativeSelectOption tabIndex={index + 2} value={value} key={index + 1}>{value}</NativeSelectOption>
                  ))
                }
              </NativeSelect>
            </Field>
            <Field>
              <FieldLabel>Bulan Laporan:</FieldLabel>
              <NativeSelect required={true} name="periode_bulan" defaultValue="Apapun" onChange={(event) => {setPickedMonth(parseInt(event.target.value)); resetSearchNow();}}>
                <NativeSelectOption tabIndex={0} value="" key={0}>Apapun</NativeSelectOption>
                {months.map((value, index) => (
                  <NativeSelectOption value={(index + 1).toString()} key={index}>{value}</NativeSelectOption>
                ))}
              </NativeSelect>
            </Field>
            <Field>
              <FieldLabel>Tahun Laporan:</FieldLabel>
              <NativeSelect required={true} name="periode_tahun" defaultValue="Apapun" onChange={(event) => {setPickedYear(parseInt(event.target.value)); resetSearchNow();}}>
                <NativeSelectOption tabIndex={0} value="" key={0}>Apapun</NativeSelectOption>
                {
                  yearOptions.map((value, index) => (
                    <NativeSelectOption tabIndex={index + 1} value={value} key={index}>{value}</NativeSelectOption>
                  ))
                }
              </NativeSelect>
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
                  <TableHead>ID</TableHead>
                  <TableHead>Jenis Laporan</TableHead>
                  <TableHead>Bulan Laporan</TableHead>
                  <TableHead>Tahun Laporan</TableHead>
                  <TableHead>Jumlah Hari</TableHead>
                  <TableHead>Format Data</TableHead>
                  <TableHead>Catatan</TableHead>
                  <TableHead>Tindakan</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                { isLoaded === true ?
                  daftarLaporan.map((laporan, index) => (
                    <TableRow key={index}>
                      <TableCell>{(reportsPerPage * (currentPage - 1) + 1) + index}</TableCell>
                      <TableCell>{laporan.id}</TableCell>
                      <TableCell>{laporan.jenis_laporan}</TableCell>
                      <TableCell>{months[laporan.periode_bulan - 1]}</TableCell>
                      <TableCell>{laporan.periode_tahun}</TableCell>
                      <TableCell>{laporan.jumlah_hari}</TableCell>
                      <TableCell>{laporan.format_data}</TableCell>
                      <CatatanPopupDialogFlow catatanText={laporan.catatan} triggerRender={
                        <TableCell title={laporan.catatan && laporan.catatan}>
                        {typeof laporan.catatan === "string" && laporan.catatan.length > 0
                          ? laporan.catatan.length > 25
                            ? `${laporan.catatan.slice(0, 25)}...`
                            : laporan.catatan
                          : "-"}
                        </TableCell>
                        } />
                      <TableCell>
                        <div className="flex flex-row items-center gap-2">
                          <DeleteReportDialogFlow retryTrigger={retrySearchNow} deleteReportId={laporan.id} />
                        </div>
                      </TableCell>
                    </TableRow>
                  ))
                :
                  Array.from({ length: reportsPerPage }).map((_, index) => (
                    <TableRow key={index}>
                      <TableCell><Skeleton className="h-5 min-w-5" /></TableCell>
                      <TableCell><Skeleton className="h-5 min-w-10" /></TableCell>
                      <TableCell><Skeleton className="h-5 min-w-30" /></TableCell>
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
            { (errorCode !== ERROR_CODE_SEARCH_NO_DATA || totalReports > 0) &&
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
                { (errorCode !== ERROR_CODE_SEARCH_NO_DATA && totalReports > 0)
                  ? `Menampilkan laporan ${reportsPerPage * (currentPage - 1) + 1}-${(reportsPerPage * (currentPage - 1)) + daftarLaporan.length} dari ${totalReports}`
                  : '\u00A0'}
              </div>
              <div className="flex items-center gap-2">
                <div>Laporan per halaman</div>
                <NativeSelect defaultValue={10} name="jenislaporan" onChange={(event) => setReportsPerPage(parseInt(event.target.value)) }>
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

export default KelolaLaporan;
