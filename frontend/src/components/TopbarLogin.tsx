import { SidebarTrigger } from "@/components/ui/sidebar";
import { Button } from "@/components/ui/button";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { useNavigate } from "react-router-dom";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import sessionManager from "../services/sessionManager";
import { LogOut } from "lucide-react";

export function TopbarLogin() {
  const navigate = useNavigate();

  return (
    <header className="sticky top-0 z-10 flex h-16 shrink-0 items-center justify-between gap-2 border-b bg-background px-4">
      <div className="flex items-center gap-2">
        <SidebarTrigger />
        <div className="h-4 w-px bg-border" />
        <h1 className="text-sm font-medium">Dashboard</h1>
      </div>

      <div className="flex items-center gap-3">
        <DropdownMenu modal={false}>
          <DropdownMenuTrigger>
            <Button variant="ghost" className="relative h-8 w-8 rounded-full">
              <Avatar className="h-8 w-8">
                <AvatarFallback>{sessionManager.getIdentity()?.username.slice(0,1).toUpperCase() || "S"}</AvatarFallback>
              </Avatar>
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="w-56">
            <DropdownMenuGroup>
              <DropdownMenuLabel>Masuk sebagai {sessionManager.getIdentity()?.username || "seseorang"}</DropdownMenuLabel>
              <DropdownMenuSeparator />
              <DropdownMenuItem className="text-destructive" onClick={async () => {await sessionManager.revokeCurrentToken(); sessionManager.deleteTokens(); navigate("/login");}}><LogOut className="w-5 h-5" />Keluar</DropdownMenuItem>
            </DropdownMenuGroup>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </header>
  );
}