import { Card, CardContent, CardDescription, CardTitle } from "@/components/ui/card";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { Link } from "react-router-dom";
import { FileUp, StickyNoteX } from "lucide-react";

type ReportUnavailableCardProps = {
  className?: string;
  shouldShowUploadCta?: boolean;
  reportUploadShortcut?: {
    jenis_laporan: string;
    periode_bulan: number;
    periode_tahun: number;
  }
}

export function ReportUnavailableCard({ className, shouldShowUploadCta = false, reportUploadShortcut }: ReportUnavailableCardProps) {
  return (
    <Card className={className}>
      <CardContent className="grid place-items-center pt-5 pb-5">
        <StickyNoteX className="w-10 h-10 stroke-[1.5] text-muted-foreground" />
        <CardTitle>Data belum tersedia</CardTitle>
        <CardDescription className="text-center">Data untuk jenis laporan periode yang dimasukkan belum tersedia. {shouldShowUploadCta && "Unggah laporan dengan jenis dan periode yang sesuai terlebih dahulu."}</CardDescription>
        { shouldShowUploadCta &&
          <Link to={{
            pathname: "/upload",
            search: reportUploadShortcut ? new URLSearchParams({
                jenis_laporan: reportUploadShortcut.jenis_laporan,
                periode_bulan: Math.floor(reportUploadShortcut.periode_bulan).toString(),
                periode_tahun: Math.floor(reportUploadShortcut.periode_tahun).toString(),
              }).toString()
            : undefined,
          }} className={cn(buttonVariants({ variant: "default" }), "mt-4")}>
            <FileUp />Menuju Halaman Upload Data
          </Link>
        }
      </CardContent>
    </Card>
  );
}