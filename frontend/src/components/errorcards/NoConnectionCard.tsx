import { Card, CardContent, CardDescription, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { RotateCcw } from "lucide-react";

type NoConnectionCardProps = {
  className?: string;
  retryTrigger: () => void;
}

export function NoConnectionCard({ className, retryTrigger }: NoConnectionCardProps) {
  return (
    <Card className={className}>
      <CardContent className="grid place-items-center pt-5 pb-5">
        <CardTitle>Kesalahan Koneksi</CardTitle>
          <CardDescription className="text-justify">Data tidak dapat dimuat karena kesalahan koneksi. Periksa koneksi anda dan coba lagi.</CardDescription>
          <Button onClick={retryTrigger} className="mt-3"><RotateCcw />Coba Lagi</Button>
      </CardContent>
    </Card>
  );
}