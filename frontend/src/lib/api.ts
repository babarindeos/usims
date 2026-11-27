// Thin client for the USIMS /api/v1 endpoints. Uses Sanctum session cookies plus the XSRF token.

export type Problem = {
  title: string
  status: number
  detail?: string
  errors?: Record<string, string[]>
  matches?: unknown[]
}

export class ApiError extends Error {
  problem: Problem
  constructor(problem: Problem) {
    super(problem.detail || problem.title)
    this.problem = problem
  }
  fieldError(field: string): string | undefined {
    return this.problem.errors?.[field]?.[0]
  }
}

let csrfReady = false

function xsrfToken(): string | undefined {
  const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)
  return match ? decodeURIComponent(match[1]) : undefined
}

async function ensureCsrf() {
  if (csrfReady && xsrfToken()) return
  await fetch('/sanctum/csrf-cookie', { credentials: 'include' })
  csrfReady = true
}

export async function api<T = unknown>(path: string, options: { method?: string; body?: unknown; reason?: string } = {}): Promise<T> {
  const method = options.method ?? 'GET'
  if (method !== 'GET') await ensureCsrf()

  const headers: Record<string, string> = { Accept: 'application/json' }
  if (options.body !== undefined) headers['Content-Type'] = 'application/json'
  const token = xsrfToken()
  if (token) headers['X-XSRF-TOKEN'] = token
  if (options.reason) headers['X-Audit-Reason'] = options.reason

  const res = await fetch(`/api/v1${path}`, {
    method,
    credentials: 'include',
    headers,
    body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
  })

  if (res.status === 204) return undefined as T
  const data = await res.json().catch(() => ({ title: res.statusText, status: res.status }))
  if (!res.ok) throw new ApiError(data as Problem)
  return data as T
}

/** Build a query string, skipping empty values. */
export function qs(params: Record<string, string | number | boolean | undefined | null>): string {
  const entries = Object.entries(params).filter(([, v]) => v !== undefined && v !== null && v !== '')
  return entries.length ? '?' + new URLSearchParams(entries.map(([k, v]) => [k, String(v)])).toString() : ''
}
