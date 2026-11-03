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
import { type ReactElement } from "react"

type CatatanPopupDialogProps = {
  catatanText: string;
  triggerRender: ReactElement;
}

export function CatatanPopupDialogFlow({ catatanText, triggerRender }: CatatanPopupDialogProps) {
  return (
    <Dialog>
        <DialogTrigger nativeButton={false} render={triggerRender} />
        <DialogContent className="sm:max-w-sm">
            <DialogHeader>
              <DialogTitle>Catatan Laporan</DialogTitle>
              <DialogDescription>{catatanText || "-"}</DialogDescription>
            </DialogHeader>
            <DialogFooter>
              <DialogClose render={<Button variant="outline">Tutup</Button>} />
            </DialogFooter>
        </DialogContent>
    </Dialog>
  )
}
