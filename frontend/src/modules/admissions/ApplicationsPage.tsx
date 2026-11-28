import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link, useSearchParams } from 'react-router-dom'
import { useAuth } from '../../app/auth'
import { Button, Card, DataTable, ErrorNotice, PageHeader, SelectField, StatusBadge, TextField } from '../../design-system'
import { api, qs } from '../../lib/api'
import type { AdmissionListSummary, Application, Paginated } from '../../lib/types'
import { useMutate } from '../../lib/useMutate'
import { OUTCOME_LABELS, useExercises } from './shared'

const STATUSES = ['submitted', 'under_review', 'recommended', 'on_hold', 'rejected', 'offered', 'withdrawn']

/** Registry's processing workspace. Departments and faculties only see applications to their own programmes. */
export function ApplicationsPage() {
  const { can } = useAuth()
  const [params, setParams] = useSearchParams()
  const exercises = useExercises()
  const filters = {
    exercise: params.get('exercise') ?? '', status: params.get('status') ?? '', outcome: params.get('outcome') ?? '',
    min_score: params.get('min_score') ?? '', q: params.get('q') ?? '', page: params.get('page') ?? '1',
  }
  const setFilter = (k: string, v: string) => {
    const next = new URLSearchParams(params)
    if (v) next.set(k, v)
    else next.delete(k)
    if (k !== 'page') next.delete('page')
    setParams(next, { replace: true })
  }
  const rows = useQuery({ queryKey: ['admission-applications', filters], queryFn: () => api<Paginated<Application>>(`/admission-applications${qs(filters)}`) })
  const [selected, setSelected] = useState<string[]>([])
  const toggle = (id: string, on: boolean) => setSelected(on ? [...selected, id] : selected.filter(x => x !== id))
  const exportFilters = { ...filters, page: undefined }

  return (
    <>
      <PageHeader title="Applications" description="Submitted applications, ranked by UTME score. Open one to verify documents, review screening and act."
        actions={<a className="rounded-md border border-line px-3.5 py-2 text-sm font-medium hover:bg-canvas" href={`/api/v1/admission-applications/export${qs(exportFilters)}`}>Export CSV</a>} />
      <Card className="mb-4">
        <div className="grid gap-3 sm:grid-cols-5">
          <SelectField label="Exercise" value={filters.exercise} onChange={e => setFilter('exercise', e.target.value)}>
            <option value="">All exercises</option>
            {exercises.data?.map(x => <option key={x.id} value={x.id}>{x.name}</option>)}
          </SelectField>
          <SelectField label="Status" value={filters.status} onChange={e => setFilter('status', e.target.value)}>
            <option value="">Any status</option>
            {STATUSES.map(s => <option key={s} value={s}>{s.replace(/_/g, ' ')}</option>)}
          </SelectField>
          <SelectField label="Screening" value={filters.outcome} onChange={e => setFilter('outcome', e.target.value)}>
            <option value="">Any outcome</option>
            {Object.entries(OUTCOME_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
          </SelectField>
          <TextField label="Minimum UTME score" type="number" value={filters.min_score} onChange={e => setFilter('min_score', e.target.value)} />
          <TextField label="Search" placeholder="Name, application or JAMB no" value={filters.q} onChange={e => setFilter('q', e.target.value)} />
        </div>
      </Card>
      {can('admissions.lists') && selected.length > 0 && <AddToList ids={selected} exercise={filters.exercise} onDone={() => setSelected([])} />}
      <Card>
        <DataTable rows={rows.data?.data} loading={rows.isLoading} rowKey={r => r.id} empty="No applications match these filters." columns={[
          ...(can('admissions.lists') ? [{ key: 'sel', header: '', render: (r: Application) => <input type="checkbox" aria-label={`Select ${r.application_no}`} checked={selected.includes(r.id)} disabled={r.status !== 'recommended'} onChange={e => toggle(r.id, e.target.checked)} /> }] : []),
          { key: 'no', header: 'Application', render: r => <Link className="font-medium text-brand" to={`/admissions/applications/${r.id}`}>{r.application_no}</Link> },
          { key: 'name', header: 'Applicant', render: r => <>{r.applicant?.full_name}<span className="block text-xs text-muted">{r.applicant?.state_of_origin} · {r.applicant?.gender}</span></> },
          { key: 'prog', header: 'Programme', render: r => r.choice && `${r.choice.programme.code} (${r.choice.entry_level})` },
          { key: 'score', header: 'UTME', render: r => r.jamb.score ?? '—' },
          { key: 'scr', header: 'Screening', render: r => (r.screening_outcome ? <StatusBadge status={r.screening_outcome} /> : '—') },
          { key: 'st', header: 'Status', render: r => <StatusBadge status={r.status} /> },
        ]} />
        {rows.data?.meta && rows.data.meta.last_page > 1 && (
          <div className="mt-3 flex items-center justify-between text-sm">
            <span className="text-muted">{rows.data.meta.total} applications · page {rows.data.meta.current_page} of {rows.data.meta.last_page}</span>
            <div className="flex gap-2">
              <Button variant="secondary" disabled={rows.data.meta.current_page <= 1} onClick={() => setFilter('page', String(rows.data!.meta!.current_page - 1))}>Previous</Button>
              <Button variant="secondary" disabled={rows.data.meta.current_page >= rows.data.meta.last_page} onClick={() => setFilter('page', String(rows.data!.meta!.current_page + 1))}>Next</Button>
            </div>
          </div>
        )}
      </Card>
    </>
  )
}

function AddToList({ ids, exercise, onDone }: { ids: string[]; exercise: string; onDone: () => void }) {
  const lists = useQuery({ queryKey: ['admission-lists', exercise], queryFn: () => api<{ data: AdmissionListSummary[] }>(`/admission-lists${qs({ exercise })}`).then(r => r.data) })
  const [list, setList] = useState('')
  const add = useMutate<{ applications: string[] }, { added: number }>('POST', `/admission-lists/${list}/entries`, [['admission-lists'], ['admission-applications']])
  const editable = lists.data?.filter(l => l.status === 'draft' || l.status === 'returned') ?? []
  return (
    <Card className="mb-4">
      <div className="flex flex-wrap items-end gap-3">
        <p className="pb-2 text-sm">{ids.length} recommended application{ids.length > 1 ? 's' : ''} selected.</p>
        <SelectField label="Add to admission list" value={list} onChange={e => setList(e.target.value)} className="min-w-64">
          <option value="">Choose a draft list…</option>
          {editable.map(l => <option key={l.id} value={l.id}>{l.name} ({l.exercise.name})</option>)}
        </SelectField>
        <Button disabled={!list} busy={add.isPending} onClick={() => add.mutate({ applications: ids }, { onSuccess: onDone })}>Add</Button>
        {!editable.length && <Link className="pb-2 text-sm text-brand" to="/admissions/lists">Create a list first</Link>}
        <ErrorNotice error={add.error} />
      </div>
    </Card>
  )
}
