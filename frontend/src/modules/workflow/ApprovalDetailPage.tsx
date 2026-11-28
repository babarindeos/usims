import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Button, Card, ErrorNotice, PageHeader, Spinner, StatusBadge, TextAreaField, formatDate } from '../../design-system'
import { api } from '../../lib/api'
import type { WorkflowItem } from '../../lib/types'
import { useMutate } from '../../lib/useMutate'

/** Where each kind of approval subject is shown, so the approver can review it before deciding. */
const SUBJECT_PAGES: Record<string, { label: string; path: (uuid: string) => string }> = {
  AdmissionList: { label: 'Review the admission list', path: u => `/admissions/lists/${u}` },
  CurriculumVersion: { label: 'Review the curriculum', path: u => `/academic/curricula/${u}` },
}

export function ApprovalDetailPage() {
  const { id } = useParams()
  const item = useQuery({ queryKey: ['workflow', id], queryFn: () => api<{ data: WorkflowItem }>(`/workflows/${id}`).then(r => r.data) })
  const act = useMutate<{ action: string; remarks: string }>('POST', `/workflows/${id}/actions`, [['workflow', id!], ['inbox'], ['workflows-mine']])
  const [remarks, setRemarks] = useState('')

  if (item.isLoading) return <Spinner />
  if (!item.data) return <ErrorNotice error={item.error} />
  const w = item.data

  return (
    <>
      <PageHeader title={w.reference} description={`${w.workflow} · ${w.org_unit?.name ?? ''} · submitted by ${w.initiated_by?.name ?? 'unknown'}`}
        actions={w.subject.uuid && SUBJECT_PAGES[w.subject.type] && <Link className="text-sm font-medium text-brand" to={SUBJECT_PAGES[w.subject.type].path(w.subject.uuid)}>{SUBJECT_PAGES[w.subject.type].label}</Link>} />
      <div className="grid gap-6 lg:grid-cols-3">
        <Card title="History" className="lg:col-span-2">
          <ol className="space-y-3 text-sm">
            {w.actions?.map(a => (
              <li key={a.reference} className="border-l-2 border-line pl-3">
                <p><span className="font-medium capitalize">{a.action}</span>{a.stage && <span className="text-muted"> at {a.stage}</span>} by {a.user ?? 'system'}{a.role && <span className="text-muted"> ({a.role.replace(/_/g, ' ')})</span>}</p>
                <p className="text-xs text-muted">{formatDate(a.at, true)} · {a.reference}</p>
                {a.remarks && <p className="mt-1">{a.remarks}</p>}
              </li>
            ))}
          </ol>
        </Card>
        <Card title="Decision">
          <p className="mb-3 text-sm">Status: <StatusBadge status={w.status} />{w.current_stage && <> · stage <strong>{w.current_stage.name}</strong></>}</p>
          {w.status === 'in_progress' ? (
            <div className="space-y-3">
              <ErrorNotice error={act.error} />
              <TextAreaField label="Remarks (required to reject or return)" value={remarks} onChange={e => setRemarks(e.target.value)} />
              <div className="flex flex-wrap gap-2">
                <Button busy={act.isPending} onClick={() => act.mutate({ action: 'approve', remarks })}>Approve</Button>
                <Button variant="secondary" busy={act.isPending} onClick={() => act.mutate({ action: 'return', remarks })}>Return for correction</Button>
                <Button variant="danger" busy={act.isPending} onClick={() => act.mutate({ action: 'reject', remarks })}>Reject</Button>
              </div>
            </div>
          ) : <p className="text-sm text-muted">No decision is pending.</p>}
        </Card>
      </div>
    </>
  )
}
