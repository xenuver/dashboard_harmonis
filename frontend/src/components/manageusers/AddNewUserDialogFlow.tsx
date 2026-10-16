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
import { Field, FieldGroup } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { NativeSelect, NativeSelectOption } from "@/components/ui/native-select"
import { Checkbox } from "@/components/ui/checkbox"
import { Plus } from "lucide-react";
import { useState, type SubmitEvent as ReactSubmitEvent } from "react"
import { api } from "../../services/api"
import { isAxiosError } from "axios";

const userRoles = [
  {
    label: "Owner",
    value: "owner"
  },
  {
    label: "Staf IT",
    value: "it_staff",
  }
];

type AddNewUserPopupProps = {
  retryTrigger?: () => void;
  buttonClassName?: string;
}

export function AddNewUserDialogFlow({ retryTrigger, buttonClassName }: AddNewUserPopupProps) {
  const [dialogIsOpen, setDialogIsOpen] = useState<boolean>(false);
  const [fetchMessageText, setFetchMessageText] = useState<string | undefined>(undefined);

  async function formSubmitCallback(event: ReactSubmitEvent) {
    event.preventDefault();

    setFetchMessageText(undefined);

    const form = new FormData(event.target);
    // get the field that you want
    const username = form.get("username");
    const password = form.get("password");
    const role = form.get("role");
    const enabled = form.get("useractive");

    if(typeof username !== "string" || (typeof username === "string" && username.trim().length < 1)) {
      setFetchMessageText("Username tidak boleh kosong");
      return;
    }

    if(typeof password !== "string" || (typeof password === "string" && password.length < 1)) {
      setFetchMessageText("Password tidak boleh kosong");
      return;
    }

    if(typeof role !== "string" || (typeof role === "string" && role.trim().length < 1)) {
      setFetchMessageText("Role tidak boleh kosong");
      return;
    }

    try {
      const newUserRequest = await api.postForm(`/api/add-user`, {
        username: username.trim(),
        password: password,
        role: role,
        enabled: enabled? 1 : 0,
      }, {
        responseType: "json",
      });

      if(newUserRequest.status === 200) {
        try {
          setDialogIsOpen(false);
          if(retryTrigger) {
            retryTrigger();
          }
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
        <DialogTrigger render={<Button title="Tambah Pengguna Baru" className={buttonClassName}><Plus />Tambahkan Pengguna Baru</Button>} />
        <DialogContent className="sm:max-w-sm">
          <form onSubmit={formSubmitCallback}>
            <DialogHeader>
              <DialogTitle>Tambahkan Pengguna Baru</DialogTitle>
              <DialogDescription>
                Anda akan menambahkan pengguna baru dengan informasi di bawah.
                Tekan simpan untuk menambahkan
              </DialogDescription>
            </DialogHeader>
            <FieldGroup>
              <Field>
                <Label htmlFor="username">Username</Label>
                <Input id="username" name="username" type="text" required={true} />
              </Field>
              <Field>
                <Label htmlFor="password">Password</Label>
                <Input id="password" name="password" type="text" required={true} />
              </Field>
              <Field>
                <Label htmlFor="role">Role</Label>
                <NativeSelect id="role" name="role" required={true}>
                  {
                    userRoles.map((value, index) => (
                      <NativeSelectOption tabIndex={index + 1} value={value.value} key={index}>{value.label}</NativeSelectOption>
                    ))
                  }
                </NativeSelect>
              </Field>
              <Field>
                <div className="flex gap-2">
                  <Checkbox id="user-active" name="useractive" defaultChecked={true} value="true" />
                  <Label htmlFor="user-active">Pengguna Aktif</Label>
                </div>
              </Field>
            </FieldGroup>
            <FieldGroup>
              <div className="text-red-600 mb-1 mt-1">
                {fetchMessageText}
              </div>
            </FieldGroup>
            <DialogFooter>
              <DialogClose render={<Button variant="outline">Batalkan</Button>} />
              <Button type="submit">Tambahkan Pengguna</Button>
            </DialogFooter>
          </form>
        </DialogContent>
    </Dialog>
  )
}
