import { useEffect } from 'react'
import { useQuery } from '@tanstack/react-query'
import { api } from '../lib/api'
import type { Branding } from '../lib/types'

export function useBranding(): Branding | undefined {
  const q = useQuery({ queryKey: ['branding'], queryFn: () => api<{ data: Branding }>('/branding').then(r => r.data), staleTime: 300_000 })
  useEffect(() => {
    if (!q.data) return
    document.documentElement.style.setProperty('--brand', q.data.primary_color)
    document.documentElement.style.setProperty('--brand-accent', q.data.secondary_color)
    document.title = q.data.portal_title
  }, [q.data])
  return q.data
}
