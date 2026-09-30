// api.ts
import axios from "axios";
import SessionManager from "./sessionManager";

const backendProtocol = location.protocol;
const backendHostname = location.hostname;
const backendPort = 8000;

export const api = axios.create({
  baseURL: `${backendProtocol}//${backendHostname}:${backendPort}`,
});

api.interceptors.request.use((config) => {
  const token = SessionManager.getToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  } else {
    if(config.headers.Authorization !== undefined) {
        delete config.headers.Authorization;
    }
  }
  return config;
});

// Handle automatic logout on 401 Unauthorized
// api.interceptors.response.use(
//   (response) => response,
//   (error) => {
//     if (error.response?.status === 401) {
//       SessionManager.logout();
//       window.location.href = "/login";
//     }
//     return Promise.reject(error);
//   }
// );