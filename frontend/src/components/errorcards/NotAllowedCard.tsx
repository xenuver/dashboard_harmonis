import { Card, CardContent, CardDescription, CardTitle } from "@/components/ui/card";
import { Lock } from "lucide-react";

type NotAllowedCardProps = {
  className?: string;
  isAction?: boolean;
}

export function NotAllowedCard({ className, isAction }: NotAllowedCardProps) {
  return (
    <Card className={className}>
      <CardContent className="grid place-items-center pt-5 pb-5">
        <Lock className="w-10 h-10 stroke-[1.5] text-muted-foreground" />
        <CardTitle>Tidak diperbolehkan</CardTitle>
        <CardDescription className="text-center">Anda tidak diperbolehkan untuk {isAction? "melakukan tindakan ini" : "untuk melihat halaman ini"}.</CardDescription>
      </CardContent>
    </Card>
  );
}