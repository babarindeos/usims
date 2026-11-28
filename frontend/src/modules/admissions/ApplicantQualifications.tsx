import { useState, type FormEvent } from 'react'
import { Button, Card, SelectField, TextField } from '../../design-system'
import { ApiError } from '../../lib/api'
import type { Application } from '../../lib/types'
import { useApplicationAction } from './applicantApi'
import { SaveRow } from './ui'
import { useAdmissionConfig } from './shared'

type Sitting = { exam_body: string; exam_year: string; exam_number: string; results: { subject: string; grade: string }[] }
const blankSitting = (): Sitting => ({ exam_body: '', exam_year: '', exam_number: '', results: Array.from({ length: 5 }, () => ({ subject: '', grade: '' })) })

export function OlevelSection({ a }: { a: Application }) {
  const save = useApplicationAction<{ sittings: unknown[] }>(a.id, 'PUT', '/olevel')
  const bodies = useAdmissionConfig('exam-bodies')
  const subjects = useAdmissionConfig('subjects')
  const [sittings, setSittings] = useState<Sitting[]>(
    a.sittings?.length
      ? a.sittings.map(s => ({ exam_body: s.exam_body, exam_year: String(s.exam_year), exam_number: s.exam_number, results: s.results.map(r => ({ subject: r.subject, grade: r.grade })) }))
      : [blankSitting()],
  )
  const err = save.error instanceof ApiError ? save.error : null
  const update = (i: number, patch: Partial<Sitting>) => setSittings(sittings.map((s, j) => (j === i ? { ...s, ...patch } : s)))

  const submit = (e: FormEvent) => {
    e.preventDefault()
    save.mutate({
      sittings: sittings.map(s => ({ exam_body: s.exam_body, exam_year: Number(s.exam_year), exam_number: s.exam_number, results: s.results.filter(r => r.subject && r.grade) })),
    })
  }

  return (
    <Card title="3. O'Level results" actions={sittings.length < 3 && <Button type="button" variant="ghost" onClick={() => setSittings([...sittings, blankSitting()])}>Add a sitting</Button>}>
      <form onSubmit={submit} className="space-y-6">
        {sittings.map((s, i) => {
          const grades = bodies.data?.find(b => b.code === s.exam_body)?.grades ?? []
          return (
            <fieldset key={i} className="rounded-md border border-line p-3">
              <legend className="px-1 text-sm font-medium">Sitting {i + 1}</legend>
              <div className="grid gap-3 sm:grid-cols-4">
                <SelectField label="Examination body" required value={s.exam_body} onChange={e => update(i, { exam_body: e.target.value })} error={err?.fieldError(`sittings.${i}.exam_body`)}>
                  <option value="">Choose…</option>
                  {bodies.data?.map(b => <option key={b.code} value={b.code}>{b.name}</option>)}
                </SelectField>
                <TextField label="Year" type="number" required value={s.exam_year} onChange={e => update(i, { exam_year: e.target.value })} error={err?.fieldError(`sittings.${i}.exam_year`)} />
                <TextField label="Examination number" required value={s.exam_number} onChange={e => update(i, { exam_number: e.target.value })} error={err?.fieldError(`sittings.${i}.exam_number`)} />
                <div className="flex items-end justify-end">
                  {sittings.length > 1 && <Button type="button" variant="ghost" onClick={() => setSittings(sittings.filter((_, j) => j !== i))}>Remove sitting</Button>}
                </div>
                {s.results.map((r, k) => (
                  <div key={k} className="grid grid-cols-3 gap-2 sm:col-span-2">
                    <SelectField label={`Subject ${k + 1}`} value={r.subject} className="col-span-2"
                      onChange={e => update(i, { results: s.results.map((x, m) => (m === k ? { ...x, subject: e.target.value } : x)) })}>
                      <option value="">Choose…</option>
                      {subjects.data?.map(x => <option key={x.code} value={x.code}>{x.name}</option>)}
                    </SelectField>
                    <SelectField label="Grade" value={r.grade} onChange={e => update(i, { results: s.results.map((x, m) => (m === k ? { ...x, grade: e.target.value } : x)) })}>
                      <option value="">—</option>
                      {grades.map(g => <option key={g.grade} value={g.grade}>{g.grade}</option>)}
                    </SelectField>
                  </div>
                ))}
                {s.results.length < 12 && (
                  <div className="sm:col-span-4">
                    <Button type="button" variant="ghost" onClick={() => update(i, { results: [...s.results, { subject: '', grade: '' }] })}>Add a subject</Button>
                  </div>
                )}
              </div>
            </fieldset>
          )
        })}
        <SaveRow busy={save.isPending} saved={save.isSuccess} error={err && (!err.problem.errors || err.fieldError('sittings') || Object.keys(err.problem.errors).some(k => !k.startsWith('sittings.'))) ? err : null} />
      </form>
    </Card>
  )
}

type EduRow = { institution: string; qualification: string; course_of_study: string; grade: string; year_obtained: string }

export function EducationSection({ a }: { a: Application }) {
  const save = useApplicationAction<{ records: unknown[] }>(a.id, 'PUT', '/education')
  const [rows, setRows] = useState<EduRow[]>(
    a.education?.map(r => ({ institution: r.institution, qualification: r.qualification, course_of_study: r.course_of_study ?? '', grade: r.grade ?? '', year_obtained: String(r.year_obtained) })) ?? [],
  )
  const err = save.error instanceof ApiError ? save.error : null
  const update = (i: number, patch: Partial<EduRow>) => setRows(rows.map((r, j) => (j === i ? { ...r, ...patch } : r)))
  const submit = (e: FormEvent) => {
    e.preventDefault()
    save.mutate({ records: rows.map(r => ({ ...r, year_obtained: Number(r.year_obtained), course_of_study: r.course_of_study || null, grade: r.grade || null })) })
  }

  return (
    <Card title="4. Previous education" actions={<Button type="button" variant="ghost" onClick={() => setRows([...rows, { institution: '', qualification: '', course_of_study: '', grade: '', year_obtained: '' }])}>Add a qualification</Button>}>
      <form onSubmit={submit} className="space-y-3">
        {!rows.length && <p className="text-sm text-muted">Add schools and qualifications you hold. Direct entry and postgraduate applicants must list the qualification they are applying with.</p>}
        {rows.map((r, i) => (
          <div key={i} className="grid gap-2 sm:grid-cols-6">
            <TextField label="Institution" required value={r.institution} onChange={e => update(i, { institution: e.target.value })} className="sm:col-span-2" error={err?.fieldError(`records.${i}.institution`)} />
            <TextField label="Qualification" required value={r.qualification} onChange={e => update(i, { qualification: e.target.value })} error={err?.fieldError(`records.${i}.qualification`)} />
            <TextField label="Course" value={r.course_of_study} onChange={e => update(i, { course_of_study: e.target.value })} />
            <TextField label="Grade" value={r.grade} onChange={e => update(i, { grade: e.target.value })} />
            <div className="flex items-end gap-2">
              <TextField label="Year" type="number" required value={r.year_obtained} onChange={e => update(i, { year_obtained: e.target.value })} error={err?.fieldError(`records.${i}.year_obtained`)} />
              <Button type="button" variant="ghost" aria-label="Remove qualification" onClick={() => setRows(rows.filter((_, j) => j !== i))}>✕</Button>
            </div>
          </div>
        ))}
        <SaveRow busy={save.isPending} saved={save.isSuccess} />
      </form>
    </Card>
  )
}
