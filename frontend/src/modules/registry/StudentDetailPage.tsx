import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useParams } from 'react-router-dom'
import { Button, Card, ErrorNotice, formatDate, PageHeader, SelectField, Spinner, StatusBadge, TextAreaField, TextField } from '../../design-system'
import { api, errorList } from '../../lib/api'
import type { Student } from '../../lib/types'

export function StudentDetailPage() {
  const { id = '' } = useParams()
  const student = useQuery({ queryKey: ['students', id], queryFn: () => api<{ data: Student }>(`/students/${id}`).then(r => r.data) })
  if (student.isLoading) return <Spinner />
  if (!student.data) return <ErrorNotice error={student.error} />
  const s = student.data
  const p = s.person
  const ob = (s.onboarding ?? {}) as Record<string, Record<string, string>>

  return (
    <>
      <PageHeader title={s.full_name} description={`${s.matric_no} · ${s.programme.name}`} actions={<><StatusBadge status={s.status} /><Link className="text-sm text-brand" to="/students">All students</Link></>} />
      <div className="grid gap-6 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
          <Card title="Academic record">
            <dl className="grid gap-3 text-sm sm:grid-cols-3">
              <Item k="Student no" v={s.student_no} />
              <Item k="Matric no" v={s.matric_no} />
              <Item k="Admission no" v={s.admission_no} />
              <Item k="Programme" v={`${s.programme.code} ${s.programme.name}`} />
              <Item k="Department" v={s.department} />
              <Item k="Faculty" v={s.faculty} />
              <Item k="Admission category" v={s.admission_category} />
              <Item k="Admission session" v={s.admission_session} />
              <Item k="Entry / current level" v={`${s.entry_level} / ${s.current_level}`} />
              <Item k="Curriculum" v={s.curriculum ? <Link className="text-brand" to={`/academic/curricula/${s.curriculum.id}`}>Version {s.curriculum.version}: {s.curriculum.title}</Link> : 'No published curriculum was found'} />
              <Item k="Matriculated" v={formatDate(s.matriculated_at, true)} />
            </dl>
          </Card>
          <Card title="Status history">
            <ol className="space-y-2 text-sm">
              {s.status_history?.map((h, i) => <li key={i}><span className="text-muted">{formatDate(h.effective_on)}</span> · {h.from ? `${h.from} → ` : ''}{h.to}{h.reason && ` · ${h.reason}`}{h.by && <span className="text-muted"> ({h.by})</span>}</li>)}
            </ol>
            {s.can_manage && s.allowed_statuses.length > 0 && <StatusChange s={s} />}
          </Card>
          <Card title="Identifiers">
            <ul className="space-y-1 text-sm">
              {s.identifiers?.map(i => <li key={`${i.type}${i.value}`} className={i.superseded_at ? 'text-muted line-through' : ''}><span className="font-mono text-xs">{i.type}</span> {i.value} <span className="text-xs text-muted">issued {formatDate(i.issued_at)}</span></li>)}
            </ul>
          </Card>
        </div>
        <div className="space-y-6">
          <Card title="Personal">
            <dl className="space-y-2 text-sm">
              <Item k="Person no" v={p?.person_no} />
              <Item k="Date of birth" v={formatDate(p?.date_of_birth)} />
              <Item k="Gender" v={p?.gender} />
              <Item k="State / LGA" v={`${p?.state_of_origin ?? '—'} / ${p?.lga ?? '—'}`} />
              <Item k="Email" v={p?.email} />
              <Item k="Phone" v={p?.phone} />
              <Item k="Address" v={p?.address} />
            </dl>
          </Card>
          <Card title="Contacts">
            <dl className="space-y-2 text-sm">
              <Item k="Next of kin" v={s.next_of_kin?.name && `${s.next_of_kin.name} (${s.next_of_kin.relationship}), ${s.next_of_kin.phone}`} />
              <Item k="Sponsor" v={ob.sponsor && `${ob.sponsor.name} (${ob.sponsor.relationship}), ${ob.sponsor.phone}`} />
              <Item k="Emergency" v={ob.emergency_contact && `${ob.emergency_contact.name}, ${ob.emergency_contact.phone}`} />
            </dl>
          </Card>
        </div>
      </div>
    </>
  )
}

function Item({ k, v }: { k: string; v: React.ReactNode }) {
  return <div><dt className="text-xs text-muted">{k}</dt><dd>{v || '—'}</dd></div>
}

function StatusChange({ s }: { s: Student }) {
  const client = useQueryClient()
  const [form, setForm] = useState({ status: s.allowed_statuses[0], reason: '', effective_on: '' })
  const change = useMutation({
    mutationFn: () => api<{ data: Student }>(`/students/${s.id}/status`, { method: 'POST', body: { ...form, effective_on: form.effective_on || null } }).then(r => r.data),
    onSuccess: data => { client.setQueryData(['students', s.id], data); setForm({ ...form, reason: '' }) },
  })
  const submit = (e: FormEvent) => { e.preventDefault(); change.mutate() }
  return (
    <form onSubmit={submit} className="mt-4 grid gap-3 border-t border-line pt-4 sm:grid-cols-3">
      <SelectField label="Change status to" value={form.status} onChange={e => setForm({ ...form, status: e.target.value })}>
        {s.allowed_statuses.map(x => <option key={x} value={x}>{x}</option>)}
      </SelectField>
      <TextField label="Effective on" type="date" value={form.effective_on} onChange={e => setForm({ ...form, effective_on: e.target.value })} hint="Today if blank" />
      <TextAreaField label="Reason" required value={form.reason} onChange={e => setForm({ ...form, reason: e.target.value })} />
      <div className="sm:col-span-3">
        {change.error && <ErrorNotice error={new Error(errorList(change.error).join(' '))} />}
        <Button type="submit" variant="secondary" busy={change.isPending}>Record status change</Button>
      </div>
    </form>
  )
}
