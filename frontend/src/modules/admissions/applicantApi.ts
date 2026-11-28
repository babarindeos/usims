import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, upload } from '../../lib/api'
import type { Application } from '../../lib/types'

const key = (id: string) => ['applicant-app', id]

export function useApplication(id: string) {
  return useQuery({ queryKey: key(id), queryFn: () => api<{ data: Application }>(`/applicant/applications/${id}`).then(r => r.data) })
}

/** Every applicant endpoint returns the whole application, so the page refreshes from the response. */
export function useApplicationAction<TBody = unknown>(id: string, method: string, path: string) {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (body: TBody) => api<{ data: Application }>(`/applicant/applications/${id}${path}`, { method, body }).then(r => r.data),
    onSuccess: data => {
      client.setQueryData(key(id), data)
      client.invalidateQueries({ queryKey: ['applicant-apps'] })
    },
  })
}

export function useUpload(id: string) {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ type, file }: { type: string; file: File }) => {
      const form = new FormData()
      form.append('document_type', type)
      form.append('file', file)
      return upload<{ data: Application }>(`/applicant/applications/${id}/documents`, form).then(r => r.data)
    },
    onSuccess: data => client.setQueryData(key(id), data),
  })
}
