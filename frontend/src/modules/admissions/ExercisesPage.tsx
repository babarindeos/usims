import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../../app/auth'
import { Button, Card, DataTable, formatDate, PageHeader, SelectField, StatusBadge, TextAreaField, TextField } from '../../design-system'
import { ApiError } from '../../lib/api'
import type { Exercise } from '../../lib/types'
import { useMutate } from '../../lib/useMutate'
import { useSessions } from '../academic/shared'
import { naira, useAdmissionConfig, useExercises } from './shared'

export function ExercisesPage() {
  const { can } = useAuth()
  const rows = useExercises()
  const [creating, setCreating] = useState(false)
  return (
    <>
      <PageHeader title="Admission exercises" description="Each exercise sets the session, category, dates, fees, programmes and screening rules for one admission round."
        actions={can('admissions.configure') && <Button onClick={() => setCreating(!creating)}>{creating ? 'Close' : 'New exercise'}</Button>} />
      {creating && <ExerciseForm />}
      <Card>
        <DataTable rows={rows.data} loading={rows.isLoading} rowKey={r => r.id} empty="No admission exercises yet." columns={[
          { key: 'name', header: 'Exercise', render: r => <Link className="font-medium text-brand" to={`/admissions/exercises/${r.id}`}>{r.name}</Link> },
          { key: 'session', header: 'Session', render: r => r.session?.name },
          { key: 'cat', header: 'Category', render: r => r.category?.name },
          { key: 'dates', header: 'Window', render: r => `${formatDate(r.opens_at)} to ${formatDate(r.closes_at)}` },
          { key: 'apps', header: 'Applications', render: r => r.applications_count ?? 0 },
          { key: 'status', header: 'Status', render: r => <StatusBadge status={r.effective_status} /> },
        ]} />
      </Card>
    </>
  )
}

export function ExerciseForm({ exercise, onDone }: { exercise?: Exercise; onDone?: () => void }) {
  const navigate = useNavigate()
  const sessions = useSessions()
  const categories = useAdmissionConfig('categories')
  const save = useMutate<Record<string, unknown>, { data: Exercise }>(exercise ? 'PATCH' : 'POST', exercise ? `/admission-exercises/${exercise.id}` : '/admission-exercises', [['admission-exercises']])
  const local = (iso?: string) => {
    if (!iso) return ''
    const d = new Date(iso)
    return new Date(d.getTime() - d.getTimezoneOffset() * 60_000).toISOString().slice(0, 16)
  }
  const [form, setForm] = useState({
    name: exercise?.name ?? '', academic_session_id: exercise?.session ? String(exercise.session.id) : '', category: exercise?.category?.code ?? '',
    opens_at: local(exercise?.opens_at), closes_at: local(exercise?.closes_at), application_fee: String(exercise?.application_fee ?? 0),
    acceptance_fee: String(exercise?.acceptance_fee ?? 0), capacity: exercise?.capacity ? String(exercise.capacity) : '', acceptance_days: String(exercise?.acceptance_days ?? 21),
    instructions: exercise?.instructions ?? '',
  })
  const err = save.error instanceof ApiError ? save.error : null
  const set = (k: keyof typeof form, v: string) => setForm({ ...form, [k]: v })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    const body: Record<string, unknown> = {
      name: form.name, opens_at: new Date(form.opens_at).toISOString(), closes_at: new Date(form.closes_at).toISOString(),
      application_fee: Number(form.application_fee), acceptance_fee: Number(form.acceptance_fee), capacity: form.capacity ? Number(form.capacity) : null,
      acceptance_days: Number(form.acceptance_days), instructions: form.instructions || null,
    }
    if (!exercise) Object.assign(body, { academic_session_id: Number(form.academic_session_id), category: form.category })
    save.mutate(body, { onSuccess: r => (exercise ? onDone?.() : navigate(`/admissions/exercises/${r.data.id}`)) })
  }

  return (
    <Card title={exercise ? 'Edit exercise' : 'New admission exercise'} className="mb-6">
      <form onSubmit={submit} className="grid gap-3 sm:grid-cols-4">
        <TextField label="Name" required value={form.name} onChange={e => set('name', e.target.value)} error={err?.fieldError('name')} className="sm:col-span-2" placeholder="2026/2027 UTME admission" />
        <SelectField label="Session" required disabled={!!exercise} value={form.academic_session_id} onChange={e => set('academic_session_id', e.target.value)} error={err?.fieldError('academic_session_id')}>
          <option value="">Choose…</option>
          {sessions.data?.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
        </SelectField>
        <SelectField label="Category" required disabled={!!exercise} value={form.category} onChange={e => set('category', e.target.value)} error={err?.fieldError('category')}>
          <option value="">Choose…</option>
          {categories.data?.map(c => <option key={c.code} value={c.code}>{c.name}</option>)}
        </SelectField>
        <TextField label="Opens" type="datetime-local" required value={form.opens_at} onChange={e => set('opens_at', e.target.value)} error={err?.fieldError('opens_at')} />
        <TextField label="Closes" type="datetime-local" required value={form.closes_at} onChange={e => set('closes_at', e.target.value)} error={err?.fieldError('closes_at')} />
        <TextField label="Application fee (NGN)" type="number" min={0} step="0.01" value={form.application_fee} onChange={e => set('application_fee', e.target.value)} error={err?.fieldError('application_fee')} hint={Number(form.application_fee) ? naira(Number(form.application_fee)) : 'No fee'} />
        <TextField label="Acceptance fee (NGN)" type="number" min={0} step="0.01" value={form.acceptance_fee} onChange={e => set('acceptance_fee', e.target.value)} error={err?.fieldError('acceptance_fee')} />
        <TextField label="Overall capacity" type="number" min={1} value={form.capacity} onChange={e => set('capacity', e.target.value)} hint="Blank means no overall cap" />
        <TextField label="Days to accept an offer" type="number" min={1} max={180} value={form.acceptance_days} onChange={e => set('acceptance_days', e.target.value)} error={err?.fieldError('acceptance_days')} />
        <TextAreaField label="Instructions for applicants" value={form.instructions} onChange={e => set('instructions', e.target.value)} className="sm:col-span-2" placeholder="Bank account for fees, documents to prepare, contact details…" />
        <div className="sm:col-span-4"><Button type="submit" busy={save.isPending}>{exercise ? 'Save changes' : 'Create exercise'}</Button></div>
      </form>
    </Card>
  )
}
