import axios, { type AxiosError, type AxiosRequestConfig } from "axios";

const configuredUrl = (process.env.NEXT_PUBLIC_API_URL ?? "http://127.0.0.1:8000").replace(/\/$/, "");
const versionedSuffix = "/api/v1";

const apiOrigin = configuredUrl.endsWith(versionedSuffix)
  ? configuredUrl.slice(0, -versionedSuffix.length)
  : configuredUrl;
const apiBaseUrl = configuredUrl.endsWith(versionedSuffix)
  ? configuredUrl
  : `${configuredUrl}${versionedSuffix}`;

const configuredMode = (process.env.NEXT_PUBLIC_DATA_MODE ?? "mock").toLowerCase();
if (configuredMode !== "mock" && configuredMode !== "api") {
  throw new Error("NEXT_PUBLIC_DATA_MODE must be either mock or api");
}
export const dataMode = configuredMode as "mock" | "api";

export class ApiClientError extends Error {
  status?: number;
  code?: string;
  requestId?: string;

  constructor(message: string, options?: { status?: number; code?: string; requestId?: string }) {
    super(message);
    this.name = "ApiClientError";
    this.status = options?.status;
    this.code = options?.code;
    this.requestId = options?.requestId;
  }
}

export const api = axios.create({
  baseURL: apiBaseUrl,
  headers: { Accept: "application/json" },
  withCredentials: true,
  withXSRFToken: true,
});

function toApiClientError(error: unknown): ApiClientError {
  if (error instanceof ApiClientError) return error;
  const axiosError = error as AxiosError<{ error?: { code?: string; message?: string }; request_id?: string }>;
  const response = axiosError.response;
  return new ApiClientError(
    response?.data?.error?.message ?? (axiosError.message || "Không thể kết nối tới máy chủ."),
    {
      status: response?.status,
      code: response?.data?.error?.code,
      requestId: response?.data?.request_id,
    },
  );
}

api.interceptors.response.use((response) => response, (error) => Promise.reject(toApiClientError(error)));

export async function initializeCsrf() {
  try {
    await axios.get(`${apiOrigin}/sanctum/csrf-cookie`, { withCredentials: true });
  } catch (error) {
    throw toApiClientError(error);
  }
}

export async function request<T>(config: AxiosRequestConfig, requiresCsrf = false) {
  if (requiresCsrf) await initializeCsrf();
  return api.request<T>(config);
}
