import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useAuth } from '../../app/auth'
import { Card, PageHeader, Stat, StatusBadge, formatDate } from '../../design-system'
import { api } from '../../lib/api'
import type { AcademicSession, OrgUnit, WorkflowItem } from '../../lib/types'

/** Every figure here is read from the API; nothing is hard-coded. */
export function DashboardPage() {
  const { user } = useAuth()
  const sessions = useQuery({ queryKey: ['sessions'], queryFn: () => api<{ data: AcademicSession[] }>('/academic-sessions').then(r => r.data) })
  const units = useQuery({ queryKey: ['org-units'], queryFn: () => api<{ data: OrgUnit[] }>('/org-units').then(r => r.data) })
  const inbox = useQuery({ queryKey: ['inbox'], queryFn: () => api<{ data: WorkflowItem[] }>('/workflows/inbox').then(r => r.data) })

  const current = sessions.data?.find(s => s.is_current)
  const academic = units.data?.filter(u => u.type?.code === 'department').length

  return (
    <>
      <PageHeader title={`Welcome, ${user?.name ?? ''}`} description="Your workspace across the university." />
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Stat label="Current session" value={current?.name ?? 'Not set'} hint={current ? `${formatDate(current.starts_on)} to ${formatDate(current.ends_on)}` : 'Set one under Sessions'} />
        <Stat label="Awaiting your decision" value={inbox.data?.length ?? '…'} />
        <Stat label="Organisational units" value={units.data?.length ?? '…'} />
        <Stat label="Departments" value={academic ?? '…'} />
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-2">
        <Card title="Current session processes">
          {current ? (
            <ul className="divide-y divide-line">
              {Object.entries(current.processes).map(([name, status]) => (
                <li key={name} className="flex items-center justify-between py-2 text-sm">
                  <span className="capitalize">{name.replace(/_/g, ' ')}</span>
                  <StatusBadge status={status} />
                </li>
              ))}
            </ul>
          ) : <p className="text-sm text-muted">No current session has been set.</p>}
        </Card>
        <Card title="Approvals waiting for you" actions={<Link to="/approvals" className="text-sm text-brand">Open approvals</Link>}>
          {inbox.data?.length ? (
            <ul className="divide-y divide-line">
              {inbox.data.slice(0, 5).map(i => (
                <li key={i.id} className="py-2 text-sm">
                  <Link to={`/approvals/${i.id}`} className="font-medium text-brand">{i.reference}</Link>
                  <span className="text-muted"> · {i.workflow} · {i.current_stage?.name}</span>
                </li>
              ))}
            </ul>
          ) : <p className="text-sm text-muted">Nothing is waiting for you.</p>}
        </Card>
      </div>
    </>
  )
}
