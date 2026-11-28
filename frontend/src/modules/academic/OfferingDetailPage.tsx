import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useParams } from 'react-router-dom'
import { Button, Card, DataTable, ErrorNotice, PageHeader, SelectField, Spinner, StatusBadge, TextField, formatDate } from '../../design-system'
import { api, qs } from '../../lib/api'
import type { Offering, Paginated, Programme, StaffMember } from '../../lib/types'
import { useMutate } from '../../lib/useMutate'
import { LEVELS, askReason, label } from './shared'

const ROLES = ['lecturer', 'coordinator', 'practical_instructor', 'teaching_assistant', 'examiner', 'moderator']

export function OfferingDetailPage() {
  const { id = '' } = useParams()
  const key = ['offerings', id]
  const query = useQuery({ queryKey: key, queryFn: () => api<{ data: Offering }>(`/offerings/${id}`).then(r => r.data) })
  const publish = useMutate('POST', `/offerings/${id}/publish`, [['offerings']])
  const cancel = useMutate<{ reason: string }>('POST', `/offerings/${id}/cancel`, [['offerings']])

  if (query.isLoading) return <Spinner />
  const o = query.data
  if (!o) return <ErrorNotice error={query.error} />

  return (
    <>
      <PageHeader title={`${o.course.code}${o.group_code !== 'A' ? ` (group ${o.group_code})` : ''} · ${o.course.title}`}
        description={`${o.semester.session} ${o.semester.name} · ${o.department?.name ?? ''}`}
        actions={<>
          <StatusBadge status={o.status} />
          {o.can.manage && o.status === 'draft' && <Button busy={publish.isPending} onClick={() => publish.mutate(undefined)}>Publish</Button>}
          {o.can.manage && <Button variant="danger" busy={cancel.isPending} onClick={() => { const reason = askReason('Why is this offering being cancelled? Staff assignments will end.'); if (reason) cancel.mutate({ reason }) }}>Cancel offering</Button>}
        </>} />
      <ErrorNotice error={publish.error || cancel.error} />
      <div className="grid gap-6 lg:grid-cols-3">
        <Card title="Details">
          <dl className="grid grid-cols-2 gap-x-3 gap-y-2 text-sm">
            <dt className="text-muted">Credit units</dt><dd>{o.course.credit_units}</dd>
            <dt className="text-muted">Capacity</dt><dd>{o.capacity ?? 'No cap'}</dd>
            <dt className="text-muted">Delivery</dt><dd>{label(o.delivery_mode)}</dd>
            <dt className="text-muted">Registration</dt><dd>{o.registration_opens_on ? `${formatDate(o.registration_opens_on)} to ${formatDate(o.registration_closes_on)}` : 'Semester window'}</dd>
            <dt className="text-muted">LMS short name</dt><dd className="font-mono text-xs">{o.lms_shortname ?? 'Assigned on publish'}</dd>
            <dt className="text-muted">Published</dt><dd>{formatDate(o.published_at, true)}</dd>
          </dl>
          <p className="mt-3 text-xs text-muted">Moodle course creation arrives with the LMS integration (milestone 5). Nothing is sent to Moodle yet.</p>
        </Card>
        <Cohorts offering={o} />
        <Staffing offering={o} />
      </div>
    </>
  )
}

function Cohorts({ offering: o }: { offering: Offering }) {
  const key = [['offerings', o.id]]
  const programmes = useQuery({ queryKey: ['programmes', ''], queryFn: () => api<{ data: Programme[] }>('/programmes').then(r => r.data), enabled: o.can.manage })
  const add = useMutate<{ programme_id: string; level: number }>('POST', `/offerings/${o.id}/programmes`, key)
  const remove = useMutate<number>('DELETE', l => `/offerings/${o.id}/programmes/${l}`, key)
  const [form, setForm] = useState({ programme_id: '', level: o.course.level })

  return (
    <Card title="Programme cohorts">
      <ErrorNotice error={add.error || remove.error} />
      {o.programmes?.length ? (
        <ul className="mb-3 divide-y divide-line text-sm">
          {o.programmes.map(p => (
            <li key={p.id} className="flex items-center justify-between py-1.5">
              <span>{p.programme.code} · {p.level} level</span>
              {o.can.manage && <Button variant="ghost" onClick={() => remove.mutate(p.id)}>Remove</Button>}
            </li>
          ))}
        </ul>
      ) : <p className="mb-3 text-sm text-muted">No cohorts linked.</p>}
      {o.can.manage && (
        <form className="grid grid-cols-3 gap-2 sm:items-end" onSubmit={e => { e.preventDefault(); add.mutate(form) }}>
          <SelectField label="Programme" value={form.programme_id} onChange={e => setForm({ ...form, programme_id: e.target.value })} required className="col-span-2">
            <option value="">Choose…</option>
            {programmes.data?.map(p => <option key={p.id} value={p.id}>{p.code}</option>)}
          </SelectField>
          <SelectField label="Level" value={form.level} onChange={e => setForm({ ...form, level: Number(e.target.value) })}>
            {LEVELS.map(l => <option key={l} value={l}>{l}</option>)}
          </SelectField>
          <Button type="submit" variant="secondary" busy={add.isPending} className="col-span-3">Link cohort</Button>
        </form>
      )}
    </Card>
  )
}

function Staffing({ offering: o }: { offering: Offering }) {
  const key = [['offerings', o.id], ['offerings']]
  const assign = useMutate<{ staff_id: string; role: string }>('POST', `/offerings/${o.id}/staff`, key)
  const end = useMutate<{ id: number; reason: string }>('POST', b => `/offerings/${o.id}/staff/${b.id}/end`, key)
  const [q, setQ] = useState('')
  const [role, setRole] = useState('lecturer')
  const staff = useQuery({
    queryKey: ['staff', 'search', q],
    queryFn: () => api<Paginated<StaffMember>>(`/staff${qs({ q, status: 'active', per_page: 8 })}`).then(r => r.data),
    enabled: o.can.allocate && q.trim().length >= 2,
  })
  const [showEnded, setShowEnded] = useState(false)
  const rows = (o.staff ?? []).filter(a => showEnded || a.status === 'active')

  return (
    <Card title="Teaching staff" className="lg:col-span-3"
      actions={<label className="flex items-center gap-2 text-xs"><input type="checkbox" checked={showEnded} onChange={e => setShowEnded(e.target.checked)} />Show ended</label>}>
      <ErrorNotice error={assign.error || end.error} />
      <DataTable rows={rows} rowKey={a => a.id} empty="No staff assigned." columns={[
        { key: 'name', header: 'Staff', render: a => <>{a.staff.full_name} <span className="text-muted">· {a.staff.staff_no}</span>{a.staff.rank && <span className="block text-xs text-muted">{a.staff.rank}</span>}</> },
        { key: 'role', header: 'Role', render: a => label(a.role) },
        { key: 'from', header: 'From', render: a => formatDate(a.effective_from) },
        { key: 'status', header: 'Status', render: a => <>
          <StatusBadge status={a.status} />
          {a.flagged_at && <span className="mt-1 block text-xs text-warn">{a.flag_reason}</span>}
          {a.end_reason && <span className="mt-1 block text-xs text-muted">{a.end_reason}</span>}
        </> },
        { key: 'act', header: '', render: a => o.can.allocate && a.status === 'active' && (
          <Button variant="ghost" onClick={() => { const reason = askReason(`Why is ${a.staff.full_name} leaving this role?`); if (reason) end.mutate({ id: a.id, reason }) }}>End</Button>
        ) },
      ]} />
      {o.can.allocate && (
        <div className="mt-4 grid gap-3 border-t border-line pt-4 sm:grid-cols-3 sm:items-start">
          <SelectField label="Role" value={role} onChange={e => setRole(e.target.value)}>
            {ROLES.map(r => <option key={r} value={r}>{label(r)}</option>)}
          </SelectField>
          <div className="relative sm:col-span-2">
            <TextField label="Find staff" placeholder="Name or staff number" value={q} onChange={e => setQ(e.target.value)} autoComplete="off" />
            {q.trim().length >= 2 && (
              <ul className="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md border border-line bg-surface text-sm shadow-lg">
                {staff.data?.map(s => (
                  <li key={s.id}>
                    <button type="button" className="w-full px-3 py-2 text-left hover:bg-canvas" onClick={() => { assign.mutate({ staff_id: s.id, role }); setQ('') }}>
                      {s.person?.full_name} <span className="text-muted">· {s.staff_no} · {s.rank ?? label(s.category)} · {s.department?.code}</span>
                    </button>
                  </li>
                ))}
                {staff.isFetched && !staff.data?.length && <li className="px-3 py-2 text-muted">No active staff match within your scope.</li>}
              </ul>
            )}
          </div>
        </div>
      )}
    </Card>
  )
}
