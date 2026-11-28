import { useState, type FormEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link, useParams } from 'react-router-dom'
import { useAuth } from '../../app/auth'
import { Button, Card, DataTable, ErrorNotice, formatDate, PageHeader, SelectField, Spinner, StatusBadge, TextField } from '../../design-system'
import { api, ApiError, errorList } from '../../lib/api'
import type { Exercise, ExerciseRules, Programme } from '../../lib/types'
import { useMutate } from '../../lib/useMutate'
import { askReason } from '../academic/shared'
import { ExerciseForm } from './ExercisesPage'
import { naira, RULE_LABELS, useAdmissionConfig } from './shared'

const NEXT: Record<string, { to: string; label: string }[]> = {
  draft: [{ to: 'scheduled', label: 'Schedule' }, { to: 'open', label: 'Open now' }],
  scheduled: [{ to: 'open', label: 'Open now' }, { to: 'draft', label: 'Back to draft' }, { to: 'suspended', label: 'Suspend' }, { to: 'closed', label: 'Close' }],
  open: [{ to: 'suspended', label: 'Suspend' }, { to: 'closed', label: 'Close' }],
  suspended: [{ to: 'open', label: 'Reopen' }, { to: 'closed', label: 'Close' }],
  closed: [],
}

export function ExerciseDetailPage() {
  const { id = '' } = useParams()
  const { can } = useAuth()
  const key = ['admission-exercises', id]
  const ex = useQuery({ queryKey: key, queryFn: () => api<{ data: Exercise }>(`/admission-exercises/${id}`).then(r => r.data) })
  const status = useMutate<{ status: string; reason: string }>('POST', `/admission-exercises/${id}/status`, [key, ['admission-exercises']])
  const [editing, setEditing] = useState(false)
  if (ex.isLoading) return <Spinner />
  if (!ex.data) return <ErrorNotice error={ex.error} />
  const e = ex.data
  const manage = can('admissions.configure')

  const change = (to: string, label: string) => {
    const reason = askReason(`${label}: give a reason for the audit trail`)
    if (reason) status.mutate({ status: to, reason })
  }

  return (
    <>
      <PageHeader title={e.name} description={`${e.category?.name} · ${e.session?.name} · ${formatDate(e.opens_at, true)} to ${formatDate(e.closes_at, true)}`}
        actions={<>
          <StatusBadge status={e.effective_status} />
          {manage && NEXT[e.status]?.map(n => <Button key={n.to} variant={n.to === 'open' ? 'primary' : 'secondary'} busy={status.isPending && status.variables?.status === n.to} onClick={() => change(n.to, n.label)}>{n.label}</Button>)}
          {manage && e.status !== 'closed' && <Button variant="ghost" onClick={() => setEditing(!editing)}>{editing ? 'Cancel' : 'Edit'}</Button>}
        </>} />
      {status.error && <div className="mb-4"><ErrorNotice error={new Error(errorList(status.error).join(' '))} /></div>}
      {e.status !== e.effective_status && <p className="mb-4 text-sm text-muted">Set to {e.status}; it is {e.effective_status.replace('_', ' ')} because of its dates.</p>}
      {editing && <ExerciseForm exercise={e} onDone={() => setEditing(false)} />}

      <div className="mb-6 grid gap-4 sm:grid-cols-4">
        <Fact label="Application fee" value={e.application_fee ? naira(e.application_fee) : 'None'} />
        <Fact label="Acceptance fee" value={e.acceptance_fee ? naira(e.acceptance_fee) : 'None'} />
        <Fact label="Applications" value={<Link className="text-brand" to={`/admissions/applications?exercise=${e.id}`}>{e.applications_count ?? 0}</Link>} />
        <Fact label="Acceptance window" value={`${e.acceptance_days} days`} />
      </div>

      <ProgrammesCard e={e} manage={manage} />
      <DocumentsCard e={e} manage={manage} />
    </>
  )
}

function Fact({ label, value }: { label: string; value: React.ReactNode }) {
  return <div className="rounded-lg border border-line bg-surface p-3"><p className="text-xs text-muted">{label}</p><p className="mt-1 font-medium">{value}</p></div>
}

function ProgrammesCard({ e, manage }: { e: Exercise; manage: boolean }) {
  const key = ['admission-exercises', e.id]
  const programmes = useQuery({ queryKey: ['programmes', ''], queryFn: () => api<{ data: Programme[] }>('/programmes').then(r => r.data), enabled: manage })
  const add = useMutate<Record<string, unknown>>('POST', `/admission-exercises/${e.id}/programmes`, [key])
  const remove = useMutate<number>('DELETE', row => `/admission-exercises/${e.id}/programmes/${row}`, [key])
  const [form, setForm] = useState({ programme_id: '', entry_level: '100', capacity: '' })
  const [editing, setEditing] = useState<number | null>(null)
  const err = add.error instanceof ApiError ? add.error : null

  const submit = (ev: FormEvent) => {
    ev.preventDefault()
    add.mutate({ programme_id: form.programme_id, entry_level: Number(form.entry_level), capacity: form.capacity ? Number(form.capacity) : null }, { onSuccess: () => setForm({ programme_id: '', entry_level: '100', capacity: '' }) })
  }

  return (
    <Card title="Programmes and screening rules" className="mb-6">
      <ErrorNotice error={remove.error} />
      <DataTable rows={e.programmes} rowKey={r => r.id} empty="No programmes yet. Add at least one before opening the exercise." columns={[
        { key: 'p', header: 'Programme', render: r => <>{r.programme.name}<span className="block text-xs text-muted">{r.programme.code} · {r.programme.department}</span></> },
        { key: 'l', header: 'Entry level', render: r => r.entry_level },
        { key: 'c', header: 'Capacity', render: r => r.capacity ?? '—' },
        { key: 'r', header: 'Rules', render: r => <RuleSummary rules={r.rules} /> },
        { key: 's', header: 'Status', render: r => <StatusBadge status={r.active ? 'active' : 'inactive'} /> },
        { key: 'a', header: '', render: r => manage && <div className="flex gap-1"><Button variant="ghost" onClick={() => setEditing(editing === r.id ? null : r.id)}>Rules</Button><Button variant="ghost" onClick={() => remove.mutate(r.id)}>Remove</Button></div> },
      ]} />
      {editing && e.programmes?.find(p => p.id === editing) && <RulesEditor e={e} row={e.programmes.find(p => p.id === editing)!} onDone={() => setEditing(null)} />}
      {manage && (
        <form onSubmit={submit} className="mt-4 grid gap-2 sm:grid-cols-5">
          <SelectField label="Programme" required value={form.programme_id} onChange={ev => setForm({ ...form, programme_id: ev.target.value })} error={err?.fieldError('programme_id')} className="sm:col-span-2">
            <option value="">Choose…</option>
            {programmes.data?.filter(p => p.status === 'active').map(p => <option key={p.id} value={p.id}>{p.code} {p.name}</option>)}
          </SelectField>
          <TextField label="Entry level" type="number" step={100} min={100} value={form.entry_level} onChange={ev => setForm({ ...form, entry_level: ev.target.value })} error={err?.fieldError('entry_level')} />
          <TextField label="Capacity" type="number" min={1} value={form.capacity} onChange={ev => setForm({ ...form, capacity: ev.target.value })} />
          <div className="flex items-end"><Button type="submit" variant="secondary" busy={add.isPending}>Add programme</Button></div>
          {err && !err.problem.errors && <div className="sm:col-span-5"><ErrorNotice error={err} /></div>}
        </form>
      )}
    </Card>
  )
}

function RuleSummary({ rules }: { rules: ExerciseRules }) {
  const entries = Object.entries(rules ?? {})
  if (!entries.length) return <span className="text-xs text-muted">No rules: every complete application is eligible</span>
  return (
    <ul className="text-xs">
      {entries.map(([k, v]) => <li key={k}>{RULE_LABELS[k] ?? k}: {Array.isArray(v) ? v.join(', ') : typeof v === 'boolean' ? (v ? 'yes' : 'no') : v}</li>)}
    </ul>
  )
}

function RulesEditor({ e, row, onDone }: { e: Exercise; row: NonNullable<Exercise['programmes']>[number]; onDone: () => void }) {
  const subjects = useAdmissionConfig('subjects')
  const save = useMutate<Record<string, unknown>>('PATCH', `/admission-exercises/${e.id}/programmes/${row.id}`, [['admission-exercises', e.id]])
  const r = row.rules ?? {}
  const [form, setForm] = useState({
    min_utme_score: r.min_utme_score != null ? String(r.min_utme_score) : '', min_credit_passes: r.min_credit_passes != null ? String(r.min_credit_passes) : '',
    max_sittings: r.max_sittings != null ? String(r.max_sittings) : '', capacity: row.capacity ? String(row.capacity) : '',
  })
  const [utme, setUtme] = useState<string[]>(r.required_utme_subjects ?? [])
  const [credits, setCredits] = useState<string[]>(r.required_credit_subjects ?? [])
  const [verified, setVerified] = useState(!!r.require_verified_documents)
  const [manual, setManual] = useState(!!r.manual_review)
  const [active, setActive] = useState(row.active)
  const err = save.error instanceof ApiError ? save.error : null
  const num = (v: string) => (v === '' ? null : Number(v))

  const submit = (ev: FormEvent) => {
    ev.preventDefault()
    save.mutate({
      capacity: num(form.capacity), active,
      rules: { min_utme_score: num(form.min_utme_score), min_credit_passes: num(form.min_credit_passes), max_sittings: num(form.max_sittings), required_utme_subjects: utme, required_credit_subjects: credits, require_verified_documents: verified, manual_review: manual },
    }, { onSuccess: onDone })
  }

  const pick = (list: string[], set: (v: string[]) => void, code: string, on: boolean) => set(on ? [...list, code] : list.filter(c => c !== code))

  return (
    <form onSubmit={submit} className="mt-4 grid gap-3 rounded-md border border-line p-4 sm:grid-cols-4">
      <p className="text-sm font-semibold sm:col-span-4">Screening rules for {row.programme.code} at {row.entry_level} level</p>
      <TextField label={RULE_LABELS.min_utme_score} type="number" min={0} max={400} value={form.min_utme_score} onChange={ev => setForm({ ...form, min_utme_score: ev.target.value })} error={err?.fieldError('rules.min_utme_score')} />
      <TextField label={RULE_LABELS.min_credit_passes} type="number" min={0} max={9} value={form.min_credit_passes} onChange={ev => setForm({ ...form, min_credit_passes: ev.target.value })} />
      <TextField label={RULE_LABELS.max_sittings} type="number" min={1} max={3} value={form.max_sittings} onChange={ev => setForm({ ...form, max_sittings: ev.target.value })} />
      <TextField label="Capacity" type="number" min={1} value={form.capacity} onChange={ev => setForm({ ...form, capacity: ev.target.value })} />
      <SubjectPicker label={`${RULE_LABELS.required_utme_subjects} (up to 4)`} subjects={subjects.data ?? []} chosen={utme} onToggle={(c, on) => pick(utme, setUtme, c, on)} />
      <SubjectPicker label={RULE_LABELS.required_credit_subjects} subjects={subjects.data ?? []} chosen={credits} onToggle={(c, on) => pick(credits, setCredits, c, on)} />
      <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={verified} onChange={ev => setVerified(ev.target.checked)} />Documents must be verified before an applicant is eligible</label>
      <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={manual} onChange={ev => setManual(ev.target.checked)} />Send every applicant to manual review</label>
      <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={active} onChange={ev => setActive(ev.target.checked)} />Taking applications</label>
      <div className="flex gap-2 sm:col-span-4">
        <Button type="submit" busy={save.isPending}>Save rules</Button>
        <Button type="button" variant="ghost" onClick={onDone}>Cancel</Button>
        {save.error && <ErrorNotice error={new Error(errorList(save.error).join(' '))} />}
      </div>
    </form>
  )
}

function SubjectPicker({ label, subjects, chosen, onToggle }: { label: string; subjects: { code?: string; name: string }[]; chosen: string[]; onToggle: (code: string, on: boolean) => void }) {
  return (
    <fieldset className="sm:col-span-2">
      <legend className="mb-1 text-sm font-medium">{label}</legend>
      <div className="flex max-h-40 flex-wrap gap-x-4 gap-y-1 overflow-y-auto rounded-md border border-line p-2 text-sm">
        {subjects.map(s => (
          <label key={s.code} className="flex items-center gap-1.5">
            <input type="checkbox" checked={chosen.includes(s.code!)} onChange={ev => onToggle(s.code!, ev.target.checked)} />{s.name}
          </label>
        ))}
      </div>
    </fieldset>
  )
}

function DocumentsCard({ e, manage }: { e: Exercise; manage: boolean }) {
  const types = useAdmissionConfig('document-types')
  const save = useMutate<{ documents: { code: string; stage: string; required: boolean }[] }>('PUT', `/admission-exercises/${e.id}/documents`, [['admission-exercises', e.id]])
  const current = e.documents ?? []
  const [adding, setAdding] = useState({ code: '', stage: 'application', required: true })
  const asBody = (list: typeof current) => list.map(d => ({ code: d.type.code, stage: d.stage, required: d.required }))

  return (
    <Card title="Required documents">
      <ErrorNotice error={save.error} />
      <DataTable rows={current} rowKey={r => r.id} empty="No documents are requested." columns={[
        { key: 'n', header: 'Document', render: r => r.type.name },
        { key: 's', header: 'Stage', render: r => (r.stage === 'application' ? 'With the application' : 'At onboarding') },
        { key: 'r', header: 'Required', render: r => (r.required ? 'Required' : 'Optional') },
        { key: 'a', header: '', render: r => manage && <Button variant="ghost" onClick={() => save.mutate({ documents: asBody(current.filter(d => d.id !== r.id)) })}>Remove</Button> },
      ]} />
      {manage && (
        <form className="mt-4 grid gap-2 sm:grid-cols-4" onSubmit={ev => { ev.preventDefault(); save.mutate({ documents: [...asBody(current), adding] }, { onSuccess: () => setAdding({ ...adding, code: '' }) }) }}>
          <SelectField label="Document type" required value={adding.code} onChange={ev => setAdding({ ...adding, code: ev.target.value })}>
            <option value="">Choose…</option>
            {types.data?.filter(t => t.code !== 'PAYMENT_EVIDENCE').map(t => <option key={t.code} value={t.code}>{t.name}</option>)}
          </SelectField>
          <SelectField label="Stage" value={adding.stage} onChange={ev => setAdding({ ...adding, stage: ev.target.value })}>
            <option value="application">With the application</option><option value="onboarding">At onboarding</option>
          </SelectField>
          <label className="flex items-end gap-2 pb-2 text-sm"><input type="checkbox" checked={adding.required} onChange={ev => setAdding({ ...adding, required: ev.target.checked })} />Required</label>
          <div className="flex items-end"><Button type="submit" variant="secondary" busy={save.isPending}>Add document</Button></div>
        </form>
      )}
    </Card>
  )
}
