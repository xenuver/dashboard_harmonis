import { Card, CardContent, CardDescription, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { GlobeX, RotateCcw } from "lucide-react";

type NoConnectionCardProps = {
  className?: string;
  retryTrigger: () => void;
}

export function NoConnectionCard({ className, retryTrigger }: NoConnectionCardProps) {
  return (
    <Card className={className}>
      <CardContent className="grid place-items-center pt-5 pb-5">
        <GlobeX className="w-10 h-10 stroke-[1.5] text-muted-foreground" />
        <CardTitle>Kesalahan Koneksi</CardTitle>
          <CardDescription className="text-justify">Data tidak dapat dimuat karena kesalahan koneksi. Periksa koneksi anda dan coba lagi.</CardDescription>
          <Button onClick={retryTrigger} className="mt-3"><RotateCcw />Coba Lagi</Button>
      </CardContent>
    </Card>
  );
}