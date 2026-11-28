import { useRef, useState, type FormEvent } from 'react'
import { Button, Card, ErrorNotice, formatDate, StatusBadge, TextField } from '../../design-system'
import { ApiError, errorList } from '../../lib/api'
import type { Application, AppPayment } from '../../lib/types'
import { useApplicationAction, useUpload } from './applicantApi'
import { naira } from './shared'

export function DocumentsSection({ a, stage }: { a: Application; stage: 'application' | 'onboarding' }) {
  const required = (a.exercise?.documents ?? []).filter(d => d.stage === stage)
  const send = useUpload(a.id)
  if (!required.length) return null

  return (
    <Card title={stage === 'application' ? '5. Documents' : 'Onboarding documents'}>
      <p className="mb-3 text-xs text-muted">PDF, JPG or PNG. Uploading again replaces the earlier file.</p>
      {send.error && <ErrorNotice error={new Error(errorList(send.error).join(' '))} />}
      <ul className="divide-y divide-line">
        {required.map(d => {
          const current = a.documents?.find(x => x.type.code === d.code)
          return (
            <li key={d.code} className="flex flex-wrap items-center justify-between gap-3 py-3">
              <div>
                <p className="text-sm font-medium">{d.name}{d.required && <span className="text-bad"> *</span>}</p>
                <p className="text-xs text-muted">
                  {current ? <>{current.original_name} · uploaded {formatDate(current.uploaded_at)} · <a className="text-brand" href={current.url} target="_blank" rel="noreferrer">View</a></> : `Not uploaded · up to ${d.max_kb} KB`}
                </p>
                {current?.status === 'rejected' && current.remarks && <p className="text-xs text-bad">Rejected: {current.remarks}</p>}
              </div>
              <div className="flex items-center gap-2">
                {current && <StatusBadge status={current.status} />}
                <FilePick label={current ? 'Replace' : 'Upload'} busy={send.isPending && send.variables?.type === d.code} onPick={file => send.mutate({ type: d.code, file })} />
              </div>
            </li>
          )
        })}
      </ul>
    </Card>
  )
}

function FilePick({ label, busy, onPick }: { label: string; busy: boolean; onPick: (f: File) => void }) {
  const ref = useRef<HTMLInputElement>(null)
  return (
    <>
      <input ref={ref} type="file" accept=".pdf,.jpg,.jpeg,.png" className="sr-only" aria-label={label}
        onChange={e => { const f = e.target.files?.[0]; if (f) onPick(f); e.target.value = '' }} />
      <Button type="button" variant="secondary" busy={busy} onClick={() => ref.current?.click()}>{label}</Button>
    </>
  )
}

const PURPOSE = { application_fee: 'Application fee', acceptance_fee: 'Acceptance fee' }

/** The candidate pays at the bank and records the payment; Bursary verifies it. No online gateway is configured yet. */
export function PaymentSection({ a, purpose }: { a: Application; purpose: AppPayment['purpose'] }) {
  const payment = a.payments?.find(p => p.purpose === purpose)
  const send = useUpload(a.id)
  const record = useApplicationAction<{ reference: string; paid_on: string; evidence_document_id: string }>(a.id, 'POST', `/payments/${purpose}`)
  const [reference, setReference] = useState('')
  const [paidOn, setPaidOn] = useState('')
  const [file, setFile] = useState<File | null>(null)
  if (!payment) return null
  if (payment.amount <= 0) return null

  const canRecord = payment.status === 'awaiting_payment' || payment.status === 'rejected'
  const err = record.error instanceof ApiError ? record.error : null

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    if (!file) return
    const updated = await send.mutateAsync({ type: 'PAYMENT_EVIDENCE', file })
    const evidence = updated.documents?.find(d => d.type.code === 'PAYMENT_EVIDENCE')
    if (evidence) record.mutate({ reference, paid_on: paidOn, evidence_document_id: evidence.id })
  }

  return (
    <Card title={`${purpose === 'application_fee' ? '6. ' : ''}${PURPOSE[purpose]}: ${naira(payment.amount)}`} actions={<StatusBadge status={payment.status} />}>
      <p className="mb-3 text-sm text-muted">Online payment is not configured for this institution yet. Pay at the bank using the details in the exercise instructions, then record the payment here. Bursary checks it against the bank statement.</p>
      {payment.status === 'rejected' && payment.remarks && <p className="mb-3 text-sm text-bad">Bursary could not verify your last payment: {payment.remarks}</p>}
      {payment.reference && !canRecord && <p className="text-sm">Reference {payment.reference}, paid {formatDate(payment.paid_on)}. {payment.status === 'verified' ? 'Verified by Bursary.' : 'Awaiting verification by Bursary.'}</p>}
      {canRecord && (
        <form onSubmit={submit} className="grid gap-3 sm:grid-cols-4">
          <TextField label="Bank or teller reference" required value={reference} onChange={e => setReference(e.target.value)} error={err?.fieldError('reference')} />
          <TextField label="Date paid" type="date" required value={paidOn} onChange={e => setPaidOn(e.target.value)} error={err?.fieldError('paid_on')} />
          <div className="sm:col-span-2">
            <label className="mb-1 block text-sm font-medium" htmlFor={`evidence-${purpose}`}>Teller or receipt (PDF, JPG, PNG)</label>
            <input id={`evidence-${purpose}`} type="file" required accept=".pdf,.jpg,.jpeg,.png" className="text-sm" onChange={e => setFile(e.target.files?.[0] ?? null)} />
          </div>
          <div className="sm:col-span-4">
            {(send.error || (err && !err.fieldError('reference') && !err.fieldError('paid_on'))) && <ErrorNotice error={new Error(errorList(send.error ?? err).join(' '))} />}
            <Button type="submit" variant="secondary" busy={send.isPending || record.isPending} className="mt-2">Record payment</Button>
          </div>
        </form>
      )}
    </Card>
  )
}
