import { useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from './api'

/** POST/PATCH/DELETE helper that refreshes the listed queries on success. */
export function useMutate<TBody = unknown, TResult = unknown>(method: string, path: string | ((b: TBody) => string), invalidate: string[][], reason?: string) {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (body: TBody) => api<TResult>(typeof path === 'function' ? path(body) : path, { method, body, reason }),
    onSuccess: () => Promise.all(invalidate.map(key => client.invalidateQueries({ queryKey: key }))),
  })
}
