// api.ts
import { create as axiosCreate, AxiosError } from "axios";
import sessionManager from "./sessionManager";

const backendProtocol = location.protocol;
const backendHostname = location.hostname;
const backendPort = 8000;

export const api = axiosCreate({
  baseURL: `${backendProtocol}//${backendHostname}:${backendPort}`,
});

api.interceptors.request.use((config) => {
  const token = sessionManager.getToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

api.interceptors.response.use((done)=> Promise.resolve(done), function(error: AxiosError) {
  if(error.status === 401) {
    if(sessionManager.getToken()) {
      sessionManager.deleteTokens();
      location.href = "/login";
    }
  }

  return Promise.reject(error);
});
