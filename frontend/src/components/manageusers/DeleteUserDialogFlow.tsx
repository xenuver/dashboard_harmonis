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

type ConfirmDeleteDialogProps = {
  deleteUsername?: string;
  deleteUserId: number;
  retryTrigger?: () => void;
  buttonClassName?: string;
}

export function DeleteUserDialogFlow({ retryTrigger, deleteUsername, deleteUserId, buttonClassName }: ConfirmDeleteDialogProps) {
  const [dialogIsOpen, setDialogIsOpen] = useState<boolean>(false);
  const [fetchMessageText, setFetchMessageText] = useState<string | undefined>(undefined);

  async function formSubmitCallback(event: ReactSubmitEvent) {
    event.preventDefault();

    try {
      const newUserRequest = await api.postForm(`/api/delete-user`, {
        userid: deleteUserId,
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
        if (e.code === "ERR_NETWORK") {
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
        <DialogTrigger render={<Button title={`Hapus Pengguna ${deleteUsername? deleteUsername : "ini"}`} variant="destructive" className={buttonClassName}><Trash /></Button>} />
        <DialogContent className="sm:max-w-sm">
          <form onSubmit={formSubmitCallback}>
            <DialogHeader>
              <DialogTitle>Hapus Pengguna</DialogTitle>
              <DialogDescription>
                Anda akan menghapus pengguna{deleteUsername? ` dengan nama pengguna '${deleteUsername}'. ` : ". "} 
                Setelah dihapus, pengguna ini tidak dapat diakses lagi dan jika terdapat laporan yang diunggah pengguna ini,
                pengguna ini tidak dapat dihapus lagi hingga semua pengunggah laporan dirubah ke pengguna lain.
                Apakah anda ingin melanjutkan?
              </DialogDescription>
              <div className="text-red-700">{fetchMessageText}</div>
            </DialogHeader>
            <DialogFooter>
              <DialogClose render={<Button variant="outline">Batalkan</Button>} />
              <Button type="submit" variant="destructive">Hapus Pengguna</Button>
            </DialogFooter>
          </form>
        </DialogContent>
    </Dialog>
  )
}
