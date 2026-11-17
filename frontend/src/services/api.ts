// api.ts
import { create as axiosCreate, AxiosError } from "axios";
import sessionManager from "./sessionManager";

const backendProtocol = import.meta.env.VITE_BACKEND_PROTOCOL === "current"? 
    location.protocol : import.meta.env.VITE_BACKEND_PROTOCOL || location.protocol;

const backendHostname = import.meta.env.VITE_BACKEND_HOSTNAME === "current"? 
    location.hostname : import.meta.env.VITE_BACKEND_HOSTNAME || location.hostname;

const backendPort = import.meta.env.VITE_BACKEND_PORT === "current"?
    location.port : import.meta.env.VITE_BACKEND_PORT === "default"?
    "" : import.meta.env.VITE_BACKEND_PORT || "";

export const api = axiosCreate({
  baseURL: `${backendProtocol}//${backendHostname}${backendPort ? `:${backendPort}` : ""}`,
});

api.interceptors.request.use((config) => {
  const token = sessionManager.getToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

api.interceptors.request.use((config) => {
  const accept = config.headers.get('Accept');

  const expectsJson =
      config.responseType === 'json' || (typeof accept === 'string' && accept.includes('application/json'));

  if (expectsJson) {
    config.responseType = 'json';

    config.transitional = {
      ...config.transitional,
      silentJSONParsing: false,
    };
  }

  return config;
});

api.interceptors.response.use((done)=> Promise.resolve(done), function(error: AxiosError) {
  if((error.status === 401 || error.response?.status === 401) && location.pathname !== "/login") {
    sessionManager.deleteTokens();
    const lastLocation = `${location.pathname}${location.search}`;
    let nextUrl = "/login?reason=invalid_session";

    if(lastLocation) {
      nextUrl = `${nextUrl}&next=${encodeURIComponent(lastLocation)}`;
    }

    window.location.href = nextUrl;
  }

  return Promise.reject(error);
});
