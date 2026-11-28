import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { useAuth } from '../../app/auth'
import { Button, Card, DataTable, ErrorNotice, PageHeader, SelectField, StatusBadge, TextField } from '../../design-system'
import { api, ApiError, qs } from '../../lib/api'
import type { Course, Offering, Paginated } from '../../lib/types'
import { useMutate } from '../../lib/useMutate'
import { CourseSearch } from './CourseSearch'
import { useSessions } from './shared'

type GenerateResult = { created: number; linked: number; skipped_out_of_scope: number }

export function OfferingsPage() {
  const { can } = useAuth()
  const sessions = useSessions()
  const semesters = useMemo(() => (sessions.data ?? []).flatMap(s => s.semesters.map(m => ({ ...m, session: s.name, current: s.is_current }))), [sessions.data])
  const [semester, setSemester] = useState('')
  const [filters, setFilters] = useState({ q: '', status: '', flagged: false })
  useEffect(() => {
    if (!semester && semesters.length) setSemester(String((semesters.find(s => s.current) ?? semesters[0]).id))
  }, [semester, semesters])

  const rows = useQuery({
    queryKey: ['offerings', semester, filters],
    queryFn: () => api<Paginated<Offering>>(`/offerings${qs({ semester, q: filters.q, status: filters.status, flagged: filters.flagged || undefined, per_page: 200 })}`),
    enabled: !!semester,
  })
  const generate = useMutate<undefined, { data: GenerateResult }>('POST', `/semesters/${semester}/offerings/generate`, [['offerings']])
  const result = generate.data?.data

  return (
    <>
      <PageHeader title="Course offerings" description="Courses running in a semester, the cohorts that take them and who teaches them."
        actions={can('offerings.manage') && semester && (
          <Button busy={generate.isPending} onClick={() => generate.mutate(undefined)}>Generate from published curricula</Button>
        )} />
      <ErrorNotice error={generate.error} />
      {result && (
        <p role="status" className="mb-4 rounded-md border border-ok/30 bg-ok/5 px-3 py-2 text-sm">
          {result.created} new draft offerings, {result.linked} cohort links added.
          {result.skipped_out_of_scope > 0 && ` ${result.skipped_out_of_scope} courses belong to departments outside your scope and were left for them.`}
        </p>
      )}
      {can('offerings.manage') && semester && <NewOffering semesterId={Number(semester)} />}
      <Card>
        <div className="mb-3 grid gap-3 sm:grid-cols-4 sm:items-end">
          <SelectField label="Semester" value={semester} onChange={e => setSemester(e.target.value)}>
            {!semesters.length && <option value="">No semesters set up</option>}
            {semesters.map(s => <option key={s.id} value={s.id}>{s.session} · {s.name}</option>)}
          </SelectField>
          <TextField label="Course" placeholder="Code or title" value={filters.q} onChange={e => setFilters({ ...filters, q: e.target.value })} />
          <SelectField label="Status" value={filters.status} onChange={e => setFilters({ ...filters, status: e.target.value })}>
            <option value="">All</option><option value="draft">Draft</option><option value="published">Published</option><option value="cancelled">Cancelled</option>
          </SelectField>
          <label className="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" checked={filters.flagged} onChange={e => setFilters({ ...filters, flagged: e.target.checked })} />Needs reassignment</label>
        </div>
        <DataTable rows={rows.data?.data} loading={rows.isLoading} rowKey={r => r.id} empty={semester ? 'No offerings for this semester yet.' : 'Create a session and semester first.'} columns={[
          { key: 'code', header: 'Course', render: r => <Link className="font-medium text-brand" to={`/academic/offerings/${r.id}`}>{r.course.code}{r.group_code !== 'A' && ` (${r.group_code})`}</Link> },
          { key: 'title', header: 'Title', render: r => r.course.title },
          { key: 'units', header: 'Units', render: r => r.course.credit_units },
          { key: 'dept', header: 'Department', render: r => r.department?.code },
          { key: 'staff', header: 'Staff', render: r => <>{r.staff_count ?? 0}{(r.flagged_count ?? 0) > 0 && <span className="ml-1 text-xs text-warn">({r.flagged_count} to reassign)</span>}</> },
          { key: 'lms', header: 'LMS short name', render: r => r.lms_shortname ? <span className="font-mono text-xs">{r.lms_shortname}</span> : <span className="text-muted">On publish</span> },
          { key: 'status', header: 'Status', render: r => <StatusBadge status={r.status} /> },
        ]} />
      </Card>
    </>
  )
}

function NewOffering({ semesterId }: { semesterId: number }) {
  const [course, setCourse] = useState<Course | null>(null)
  const [form, setForm] = useState({ group_code: 'A', capacity: '', delivery_mode: 'in_person' })
  const create = useMutate<Record<string, unknown>>('POST', '/offerings', [['offerings']])
  const err = create.error instanceof ApiError ? create.error : null
  const submit = (e: FormEvent) => {
    e.preventDefault()
    if (!course) return
    create.mutate({ course_id: course.id, semester_id: semesterId, ...form, capacity: form.capacity ? Number(form.capacity) : null }, { onSuccess: () => setCourse(null) })
  }

  return (
    <Card title="Offer a course" className="mb-6">
      <form onSubmit={submit} className="grid gap-3 sm:grid-cols-6 sm:items-end">
        <div className="sm:col-span-2">
          {course ? (
            <div className="text-sm"><span className="mb-1 block font-medium">Course</span>{course.code} {course.title} <Button variant="ghost" type="button" onClick={() => setCourse(null)}>Change</Button></div>
          ) : <CourseSearch label="Course" onPick={setCourse} />}
        </div>
        <TextField label="Group" value={form.group_code} onChange={e => setForm({ ...form, group_code: e.target.value.toUpperCase() })} error={err?.fieldError('group_code')} />
        <TextField label="Capacity" type="number" min={1} value={form.capacity} onChange={e => setForm({ ...form, capacity: e.target.value })} hint="Blank for no cap" />
        <SelectField label="Delivery" value={form.delivery_mode} onChange={e => setForm({ ...form, delivery_mode: e.target.value })}>
          <option value="in_person">In person</option><option value="online">Online</option><option value="blended">Blended</option>
        </SelectField>
        <Button type="submit" disabled={!course} busy={create.isPending}>Create draft</Button>
        {err && <p className="text-sm text-bad sm:col-span-6">{err.fieldError('course_id') ?? err.fieldError('semester_id') ?? err.message}</p>}
      </form>
    </Card>
  )
}
