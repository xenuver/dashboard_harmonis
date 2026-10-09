import {
  Sidebar,
  SidebarContent,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  useSidebar,
} from "@/components/ui/sidebar";
import { LayoutDashboard, FileUp, ShoppingCart, Truck } from "lucide-react";
import { Link, useLocation } from "react-router-dom";
import Branding from "./NavBranding";

const navigationItems = [
  { title: "Dashboard", url: "/dashboard", icon: LayoutDashboard },
  { title: "Upload Data", url: "/upload", icon: FileUp },
  { title: "Penjualan Produk", url: "/produk-penjualan", icon: ShoppingCart },
  { title: "Supplier Teratas", url: "/supplier-penjualan", icon: Truck },
];

export function AppSidebar() {
  const { isMobile, setOpenMobile } = useSidebar();
  const { pathname } = useLocation();

  return (
    <Sidebar variant="sidebar" collapsible="icon">
      <SidebarHeader className="p-4 border-b">
        <Branding />
      </SidebarHeader>

      <SidebarContent>
        <SidebarGroup>
          <SidebarGroupLabel>Application</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              {navigationItems.map((item) => (
                <SidebarMenuItem key={item.title}>
                  <SidebarMenuButton tooltip={item.title} onClick={() => isMobile && setOpenMobile(false)} isActive={pathname === item.url}>
                    <Link to={item.url} className="flex items-center gap-2 w-full h-full">
                      <item.icon className="h-4 w-4 shrink-0" />
                      <span>{item.title}</span>
                    </Link>
                  </SidebarMenuButton>
                </SidebarMenuItem>
              ))}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </SidebarContent>
    </Sidebar>
  );
}