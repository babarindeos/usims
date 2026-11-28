import { useQuery } from '@tanstack/react-query'
import { api } from '../../lib/api'
import type { AdmissionConfigRow, Exercise } from '../../lib/types'

export type ConfigKind = 'categories' | 'exam-bodies' | 'subjects' | 'document-types' | 'clearance-units' | 'matric-formats'

export function useAdmissionConfig(kind: ConfigKind, includeInactive = false) {
  return useQuery({
    queryKey: ['admission-config', kind, includeInactive],
    queryFn: () => api<{ data: AdmissionConfigRow[] }>(`/admission-config/${kind}${includeInactive ? '?include_inactive=1' : ''}`).then(r => r.data),
    staleTime: 60_000,
  })
}

export function useExercises() {
  return useQuery({ queryKey: ['admission-exercises'], queryFn: () => api<{ data: Exercise[] }>('/admission-exercises').then(r => r.data) })
}

export const naira = (n: number) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', maximumFractionDigits: 2 }).format(n)

export const RULE_LABELS: Record<string, string> = {
  min_utme_score: 'Minimum UTME score',
  required_utme_subjects: 'Required UTME subjects',
  required_credit_subjects: "O'Level credit subjects",
  min_credit_passes: 'Minimum credit passes',
  max_sittings: 'Maximum sittings',
  require_verified_documents: 'Documents must be verified',
  manual_review: 'Manual review',
}

export const OUTCOME_LABELS: Record<string, string> = {
  eligible: 'Eligible', ineligible: 'Not eligible', manual_review: 'Manual review', pending_verification: 'Awaiting verification',
}
