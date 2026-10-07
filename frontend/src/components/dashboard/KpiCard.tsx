import * as React from "react"

import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { Skeleton } from "@/components/ui/skeleton"
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "@/components/ui/tooltip"
import { cn } from "@/lib/utils"
import type { LucideIcon } from "lucide-react"

type KpiCardProps = {
  title: string
  value: React.ReactNode
  tooltip?: React.ReactNode
  icon?: LucideIcon
  isLoading?: boolean
  className?: string
}

export function KpiCard({
  title,
  value,
  tooltip,
  icon: Icon,
  isLoading = false,
  className,
}: KpiCardProps) {
  return (
    <Card className={cn("relative", className)}>
      <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
        <CardTitle className="text-sm font-medium text-muted-foreground">
          {title}
        </CardTitle>

        {Icon && (
          <Icon className="size-5 text-muted-foreground" aria-hidden="true" />
        )}
      </CardHeader>

      <CardContent>
        {isLoading ? (
          <Skeleton className="h-8 w-28" />
        ) : tooltip ? (
          <TooltipProvider>
            <Tooltip>
              <TooltipTrigger asChild>
                <div className="w-fit cursor-help text-2xl font-bold tracking-tight">
                  {value}
                </div>
              </TooltipTrigger>

              <TooltipContent>
                {tooltip}
              </TooltipContent>
            </Tooltip>
          </TooltipProvider>
        ) : (
          <div className="text-2xl font-bold tracking-tight">
            {value}
          </div>
        )}
      </CardContent>
    </Card>
  )
}

export default KpiCard;