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
import { SquarePen } from "lucide-react";
import { useState, type SubmitEvent as ReactSubmitEvent } from "react"
import { api } from "../../services/api"
import { isAxiosError } from "axios";
import type { UserInformation } from "../../../types/UserInformation"
import type LaravelValidationError from "../../../types/LaravelValidationError";

import lodashIsEmpty from "lodash/isEmpty";

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
  currentUserInfo: UserInformation;
  buttonClassName?: string;
}

export function EditUserDialogFlow({ retryTrigger, currentUserInfo, buttonClassName }: AddNewUserPopupProps) {
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

    const requestBody = {};

    if(typeof username === "string" && username.trim() !== currentUserInfo.username) {
        Object.defineProperty(requestBody, "username", {value: username.trim(), enumerable: true});
    }

    if(role !== currentUserInfo.role) {
        Object.defineProperty(requestBody, "role", {value: role, enumerable: true});
    }

    if((enabled? true : false) !== currentUserInfo.enabled) {
        Object.defineProperty(requestBody, "enabled", {value: (enabled? 1 : 0), enumerable: true});
    }

    if(password) {
        Object.defineProperty(requestBody, "password", {value: password, enumerable: true});
    }

    if(lodashIsEmpty(requestBody)) {
        setDialogIsOpen(false);
        return;
    }

    Object.defineProperty(requestBody, "userid", {value: currentUserInfo.id, enumerable: true});

    try {
      const newUserRequest = await api.postForm(`/api/update-user`, requestBody, {
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
          setFetchMessageText("Respon login berhasil memiliki format yang tidak tepat");
        }
      }
    } catch(e) {
      if(isAxiosError(e)) {
        const status = e.status || e.response?.status;

        if (status === 422) {
          const message = e.response?.data as LaravelValidationError;
          if(message.errors?.username[0]) {
            setFetchMessageText(message.errors.username[0]);
          } else if(message.errors?.password[0]) {
            setFetchMessageText(message.errors.password[0]);
          } else if(message.errors?.role[0]) {
            setFetchMessageText(message.errors.role[0]);
          } else {
            setFetchMessageText(message.message);
          }
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
        <DialogTrigger render={<Button title={`Ubah Pengguna ${currentUserInfo.username? currentUserInfo.username : "ini"}`} className={buttonClassName}><SquarePen /></Button>} />
        <DialogContent className="sm:max-w-sm">
          <form onSubmit={formSubmitCallback} noValidate>
            <DialogHeader>
              <DialogTitle>Ubah Informasi Pengguna</DialogTitle>
              <DialogDescription>
                Anda akan pengguna dengan informasi di bawah.
                Tekan simpan untuk memperbarui data pengguna.
              </DialogDescription>
            </DialogHeader>
            <FieldGroup>
              <Field>
                <Label htmlFor="username">Username</Label>
                <Input id="username" name="username" type="text" defaultValue={currentUserInfo.username} required={true} />
              </Field>
              <Field>
                {/* Khusus password, kosongkan untuk tidak ubah */}
                <Label htmlFor="password">Password</Label>
                <Input id="password" name="password" type="text" placeholder="Kosong berarti tidak akan dirubah" />
              </Field>
              <Field>
                <Label htmlFor="role">Role</Label>
                <NativeSelect id="role" name="role" required={true} defaultValue={currentUserInfo.role}>
                  {
                    userRoles.map((value, index) => (
                      <NativeSelectOption tabIndex={index + 1} value={value.value} key={index}>{value.label}</NativeSelectOption>
                    ))
                  }
                </NativeSelect>
              </Field>
              <Field>
                <div className="flex gap-2">
                  <Checkbox id="user-active" name="useractive" defaultChecked={currentUserInfo.enabled} value="true" />
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
              <Button type="submit">Ubah Pengguna</Button>
            </DialogFooter>
          </form>
        </DialogContent>
    </Dialog>
  )
}
