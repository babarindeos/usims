import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Card, DataTable, PageHeader, StatusBadge, formatDate } from '../../design-system'
import { api } from '../../lib/api'
import type { WorkflowItem } from '../../lib/types'

export function ApprovalsPage() {
  const inbox = useQuery({ queryKey: ['inbox'], queryFn: () => api<{ data: WorkflowItem[] }>('/workflows/inbox').then(r => r.data) })
  const mine = useQuery({ queryKey: ['workflows-mine'], queryFn: () => api<{ data: WorkflowItem[] }>('/workflows/mine').then(r => r.data) })

  const columns = [
    { key: 'ref', header: 'Reference', render: (i: WorkflowItem) => <Link to={`/approvals/${i.id}`} className="font-medium text-brand">{i.reference}</Link> },
    { key: 'wf', header: 'Process', render: (i: WorkflowItem) => i.workflow },
    { key: 'unit', header: 'Unit', render: (i: WorkflowItem) => i.org_unit?.name ?? '—' },
    { key: 'stage', header: 'Stage', render: (i: WorkflowItem) => i.current_stage?.name ?? '—' },
    { key: 'status', header: 'Status', render: (i: WorkflowItem) => <StatusBadge status={i.status} /> },
    { key: 'due', header: 'Due', render: (i: WorkflowItem) => formatDate(i.due_at) },
  ]

  return (
    <>
      <PageHeader title="Approvals" description="Items waiting for your decision, and submissions you have made." />
      <Card title="Waiting for you" className="mb-6"><DataTable rows={inbox.data} loading={inbox.isLoading} rowKey={i => i.id} columns={columns} empty="Nothing is waiting for you." /></Card>
      <Card title="Your submissions"><DataTable rows={mine.data} loading={mine.isLoading} rowKey={i => i.id} columns={columns} empty="You have not submitted anything for approval." /></Card>
    </>
  )
}
