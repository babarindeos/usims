import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useParams } from 'react-router-dom'
import { useAuth } from '../../app/auth'
import { Button, Card, DataTable, ErrorNotice, formatDate, PageHeader, SelectField, Spinner, StatusBadge } from '../../design-system'
import { api, errorList } from '../../lib/api'
import type { Application, Exercise } from '../../lib/types'
import { askReason } from '../academic/shared'
import { OUTCOME_LABELS, naira } from './shared'

/** One application, as Registry or the department sees it (PRD 5.9). Opening it is written to the access log. */
export function ApplicationReviewPage() {
  const { id = '' } = useParams()
  const { can } = useAuth()
  const client = useQueryClient()
  const key = ['admission-applications', id]
  const app = useQuery({ queryKey: key, queryFn: () => api<{ data: Application }>(`/admission-applications/${id}`).then(r => r.data) })
  const act = useMutation({
    mutationFn: ({ action, body }: { action: string; body?: Record<string, unknown> }) => api<{ data: Application }>(`/admission-applications/${id}/${action}`, { method: 'POST', body: body ?? {} }).then(r => r.data),
    onSuccess: data => { client.setQueryData(key, data); client.invalidateQueries({ queryKey: ['admission-applications'], exact: false }) },
  })
  const review = useMutation({
    mutationFn: ({ doc, status, remarks }: { doc: string; status: string; remarks?: string | null }) => api<{ data: Application }>(`/admission-documents/${doc}`, { method: 'PATCH', body: { status, remarks } }).then(r => r.data),
    onSuccess: data => client.setQueryData(key, data),
  })

  if (app.isLoading) return <Spinner />
  if (!app.data) return <ErrorNotice error={app.error} />
  const a = app.data
  const p = a.applicant
  const screening = a.screenings?.[0]
  const process = can('admissions.process')
  const locked = ['offered', 'withdrawn'].includes(a.status)

  const run = (action: string, question?: string, required = true) => {
    if (!question) return act.mutate({ action })
    const reason = askReason(question)
    if (!reason && required) return
    act.mutate({ action, body: { reason } })
  }

  return (
    <>
      <PageHeader title={`${p?.full_name} · ${a.application_no}`} description={`${a.exercise?.name} · submitted ${formatDate(a.submitted_at, true)}`}
        actions={<><StatusBadge status={a.status} /><Link to="/admissions/applications" className="text-sm text-brand">Back to applications</Link></>} />
      {act.error && <div className="mb-4"><ErrorNotice error={new Error(errorList(act.error).join(' '))} /></div>}

      {process && !locked && (
        <Card className="mb-6">
          <div className="flex flex-wrap gap-2">
            <Button variant="secondary" busy={act.isPending && act.variables?.action === 'screen'} onClick={() => run('screen')}>Run screening again</Button>
            {['submitted', 'under_review', 'on_hold'].includes(a.status) && (
              <Button busy={act.isPending && act.variables?.action === 'recommend'}
                onClick={() => (a.screening_outcome === 'manual_review' ? run('recommend', 'Manual review: record why this applicant is recommended') : run('recommend', 'Remarks for the committee (optional)', false))}>
                Recommend for admission
              </Button>
            )}
            {a.status !== 'on_hold' && a.status !== 'rejected' && <Button variant="secondary" onClick={() => run('hold', 'Why is this application on hold?')}>Put on hold</Button>}
            {a.status !== 'rejected' && <Button variant="danger" onClick={() => run('reject', 'Why is this application rejected? The applicant is told.')}>Reject</Button>}
            {a.corrections_open
              ? <Button variant="ghost" onClick={() => run('close-corrections', 'Why are corrections closed?')}>Close corrections</Button>
              : <Button variant="ghost" onClick={() => run('open-corrections', 'What must the applicant correct? They are told.')}>Open for corrections</Button>}
          </div>
          {can('admissions.change_programme') && a.exercise && <ChangeProgramme a={a} onChange={(choice, reason) => act.mutate({ action: 'change-programme', body: { exercise_programme_id: choice, reason } })} />}
        </Card>
      )}

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
          <Card title="Screening" actions={a.screening_outcome && <StatusBadge status={a.screening_outcome} />}>
            {screening ? (
              <>
                <p className="mb-2 text-xs text-muted">{OUTCOME_LABELS[screening.outcome] ?? screening.outcome} · run {formatDate(screening.at, true)}</p>
                <ul className="space-y-1 text-sm">
                  {screening.checks.map(c => (
                    <li key={c.check} className="flex gap-2">
                      <span aria-hidden className={c.passed === true ? 'text-ok' : c.passed === false ? 'text-bad' : 'text-warn'}>{c.passed === true ? '✓' : c.passed === false ? '✕' : '…'}</span>
                      <span>{c.detail}</span>
                    </li>
                  ))}
                </ul>
              </>
            ) : <p className="text-sm text-muted">Not screened yet.</p>}
          </Card>

          <Card title="Documents">
            <ErrorNotice error={review.error} />
            <DataTable rows={a.documents} rowKey={d => d.id} empty="No documents uploaded." columns={[
              { key: 'n', header: 'Document', render: d => <><a className="text-brand" href={d.url} target="_blank" rel="noreferrer">{d.type.name}</a><span className="block text-xs text-muted">{d.original_name} · {Math.round(d.size / 1024)} KB</span></> },
              { key: 's', header: 'Status', render: d => <><StatusBadge status={d.status} />{d.remarks && <span className="block text-xs text-muted">{d.remarks}</span>}</> },
              { key: 'a', header: '', render: d => process && !locked && (
                <div className="flex gap-1">
                  {d.status !== 'verified' && <Button variant="ghost" onClick={() => review.mutate({ doc: d.id, status: 'verified' })}>Verify</Button>}
                  {d.status !== 'rejected' && <Button variant="ghost" onClick={() => { const r = askReason('Why is this document rejected? The applicant is told.'); if (r) review.mutate({ doc: d.id, status: 'rejected', remarks: r }) }}>Reject</Button>}
                </div>
              ) },
            ]} />
          </Card>

          <Card title="O'Level results">
            {a.sittings?.length ? a.sittings.map(s => (
              <div key={s.exam_number} className="mb-3">
                <p className="text-sm font-medium">{s.exam_body} {s.exam_year} · {s.exam_number}</p>
                <p className="text-sm">{s.results.map(r => <span key={r.subject} className={`mr-3 inline-block ${r.credit ? '' : 'text-muted'}`}>{r.subject_name} {r.grade}</span>)}</p>
              </div>
            )) : <p className="text-sm text-muted">None entered.</p>}
            {!!a.education?.length && (
              <>
                <p className="mt-4 text-sm font-medium">Previous education</p>
                <ul className="text-sm">{a.education.map((e, i) => <li key={i}>{e.qualification} · {e.institution} ({e.year_obtained}){e.grade && `, ${e.grade}`}</li>)}</ul>
              </>
            )}
          </Card>

          <Card title="History">
            <ol className="space-y-2 text-sm">
              {a.history?.map((h, i) => <li key={i}><span className="text-muted">{formatDate(h.at, true)}</span> · {h.from ? `${h.from} → ` : ''}{h.to}{h.reason && ` · ${h.reason}`}{h.by && <span className="text-muted"> ({h.by})</span>}</li>)}
            </ol>
          </Card>
        </div>

        <div className="space-y-6">
          <Card title="Applicant">
            <dl className="space-y-2 text-sm">
              <Row k="Person no" v={p?.person_no} />
              <Row k="Date of birth" v={formatDate(p?.date_of_birth)} />
              <Row k="Gender" v={p?.gender} />
              <Row k="State / LGA" v={`${p?.state_of_origin ?? '—'} / ${p?.lga ?? '—'}`} />
              <Row k="Email" v={p?.email} />
              <Row k="Phone" v={p?.phone} />
              <Row k="Next of kin" v={a.next_of_kin.name && `${a.next_of_kin.name} (${a.next_of_kin.relationship}), ${a.next_of_kin.phone}`} />
            </dl>
          </Card>
          <Card title="Choice and UTME">
            <dl className="space-y-2 text-sm">
              <Row k="First choice" v={a.choice && `${a.choice.programme.name} (${a.choice.entry_level})`} />
              <Row k="Second choice" v={a.second_choice && `${a.second_choice.programme.name} (${a.second_choice.entry_level})`} />
              <Row k="JAMB no" v={a.jamb.reg_no} />
              <Row k="UTME score" v={a.jamb.score} />
              <Row k="UTME subjects" v={a.jamb.subjects.join(', ')} />
            </dl>
          </Card>
          <Card title="Payments">
            {a.payments?.map(pay => (
              <div key={pay.id} className="mb-2 text-sm">
                <p className="flex items-center justify-between"><span>{pay.purpose === 'application_fee' ? 'Application fee' : 'Acceptance fee'} {naira(pay.amount)}</span><StatusBadge status={pay.status} /></p>
                {pay.reference && <p className="text-xs text-muted">Ref {pay.reference} · {formatDate(pay.paid_on)}</p>}
              </div>
            ))}
          </Card>
          {a.offer && (
            <Card title="Offer">
              <p className="text-sm"><Link to="/admissions/offers" className="text-brand">{a.offer.admission_no}</Link> · <StatusBadge status={a.offer.status} /></p>
              <p className="text-xs text-muted">Accept by {formatDate(a.offer.accept_by)}</p>
            </Card>
          )}
        </div>
      </div>
    </>
  )
}

function Row({ k, v }: { k: string; v: React.ReactNode }) {
  return <div className="grid grid-cols-3 gap-2"><dt className="text-muted">{k}</dt><dd className="col-span-2">{v || '—'}</dd></div>
}

function ChangeProgramme({ a, onChange }: { a: Application; onChange: (choice: number, reason: string) => void }) {
  const ex = useQuery({ queryKey: ['admission-exercises', a.exercise!.id], queryFn: () => api<{ data: Exercise }>(`/admission-exercises/${a.exercise!.id}`).then(r => r.data) })
  const [choice, setChoice] = useState('')
  const options = ex.data?.programmes?.filter(p => p.active && p.id !== a.choice?.id) ?? []
  if (!options.length) return null
  return (
    <div className="mt-3 flex flex-wrap items-end gap-2">
      <SelectField label="Move to another programme" value={choice} onChange={e => setChoice(e.target.value)} className="min-w-64">
        <option value="">Choose…</option>
        {options.map(o => <option key={o.id} value={o.id}>{o.programme.code} {o.programme.name} ({o.entry_level})</option>)}
      </SelectField>
      <Button variant="secondary" disabled={!choice} onClick={() => { const r = askReason('Why is the programme changed? The applicant is told.'); if (r) onChange(Number(choice), r) }}>Change programme</Button>
    </div>
  )
}
