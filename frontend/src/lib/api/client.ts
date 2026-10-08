import axios, { type AxiosError, type AxiosRequestConfig } from "axios";

type ApiErrorBody = {
  message?: string;
  errors?: Record<string, string[]>;
  request_id?: string;
  error?: { code?: string; message?: string };
};

const configuredUrl = (process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000").replace(/\/$/, "");
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
  fieldErrors?: Record<string, string[]>;
  requestId?: string;

  constructor(
    message: string,
    options?: {
      status?: number;
      code?: string;
      fieldErrors?: Record<string, string[]>;
      requestId?: string;
    },
  ) {
    super(message);
    this.name = "ApiClientError";
    this.status = options?.status;
    this.code = options?.code;
    this.fieldErrors = options?.fieldErrors;
    this.requestId = options?.requestId;
  }
}

export function describeApiError(error: unknown, fallback = "Không thể thực hiện thao tác.") {
  if (!(error instanceof ApiClientError)) return error instanceof Error ? error.message : fallback;
  if (error.status === 401) return "Phiên đăng nhập không hợp lệ hoặc đã hết hạn. Hãy đăng nhập lại.";
  if (error.status === 419) return "Phiên CSRF đã hết hạn. Hãy thử lại để tạo phiên bảo mật mới.";
  if (error.status === 422) return error.message || "Dữ liệu chưa hợp lệ. Hãy kiểm tra lại biểu mẫu.";
  if (error.status === 403) return `Bạn không có quyền thực hiện thao tác này. ${error.message}`;
  return error.message || fallback;
}

export const api = axios.create({
  baseURL: apiBaseUrl,
  headers: { Accept: "application/json" },
  withCredentials: true,
  withXSRFToken: true,
});

function toApiClientError(error: unknown): ApiClientError {
  if (error instanceof ApiClientError) return error;
  const axiosError = error as AxiosError<ApiErrorBody>;
  const response = axiosError.response;
  const body = response?.data;
  return new ApiClientError(body?.message ?? body?.error?.message ?? (axiosError.message || "Không thể kết nối tới máy chủ."), {
    status: response?.status,
    code: body?.error?.code,
    fieldErrors: body?.errors,
    requestId: body?.request_id,
  });
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
