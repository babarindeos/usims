import { useState } from 'react'
import { useInfiniteQuery } from '@tanstack/react-query'
import { Button, Card, DataTable, PageHeader, TextField, formatDate } from '../../design-system'
import { api, qs } from '../../lib/api'
import type { AuditEntry } from '../../lib/types'

type Page = { data: AuditEntry[]; next_cursor: string | null }

function Changes({ entry }: { entry: AuditEntry }) {
  const keys = [...new Set([...Object.keys(entry.before ?? {}), ...Object.keys(entry.after ?? {})])].filter(k => k !== 'created_at')
  if (!keys.length) return <span className="text-muted">—</span>
  return (
    <ul className="space-y-0.5 text-xs">
      {keys.slice(0, 6).map(k => (
        <li key={k}><code>{k}</code>: {entry.before && k in entry.before && <span className="text-bad line-through">{String(entry.before[k] ?? '∅')}</span>} {entry.after && k in entry.after && <span className="text-ok">{String(entry.after[k] ?? '∅')}</span>}</li>
      ))}
      {keys.length > 6 && <li className="text-muted">and {keys.length - 6} more</li>}
    </ul>
  )
}

export function AuditLogPage() {
  const [type, setType] = useState('')
  const [action, setAction] = useState('')
  const logs = useInfiniteQuery({
    queryKey: ['audit', type, action],
    initialPageParam: '' as string,
    queryFn: ({ pageParam }) => api<Page>('/audit-logs' + qs({ type, action, cursor: pageParam })),
    getNextPageParam: last => last.next_cursor ?? undefined,
  })
  const rows = logs.data?.pages.flatMap(p => p.data)

  return (
    <>
      <PageHeader title="Audit trail" description="Every change to a sensitive record: who, when, what changed and why. Entries cannot be edited or deleted." />
      <Card>
        <div className="mb-4 flex flex-wrap gap-3">
          <TextField label="Record type" placeholder="Person, User, OrgUnit ..." value={type} onChange={e => setType(e.target.value)} />
          <TextField label="Action" placeholder="created, updated ..." value={action} onChange={e => setAction(e.target.value)} />
        </div>
        <DataTable rows={rows} loading={logs.isLoading} rowKey={r => r.id}
          columns={[
            { key: 'when', header: 'When', render: r => formatDate(r.created_at, true), className: 'whitespace-nowrap' },
            { key: 'who', header: 'Who', render: r => r.user?.name ?? 'System' },
            { key: 'what', header: 'Record', render: r => `${r.record_type} #${r.record_id ?? ''}` },
            { key: 'action', header: 'Action', render: r => r.action },
            { key: 'changes', header: 'Changes', render: r => <Changes entry={r} /> },
            { key: 'why', header: 'Reason', render: r => r.reason ?? <span className="text-muted">—</span> },
          ]} />
        {logs.hasNextPage && <div className="mt-4"><Button variant="secondary" busy={logs.isFetchingNextPage} onClick={() => logs.fetchNextPage()}>Load more</Button></div>}
      </Card>
    </>
  )
}
