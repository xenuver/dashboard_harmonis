import { SidebarProvider, SidebarInset } from "@/components/ui/sidebar";
import { AppSidebar } from "../components/AppSidebar";
import { TopbarLogin } from "../components/TopbarLogin";
import { Outlet } from "react-router-dom";
import { useEffect } from "react";
import sessionManager from "../services/sessionManager";

export function LoggedInLayout() {
  useEffect(function() {
    sessionManager.updateIdentity();
  });

  return (
    <SidebarProvider>
      <AppSidebar />
      <SidebarInset className="flex min-h-screen flex-col">
        <TopbarLogin />
        <main className="flex-1">
          <Outlet />
        </main>
      </SidebarInset>
    </SidebarProvider>
  );
}