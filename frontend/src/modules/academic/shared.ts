import { useQuery } from '@tanstack/react-query'
import { api } from '../../lib/api'
import type { AcademicSession, ConfigEntry, OrgUnit } from '../../lib/types'

/** Academic units (departments, faculties, colleges) a programme, course or staff record can belong to. */
export function useAcademicUnits() {
  return useQuery({
    queryKey: ['org-units', 'academic'],
    queryFn: () => api<{ data: OrgUnit[] }>('/org-units').then(r => r.data.filter(u => u.type?.is_academic)),
    staleTime: 60_000,
  })
}

export function useConfig(kind: 'award-types' | 'study-modes' | 'programme-categories') {
  return useQuery({ queryKey: ['academic-config', kind], queryFn: () => api<{ data: ConfigEntry[] }>(`/academic-config/${kind}`).then(r => r.data), staleTime: 60_000 })
}

export function useSessions() {
  return useQuery({ queryKey: ['sessions'], queryFn: () => api<{ data: AcademicSession[] }>('/academic-sessions').then(r => r.data) })
}

export const LEVELS = [100, 200, 300, 400, 500, 600, 700, 800]

export const label = (value: string) => value.replace(/_/g, ' ').replace(/^\w/, c => c.toUpperCase())

/** Asks for a reason before an action that the audit trail must explain. */
export function askReason(question: string): string | null {
  const reason = window.prompt(question)
  return reason && reason.trim() ? reason.trim() : null
}
