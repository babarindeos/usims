import { useState, type FormEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useAuth } from '../../app/auth'
import { Button, Card, ErrorNotice, PageHeader, Spinner, StatusBadge, TextField, formatDate } from '../../design-system'
import { api, ApiError } from '../../lib/api'
import type { AcademicSession } from '../../lib/types'
import { useMutate } from '../../lib/useMutate'

const PROCESSES = ['application', 'admission', 'registration', 'course_registration', 'examination', 'result_processing']

export function SessionsPage() {
  const { can } = useAuth()
  const manage = can('sessions.manage')
  const sessions = useQuery({ queryKey: ['sessions'], queryFn: () => api<{ data: AcademicSession[] }>('/academic-sessions').then(r => r.data) })
  const create = useMutate<Record<string, string>>('POST', '/academic-sessions', [['sessions']])
  const [form, setForm] = useState({ name: '', starts_on: '', ends_on: '' })
  const err = create.error instanceof ApiError ? create.error : null

  return (
    <>
      <PageHeader title="Sessions and semesters" description="Each process opens and closes on its own. Closing a session locks it." />
      {manage && (
        <Card title="New session" className="mb-6">
          <form onSubmit={(e: FormEvent) => { e.preventDefault(); create.mutate(form) }} className="grid gap-3 sm:grid-cols-4 sm:items-end">
            <TextField label="Name" placeholder="2026/2027" value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} error={err?.fieldError('name')} required />
            <TextField label="Starts" type="date" value={form.starts_on} onChange={e => setForm({ ...form, starts_on: e.target.value })} error={err?.fieldError('starts_on')} required />
            <TextField label="Ends" type="date" value={form.ends_on} onChange={e => setForm({ ...form, ends_on: e.target.value })} error={err?.fieldError('ends_on')} required />
            <Button type="submit" busy={create.isPending}>Create session</Button>
          </form>
        </Card>
      )}
      {sessions.isLoading ? <Spinner /> : (
        <div className="space-y-6">
          {sessions.data?.map(s => <SessionCard key={s.id} session={s} manage={manage} />)}
          {!sessions.data?.length && <p className="text-sm text-muted">No sessions yet.</p>}
        </div>
      )}
    </>
  )
}

function SessionCard({ session, manage }: { session: AcademicSession; manage: boolean }) {
  const editable = manage && !session.closed_at
  const update = useMutate<{ processes: Record<string, string> }>('PATCH', `/academic-sessions/${session.id}`, [['sessions']])
  const current = useMutate('POST', `/academic-sessions/${session.id}/make-current`, [['sessions']])
  const close = useMutate<{ reason: string }>('POST', `/academic-sessions/${session.id}/close`, [['sessions']])
  const addSemester = useMutate<Record<string, string | number>>('POST', `/academic-sessions/${session.id}/semesters`, [['sessions']])
  const [sem, setSem] = useState({ name: '', code: '', sequence: session.semesters.length + 1, starts_on: '', ends_on: '' })
  const semErr = addSemester.error instanceof ApiError ? addSemester.error : null

  return (
    <Card
      title={`${session.name}${session.is_current ? ' (current)' : ''}`}
      actions={editable && (
        <div className="flex gap-2">
          {!session.is_current && <Button variant="secondary" busy={current.isPending} onClick={() => current.mutate(undefined)}>Make current</Button>}
          <Button variant="danger" busy={close.isPending} onClick={() => {
            const reason = window.prompt('Reason for closing this session')
            if (reason) close.mutate({ reason })
          }}>Close session</Button>
        </div>
      )}
    >
      <p className="mb-3 text-sm text-muted">{formatDate(session.starts_on)} to {formatDate(session.ends_on)}{session.closed_at && ` · closed ${formatDate(session.closed_at)}`}</p>
      <ErrorNotice error={update.error || current.error || close.error} />
      <div className="grid gap-3 sm:grid-cols-3">
        {PROCESSES.map(p => (
          <div key={p} className="flex items-center justify-between rounded-md border border-line px-3 py-2 text-sm">
            <span className="capitalize">{p.replace(/_/g, ' ')}</span>
            {editable ? (
              <select aria-label={`${p} status`} value={session.processes[p]} className="rounded border border-line bg-surface px-1 py-0.5 text-xs"
                onChange={e => update.mutate({ processes: { [p]: e.target.value } })}>
                <option value="closed">Closed</option><option value="open">Open</option><option value="suspended">Suspended</option>
              </select>
            ) : <StatusBadge status={session.processes[p]} />}
          </div>
        ))}
      </div>

      <h3 className="mt-5 mb-2 text-sm font-semibold">Semesters</h3>
      {session.semesters.length ? (
        <ul className="divide-y divide-line text-sm">
          {session.semesters.map(s => (
            <li key={s.id} className="flex flex-wrap justify-between gap-2 py-2">
              <span className="font-medium">{s.name} <span className="text-muted">({s.code})</span></span>
              <span className="text-muted">{formatDate(s.starts_on)} to {formatDate(s.ends_on)} · registration {formatDate(s.registration_opens_on)} to {formatDate(s.registration_closes_on)}</span>
            </li>
          ))}
        </ul>
      ) : <p className="text-sm text-muted">No semesters yet.</p>}

      {editable && (
        <form className="mt-3 grid gap-2 sm:grid-cols-6 sm:items-end" onSubmit={e => { e.preventDefault(); addSemester.mutate(sem) }}>
          <TextField label="Semester name" value={sem.name} onChange={e => setSem({ ...sem, name: e.target.value })} required className="sm:col-span-2" error={semErr?.fieldError('name')} />
          <TextField label="Code" value={sem.code} onChange={e => setSem({ ...sem, code: e.target.value.toUpperCase() })} required error={semErr?.fieldError('code')} />
          <TextField label="Starts" type="date" value={sem.starts_on} onChange={e => setSem({ ...sem, starts_on: e.target.value })} required error={semErr?.fieldError('starts_on')} />
          <TextField label="Ends" type="date" value={sem.ends_on} onChange={e => setSem({ ...sem, ends_on: e.target.value })} required error={semErr?.fieldError('ends_on')} />
          <Button type="submit" variant="secondary" busy={addSemester.isPending}>Add semester</Button>
        </form>
      )}
    </Card>
  )
}

