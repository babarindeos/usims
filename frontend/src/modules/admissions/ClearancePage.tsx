import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Button, Card, ErrorNotice, formatDate, PageHeader, SelectField, Spinner, StatusBadge, TextField } from '../../design-system'
import { api, errorList } from '../../lib/api'
import { useMutate } from '../../lib/useMutate'

type Item = {
  id: number; unit: string; instructions: string | null; reviewer_unit: string | null; status: string; admission_no: string; name: string; programme: string
  offer_id: string; onboarding: Record<string, Record<string, string> | boolean> | null; documents: { name: string; status: string; url: string }[]; updated_at: string
}

/** Each clearance office sees the candidates waiting on it (PRD 6.2). */
export function ClearancePage() {
  const [status, setStatus] = useState('submitted')
  const rows = useQuery({ queryKey: ['clearance-items', status], queryFn: () => api<{ data: Item[] }>(`/clearance-items?status=${status}`).then(r => r.data) })
  return (
    <>
      <PageHeader title="Clearance" description="Check each candidate's documents and onboarding details for your office, then approve or say what needs correcting." />
      <SelectField label="Show" value={status} onChange={e => setStatus(e.target.value)} className="mb-4 max-w-xs">
        <option value="submitted">Waiting for review</option><option value="under_review">Under review</option><option value="requires_correction">Sent back for correction</option>
        <option value="approved">Approved</option><option value="rejected">Rejected</option>
      </SelectField>
      {rows.isLoading ? <Spinner /> : rows.data?.length ? (
        <div className="space-y-4">{rows.data.map(i => <ClearanceCard key={i.id} item={i} />)}</div>
      ) : <Card><p className="text-sm text-muted">No candidates in this queue.</p></Card>}
    </>
  )
}

function ClearanceCard({ item }: { item: Item }) {
  const review = useMutate<{ status: string; remarks: string | null }>('POST', `/clearance-items/${item.id}/review`, [['clearance-items']])
  const [remarks, setRemarks] = useState('')
  const open = ['submitted', 'under_review'].includes(item.status)
  const ob = (item.onboarding ?? {}) as Record<string, Record<string, string>>
  return (
    <Card title={`${item.name} · ${item.admission_no}`} actions={<StatusBadge status={item.status} />}>
      <p className="mb-2 text-xs text-muted">{item.unit}{item.reviewer_unit && ` · ${item.reviewer_unit}`} · {item.programme} · updated {formatDate(item.updated_at, true)}</p>
      {item.instructions && <p className="mb-3 text-sm">{item.instructions}</p>}
      <div className="grid gap-4 sm:grid-cols-2">
        <div>
          <p className="mb-1 text-sm font-medium">Documents</p>
          <ul className="space-y-1 text-sm">{item.documents.map(d => <li key={d.url}><a className="text-brand" href={d.url} target="_blank" rel="noreferrer">{d.name}</a> <StatusBadge status={d.status} /></li>)}</ul>
        </div>
        <div className="text-sm">
          <p className="mb-1 font-medium">Onboarding</p>
          {ob.sponsor && <p>Sponsor: {ob.sponsor.name} ({ob.sponsor.relationship}), {ob.sponsor.phone}</p>}
          {ob.residence && <p>Residence: {ob.residence.type?.replace('_', ' ')}{ob.residence.address && `, ${ob.residence.address}`}</p>}
          {ob.emergency_contact && <p>Emergency: {ob.emergency_contact.name}, {ob.emergency_contact.phone}</p>}
          {ob.medical && (ob.medical.blood_group || ob.medical.genotype) && <p>Medical: {[ob.medical.blood_group, ob.medical.genotype, ob.medical.conditions].filter(Boolean).join(', ')}</p>}
        </div>
      </div>
      {open && (
        <div className="mt-4 flex flex-wrap items-end gap-2">
          <TextField label="Remarks" value={remarks} onChange={e => setRemarks(e.target.value)} className="min-w-64 flex-1" hint="Required when sending back or rejecting; the candidate sees them." />
          {item.status === 'submitted' && <Button variant="ghost" onClick={() => review.mutate({ status: 'under_review', remarks: remarks || null })}>Start review</Button>}
          <Button busy={review.isPending && review.variables?.status === 'approved'} onClick={() => review.mutate({ status: 'approved', remarks: remarks || null })}>Approve</Button>
          <Button variant="secondary" onClick={() => review.mutate({ status: 'requires_correction', remarks: remarks || null })}>Needs correction</Button>
          <Button variant="danger" onClick={() => review.mutate({ status: 'rejected', remarks: remarks || null })}>Reject</Button>
        </div>
      )}
      {review.error && <div className="mt-2"><ErrorNotice error={new Error(errorList(review.error).join(' '))} /></div>}
    </Card>
  )
}
