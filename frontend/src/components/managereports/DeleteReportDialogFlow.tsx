import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog"
import { Trash } from "lucide-react";
import { useState, type SubmitEvent as ReactSubmitEvent } from "react"
import { api } from "../../services/api"
import { isAxiosError } from "axios";
import type LaravelValidationError from "../../../types/LaravelValidationError";

type ConfirmDeleteDialogProps = {
  deleteReport?: string;
  deleteReportId: number;
  retryTrigger?: () => void;
  buttonClassName?: string;
}

export function DeleteReportDialogFlow({ retryTrigger, deleteReport: deleteReport, deleteReportId: deleteReportId, buttonClassName }: ConfirmDeleteDialogProps) {
  const [dialogIsOpen, setDialogIsOpen] = useState<boolean>(false);
  const [fetchMessageText, setFetchMessageText] = useState<string | undefined>(undefined);

  async function formSubmitCallback(event: ReactSubmitEvent) {
    event.preventDefault();

    try {
      const newUserRequest = await api.postForm(`/api/delete-report`, {
        reportid: deleteReportId,
      }, {
        responseType: "json",
      });

      if(newUserRequest.status === 200) {
        try {
          if(retryTrigger) {
            retryTrigger();
          }
          setDialogIsOpen(false);
        } catch(e) {
          console.error(e);
          setFetchMessageText("Respon berhasil memiliki format yang tidak tepat");
        }
      }
    } catch(e) {
      if(isAxiosError(e)) {
        const status = e.status || e.response?.status;

        if (status === 422) {
          const message = e.response?.data as LaravelValidationError;
          setFetchMessageText(message.message);
        } else if (e.code === "ERR_NETWORK") {
          console.error(e);
          console.warn("Terjadi kesalahan dalam mendapatkan respon dari server");
          setFetchMessageText("Terjadi kesalahan dalam mendapatkan respon dari server");
        } else if (e.code === "ERR_BAD_RESPONSE") {
          setFetchMessageText("Server tidak merespon dengan format data yang tepat");
        } else if (e.code === "ECONNABORTED" || e.code === "ERR_CANCELED") {
          setFetchMessageText("Permintaan dibatalkan atau waktu habis");
        } else {
          console.error(e);
          console.warn("Terjadi kesalahan dalam membuat permintaan");
          setFetchMessageText("Terjadi kesalahan dalam membuat permintaan");
        }
      } else {
        console.log(e);
        console.warn("Terjadi kesalahan dalam membuat koneksi");
      }
    }
  }

  return (
    <Dialog open={dialogIsOpen} onOpenChange={setDialogIsOpen}>
        <DialogTrigger render={<Button title={`Hapus Pengguna ${deleteReport? deleteReport : "ini"}`} variant="destructive" className={buttonClassName}><Trash /></Button>} />
        <DialogContent className="sm:max-w-sm">
          <form onSubmit={formSubmitCallback}>
            <DialogHeader>
              <DialogTitle>Hapus Laporan</DialogTitle>
              <DialogDescription>
                Setelah laporan dihapus, laporan ini tidak akan bisa diakses lagi dalam sistem ini hingga anda
                mengunggah ulang laporan. Apakah anda ingin melanjutkan?
              </DialogDescription>
              <div className="text-red-700">{fetchMessageText}</div>
            </DialogHeader>
            <DialogFooter>
              <DialogClose render={<Button variant="outline">Batalkan</Button>} />
              <Button type="submit" variant="destructive">Hapus Laporan</Button>
            </DialogFooter>
          </form>
        </DialogContent>
    </Dialog>
  )
}
