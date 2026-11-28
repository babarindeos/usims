import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Button, Card, DataTable, ErrorNotice, formatDate, PageHeader, SelectField } from '../../design-system'
import { api } from '../../lib/api'
import { useMutate } from '../../lib/useMutate'
import { askReason } from '../academic/shared'
import { naira } from './shared'

type Row = { id: string; purpose: string; amount: number; status: string; method: string | null; reference: string | null; paid_on: string | null; submitted_at: string | null; remarks: string | null; evidence_url: string | null; applicant: string; application_no: string; exercise: string }

/** Bursary checks recorded bank payments against the statement. Nothing here talks to a payment gateway. */
export function PaymentsPage() {
  const [status, setStatus] = useState('submitted')
  const key = ['admission-payments', status]
  const rows = useQuery({ queryKey: key, queryFn: () => api<{ data: Row[] }>(`/admission-payments?status=${status}`).then(r => r.data) })
  const review = useMutate<{ id: string; approve: boolean; remarks?: string | null }>('POST', b => `/admission-payments/${b.id}/review`, [['admission-payments']])

  return (
    <>
      <PageHeader title="Payment verification" description="Application and acceptance fees paid at the bank. Confirm each against the bank statement before verifying." />
      <Card>
        <SelectField label="Show" value={status} onChange={e => setStatus(e.target.value)} className="mb-3 max-w-xs">
          <option value="submitted">Awaiting verification</option><option value="verified">Verified</option><option value="rejected">Rejected</option>
        </SelectField>
        <ErrorNotice error={review.error} />
        <DataTable rows={rows.data} loading={rows.isLoading} rowKey={r => r.id} empty="Nothing in this queue." columns={[
          { key: 'a', header: 'Applicant', render: r => <>{r.applicant}<span className="block text-xs text-muted">{r.application_no} · {r.exercise}</span></> },
          { key: 'p', header: 'Fee', render: r => <>{r.purpose === 'application_fee' ? 'Application' : 'Acceptance'}<span className="block text-xs text-muted">{naira(r.amount)}</span></> },
          { key: 'r', header: 'Reference', render: r => <span className="font-mono text-xs">{r.reference}</span> },
          { key: 'd', header: 'Paid on', render: r => formatDate(r.paid_on) },
          { key: 'e', header: 'Evidence', render: r => (r.evidence_url ? <a className="text-brand" href={r.evidence_url} target="_blank" rel="noreferrer">View</a> : '—') },
          { key: 'x', header: '', render: r => (r.status === 'submitted' ? (
            <div className="flex gap-1">
              <Button variant="secondary" busy={review.isPending && review.variables?.id === r.id && review.variables.approve} onClick={() => review.mutate({ id: r.id, approve: true })}>Verify</Button>
              <Button variant="ghost" onClick={() => { const m = askReason('Why can this payment not be verified? The applicant is told.'); if (m) review.mutate({ id: r.id, approve: false, remarks: m }) }}>Reject</Button>
            </div>
          ) : r.remarks) },
        ]} />
      </Card>
    </>
  )
}
