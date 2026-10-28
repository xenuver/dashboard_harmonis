import { useState as reactUseState, type SubmitEvent as ReactSubmitEvent } from "react";
import { isAxiosError, isCancel as axiosIsCancel } from "axios";
import { api } from "../services/api";
import { CircleCheck, CircleX, Download } from "lucide-react";

import { Button, } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field";
import { NativeSelect, NativeSelectOption } from "@/components/ui/native-select"
import type LaravelValidationError from "types/LaravelValidationError";

const months = [
  "Januari", "Februari", "Maret", "April", "Mei", "Juni",
  "Juli", "Agustus", "September", "Oktober", "November", "Desember"
];

const EXPORT_MESSAGE_NOTHING = 0;
const EXPORT_MESSAGE_SUCCESS = 1;
const EXPORT_MESSAGE_FAIL = 2;

export function EksporData() {
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

  const [exportMessageText, setExportMessageText] = reactUseState<string>("");
  const [exportMessageState, setExportMessageState] = reactUseState<number>(EXPORT_MESSAGE_NOTHING);
  const [restrictFormChange, setRestrictFormChange] = reactUseState<boolean>(false);

  async function runExportData(event: ReactSubmitEvent) {
    event.preventDefault();
    setExportMessageState(EXPORT_MESSAGE_NOTHING);
    const form = new FormData(event.target);

    const jenisLaporan = form.get("jenis_laporan");
    const periodeBulan = form.get("periode_bulan");
    const periodeTahun = form.get("periode_tahun");

    if(typeof jenisLaporan !== "string") {
      setExportMessageState(EXPORT_MESSAGE_FAIL);
      setExportMessageText("jenis laporan yang dimasukkan harus merupakan teks");
      setRestrictFormChange(false);
      return;
    }

    if(typeof periodeBulan === "string") {
      try {
        parseInt(periodeBulan);
      } catch(e) {
        setExportMessageState(EXPORT_MESSAGE_FAIL);
        setExportMessageText("periode bulan yang dimasukkan harus merupakan angka");
        setRestrictFormChange(false);
        console.warn(e);
        return;
      }
    } else {
      setExportMessageState(EXPORT_MESSAGE_FAIL);
      setExportMessageText("periode bulan yang dimasukkan harus merupakan angka");
      setRestrictFormChange(false);
      return;
    }

    if(typeof periodeTahun === "string") {
      try {
        parseInt(periodeTahun);
      } catch(e) {
        setExportMessageState(EXPORT_MESSAGE_FAIL);
        setExportMessageText("periode tahun yang dimasukkan harus merupakan angka");
        setRestrictFormChange(false);
        console.warn(e);
        return;
      }
    } else {
      setExportMessageState(EXPORT_MESSAGE_FAIL);
      setExportMessageText("periode tahun yang dimasukkan harus merupakan angka");
      setRestrictFormChange(false);
      return;
    }


    // construct query params
    const body = {
      jenis_laporan: jenisLaporan,
      periode_tahun: periodeTahun,
      periode_bulan: periodeBulan,
    }

    try {
      const response = await api.get('/api/export-xlsx', {
        responseType: "blob",
        params: body
      });

      const downloadBlob = new Blob([response.data]);
      const blobDownloadUrl = window.URL.createObjectURL(downloadBlob);

      const link = document.createElement("a");
      link.href = blobDownloadUrl;
      link.download = `REPORT_${months[parseInt(periodeBulan) - 1]}_${periodeTahun}.xlsx`;
      link.hidden = true;
      link.style.display = "none";

      document.body.appendChild(link);
      link.click();
      link.remove();
    } catch (err) {
      if (axiosIsCancel(err)) {
        return;
      }

      if (isAxiosError(err)) {
        if (err.status === 404) {
          setExportMessageText("Data belum tersedia");
          setExportMessageState(EXPORT_MESSAGE_FAIL);
          return;
        } else if (err.status === 422) {
          const message = JSON.parse(await err.response?.data.text()) as LaravelValidationError ?? null;
          if(Array.isArray(message.errors?.jenis_laporan)) {
            setExportMessageText(message.errors.jenis_laporan[0]);
          } else if(Array.isArray(message.errors?.periode_bulan)) {
            setExportMessageText(message.errors.periode_bulan[0]);
          } else if(Array.isArray(message.errors?.periode_tahun)) {
            setExportMessageText(message.errors.periode_tahun[0]);
          } else if(message) {
            setExportMessageText(message.message);
          } else {
            setExportMessageText("Terjadi kesalahan yang tidak diketahui");
            setExportMessageState(EXPORT_MESSAGE_FAIL);
          }
          setExportMessageState(EXPORT_MESSAGE_FAIL);
          return;
        }
        console.error(err);
        setExportMessageText("Terjadi kesalahan yang tidak diketahui");
        setExportMessageState(EXPORT_MESSAGE_FAIL);
      }
    }
  }

    return (<>
      <h1 className="scroll-m-20 text-2xl font-semibold tracking-tight lg:text-3xl m-5">Ekspor Laporan</h1>
      { exportMessageState === EXPORT_MESSAGE_SUCCESS &&
        <Card className="m-5 border-green-300 bg-green-50">
          <CardContent>
            <div className="flex items-center gap-2">
              <CircleCheck className="shrink-0 stroke-green-700" />
              <span className="text-green-700 font-medium">{exportMessageText}</span>
            </div>
          </CardContent>
        </Card>
      }
      { exportMessageState === EXPORT_MESSAGE_FAIL &&
        <Card className="m-5 border-red-300 bg-red-50">
          <CardContent>
            <div className="flex items-center gap-2">
              <CircleX className="shrink-0 stroke-red-700" />
              <span className="text-red-700 font-medium">{exportMessageText}</span>
            </div>
          </CardContent>
        </Card>
      }
      <Card className="m-5">
        <CardContent>
            <form onSubmit={runExportData}>
                <FieldGroup className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <Field>
                    <FieldLabel htmlFor="jenislaporan">Jenis laporan:</FieldLabel>
                    <NativeSelect required={true} name="jenis_laporan" defaultValue="gabungan" disabled={restrictFormChange}>
                        {
                            ["ampera", "pal", "gabungan"].map((value, index) => (
                            <NativeSelectOption tabIndex={index + 1} value={value} key={index}>{value}</NativeSelectOption>
                            ))
                        }
                    </NativeSelect>
                    </Field>
                    <Field>
                    <FieldLabel>Bulan Laporan:</FieldLabel>
                    <NativeSelect required={true} name="periode_bulan" defaultValue={monthNow} disabled={restrictFormChange}>
                        {months.map((value, index) => (
                        <NativeSelectOption value={(index + 1).toString()} key={index}>{value}</NativeSelectOption>
                        ))}
                    </NativeSelect>
                    </Field>
                    <Field>
                    <FieldLabel>Tahun Laporan:</FieldLabel>
                    <NativeSelect required={true} name="periode_tahun" defaultValue={yearNow} disabled={restrictFormChange}>
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
                    <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full">
                        <Button className="w-full sm:w-auto shrink-0 px-4" type="submit" disabled={restrictFormChange}>
                        <Download className="w-5 h-5" />
                        Unduh .xlsx
                        </Button>
                    </div>
                    </Field>
                </FieldGroup>
            </form>
        </CardContent>
      </Card>
    </>);
}

export default EksporData;
