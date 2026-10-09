import { Card, CardContent, CardDescription, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { RotateCcw } from "lucide-react";

type UnknownErrorCardProps = {
  className?: string;
  retryTrigger: () => void;
}

export function UnknownErrorCard({ className, retryTrigger }: UnknownErrorCardProps) {
  return (
    <Card className={className}>
      <CardContent className="grid place-items-center pt-5 pb-5">
        <CardTitle>Kesalahan Tidak Diketahui</CardTitle>
          <CardDescription className="text-justify">Terjadi kesalahan yang tidak diketahui. Mohon coba lagi setelah beberapa saat.</CardDescription>
          <Button onClick={retryTrigger} className="mt-3"><RotateCcw />Coba Lagi</Button>
      </CardContent>
    </Card>
  );
}