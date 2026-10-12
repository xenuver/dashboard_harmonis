import { Navigate, Outlet } from "react-router-dom";

import sessionManager from "../../../services/sessionManager"


export default function AutoRedirectIfSignedOut() {
  if (!sessionManager.getToken()) {
    return <Navigate to="/login" />;
  }

  return <Outlet />;
}