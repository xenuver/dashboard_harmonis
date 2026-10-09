import { Card, CardContent, CardDescription, CardTitle } from "@/components/ui/card";
import { SearchX } from "lucide-react";

type NoDataFoundCardProps = {
  className?: string;
}

export function NoDataFoundCard({ className }: NoDataFoundCardProps) {
  return (
    <Card className={className}>
      <CardContent className="grid place-items-center pt-5 pb-5">
        <SearchX className="w-10 h-10 stroke-[1.5] text-muted-foreground" />
        <CardTitle>Tidak ada data yang cocok</CardTitle>
        <CardDescription className="text-justify">Tidak ada hasil yang cocok. Silahkan periksa jenis laporan, periode data, dan ejaan produk yang ingin dicari.</CardDescription>
      </CardContent>
    </Card>
  );
}