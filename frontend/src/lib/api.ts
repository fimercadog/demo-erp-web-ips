import axios from "axios";
import { AUTH_EXPIRED_EVENT, clearAuthSession } from "@/lib/auth";

const API_BASE = process.env.NEXT_PUBLIC_API_URL ?? "/api";

export const api = axios.create({
  baseURL: API_BASE,
  withCredentials: true,
  headers: {
    Accept: "application/json",
    "ngrok-skip-browser-warning": "true",
  },
});

// Cookie httpOnly de sesion (Sanctum SPA) en vez de bearer token: el login
// necesita la cookie CSRF puesta antes de mandar el POST.
export function primeCsrfCookie() {
  const sanctumOrigin = API_BASE.replace(/\/api\/?$/, "");
  return axios.get(`${sanctumOrigin}/sanctum/csrf-cookie`, { withCredentials: true });
}

export async function fetchApi<T = any>(endpoint: string, options?: { method?: string; body?: any }): Promise<T> {
  const method = (options?.method || "GET").toLowerCase();
  const url = endpoint.startsWith("/") ? endpoint : `/${endpoint}`;
  let data = options?.body;
  if (typeof data === "string") {
    try {
      data = JSON.parse(data);
    } catch {
      // keep string
    }
  }
  const response = await api.request<T>({
    url,
    method,
    data,
  });
  return response.data;
}

function readXsrfCookie(): string | null {
  const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
  return match ? decodeURIComponent(match[1]) : null;
}

api.interceptors.request.use((config) => {
  const xsrfToken = readXsrfCookie();
  if (xsrfToken) {
    config.headers["X-XSRF-TOKEN"] = xsrfToken;
  }

  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (typeof window !== "undefined" && error.response?.status === 401) {
      // Token invalido/expirado: se limpia ya para no repetir 401 en cada carga.
      clearAuthSession();
      window.dispatchEvent(new Event(AUTH_EXPIRED_EVENT));
    }

    return Promise.reject(error);
  },
);

export type PaginatedResponse<T> = {
  data: T[];
  links: Record<string, string | null>;
  meta: {
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
  };
};
