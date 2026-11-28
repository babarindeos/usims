import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { useAuth } from '../../app/auth'
import { Button, Card, DataTable, ErrorNotice, formatDate, PageHeader, SelectField, StatusBadge } from '../../design-system'
import { api, qs } from '../../lib/api'
import type { OfferRow } from '../../lib/types'
import { useExercises } from './shared'

type Result = { matriculated: { admission_no: string; matric_no: string; student_id: string }[]; skipped: { admission_no: string; unmet: string[] }[] }

/** Offers and their progress to matriculation. Registry matriculates candidates who have met every condition. */
export function OffersPage() {
  const { can } = useAuth()
  const client = useQueryClient()
  const exercises = useExercises()
  const [exercise, setExercise] = useState('')
  const [status, setStatus] = useState('')
  const rows = useQuery({ queryKey: ['admission-offers', exercise, status], queryFn: () => api<{ data: OfferRow[] }>(`/admission-offers${qs({ exercise, status })}`).then(r => r.data) })
  const [selected, setSelected] = useState<string[]>([])
  const matriculate = useMutation({
    mutationFn: (offers: string[]) => api<{ data: Result }>('/matriculations', { method: 'POST', body: { offers } }).then(r => r.data),
    onSuccess: () => { setSelected([]); client.invalidateQueries({ queryKey: ['admission-offers'] }); client.invalidateQueries({ queryKey: ['students'] }) },
  })
  const ready = rows.data?.filter(r => !r.matric_no && !r.unmet.length) ?? []
  const canMatric = can('students.matriculate')

  return (
    <>
      <PageHeader title="Offers and matriculation" description="A candidate is ready for a matriculation number once the offer is accepted, the acceptance fee is verified, onboarding is submitted and every clearance office has approved."
        actions={canMatric && <Button disabled={!selected.length} busy={matriculate.isPending} onClick={() => matriculate.mutate(selected)}>Matriculate {selected.length || ''} selected</Button>} />
      <Card className="mb-4">
        <div className="grid gap-3 sm:grid-cols-4">
          <SelectField label="Exercise" value={exercise} onChange={e => setExercise(e.target.value)}>
            <option value="">All exercises</option>
            {exercises.data?.map(x => <option key={x.id} value={x.id}>{x.name}</option>)}
          </SelectField>
          <SelectField label="Offer status" value={status} onChange={e => setStatus(e.target.value)}>
            <option value="">Any</option>
            {['offered', 'accepted', 'declined', 'expired', 'withdrawn'].map(s => <option key={s} value={s}>{s}</option>)}
          </SelectField>
          {canMatric && <div className="flex items-end"><Button variant="ghost" disabled={!ready.length} onClick={() => setSelected(ready.map(r => r.id))}>Select all ready ({ready.length})</Button></div>}
        </div>
      </Card>
      <ErrorNotice error={matriculate.error} />
      {matriculate.data && (
        <Card className="mb-4" title="Matriculation result">
          {matriculate.data.matriculated.map(m => <p key={m.admission_no} className="text-sm text-ok">{m.admission_no}: <Link className="font-medium" to={`/students/${m.student_id}`}>{m.matric_no}</Link></p>)}
          {matriculate.data.skipped.map(s => <p key={s.admission_no} className="text-sm text-warn">{s.admission_no} skipped: {s.unmet.join('; ')}</p>)}
        </Card>
      )}
      <Card>
        <DataTable rows={rows.data} loading={rows.isLoading} rowKey={r => r.id} empty="No offers yet." columns={[
          ...(canMatric ? [{ key: 'sel', header: '', render: (r: OfferRow) => <input type="checkbox" aria-label={`Select ${r.admission_no}`} disabled={!!r.matric_no || r.unmet.length > 0} checked={selected.includes(r.id)} onChange={e => setSelected(e.target.checked ? [...selected, r.id] : selected.filter(x => x !== r.id))} /> }] : []),
          { key: 'no', header: 'Admission no', render: r => <span className="font-mono text-xs">{r.admission_no}</span> },
          { key: 'n', header: 'Candidate', render: r => <>{r.name}<span className="block text-xs text-muted">{r.application_no}</span></> },
          { key: 'p', header: 'Programme', render: r => `${r.programme} (${r.entry_level})` },
          { key: 's', header: 'Offer', render: r => <><StatusBadge status={r.status} />{r.status === 'offered' && <span className="block text-xs text-muted">by {formatDate(r.accept_by)}</span>}</> },
          { key: 'c', header: 'Clearance', render: r => (r.clearance.length ? <ul className="text-xs">{r.clearance.map(c => <li key={c.unit}>{c.unit}: {c.status.replace(/_/g, ' ')}</li>)}</ul> : <span className="text-xs text-muted">Not started</span>) },
          { key: 'm', header: 'Matriculation', render: r => (r.matric_no ? <span className="font-medium">{r.matric_no}</span> : r.unmet.length ? <span className="text-xs text-muted">Waiting for: {r.unmet.join('; ')}</span> : <StatusBadge status="ready" />) },
        ]} />
      </Card>
    </>
  )
}
