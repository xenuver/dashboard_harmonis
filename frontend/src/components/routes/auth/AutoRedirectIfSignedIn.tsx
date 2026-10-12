import { Navigate, Outlet } from "react-router-dom";

import sessionManager from "../../../services/sessionManager"


export default function AutoRedirectIfSignedIn() {
  if (sessionManager.getToken()) {
    return <Navigate to="/dashboard" />;
  }

  return <Outlet />;
}