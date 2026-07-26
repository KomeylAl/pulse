const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api/v1";
const PROJECT_STORAGE_KEY = "pulse_project";

export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public errors?: Record<string, string[]>,
  ) {
    super(message);
    this.name = "ApiError";
  }
}

function getToken(): string | null {
  if (typeof window === "undefined") return null;
  return localStorage.getItem("pulse_token");
}

export function setToken(token: string | null) {
  if (typeof window === "undefined") return;
  if (token) {
    localStorage.setItem("pulse_token", token);
  } else {
    localStorage.removeItem("pulse_token");
  }
}

export function getProjectKey(): string | null {
  if (typeof window === "undefined") return null;
  return localStorage.getItem(PROJECT_STORAGE_KEY);
}

export function setProjectKey(projectKey: string | null) {
  if (typeof window === "undefined") return;
  if (projectKey) {
    localStorage.setItem(PROJECT_STORAGE_KEY, projectKey);
  } else {
    localStorage.removeItem(PROJECT_STORAGE_KEY);
  }
}

export async function apiFetch<T>(
  path: string,
  options: RequestInit = {},
): Promise<T> {
  const token = getToken();
  const headers = new Headers(options.headers);

  if (!headers.has("Content-Type") && options.body) {
    headers.set("Content-Type", "application/json");
  }

  if (token) {
    headers.set("Authorization", `Bearer ${token}`);
  }

  const projectKey = getProjectKey();
  if (projectKey && !headers.has("X-Pulse-Project")) {
    headers.set("X-Pulse-Project", projectKey);
  }

  headers.set("Accept", "application/json");

  const response = await fetch(`${API_URL}${path}`, {
    ...options,
    headers,
  });

  const contentType = response.headers.get("content-type");
  const isJson = contentType?.includes("application/json");
  const data = isJson ? await response.json() : null;

  if (!response.ok) {
    throw new ApiError(
      data?.message ?? "Request failed",
      response.status,
      data?.errors,
    );
  }

  return data as T;
}

export { API_URL };
