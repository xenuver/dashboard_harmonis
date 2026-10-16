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
import sessionManager from "@/services/sessionManager";

const topNavigationItems = [
  { title: "Dashboard", url: "/dashboard", icon: LayoutDashboard, viewableBy: ["owner", "it_staff"] },
  { title: "Penjualan Produk", url: "/produk-penjualan", icon: ShoppingCart, viewableBy: ["owner", "it_staff"] },
  { title: "Supplier Teratas", url: "/supplier-penjualan", icon: Truck, viewableBy: ["owner", "it_staff"] },
];

const manageDataNavigationItems = [
  { title: "Upload Laporan", url: "/upload", icon: FileUp, viewableBy: ["it_staff"] },
];

export function AppSidebar() {
  const { isMobile, setOpenMobile } = useSidebar();
  const { pathname } = useLocation();
  const userRole = sessionManager.getIdentity()?.role;

  return (
    <Sidebar variant="sidebar" collapsible="icon">
      <SidebarHeader className="p-4 border-b">
        <Branding />
      </SidebarHeader>

      <SidebarContent>
        { (sessionManager.getIdentity()?.role === "owner" || sessionManager.getIdentity()?.role === "it_staff") &&
          <SidebarGroup>
            <SidebarGroupLabel>Navigasi Utama</SidebarGroupLabel>
            <SidebarGroupContent>
              <SidebarMenu>
                {topNavigationItems.map((item) => {
                  let userCanSee = false;

                  if(item.viewableBy?.length > 0) {
                    item.viewableBy?.forEach(function(value) {
                      if(value === userRole) {
                        userCanSee = true;
                      }
                    });
                  }

                  if(userCanSee) {
                    return (
                      <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton tooltip={item.title} onClick={() => isMobile && setOpenMobile(false)} isActive={pathname === item.url}>
                          <Link to={item.url} className="flex items-center gap-2 w-full h-full">
                            <item.icon className="h-4 w-4 shrink-0" />
                            <span>{item.title}</span>
                          </Link>
                        </SidebarMenuButton>
                      </SidebarMenuItem>
                    );
                  } else {
                    return null;
                  }
                })}
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>
        }
        { sessionManager.getIdentity()?.role === "it_staff" &&
          <SidebarGroup>
            <SidebarGroupLabel>Kelola Data</SidebarGroupLabel>
            <SidebarGroupContent>
              <SidebarMenu>
                {manageDataNavigationItems.map((item) => {
                  let userCanSee = false;

                  if(item.viewableBy?.length > 0) {
                    item.viewableBy?.forEach(function(value) {
                      if(value === userRole) {
                        userCanSee = true;
                      }
                    });
                  }

                  if(userCanSee) {
                    return (
                      <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton tooltip={item.title} onClick={() => isMobile && setOpenMobile(false)} isActive={pathname === item.url}>
                          <Link to={item.url} className="flex items-center gap-2 w-full h-full">
                            <item.icon className="h-4 w-4 shrink-0" />
                            <span>{item.title}</span>
                          </Link>
                        </SidebarMenuButton>
                      </SidebarMenuItem>
                    );
                  } else {
                    return null;
                  }
                })}
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>
        }
      </SidebarContent>
    </Sidebar>
  );
}