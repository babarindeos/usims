import { useState, type FormEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useAuth } from '../../app/auth'
import { Button, Card, DataTable, ErrorNotice, PageHeader, SelectField, StatusBadge, TextField } from '../../design-system'
import { api, ApiError, qs } from '../../lib/api'
import type { Paginated, Person, StaffMember } from '../../lib/types'
import { useMutate } from '../../lib/useMutate'
import { askReason, label, useAcademicUnits } from '../academic/shared'

const CATEGORIES = ['academic', 'administrative', 'technical', 'professional', 'management', 'contract', 'temporary']
const STATUSES = ['active', 'on_leave', 'suspended', 'exited']

export function StaffPage() {
  const { can } = useAuth()
  const [filters, setFilters] = useState({ q: '', status: '', category: '' })
  const [creating, setCreating] = useState(false)
  const rows = useQuery({ queryKey: ['staff', filters], queryFn: () => api<Paginated<StaffMember>>(`/staff${qs(filters)}`) })
  const update = useMutate<{ id: string; employment_status: string; reason: string }>('PATCH', b => `/staff/${b.id}`, [['staff'], ['offerings']])

  return (
    <>
      <PageHeader title="Staff records" description="The staff list used for course allocation. The full HR module replaces manual entry in a later milestone."
        actions={can('staff.manage') && <Button onClick={() => setCreating(!creating)}>{creating ? 'Close' : 'Add staff'}</Button>} />
      {creating && <StaffForm onDone={() => setCreating(false)} />}
      <Card>
        <div className="mb-3 grid gap-3 sm:grid-cols-3">
          <TextField label="Search" placeholder="Name or staff number" value={filters.q} onChange={e => setFilters({ ...filters, q: e.target.value })} />
          <SelectField label="Category" value={filters.category} onChange={e => setFilters({ ...filters, category: e.target.value })}>
            <option value="">All</option>{CATEGORIES.map(c => <option key={c} value={c}>{label(c)}</option>)}
          </SelectField>
          <SelectField label="Status" value={filters.status} onChange={e => setFilters({ ...filters, status: e.target.value })}>
            <option value="">All</option>{STATUSES.map(c => <option key={c} value={c}>{label(c)}</option>)}
          </SelectField>
        </div>
        <ErrorNotice error={update.error} />
        <DataTable rows={rows.data?.data} loading={rows.isLoading} rowKey={r => r.id} empty="No staff records in your scope." columns={[
          { key: 'no', header: 'Staff no.', render: r => <span className="font-mono text-xs">{r.staff_no}</span> },
          { key: 'name', header: 'Name', render: r => r.person?.full_name },
          { key: 'dept', header: 'Unit', render: r => r.department?.name },
          { key: 'cat', header: 'Category', render: r => label(r.category) },
          { key: 'rank', header: 'Rank', render: r => r.rank ?? '—' },
          { key: 'status', header: 'Status', render: r => r.can_manage ? (
            <select aria-label={`Status of ${r.staff_no}`} value={r.employment_status} className="rounded border border-line bg-surface px-1 py-0.5 text-xs"
              onChange={e => { const reason = askReason(`Reason for changing ${r.person?.full_name} to ${label(e.target.value).toLowerCase()}`); if (reason) update.mutate({ id: r.id, employment_status: e.target.value, reason }) }}>
              {STATUSES.map(s => <option key={s} value={s}>{label(s)}</option>)}
            </select>
          ) : <StatusBadge status={r.employment_status} /> },
        ]} />
      </Card>
    </>
  )
}

function StaffForm({ onDone }: { onDone: () => void }) {
  const units = useAcademicUnits()
  const allUnits = useQuery({ queryKey: ['org-units'], queryFn: () => api<{ data: { id: string; name: string }[] }>('/org-units').then(r => r.data) })
  const create = useMutate<Record<string, unknown>>('POST', '/staff', [['staff']])
  const [person, setPerson] = useState({ surname: '', first_name: '', email: '', phone: '', date_of_birth: '' })
  const [form, setForm] = useState({ staff_no: '', org_unit_id: '', category: 'academic', rank: '', institutional_email: '' })
  const err = create.error instanceof ApiError ? create.error : null
  const matches = err?.problem.status === 409 ? (err.problem.matches as Person[] | undefined) : undefined
  const clean = (o: Record<string, string>) => Object.fromEntries(Object.entries(o).filter(([, v]) => v !== ''))

  const send = (extra: Record<string, unknown>) => create.mutate({ ...clean(form), ...extra }, { onSuccess: onDone })
  const submit = (e: FormEvent) => { e.preventDefault(); send({ person: clean(person) }) }

  return (
    <Card title="Add staff" className="mb-6">
      <form onSubmit={submit} className="grid gap-3 sm:grid-cols-4">
        <TextField label="Surname" value={person.surname} onChange={e => setPerson({ ...person, surname: e.target.value })} required error={err?.fieldError('person.surname')} />
        <TextField label="First name" value={person.first_name} onChange={e => setPerson({ ...person, first_name: e.target.value })} required error={err?.fieldError('person.first_name')} />
        <TextField label="Personal email" type="email" value={person.email} onChange={e => setPerson({ ...person, email: e.target.value })} error={err?.fieldError('person.email')} />
        <TextField label="Date of birth" type="date" value={person.date_of_birth} onChange={e => setPerson({ ...person, date_of_birth: e.target.value })} />
        <TextField label="Staff number" value={form.staff_no} onChange={e => setForm({ ...form, staff_no: e.target.value.toUpperCase() })} required error={err?.fieldError('staff_no')} />
        <SelectField label="Unit" value={form.org_unit_id} onChange={e => setForm({ ...form, org_unit_id: e.target.value })} required error={err?.fieldError('org_unit_id')}>
          <option value="">Choose…</option>
          {(allUnits.data ?? units.data)?.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
        </SelectField>
        <SelectField label="Category" value={form.category} onChange={e => setForm({ ...form, category: e.target.value })}>
          {CATEGORIES.map(c => <option key={c} value={c}>{label(c)}</option>)}
        </SelectField>
        <TextField label="Rank" value={form.rank} onChange={e => setForm({ ...form, rank: e.target.value })} placeholder="Lecturer II" />
        <TextField label="Institutional email" type="email" value={form.institutional_email} onChange={e => setForm({ ...form, institutional_email: e.target.value })} error={err?.fieldError('institutional_email')} className="sm:col-span-2" />
        <div className="self-end"><Button type="submit" busy={create.isPending}>Save staff record</Button></div>
      </form>
      {matches ? (
        <div role="alert" className="mt-4 rounded-md border border-warn/40 bg-warn/5 p-3 text-sm">
          <p className="mb-2 font-medium">People with matching details already exist.</p>
          <ul className="mb-2 space-y-1">
            {matches.map(m => (
              <li key={m.id} className="flex items-center justify-between gap-2">
                <span>{m.full_name} · {m.person_no} {m.email && `· ${m.email}`}</span>
                <Button variant="secondary" onClick={() => send({ person_id: m.id })}>Use this person</Button>
              </li>
            ))}
          </ul>
          <Button variant="ghost" onClick={() => send({ person: clean(person), confirm_not_duplicate: true })}>None of these, create a new person</Button>
        </div>
      ) : err && !err.problem.errors && <p className="mt-3 text-sm text-bad">{err.message}</p>}
    </Card>
  )
}
