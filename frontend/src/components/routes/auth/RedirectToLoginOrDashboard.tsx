import { Navigate } from "react-router-dom";

import sessionManager from "../../../services/sessionManager"


export default function RedirectToLoginOrDashboard() {
  if (sessionManager.getToken()) {
    return <Navigate to="/dashboard" />;
  } else {
    return <Navigate to="/login" />;
  }
}