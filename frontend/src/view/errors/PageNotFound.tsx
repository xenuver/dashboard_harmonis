import { Link } from "react-router-dom";

import "../../styles/index.css";

import { buttonVariants } from "@/components/ui/button"

import {
  Empty,
  EmptyContent,
  EmptyHeader,
  EmptyTitle,
} from "@/components/ui/empty"

export default function PageNotFound() {
  return (
    <Empty className="flex min-h-screen w-full items-center justify-center p-4 -translate-y-20">
      <EmptyHeader>
        <div className="text-5xl font-semibold ">404</div>
        <EmptyTitle className="font-medium">Halaman Tidak Ditemukan</EmptyTitle>
      </EmptyHeader>
      <EmptyContent>
        <Link to="/" className={buttonVariants({ variant: "default" })}>Kembali ke Halaman Utama</Link>
      </EmptyContent>
    </Empty>
  )
}
