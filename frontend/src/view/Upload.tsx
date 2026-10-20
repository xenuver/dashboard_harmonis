import { useState as reactUseState, useRef as reactUseRef } from "react";
import { api } from "../services/api";
import { isAxiosError } from "axios";

import type { SubmitEvent as ReactSubmitEvent } from "react";
import type ReportUploadValidationErrorResponse from '../../types/ReportUploadValidationErrorResponse';
import type ReportValidationErrorItem from '../../types/Models/ReportValidationErrorItem';
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field";
import { NativeSelect, NativeSelectOption } from "@/components/ui/native-select"
import { Button } from "@/components/ui/button";
import { Dropzone, type ExcelDropzoneRef } from "../components/upload/ExcelDropZone";
import { CircleCheck, CircleX, FileUp, ChevronDown } from "lucide-react";

import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible";

const UPLOAD_MESSAGE_NOTHING = 0;
const UPLOAD_MESSAGE_SUCCESS = 1;
const UPLOAD_MESSAGE_FAIL = 2;
const UPLOAD_MESSAGE_XLSX_VALIDATION_ERROR = 3;

const months = [
  "Januari", "Februari", "Maret", "April", "Mei", "Juni",
  "Juli", "Agustus", "September", "Oktober", "November", "Desember"
];

export function Upload() {
  const yearNow = new Date().getFullYear();
  const monthNow = new Date().getMonth() + 1;

  const [restrictFormChange, setRestrictFormChange] = reactUseState<boolean>(false);

  const uploadFormRef = reactUseRef<HTMLFormElement | null>(null);

  const [uploadMessageText, setUploadMessageText] = reactUseState<string>("");
  const [uploadMessageState, setUploadMessageState] = reactUseState<number>(0);
  const [xlsxUploadValidationErrorsList, setXlsxUploadValidationErrorsList] = reactUseState<Record<string, ReportValidationErrorItem[]> | null>(null);
  const [attachedFile, setAttachedFile] = reactUseState<File | null>(null);

  const dropzoneRef = reactUseRef<ExcelDropzoneRef | null>(null);

  const yearOptions: number[] = [];

  for (let i = 0; i < 100; i++) {
    if(yearNow - i > 2155) {
      continue;
    }
    if(yearNow - i < 1901) {
      break;
    }
    yearOptions.push(yearNow - i);
  }

  const clearAttachedFile = function() {
    setAttachedFile(null); // Clears the file from parent state!
    dropzoneRef.current?.clear();
  }

  const resetAttachmentState = function() {
    if(uploadFormRef.current) {
      uploadFormRef.current.reset();
    }
    clearAttachedFile();
    setUploadMessageState(UPLOAD_MESSAGE_NOTHING);
    setUploadMessageText("");
  }

  async function handleFormSubmit(event: ReactSubmitEvent) {
    event.preventDefault();
    setUploadMessageText("");
    setUploadMessageState(UPLOAD_MESSAGE_NOTHING);
    setXlsxUploadValidationErrorsList(null);
    setRestrictFormChange(true);

    const uploadInformation = new FormData(event.target);

    const jenisLaporan = uploadInformation.get("jenis_laporan");
    const periodeBulan = uploadInformation.get("periode_bulan");
    const periodeTahun = uploadInformation.get("periode_tahun");
    const fileLaporan = attachedFile;

    if(typeof jenisLaporan !== "string") {
      setUploadMessageState(UPLOAD_MESSAGE_FAIL);
      setUploadMessageText("jenis laporan yang dimasukkan harus merupakan teks");
      setRestrictFormChange(false);
      return;
    }

    if(typeof periodeBulan === "string") {
      try {
        parseInt(periodeBulan);
      } catch(e) {
        setUploadMessageState(UPLOAD_MESSAGE_FAIL);
        setUploadMessageText("periode bulan yang dimasukkan harus merupakan angka");
        setRestrictFormChange(false);
        console.warn(e);
        return;
      }
    } else {
      setUploadMessageState(UPLOAD_MESSAGE_FAIL);
      setUploadMessageText("periode bulan yang dimasukkan harus merupakan angka");
      setRestrictFormChange(false);
      return;
    }

    if(typeof periodeTahun === "string") {
      try {
        parseInt(periodeTahun);
      } catch(e) {
        setUploadMessageState(UPLOAD_MESSAGE_FAIL);
        setUploadMessageText("periode tahun yang dimasukkan harus merupakan angka");
        setRestrictFormChange(false);
        console.warn(e);
        return;
      }
    } else {
      setUploadMessageState(UPLOAD_MESSAGE_FAIL);
      setUploadMessageText("periode tahun yang dimasukkan harus merupakan angka");
      setRestrictFormChange(false);
      return;
    }

    if(fileLaporan instanceof File) {
      if(!fileLaporan.name.endsWith(".xlsx") && fileLaporan.name !== "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet")  {
        setUploadMessageState(UPLOAD_MESSAGE_FAIL);
        setUploadMessageText("file laporan harus dalam format xslx");
        setRestrictFormChange(false);
        return;
      }
    } else {
      setUploadMessageState(UPLOAD_MESSAGE_FAIL);
      setUploadMessageText("File laporan harus berbentuk file .xlsx");
      setRestrictFormChange(false);
      return;
    }

    try {
      const uploadRequest = await api.postForm("/api/upload", {
        jenis_laporan: jenisLaporan,
        periode_bulan: periodeBulan,
        periode_tahun: periodeTahun,
        file: fileLaporan,
      }, {
        responseType: "json",
      });
      if(uploadRequest.status === 200) {
        setUploadMessageState(UPLOAD_MESSAGE_SUCCESS);
        setUploadMessageText("Upload berhasil");
        clearAttachedFile();
      }
    } catch(e) {
      if(isAxiosError(e)) {
        const status = e.status || e.response?.status;

        if(status === 409) {
          setUploadMessageState(UPLOAD_MESSAGE_FAIL);
          setUploadMessageText("Data untuk periode ini sudah ada");
        } else if(status === 422) {
          if(e.response?.data?.xlsxValidationErrors) {
            const response = e.response.data as ReportUploadValidationErrorResponse;
            setUploadMessageText("Terjadi kesalahan dalam validasi data");
            setXlsxUploadValidationErrorsList(response.xlsxValidationErrors);
            setUploadMessageState(UPLOAD_MESSAGE_XLSX_VALIDATION_ERROR);
          } else {
            setUploadMessageText("Terjadi kesalahan dalam membaca data. Pastikan laporan merupakan file .xlsx yang valid");
            setUploadMessageState(UPLOAD_MESSAGE_FAIL);
          }
        } else if (e.code === "ERR_NETWORK") {
          console.error(e);
          console.warn("Terjadi kesalahan dalam mendapatkan respon dari server");
          setUploadMessageState(UPLOAD_MESSAGE_FAIL);
          setUploadMessageText("Terjadi kesalahan dalam mengirmkan data ke server");
        } else if (e.code === "ERR_BAD_RESPONSE") {
          setUploadMessageState(UPLOAD_MESSAGE_FAIL);
          console.error(e);
          console.warn("Server tidak merespon dengan format data yang tepat");
          setUploadMessageText("Terjadi kesalahan dalam membaca respon dari server");
        } else if (e.code === "ECONNABORTED" || e.code === "ERR_CANCELED") {
          setUploadMessageState(UPLOAD_MESSAGE_FAIL);
          setUploadMessageText("Permintaan dibatalkan atau waktu tunggu habis");
        } else {
          console.error(e);
          console.warn("Terjadi kesalahan dalam membuat permintaan upload");
          setUploadMessageState(UPLOAD_MESSAGE_FAIL);
          setUploadMessageText("Terjadi kesalahan dalam membuat permintaan upload");
        }
      } else {
        console.log(e);
        console.warn("Terjadi kesalahan dalam membuat koneksi");
      }
    } finally {
      setRestrictFormChange(false);
    }
  }

  return (<>
    <h1 className="scroll-m-20 text-2xl font-semibold tracking-tight lg:text-3xl m-5">Upload Laporan</h1>
    <div>
      <form onSubmit={handleFormSubmit} ref={uploadFormRef}>
        <Card className="m-5">
          <CardHeader>
            <CardTitle>Informasi Laporan</CardTitle>
          </CardHeader>
          <CardContent>
            <FieldGroup className="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-3 gap-4">
              <Field>
                <FieldLabel>Jenis Laporan:</FieldLabel>
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
          </CardContent>
        </Card>
        <Card className="m-5">
          <CardHeader>
            <CardTitle>Unggah File</CardTitle>
          </CardHeader>
          <CardContent className="gap-2">
            { uploadMessageState === UPLOAD_MESSAGE_SUCCESS &&
              <Card className="mb-5 border-green-300 bg-green-50">
                <CardContent>
                  <div className="flex items-center gap-2">
                    <CircleCheck className="shrink-0 stroke-green-700" />
                    <span className="text-green-700 font-medium">{uploadMessageText}</span>
                  </div>
                </CardContent>
              </Card>
            }
            { uploadMessageState === UPLOAD_MESSAGE_FAIL &&
              <Card className=" mb-5 border-red-300 bg-red-50">
                <CardContent>
                  <div className="flex items-center gap-2">
                    <CircleX className="shrink-0 stroke-red-700" />
                    <span className="text-red-700 font-medium">{uploadMessageText}</span>
                  </div>
                </CardContent>
              </Card>
            }
            { uploadMessageState === UPLOAD_MESSAGE_XLSX_VALIDATION_ERROR && (
              <Card className="mb-5 border-red-300 bg-red-50">
                <CardHeader>
                  <div className="flex items-center gap-2">
                    <CircleX className="shrink-0 stroke-red-700" />

                    <span className="text-red-700 font-medium">
                      {uploadMessageText}
                    </span>
                  </div>
                </CardHeader>

                { xlsxUploadValidationErrorsList &&
                  <CardContent className="space-y-3">
                  { Object.entries(xlsxUploadValidationErrorsList).map(
                    ([sheetName, errors]) => (
                      <Collapsible key={sheetName}>
                        <Card>
                          <CollapsibleTrigger className="group1 flex w-full items-center justify-between pl-5 pr-5 text-left hover:bg-muted/50">
                              <div className="flex items-center gap-2">
                                <span className="font-medium">
                                  {sheetName}
                                </span>

                                <span className="rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-700">
                                  {errors.length}
                                </span>
                              </div>

                              <ChevronDown className="h-4 w-4 transition-transform duration-200 group1-data-panel-open:rotate-180" />
                          </CollapsibleTrigger>

                          <CollapsibleContent>
                            <CardContent className="border-t pt-4">
                              <ul className="space-y-2">
                                {errors.map((error, index) => (
                                  <li
                                    key={`${sheetName}-${error.location}-${index}`}
                                    className="rounded-md bg-red-50 p-3"
                                  >
                                    <div className="flex gap-3">
                                      <div>
                                        {error.location && (
                                          <div className="font-mono text-sm font-semibold text-red-700">
                                            {error.location}
                                          </div>
                                        )}

                                        <div className="text-sm text-red-900">
                                          {error.message}
                                        </div>
                                      </div>
                                    </div>
                                  </li>
                                ))}
                              </ul>
                            </CardContent>
                          </CollapsibleContent>
                        </Card>
                      </Collapsible>
                    )
                  )}
                  </CardContent>
                }
              </Card>
            )}
            <Dropzone ref={dropzoneRef} onDropAccepted={(files) => setAttachedFile(files[0])} disabled={restrictFormChange} />
            <div className="flex flex-col sm:flex-row sm:justify-end gap-2 mt-4">
              <Button variant="destructive" onClick={resetAttachmentState}  disabled={restrictFormChange}>
                Reset Form
              </Button>
              <Button type="submit" disabled={restrictFormChange}>
                <FileUp className="w-5 h-5" />
                Unggah File
              </Button>
            </div>
          </CardContent>
        </Card>
      </form>
    </div>
  </>);
}

export default Upload;
