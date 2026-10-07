import { useState as reactUseState } from "react";
import { api } from "../services/api";
import { useNavigate } from "react-router-dom";
import { isAxiosError } from "axios";

import type { SubmitEvent as ReactSubmitEvent } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";

const months = [
  "Januari", "Februari", "Maret", "April", "Mei", "Juni",
  "Juli", "Agustus", "September", "Oktober", "November", "Desember"
];

export function Upload() {
  const navigate = useNavigate();

  const yearNow = new Date().getFullYear();
  const monthNow = new Date().getMonth() + 1;

  const [uploadMessageText, setUploadMessageText] = reactUseState<string>("");
  const [uploadSuccess, setUploadSuccess] = reactUseState<boolean>(false);
  const [pickedMonth, setPickedMonth] = reactUseState<number>(monthNow);

  const yearOptions: number[] = [];

  for (let i = 0; i < 100; i++) {
    yearOptions.push(yearNow - i);
  }

  async function handleFormSubmit(event: ReactSubmitEvent) {
    event.preventDefault();
    setUploadMessageText("");
    setUploadSuccess(false);

    const uploadInformation = new FormData(event.target);

    const jenisLaporan = uploadInformation.get("jenis_laporan");
    const periodeBulan = uploadInformation.get("periode_bulan");
    const periodeTahun = uploadInformation.get("periode_tahun");
    const fileLaporan = uploadInformation.get("file");

    if(typeof jenisLaporan !== "string") {
      setUploadMessageText("jenis laporan yang dimasukkan harus merupakan teks");
      return;
    }

    if(typeof periodeBulan === "string") {
      try {
        parseInt(periodeBulan);
      } catch(e) {
        setUploadMessageText("periode bulan yang dimasukkan harus merupakan angka");
        console.warn(e);
        return;
      }
    } else {
      setUploadMessageText("periode bulan yang dimasukkan harus merupakan angka");
      return;
    }

    if(typeof periodeTahun === "string") {
      try {
        parseInt(periodeTahun);
      } catch(e) {
        setUploadMessageText("periode tahun yang dimasukkan harus merupakan angka");
        console.warn(e);
        return;
      }
    } else {
      setUploadMessageText("periode tahun yang dimasukkan harus merupakan angka");
      return;
    }

    if(fileLaporan instanceof File) {
      if(!fileLaporan.name.endsWith(".xlsx") && fileLaporan.name !== "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet")  {
        setUploadMessageText("file laporan harus dalam format xslx");
        return;
      }
    } else {
      setUploadMessageText("file laporan harus berbentuk file");
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
        setUploadSuccess(true);
      }
    } catch(e) {
      if(isAxiosError(e)) {
        const status = e.status || e.response?.status;

        if (status === 401) {
          setUploadMessageText("username atau password salah");
        } else if(status === 409) {
          setUploadMessageText("data sudah ada");
        } else if (e.code === "ERR_NETWORK") {
          console.error(e);
          console.warn("Terjadi kesalahan dalam mendapatkan respon dari server");
          setUploadMessageText("Terjadi kesalahan dalam mendapatkan respon dari server");
        } else if (e.code === "ERR_BAD_RESPONSE") {
          setUploadMessageText("Server tidak merespon dengan format data yang tepat");
        } else if (e.code === "ECONNABORTED" || e.code === "ERR_CANCELED") {
          setUploadMessageText("Permintaan dibatalkan atau waktu habis");
        } else {
          console.error(e);
          console.warn("Terjadi kesalahan dalam membuat permintaan upload");
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
            <FieldGroup>
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
          <CardContent>
            <p>Tekan tombol di bawah untuk mulai memilih file</p>
            <input type="file" name="file" accept=".xlsx, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required={true} />
            <div id="upload-message">{uploadMessageText}</div>
            { uploadSuccess &&
              <>
                <div>Upload berhasil. Tekan tombol berikut untuk menuju halaman dasboard sekarang:</div>
                <button onClick={() => navigate("/dashboard")}>Menuju halaman dashboard</button>
              </>
            }
            <button type="submit">Unggah File</button>
          </CardContent>
        </Card>
      </form>
    </div>
  </>);
}

export default Upload;
