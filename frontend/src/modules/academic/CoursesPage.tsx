import { useState, type FormEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { useAuth } from '../../app/auth'
import { Button, Card, DataTable, PageHeader, SelectField, StatusBadge, TextField } from '../../design-system'
import { api, ApiError, qs } from '../../lib/api'
import type { Course, Paginated } from '../../lib/types'
import { useMutate } from '../../lib/useMutate'
import { LEVELS, label, useAcademicUnits } from './shared'

const TYPES = ['departmental', 'faculty', 'university', 'general_studies']

export function CoursesPage() {
  const { can } = useAuth()
  const units = useAcademicUnits()
  const [filters, setFilters] = useState({ q: '', department: '', level: '', status: 'active' })
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)
  const rows = useQuery({ queryKey: ['courses', filters, page], queryFn: () => api<Paginated<Course>>(`/courses${qs({ ...filters, page })}`) })
  const set = (k: string, v: string) => { setFilters({ ...filters, [k]: v }); setPage(1) }

  return (
    <>
      <PageHeader title="Course catalogue" description="Every course, its owning department, credit units and prerequisites."
        actions={can('courses.manage') && <Button onClick={() => setCreating(!creating)}>{creating ? 'Close' : 'New course'}</Button>} />
      {creating && <CourseForm onDone={() => setCreating(false)} />}
      <Card>
        <div className="mb-3 grid gap-3 sm:grid-cols-4">
          <TextField label="Search" placeholder="Code or title" value={filters.q} onChange={e => set('q', e.target.value)} />
          <SelectField label="Department" value={filters.department} onChange={e => set('department', e.target.value)}>
            <option value="">All</option>
            {units.data?.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
          </SelectField>
          <SelectField label="Level" value={filters.level} onChange={e => set('level', e.target.value)}>
            <option value="">All</option>
            {LEVELS.map(l => <option key={l} value={l}>{l}</option>)}
          </SelectField>
          <SelectField label="Status" value={filters.status} onChange={e => set('status', e.target.value)}>
            <option value="active">Active</option><option value="retired">Retired</option><option value="all">All</option>
          </SelectField>
        </div>
        <DataTable rows={rows.data?.data} loading={rows.isLoading} rowKey={r => r.id} empty="No courses match." columns={[
          { key: 'code', header: 'Code', render: r => <Link className="font-medium text-brand" to={`/academic/courses/${r.id}`}>{r.code}</Link> },
          { key: 'title', header: 'Title', render: r => r.title },
          { key: 'units', header: 'Units', render: r => r.credit_units },
          { key: 'level', header: 'Level', render: r => r.level },
          { key: 'dept', header: 'Department', render: r => r.department?.code },
          { key: 'type', header: 'Type', render: r => label(r.course_type) },
          { key: 'status', header: 'Status', render: r => <StatusBadge status={r.status} /> },
        ]} />
        {rows.data?.meta && rows.data.meta.last_page > 1 && (
          <div className="mt-3 flex items-center justify-end gap-2 text-sm">
            <Button variant="secondary" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</Button>
            <span className="text-muted">Page {page} of {rows.data.meta.last_page}</span>
            <Button variant="secondary" disabled={page >= rows.data.meta.last_page} onClick={() => setPage(page + 1)}>Next</Button>
          </div>
        )}
      </Card>
    </>
  )
}

function CourseForm({ onDone }: { onDone: () => void }) {
  const units = useAcademicUnits()
  const create = useMutate<Record<string, unknown>>('POST', '/courses', [['courses']])
  const [form, setForm] = useState({ code: '', title: '', credit_units: 3, level: 100, semester_sequence: '1', org_unit_id: '', course_type: 'departmental', has_practical: false })
  const err = create.error instanceof ApiError ? create.error : null
  const submit = (e: FormEvent) => {
    e.preventDefault()
    create.mutate({ ...form, semester_sequence: form.semester_sequence ? Number(form.semester_sequence) : null }, { onSuccess: onDone })
  }

  return (
    <Card title="New course" className="mb-6">
      <form onSubmit={submit} className="grid gap-3 sm:grid-cols-4">
        <TextField label="Code" placeholder="CSC 301" value={form.code} onChange={e => setForm({ ...form, code: e.target.value })} required error={err?.fieldError('code')} />
        <TextField label="Title" value={form.title} onChange={e => setForm({ ...form, title: e.target.value })} required className="sm:col-span-3" error={err?.fieldError('title')} />
        <TextField label="Credit units" type="number" min={0} value={form.credit_units} onChange={e => setForm({ ...form, credit_units: Number(e.target.value) })} required error={err?.fieldError('credit_units')} />
        <SelectField label="Level" value={form.level} onChange={e => setForm({ ...form, level: Number(e.target.value) })}>
          {LEVELS.map(l => <option key={l} value={l}>{l}</option>)}
        </SelectField>
        <SelectField label="Usual semester" value={form.semester_sequence} onChange={e => setForm({ ...form, semester_sequence: e.target.value })}>
          <option value="1">First</option><option value="2">Second</option><option value="">Either</option>
        </SelectField>
        <SelectField label="Type" value={form.course_type} onChange={e => setForm({ ...form, course_type: e.target.value })}>
          {TYPES.map(t => <option key={t} value={t}>{label(t)}</option>)}
        </SelectField>
        <SelectField label="Owning department" value={form.org_unit_id} onChange={e => setForm({ ...form, org_unit_id: e.target.value })} required className="sm:col-span-2" error={err?.fieldError('org_unit_id')}>
          <option value="">Choose…</option>
          {units.data?.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
        </SelectField>
        <label className="flex items-center gap-2 self-end pb-2 text-sm"><input type="checkbox" checked={form.has_practical} onChange={e => setForm({ ...form, has_practical: e.target.checked })} />Has a practical component</label>
        <div className="self-end"><Button type="submit" busy={create.isPending}>Create course</Button></div>
        {err && !err.problem.errors && <p className="text-sm text-bad sm:col-span-4">{err.message}</p>}
      </form>
    </Card>
  )
}
