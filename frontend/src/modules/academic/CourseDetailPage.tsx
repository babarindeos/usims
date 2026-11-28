import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useParams } from 'react-router-dom'
import { Button, Card, ErrorNotice, PageHeader, SelectField, Spinner, StatusBadge } from '../../design-system'
import { api } from '../../lib/api'
import type { Course } from '../../lib/types'
import { useMutate } from '../../lib/useMutate'
import { CourseSearch } from './CourseSearch'
import { label } from './shared'

export function CourseDetailPage() {
  const { id = '' } = useParams()
  const course = useQuery({ queryKey: ['courses', id], queryFn: () => api<{ data: Course }>(`/courses/${id}`).then(r => r.data) })
  const update = useMutate<{ status: string }>('PATCH', `/courses/${id}`, [['courses']])
  const add = useMutate<{ course_id: string; type: string; requirement: string }>('POST', `/courses/${id}/requisites`, [['courses', id]])
  const remove = useMutate<number>('DELETE', rid => `/courses/${id}/requisites/${rid}`, [['courses', id]])
  const [type, setType] = useState<'prerequisite' | 'corequisite'>('prerequisite')

  if (course.isLoading) return <Spinner />
  const c = course.data
  if (!c) return <ErrorNotice error={course.error} />

  return (
    <>
      <PageHeader title={`${c.code} · ${c.title}`} description={c.department?.name}
        actions={c.can_manage && (
          <Button variant="secondary" busy={update.isPending} onClick={() => update.mutate({ status: c.status === 'active' ? 'retired' : 'active' })}>
            {c.status === 'active' ? 'Retire course' : 'Reactivate course'}
          </Button>
        )} />
      <ErrorNotice error={update.error} />
      <div className="grid gap-6 lg:grid-cols-2">
        <Card title="Details">
          <dl className="grid grid-cols-2 gap-x-3 gap-y-2 text-sm">
            <dt className="text-muted">Credit units</dt><dd>{c.credit_units}</dd>
            <dt className="text-muted">Level</dt><dd>{c.level}</dd>
            <dt className="text-muted">Usual semester</dt><dd>{c.semester_sequence ?? 'Either'}</dd>
            <dt className="text-muted">Type</dt><dd>{label(c.course_type)}</dd>
            <dt className="text-muted">Practical</dt><dd>{c.has_practical ? 'Yes' : 'No'}</dd>
            <dt className="text-muted">Status</dt><dd><StatusBadge status={c.status} /></dd>
          </dl>
          {c.description && <p className="mt-3 text-sm">{c.description}</p>}
        </Card>
        <Card title="Prerequisites and co-requisites">
          <ErrorNotice error={add.error || remove.error} />
          {c.requisites?.length ? (
            <ul className="mb-3 divide-y divide-line text-sm">
              {c.requisites.map(r => (
                <li key={r.id} className="flex items-center justify-between py-2">
                  <span><span className="font-medium">{r.course.code}</span> {r.course.title} <span className="text-muted">· {label(r.type)}{r.type === 'corequisite' ? ` (${r.requirement})` : ''}</span></span>
                  {c.can_manage && <Button variant="ghost" onClick={() => remove.mutate(r.id)}>Remove</Button>}
                </li>
              ))}
            </ul>
          ) : <p className="mb-3 text-sm text-muted">None. Students can register for this course without earlier courses.</p>}
          {c.can_manage && (
            <div className="grid gap-2 sm:grid-cols-3 sm:items-end">
              <SelectField label="Kind" value={type} onChange={e => setType(e.target.value as 'prerequisite' | 'corequisite')}>
                <option value="prerequisite">Prerequisite (must have passed)</option>
                <option value="corequisite">Co-requisite (taken together)</option>
              </SelectField>
              <div className="sm:col-span-2">
                <CourseSearch label="Course" exclude={[c.id]} onPick={picked => add.mutate({ course_id: picked.id, type, requirement: type === 'prerequisite' ? 'pass' : 'registered' })} />
              </div>
            </div>
          )}
        </Card>
      </div>
    </>
  )
}
