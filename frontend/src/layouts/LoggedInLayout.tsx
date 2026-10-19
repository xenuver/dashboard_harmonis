import { SidebarProvider, SidebarInset } from "@/components/ui/sidebar";
import { AppSidebar } from "../components/AppSidebar";
import { TopbarLogin } from "../components/TopbarLogin";
import { Outlet, useLocation } from "react-router-dom";
import { useEffect } from "react";
import sessionManager from "../services/sessionManager";

export function LoggedInLayout() {
  useEffect(function() {
    sessionManager.updateIdentity();
  });

  const location = useLocation();

  const pageTitles: Record<string, string> = {
    "/dashboard": "Dashboard",
    "/upload": "Upload Laporan",
    "/produk-penjualan": "Peringkat Produk",
    "/supplier-penjualan": "Supplier Teratas",
    "/kelola-pengguna": "Kelola Pengguna",
  };

  return (
    <SidebarProvider>
      <AppSidebar />
      <SidebarInset className="flex min-h-screen flex-col">
        <TopbarLogin titleBar={pageTitles[location.pathname] || "Dashboard"} />
        <main className="flex-1">
          <Outlet />
        </main>
      </SidebarInset>
    </SidebarProvider>
  );
}