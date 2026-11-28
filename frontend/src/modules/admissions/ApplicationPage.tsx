import { useState, type FormEvent } from 'react'
import { Link, useParams } from 'react-router-dom'
import { Button, Card, ErrorNotice, formatDate, PageHeader, SelectField, Spinner, StatusBadge, TextAreaField, TextField } from '../../design-system'
import { ApiError, errorList } from '../../lib/api'
import type { Application } from '../../lib/types'
import { useApplication, useApplicationAction } from './applicantApi'
import { DocumentsSection, PaymentSection } from './ApplicantDocuments'
import { EducationSection, OlevelSection } from './ApplicantQualifications'
import { OfferSection } from './OfferSection'
import { useAdmissionConfig } from './shared'
import { SaveRow } from './ui'

export function ApplicationPage() {
  const { id = '' } = useParams()
  const app = useApplication(id)
  if (app.isLoading) return <Spinner />
  if (!app.data) return <ErrorNotice error={app.error ?? new Error('Application not found.')} />
  const a = app.data

  return (
    <>
      <PageHeader title={a.exercise?.name ?? 'Application'}
        description={`${a.application_no ?? 'Draft, not yet submitted'}${a.exercise ? ` · closes ${formatDate(a.exercise.closes_at, true)}` : ''}`}
        actions={<><StatusBadge status={a.status} /><Link to="/applicant" className="text-sm text-brand">All applications</Link></>} />

      {a.offer && <OfferSection application={a} />}

      {a.corrections_open && <p role="status" className="mb-4 rounded-md border border-warn/30 bg-warn/5 px-3 py-2 text-sm">Registry has opened your application for corrections. Make the changes, then submit again.</p>}
      {a.exercise?.instructions && a.editable && <Card title="Instructions" className="mb-6"><p className="whitespace-pre-line text-sm">{a.exercise.instructions}</p></Card>}

      {a.editable ? (
        <div className="space-y-6">
          <BiodataSection a={a} />
          <DetailsSection a={a} />
          <OlevelSection a={a} />
          <EducationSection a={a} />
          <DocumentsSection a={a} stage="application" />
          <PaymentSection a={a} purpose="application_fee" />
          <SubmitSection a={a} />
        </div>
      ) : !a.offer && <SubmittedSummary a={a} />}
    </>
  )
}

function SubmittedSummary({ a }: { a: Application }) {
  return (
    <Card title="Your application">
      <dl className="grid gap-3 text-sm sm:grid-cols-3">
        <div><dt className="text-muted">Submitted</dt><dd>{formatDate(a.submitted_at, true)}</dd></div>
        <div><dt className="text-muted">Programme</dt><dd>{a.choice ? `${a.choice.programme.name}, ${a.choice.entry_level} level` : '—'}</dd></div>
        <div><dt className="text-muted">Status</dt><dd><StatusBadge status={a.status} /></dd></div>
      </dl>
      <p className="mt-4 text-sm text-muted">Your application is locked while the institution processes it. You will be notified when there is a decision.</p>
      {!!a.documents?.length && (
        <ul className="mt-4 space-y-1 text-sm">
          {a.documents.map(d => <li key={d.id} className="flex items-center gap-2">{d.type.name} <StatusBadge status={d.status} />{d.remarks && <span className="text-xs text-muted">{d.remarks}</span>}</li>)}
        </ul>
      )}
    </Card>
  )
}

function BiodataSection({ a }: { a: Application }) {
  const save = useApplicationAction<Record<string, string>>(a.id, 'PUT', '/biodata')
  const p = a.applicant
  const [form, setForm] = useState({
    title: p?.title ?? '', surname: p?.surname ?? '', first_name: p?.first_name ?? '', other_names: p?.other_names ?? '',
    date_of_birth: p?.date_of_birth ?? '', gender: p?.gender ?? '', nationality: p?.nationality ?? 'Nigerian',
    state_of_origin: p?.state_of_origin ?? '', lga: p?.lga ?? '', phone: p?.phone ?? '', address: p?.address ?? '',
  })
  const err = save.error instanceof ApiError ? save.error : null
  const set = (k: keyof typeof form, v: string) => setForm({ ...form, [k]: v })
  const submit = (e: FormEvent) => { e.preventDefault(); save.mutate(form) }

  return (
    <Card title="1. Personal details">
      <form onSubmit={submit} className="grid gap-3 sm:grid-cols-4">
        <TextField label="Title" value={form.title} onChange={e => set('title', e.target.value)} placeholder="Mr, Ms…" />
        <TextField label="Surname" required value={form.surname} onChange={e => set('surname', e.target.value)} error={err?.fieldError('surname')} />
        <TextField label="First name" required value={form.first_name} onChange={e => set('first_name', e.target.value)} error={err?.fieldError('first_name')} />
        <TextField label="Other names" value={form.other_names} onChange={e => set('other_names', e.target.value)} />
        <TextField label="Date of birth" type="date" required value={form.date_of_birth} onChange={e => set('date_of_birth', e.target.value)} error={err?.fieldError('date_of_birth')} />
        <SelectField label="Gender" required value={form.gender} onChange={e => set('gender', e.target.value)} error={err?.fieldError('gender')}>
          <option value="">Choose…</option><option value="female">Female</option><option value="male">Male</option>
        </SelectField>
        <TextField label="Nationality" required value={form.nationality} onChange={e => set('nationality', e.target.value)} error={err?.fieldError('nationality')} />
        <TextField label="Phone" type="tel" required value={form.phone} onChange={e => set('phone', e.target.value)} error={err?.fieldError('phone')} />
        <TextField label="State of origin" required value={form.state_of_origin} onChange={e => set('state_of_origin', e.target.value)} error={err?.fieldError('state_of_origin')} />
        <TextField label="Local government area" required value={form.lga} onChange={e => set('lga', e.target.value)} error={err?.fieldError('lga')} />
        <TextAreaField label="Residential address" required value={form.address} onChange={e => set('address', e.target.value)} error={err?.fieldError('address')} className="sm:col-span-2" />
        <SaveRow busy={save.isPending} saved={save.isSuccess} error={err && !err.problem.errors ? err : null} />
      </form>
    </Card>
  )
}

function DetailsSection({ a }: { a: Application }) {
  const save = useApplicationAction<Record<string, unknown>>(a.id, 'PUT', '/details')
  const subjects = useAdmissionConfig('subjects')
  const jamb = a.exercise?.category?.requires_jamb
  const [form, setForm] = useState({
    exercise_programme_id: a.choice?.id ? String(a.choice.id) : '', second_choice_id: a.second_choice?.id ? String(a.second_choice.id) : '',
    kin_name: a.next_of_kin.name ?? '', kin_relationship: a.next_of_kin.relationship ?? '', kin_phone: a.next_of_kin.phone ?? '', kin_address: a.next_of_kin.address ?? '',
    jamb_reg_no: a.jamb.reg_no ?? '', jamb_year: a.jamb.year ? String(a.jamb.year) : String(new Date().getFullYear()), utme_score: a.jamb.score != null ? String(a.jamb.score) : '',
  })
  const [utme, setUtme] = useState<string[]>(a.jamb.subjects.length ? a.jamb.subjects : ['', '', '', ''])
  const err = save.error instanceof ApiError ? save.error : null
  const set = (k: keyof typeof form, v: string) => setForm({ ...form, [k]: v })
  const choices = a.exercise?.programmes ?? []

  const submit = (e: FormEvent) => {
    e.preventDefault()
    save.mutate({
      exercise_programme_id: form.exercise_programme_id ? Number(form.exercise_programme_id) : null,
      second_choice_id: form.second_choice_id ? Number(form.second_choice_id) : null,
      next_of_kin: { name: form.kin_name, relationship: form.kin_relationship, phone: form.kin_phone, address: form.kin_address || null },
      ...(jamb ? { jamb_reg_no: form.jamb_reg_no || null, jamb_year: form.jamb_year ? Number(form.jamb_year) : null, utme_score: form.utme_score === '' ? null : Number(form.utme_score), utme_subjects: utme.filter(Boolean) } : {}),
    })
  }

  return (
    <Card title="2. Programme, next of kin and entrance examination">
      <form onSubmit={submit} className="grid gap-3 sm:grid-cols-4">
        <SelectField label="First choice" required value={form.exercise_programme_id} onChange={e => set('exercise_programme_id', e.target.value)} error={err?.fieldError('exercise_programme_id')} className="sm:col-span-2">
          <option value="">Choose…</option>
          {choices.map(c => <option key={c.id} value={c.id}>{c.programme.name}, {c.entry_level} level</option>)}
        </SelectField>
        <SelectField label="Second choice (optional)" value={form.second_choice_id} onChange={e => set('second_choice_id', e.target.value)} error={err?.fieldError('second_choice_id')} className="sm:col-span-2">
          <option value="">None</option>
          {choices.filter(c => String(c.id) !== form.exercise_programme_id).map(c => <option key={c.id} value={c.id}>{c.programme.name}, {c.entry_level} level</option>)}
        </SelectField>
        <TextField label="Next of kin name" required value={form.kin_name} onChange={e => set('kin_name', e.target.value)} error={err?.fieldError('next_of_kin.name')} />
        <TextField label="Relationship" required value={form.kin_relationship} onChange={e => set('kin_relationship', e.target.value)} error={err?.fieldError('next_of_kin.relationship')} />
        <TextField label="Next of kin phone" required value={form.kin_phone} onChange={e => set('kin_phone', e.target.value)} error={err?.fieldError('next_of_kin.phone')} />
        <TextField label="Next of kin address" value={form.kin_address} onChange={e => set('kin_address', e.target.value)} />
        {jamb && (
          <>
            <TextField label="JAMB registration number" required value={form.jamb_reg_no} onChange={e => set('jamb_reg_no', e.target.value.toUpperCase())} error={err?.fieldError('jamb_reg_no')} />
            <TextField label="JAMB year" type="number" required value={form.jamb_year} onChange={e => set('jamb_year', e.target.value)} error={err?.fieldError('jamb_year')} />
            <TextField label="UTME score" type="number" min={0} max={400} required value={form.utme_score} onChange={e => set('utme_score', e.target.value)} error={err?.fieldError('utme_score')} />
            <div />
            {utme.map((s, i) => (
              <SelectField key={i} label={`UTME subject ${i + 1}`} value={s} onChange={e => setUtme(utme.map((x, j) => (j === i ? e.target.value : x)))} error={err?.fieldError(`utme_subjects.${i}`)}>
                <option value="">Choose…</option>
                {subjects.data?.map(x => <option key={x.code} value={x.code}>{x.name}</option>)}
              </SelectField>
            ))}
          </>
        )}
        <SaveRow busy={save.isPending} saved={save.isSuccess} error={err && !err.problem.errors ? err : null} />
      </form>
    </Card>
  )
}

function SubmitSection({ a }: { a: Application }) {
  const submit = useApplicationAction(a.id, 'POST', '/submit')
  const missing = a.missing ?? []
  return (
    <Card title="Review and submit">
      {missing.length ? (
        <>
          <p className="mb-2 text-sm">Complete these before you submit:</p>
          <ul className="mb-4 list-disc pl-5 text-sm text-warn">{missing.map(m => <li key={m}>{m}</li>)}</ul>
        </>
      ) : <p className="mb-4 text-sm">Everything required is in place. Once you submit, the application is locked unless Registry opens it for corrections.</p>}
      {submit.error && <ul role="alert" className="mb-3 list-disc pl-5 text-sm text-bad">{errorList(submit.error).map(m => <li key={m}>{m}</li>)}</ul>}
      <Button disabled={missing.length > 0} busy={submit.isPending} onClick={() => submit.mutate(undefined)}>{a.corrections_open ? 'Resubmit application' : 'Submit application'}</Button>
    </Card>
  )
}
