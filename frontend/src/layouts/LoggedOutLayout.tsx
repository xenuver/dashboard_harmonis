import { TopbarLogout } from "../components/TopbarLogout";
import { Outlet } from "react-router-dom";

export function LoggedOutLayout() {
  return (
    <>
      <TopbarLogout />
      <main className="flex-1">
        <Outlet />
      </main>
    </>
  );
}