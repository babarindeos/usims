import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { Button, Card, DataTable, PageHeader, SelectField, StatusBadge, TextField } from '../../design-system'
import { api, qs } from '../../lib/api'
import type { Paginated, Student } from '../../lib/types'
import { LEVELS } from '../academic/shared'

const STATUSES = ['matriculated', 'active', 'deferred', 'suspended', 'withdrawn', 'expelled', 'graduated', 'deceased']

export function StudentsPage() {
  const [q, setQ] = useState('')
  const [status, setStatus] = useState('')
  const [level, setLevel] = useState('')
  const [page, setPage] = useState(1)
  const rows = useQuery({ queryKey: ['students', q, status, level, page], queryFn: () => api<Paginated<Student>>(`/students${qs({ q, status, level, page })}`) })
  const meta = rows.data?.meta

  return (
    <>
      <PageHeader title="Student records" description="Students created at matriculation. Departments and faculties see their own programmes." />
      <Card>
        <div className="mb-3 grid gap-3 sm:grid-cols-4">
          <TextField label="Search" placeholder="Name, matric or student no" value={q} onChange={e => { setQ(e.target.value); setPage(1) }} className="sm:col-span-2" />
          <SelectField label="Status" value={status} onChange={e => { setStatus(e.target.value); setPage(1) }}>
            <option value="">Any</option>
            {STATUSES.map(s => <option key={s} value={s}>{s}</option>)}
          </SelectField>
          <SelectField label="Level" value={level} onChange={e => { setLevel(e.target.value); setPage(1) }}>
            <option value="">Any</option>
            {LEVELS.map(l => <option key={l} value={l}>{l}</option>)}
          </SelectField>
        </div>
        <DataTable rows={rows.data?.data} loading={rows.isLoading} rowKey={r => r.id} empty="No students yet." columns={[
          { key: 'm', header: 'Matric no', render: r => <Link className="font-medium text-brand" to={`/students/${r.id}`}>{r.matric_no}</Link> },
          { key: 'n', header: 'Name', render: r => <>{r.full_name}<span className="block text-xs text-muted">{r.student_no}</span></> },
          { key: 'p', header: 'Programme', render: r => <>{r.programme.code}<span className="block text-xs text-muted">{r.department}</span></> },
          { key: 'l', header: 'Level', render: r => r.current_level },
          { key: 'sess', header: 'Admitted', render: r => r.admission_session },
          { key: 's', header: 'Status', render: r => <StatusBadge status={r.status} /> },
        ]} />
        {meta && meta.last_page > 1 && (
          <div className="mt-3 flex items-center justify-between text-sm">
            <span className="text-muted">{meta.total} students</span>
            <div className="flex gap-2">
              <Button variant="secondary" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</Button>
              <Button variant="secondary" disabled={page >= meta.last_page} onClick={() => setPage(page + 1)}>Next</Button>
            </div>
          </div>
        )}
      </Card>
    </>
  )
}
