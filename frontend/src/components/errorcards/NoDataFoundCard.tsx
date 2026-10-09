import { Card, CardContent, CardDescription, CardTitle } from "@/components/ui/card";

type NoDataFoundCardProps = {
  className?: string;
}

export function NoDataFoundCard({ className }: NoDataFoundCardProps) {
  return (
    <Card className={className}>
      <CardContent className="grid place-items-center pt-5 pb-5">
        <CardTitle>Tidak ada data yang cocok</CardTitle>
        <CardDescription className="text-justify">Tidak ada hasil yang cocok. Silahkan periksa jenis laporan, periode data, dan ejaan produk yang ingin dicari.</CardDescription>
      </CardContent>
    </Card>
  );
}