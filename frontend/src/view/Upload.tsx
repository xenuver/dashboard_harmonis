import { useState as reactUseState, useRef as reactUseRef } from "react";
import { api } from "../services/api";
import { useNavigate } from "react-router-dom";
import { isAxiosError } from "axios";

import type { SubmitEvent as ReactSubmitEvent } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { Dropzone, type ExcelDropzoneRef } from "../components/upload/ExcelDropZone";
import { CircleCheck, CircleX } from "lucide-react";

const UPLOAD_MESSAGE_NOTHING = 0;
const UPLOAD_MESSAGE_SUCCESS = 1;
const UPLOAD_MESSAGE_FAIL = 2;

const months = [
  "Januari", "Februari", "Maret", "April", "Mei", "Juni",
  "Juli", "Agustus", "September", "Oktober", "November", "Desember"
];

export function Upload() {
  const navigate = useNavigate();

  const yearNow = new Date().getFullYear();
  const monthNow = new Date().getMonth() + 1;

  const [uploadMessageText, setUploadMessageText] = reactUseState<string>("");
  const [uploadMessageState, setUploadMessageState] = reactUseState<number>(0);
  const [pickedMonth, setPickedMonth] = reactUseState<number>(monthNow);
  const [attachedFile, setAttachedFile] = reactUseState<File | null>(null);

  const dropzoneRef = reactUseRef<ExcelDropzoneRef | null>(null);

  const yearOptions: number[] = [];

  for (let i = 0; i < 100; i++) {
    yearOptions.push(yearNow - i);
  }

  const clearAttachedFile = function() {
    setAttachedFile(null); // Clears the file from parent state!
    dropzoneRef.current?.clear();
  }

  const resetAttachmentState = function() {
    clearAttachedFile();
    setUploadMessageState(UPLOAD_MESSAGE_NOTHING);
    setUploadMessageText("");
  }

  async function handleFormSubmit(event: ReactSubmitEvent) {
    event.preventDefault();
    setUploadMessageText("");
    setUploadMessageState(UPLOAD_MESSAGE_NOTHING);

    const uploadInformation = new FormData(event.target);

    const jenisLaporan = uploadInformation.get("jenis_laporan");
    const periodeBulan = uploadInformation.get("periode_bulan");
    const periodeTahun = uploadInformation.get("periode_tahun");
    const fileLaporan = attachedFile;

    if(typeof jenisLaporan !== "string") {
      setUploadMessageState(UPLOAD_MESSAGE_FAIL);
      setUploadMessageText("jenis laporan yang dimasukkan harus merupakan teks");
      return;
    }

    if(typeof periodeBulan === "string") {
      try {
        parseInt(periodeBulan);
      } catch(e) {
        setUploadMessageState(UPLOAD_MESSAGE_FAIL);
        setUploadMessageText("periode bulan yang dimasukkan harus merupakan angka");
        console.warn(e);
        return;
      }
    } else {
      setUploadMessageState(UPLOAD_MESSAGE_FAIL);
      setUploadMessageText("periode bulan yang dimasukkan harus merupakan angka");
      return;
    }

    if(typeof periodeTahun === "string") {
      try {
        parseInt(periodeTahun);
      } catch(e) {
        setUploadMessageState(UPLOAD_MESSAGE_FAIL);
        setUploadMessageText("periode tahun yang dimasukkan harus merupakan angka");
        console.warn(e);
        return;
      }
    } else {
      setUploadMessageState(UPLOAD_MESSAGE_FAIL);
      setUploadMessageText("periode tahun yang dimasukkan harus merupakan angka");
      return;
    }

    if(fileLaporan instanceof File) {
      if(!fileLaporan.name.endsWith(".xlsx") && fileLaporan.name !== "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet")  {
        setUploadMessageState(UPLOAD_MESSAGE_FAIL);
        setUploadMessageText("file laporan harus dalam format xslx");
        return;
      }
    } else {
      setUploadMessageState(UPLOAD_MESSAGE_FAIL);
      setUploadMessageText("File laporan harus berbentuk file .xlsx");
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
          setUploadMessageText("data sudah ada");
        } else if (e.code === "ERR_NETWORK") {
          console.error(e);
          console.warn("Terjadi kesalahan dalam mendapatkan respon dari server");
          setUploadMessageState(UPLOAD_MESSAGE_FAIL);
          setUploadMessageText("Terjadi kesalahan dalam mendapatkan respon dari server");
        } else if (e.code === "ERR_BAD_RESPONSE") {
          setUploadMessageState(UPLOAD_MESSAGE_FAIL);
          setUploadMessageText("Server tidak merespon dengan format data yang tepat");
        } else if (e.code === "ECONNABORTED" || e.code === "ERR_CANCELED") {
          setUploadMessageState(UPLOAD_MESSAGE_FAIL);
          setUploadMessageText("Permintaan dibatalkan atau waktu habis");
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
    }
  }

  return (<>
    <h1 className="scroll-m-20 text-2xl font-semibold tracking-tight lg:text-3xl m-5">Upload Data</h1>
    <div>
      <form onSubmit={handleFormSubmit}>
        <Card className="m-5">
          <CardHeader>
            <CardTitle>Informasi Laporan</CardTitle>
          </CardHeader>
          <CardContent>
            <FieldGroup className="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-3 gap-4">
              <Field>
                <FieldLabel>Jenis Laporan:</FieldLabel>
                <Select required={true} name="jenis_laporan" defaultValue="ampera">
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
                <FieldLabel>Bulan Laporan:</FieldLabel>
                <Select 
                    required={true} 
                    name="periode_bulan" 
                    value={pickedMonth} 
                    onValueChange={(value) => value && setPickedMonth(value)}
                  >
                    <SelectTrigger className="w-45">
                      <SelectValue placeholder="Pilih Bulan Laporan">
                        {pickedMonth ? months[parseInt(months[monthNow]) - 1] : undefined}
                      </SelectValue>
                    </SelectTrigger>
                    <SelectContent>
                      {months.map((value, index) => (
                        <SelectItem value={(index + 1).toString()} key={index}>
                          {value}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
              </Field>
              <Field>
                <FieldLabel>Tahun Laporan:</FieldLabel>
                <Select required={true} name="periode_tahun" defaultValue={yearNow}>
                  <SelectTrigger className="w-45">
                    <SelectValue placeholder="Pilih Tahun Laporan" />
                  </SelectTrigger>
                  <SelectContent>
                    {
                      yearOptions.map((value, index) => (
                        <SelectItem tabIndex={index + 1} value={value} key={index}>{value}</SelectItem>
                      ))
                    }
                  </SelectContent>
                </Select>
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
              <Card className="bg-green-300 mb-5">
                <CardContent>
                  <div className="flex items-center gap-2">
                    <CircleCheck className="shrink-0 stroke-green-700" />
                    <span>{uploadMessageText}</span>
                  </div>
                </CardContent>
              </Card>
            }
            { uploadMessageState === UPLOAD_MESSAGE_FAIL &&
              <Card className="bg-red-300 mb-5">
                <CardContent>
                  <div className="flex items-center gap-2">
                    <CircleX className="shrink-0 stroke-red-700" />
                    <span>{uploadMessageText}</span>
                  </div>
                </CardContent>
              </Card>
            }
            <Dropzone ref={dropzoneRef} onDropAccepted={(files) => setAttachedFile(files[0])} />
            <div className="flex flex-col sm:flex-row sm:justify-end gap-2 mt-4">
              <Button variant="outline" onClick={resetAttachmentState}>
                Reset File
              </Button>
              <Button type="submit">
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
