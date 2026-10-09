import { Card, CardContent, CardDescription, CardTitle } from "@/components/ui/card";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { Link } from "react-router-dom";
import { FileUp } from "lucide-react";

type ReportUnavailableCardProps = {
  className?: string;
}

export function ReportUnavailableCard({ className }: ReportUnavailableCardProps) {
  return (
    <Card className={className}>
      <CardContent className="grid place-items-center gap-2 pt-5 pb-5">
        <CardTitle>Data belum tersedia</CardTitle>
        <CardDescription className="text-justify">Data untuk jenis laporan periode yang dimasukkan belum tersedia. Unggah laporan dengan jenis dan periode yang sesuai terlebih dahulu.</CardDescription>
        <Link to="/upload" className={cn(buttonVariants({ variant: "default" }), "mt-4")}>
          <FileUp />Menuju Halaman Upload Data
        </Link>
      </CardContent>
    </Card>
  );
}