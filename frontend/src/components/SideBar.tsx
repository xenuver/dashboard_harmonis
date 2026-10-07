import { SidebarProvider, Sidebar, SidebarContent, SidebarMenu, SidebarMenuItem, SidebarMenuButton, SidebarGroup } from "@/components/ui/sidebar";
import { FileChartPie, FileUp, ShoppingCart, Truck } from "lucide-react";


function Sidebar1() {
  return (<>
    <SidebarProvider>
      <Sidebar>
        <SidebarContent>
          <SidebarGroup>
            <SidebarMenu>
                <SidebarMenuItem key={"dashboard"}>
                  <SidebarMenuButton render={<a href="/dashboard" />}>
                    <FileChartPie />
                    <span>Dashboard</span>
                  </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>

            <SidebarMenu>
                <SidebarMenuItem key={"upload"}>
                  <SidebarMenuButton render={<a href="/upload" />}>
                    <FileUp />
                    <span>Upload Data</span>
                  </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>

            <SidebarMenu>
                <SidebarMenuItem key={"produk-penjualan"}>
                  <SidebarMenuButton render={<a href="/produk-penjualan" />}>
                    <ShoppingCart />
                    <span>Penjualan Produk</span>
                  </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>

            <SidebarMenu>
                <SidebarMenuItem key={"supplier-penjualan"}>
                  <SidebarMenuButton render={<a href="/supplier-penjualan" />}>
                    <Truck />
                    <span>Data Supplier</span>
                  </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
          </SidebarGroup>
        </SidebarContent>
      </Sidebar>
    </SidebarProvider>
  </>)
}

export default Sidebar1;
