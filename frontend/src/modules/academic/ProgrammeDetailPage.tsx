import { useState, type FormEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useAuth } from '../../app/auth'
import { Button, Card, DataTable, ErrorNotice, PageHeader, SelectField, Spinner, StatusBadge, TextField, formatDate } from '../../design-system'
import { api, ApiError, qs } from '../../lib/api'
import type { Curriculum, Programme } from '../../lib/types'
import { useMutate } from '../../lib/useMutate'
import { label, useSessions } from './shared'

export function ProgrammeDetailPage() {
  const { id = '' } = useParams()
  const { can } = useAuth()
  const programme = useQuery({ queryKey: ['programmes', id], queryFn: () => api<{ data: Programme }>(`/programmes/${id}`).then(r => r.data) })
  const curricula = useQuery({ queryKey: ['curricula', { programme: id }], queryFn: () => api<{ data: Curriculum[] }>(`/curricula${qs({ programme: id })}`).then(r => r.data) })
  const status = useMutate<{ status: string }>('PATCH', `/programmes/${id}`, [['programmes']])

  if (programme.isLoading) return <Spinner />
  const p = programme.data
  if (!p) return <ErrorNotice error={programme.error} />

  return (
    <>
      <PageHeader title={`${p.code} · ${p.name}`} description={p.department?.name}
        actions={p.can_manage && (
          <SelectField label="Status" value={p.status} onChange={e => status.mutate({ status: e.target.value })}>
            {['active', 'suspended', 'phased_out'].map(s => <option key={s} value={s}>{label(s)}</option>)}
          </SelectField>
        )} />
      <ErrorNotice error={status.error} />
      <div className="grid gap-6 lg:grid-cols-3">
        <Card title="Programme details">
          <dl className="grid grid-cols-2 gap-x-3 gap-y-2 text-sm">
            <dt className="text-muted">Award</dt><dd>{p.award_type?.name}</dd>
            <dt className="text-muted">Category</dt><dd>{p.category?.name}</dd>
            <dt className="text-muted">Duration</dt><dd>{p.duration_years} years (min {p.min_duration_years}, max {p.max_duration_years})</dd>
            <dt className="text-muted">Capacity</dt><dd>{p.capacity ?? 'Not capped'}</dd>
            <dt className="text-muted">Study modes</dt><dd>{p.study_modes?.map(m => m.duration_years ? `${m.name} (${m.duration_years} yrs)` : m.name).join(', ') || '—'}</dd>
            <dt className="text-muted">Entry levels</dt><dd>{p.entry_levels?.join(', ') || '—'}</dd>
            <dt className="text-muted">Accreditation</dt><dd>{p.accreditation_status ? `${label(p.accreditation_status)} · expires ${formatDate(p.accreditation_expires_on)}` : 'Not recorded'}</dd>
            <dt className="text-muted">Status</dt><dd><StatusBadge status={p.status} /></dd>
          </dl>
        </Card>
        <Card title="Curriculum versions" className="lg:col-span-2">
          <DataTable rows={curricula.data} loading={curricula.isLoading} rowKey={c => c.id} empty="No curriculum yet." columns={[
            { key: 'v', header: 'Version', render: c => <Link className="font-medium text-brand" to={`/academic/curricula/${c.id}`}>v{c.version}</Link> },
            { key: 'title', header: 'Title', render: c => c.title },
            { key: 'session', header: 'First cohort', render: c => c.effective_session?.name },
            { key: 'units', header: 'Required units', render: c => c.required_credit_units },
            { key: 'status', header: 'Status', render: c => <StatusBadge status={c.status} /> },
          ]} />
          {can('curricula.manage') && <NewVersion programme={p} latest={curricula.data?.[0]} />}
        </Card>
      </div>
    </>
  )
}

function NewVersion({ programme, latest }: { programme: Programme; latest?: Curriculum }) {
  const navigate = useNavigate()
  const sessions = useSessions()
  const create = useMutate<Record<string, unknown>, { data: Curriculum }>('POST', '/curricula', [['curricula']])
  const [form, setForm] = useState({ effective_session_id: '', required_credit_units: '', copy: true })
  const err = create.error instanceof ApiError ? create.error : null

  const submit = (e: FormEvent) => {
    e.preventDefault()
    create.mutate({
      programme_id: programme.id,
      effective_session_id: Number(form.effective_session_id),
      based_on_id: form.copy && latest ? latest.id : undefined,
      required_credit_units: form.required_credit_units ? Number(form.required_credit_units) : undefined,
    }, { onSuccess: r => navigate(`/academic/curricula/${r.data.id}`) })
  }

  return (
    <form onSubmit={submit} className="mt-4 grid gap-3 border-t border-line pt-4 sm:grid-cols-4 sm:items-end">
      <SelectField label="First entry session" value={form.effective_session_id} onChange={e => setForm({ ...form, effective_session_id: e.target.value })} required error={err?.fieldError('effective_session_id')}>
        <option value="">Choose…</option>
        {sessions.data?.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
      </SelectField>
      <TextField label="Required credit units" type="number" min={1} value={form.required_credit_units} onChange={e => setForm({ ...form, required_credit_units: e.target.value })}
        required={!(form.copy && latest)} hint={form.copy && latest ? `Blank keeps v${latest.version}'s value` : undefined} error={err?.fieldError('required_credit_units')} />
      {latest && (
        <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.copy} onChange={e => setForm({ ...form, copy: e.target.checked })} />Copy courses from v{latest.version}</label>
      )}
      <Button type="submit" variant="secondary" busy={create.isPending}>New curriculum version</Button>
      <div className="sm:col-span-4"><ErrorNotice error={create.error && !err?.problem.errors ? create.error : null} /></div>
    </form>
  )
}
