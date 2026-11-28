import { useState, type FormEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link, useParams } from 'react-router-dom'
import { Button, Card, ErrorNotice, PageHeader, SelectField, Spinner, StatusBadge, TextField, formatDate } from '../../design-system'
import { api, ApiError } from '../../lib/api'
import type { Curriculum, CurriculumRow } from '../../lib/types'
import { useMutate } from '../../lib/useMutate'
import { CourseSearch } from './CourseSearch'
import { LEVELS, askReason, label } from './shared'

const CATEGORIES = ['core', 'elective', 'required_elective', 'general_studies']

export function CurriculumPage() {
  const { id = '' } = useParams()
  const key = ['curricula', id]
  const query = useQuery({ queryKey: key, queryFn: () => api<{ data: Curriculum }>(`/curricula/${id}`).then(r => r.data) })
  const submit = useMutate<{ remarks?: string }>('POST', `/curricula/${id}/submit`, [['curricula']])
  const publish = useMutate('POST', `/curricula/${id}/publish`, [['curricula']])
  const retire = useMutate<{ reason: string }>('POST', `/curricula/${id}/retire`, [['curricula']])

  if (query.isLoading) return <Spinner />
  const c = query.data
  if (!c) return <ErrorNotice error={query.error} />
  const submitErrors = submit.error instanceof ApiError ? submit.error.problem.errors?.curriculum : undefined

  return (
    <>
      <PageHeader
        title={`${c.programme?.code} curriculum v${c.version}`}
        description={`${c.title} · first entry cohort ${c.effective_session?.name ?? '—'}`}
        actions={<>
          <StatusBadge status={c.status} />
          {c.can.submit && <Button busy={submit.isPending} onClick={() => submit.mutate({ remarks: window.prompt('Remarks for the reviewers (optional)') ?? undefined })}>{c.status === 'returned' ? 'Resubmit for approval' : 'Submit for approval'}</Button>}
          {c.can.publish && <Button busy={publish.isPending} onClick={() => publish.mutate(undefined)}>Publish</Button>}
          {c.can.retire && <Button variant="secondary" busy={retire.isPending} onClick={() => { const reason = askReason('Why is this curriculum being retired?'); if (reason) retire.mutate({ reason }) }}>Retire</Button>}
        </>}
      />
      <div className="mb-4 space-y-2">
        {submitErrors ? <div role="alert" className="rounded-md border border-bad/30 bg-bad/5 px-3 py-2 text-sm text-bad">{submitErrors.join(' ')}</div> : <ErrorNotice error={submit.error} />}
        <ErrorNotice error={publish.error || retire.error} />
        {!c.can.edit && ['in_review', 'approved', 'published', 'retired', 'rejected'].includes(c.status) && (
          <p className="rounded-md border border-line bg-canvas px-3 py-2 text-sm text-muted">
            This version is {label(c.status).toLowerCase()} and can no longer be edited. Changes need a new version from the <Link className="text-brand" to={`/academic/programmes/${c.programme?.id}`}>programme page</Link>.
          </p>
        )}
      </div>

      <div className="mb-6 grid gap-4 sm:grid-cols-4">
        <Summary label="Mapped units" value={`${c.total_units ?? 0} / ${c.required_credit_units}`} warn={(c.total_units ?? 0) < c.required_credit_units} />
        <Summary label="Duration" value={`${c.duration_years} years`} />
        <Summary label="Semester load" value={c.min_semester_units || c.max_semester_units ? `${c.min_semester_units ?? 0}–${c.max_semester_units ?? '∞'} units` : 'Not set'} />
        <Summary label="Approval" value={c.workflow ? <Link className="text-brand" to={`/approvals/${c.workflow.id}`}>{c.workflow.reference}</Link> : 'Not submitted'}
          hint={c.workflow?.current_stage ?? (c.published_at ? `Published ${formatDate(c.published_at)}` : c.approved_at ? `Approved ${formatDate(c.approved_at)}` : undefined)} />
      </div>

      {c.can.edit && <HeaderForm curriculum={c} />}
      <CourseMap curriculum={c} />
      <ElectiveGroups curriculum={c} />
    </>
  )
}

function Summary({ label: l, value, hint, warn }: { label: string; value: React.ReactNode; hint?: string; warn?: boolean }) {
  return (
    <div className="rounded-lg border border-line bg-surface p-3">
      <p className="text-xs font-medium uppercase tracking-wide text-muted">{l}</p>
      <p className={`mt-1 text-lg font-semibold ${warn ? 'text-warn' : ''}`}>{value}</p>
      {hint && <p className="text-xs text-muted">{hint}</p>}
    </div>
  )
}

function HeaderForm({ curriculum: c }: { curriculum: Curriculum }) {
  const update = useMutate<Record<string, unknown>>('PATCH', `/curricula/${c.id}`, [['curricula', c.id]])
  const [form, setForm] = useState({ required_credit_units: c.required_credit_units, min_semester_units: c.min_semester_units ?? '', max_semester_units: c.max_semester_units ?? '', duration_years: c.duration_years })
  const err = update.error instanceof ApiError ? update.error : null
  const submit = (e: FormEvent) => {
    e.preventDefault()
    update.mutate({
      ...form,
      min_semester_units: form.min_semester_units === '' ? null : Number(form.min_semester_units),
      max_semester_units: form.max_semester_units === '' ? null : Number(form.max_semester_units),
    })
  }
  return (
    <Card title="Rules" className="mb-6">
      <form onSubmit={submit} className="grid gap-3 sm:grid-cols-5 sm:items-end">
        <TextField label="Required credit units" type="number" value={form.required_credit_units} onChange={e => setForm({ ...form, required_credit_units: Number(e.target.value) })} error={err?.fieldError('required_credit_units')} />
        <TextField label="Duration (years)" type="number" value={form.duration_years} onChange={e => setForm({ ...form, duration_years: Number(e.target.value) })} error={err?.fieldError('duration_years')} />
        <TextField label="Min units per semester" type="number" value={form.min_semester_units} onChange={e => setForm({ ...form, min_semester_units: e.target.value })} error={err?.fieldError('min_semester_units')} />
        <TextField label="Max units per semester" type="number" value={form.max_semester_units} onChange={e => setForm({ ...form, max_semester_units: e.target.value })} error={err?.fieldError('max_semester_units')} />
        <Button type="submit" variant="secondary" busy={update.isPending}>Save rules</Button>
      </form>
    </Card>
  )
}

function CourseMap({ curriculum: c }: { curriculum: Curriculum }) {
  const key = [['curricula', c.id]]
  const add = useMutate<Record<string, unknown>>('POST', `/curricula/${c.id}/courses`, key)
  const update = useMutate<{ row: number } & Partial<CurriculumRow>>('PATCH', b => `/curricula/${c.id}/courses/${b.row}`, key)
  const remove = useMutate<number>('DELETE', row => `/curricula/${c.id}/courses/${row}`, key)
  const [category, setCategory] = useState('core')
  const rows = c.courses ?? []
  const groups = new Map<string, CurriculumRow[]>()
  for (const r of rows) {
    const k = `${r.level}-${r.semester_sequence}`
    groups.set(k, [...(groups.get(k) ?? []), r])
  }

  return (
    <Card title="Courses by level and semester" className="mb-6">
      <ErrorNotice error={add.error || update.error || remove.error} />
      {c.can.edit && (
        <div className="mb-4 grid gap-3 sm:grid-cols-3 sm:items-end">
          <SelectField label="Add as" value={category} onChange={e => setCategory(e.target.value)}>
            {CATEGORIES.map(k => <option key={k} value={k}>{label(k)}</option>)}
          </SelectField>
          <div className="sm:col-span-2">
            <CourseSearch label="Add a course" exclude={rows.map(r => r.course.id)} onPick={course => add.mutate({ course_id: course.id, category })} />
          </div>
        </div>
      )}
      {!rows.length && <p className="text-sm text-muted">No courses mapped yet.</p>}
      <div className="space-y-5">
        {[...groups.entries()].map(([k, list]) => {
          const [level, sem] = k.split('-')
          return (
            <div key={k}>
              <h3 className="mb-1 text-sm font-semibold">{level} level · {sem === '1' ? 'First' : sem === '2' ? 'Second' : `Semester ${sem}`} semester <span className="font-normal text-muted">· {list.reduce((s, r) => s + r.credit_units, 0)} units</span></h3>
              <table className="w-full text-left text-sm">
                <tbody>
                  {list.map(r => (
                    <tr key={r.id} className="border-b border-line last:border-0">
                      <td className="w-28 py-1.5 font-medium">{r.course.code}</td>
                      <td className="py-1.5">{r.course.title}</td>
                      <td className="w-16 py-1.5">{r.credit_units}u</td>
                      <td className="w-56 py-1.5">
                        {c.can.edit ? (
                          <select aria-label={`Category for ${r.course.code}`} value={r.category} onChange={e => update.mutate({ row: r.id, category: e.target.value })} className="rounded border border-line bg-surface px-1 py-0.5 text-xs">
                            {CATEGORIES.map(k => <option key={k} value={k}>{label(k)}</option>)}
                          </select>
                        ) : label(r.category)}
                        {r.category !== 'core' && (c.elective_groups?.length ?? 0) > 0 && (c.can.edit ? (
                          <select aria-label={`Elective group for ${r.course.code}`} value={r.elective_group_id ?? ''} onChange={e => update.mutate({ row: r.id, elective_group_id: e.target.value ? Number(e.target.value) : null })} className="ml-1 rounded border border-line bg-surface px-1 py-0.5 text-xs">
                            <option value="">No group</option>
                            {c.elective_groups?.map(g => <option key={g.id} value={g.id}>{g.code}</option>)}
                          </select>
                        ) : r.elective_group_id && <span className="ml-1 text-xs text-muted">{c.elective_groups?.find(g => g.id === r.elective_group_id)?.code}</span>)}
                      </td>
                      {c.can.edit && (
                        <td className="w-48 py-1.5 text-right">
                          <select aria-label={`Placement for ${r.course.code}`} value={`${r.level}-${r.semester_sequence}`} className="mr-2 rounded border border-line bg-surface px-1 py-0.5 text-xs"
                            onChange={e => { const [lv, sq] = e.target.value.split('-').map(Number); update.mutate({ row: r.id, level: lv, semester_sequence: sq }) }}>
                            {LEVELS.slice(0, Math.max(c.duration_years, 1) + 1).flatMap(lv => [1, 2].map(sq => <option key={`${lv}-${sq}`} value={`${lv}-${sq}`}>{lv} · S{sq}</option>))}
                          </select>
                          <Button variant="ghost" onClick={() => remove.mutate(r.id)}>Remove</Button>
                        </td>
                      )}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )
        })}
      </div>
    </Card>
  )
}

function ElectiveGroups({ curriculum: c }: { curriculum: Curriculum }) {
  const key = [['curricula', c.id]]
  const add = useMutate<Record<string, unknown>>('POST', `/curricula/${c.id}/elective-groups`, key)
  const remove = useMutate<number>('DELETE', g => `/curricula/${c.id}/elective-groups/${g}`, key)
  const [form, setForm] = useState({ code: '', name: '', level: 100, semester_sequence: 1, min_units: 0 })
  const err = add.error instanceof ApiError ? add.error : null
  const groups = c.elective_groups ?? []
  if (!c.can.edit && !groups.length) return null

  return (
    <Card title="Elective groups">
      <ErrorNotice error={remove.error} />
      {groups.length ? (
        <ul className="mb-3 divide-y divide-line text-sm">
          {groups.map(g => (
            <li key={g.id} className="flex items-center justify-between py-2">
              <span><span className="font-medium">{g.code}</span> {g.name} <span className="text-muted">· {g.level} level, semester {g.semester_sequence} · choose at least {g.min_units} units{g.max_units ? `, at most ${g.max_units}` : ''}</span></span>
              {c.can.edit && <Button variant="ghost" onClick={() => remove.mutate(g.id)}>Remove</Button>}
            </li>
          ))}
        </ul>
      ) : <p className="mb-3 text-sm text-muted">No elective groups.</p>}
      {c.can.edit && (
        <form onSubmit={e => { e.preventDefault(); add.mutate(form) }} className="grid gap-2 sm:grid-cols-6 sm:items-end">
          <TextField label="Code" value={form.code} onChange={e => setForm({ ...form, code: e.target.value.toUpperCase() })} required error={err?.fieldError('code')} />
          <TextField label="Name" value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} required className="sm:col-span-2" />
          <SelectField label="Level" value={form.level} onChange={e => setForm({ ...form, level: Number(e.target.value) })}>
            {LEVELS.map(l => <option key={l} value={l}>{l}</option>)}
          </SelectField>
          <TextField label="Minimum units" type="number" min={0} value={form.min_units} onChange={e => setForm({ ...form, min_units: Number(e.target.value) })} />
          <Button type="submit" variant="secondary" busy={add.isPending}>Add group</Button>
        </form>
      )}
    </Card>
  )
}
