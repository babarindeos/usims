import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Button, Card, PageHeader, Spinner, formatDate } from '../../design-system'
import { api } from '../../lib/api'
import { useMutate } from '../../lib/useMutate'

type Note = { id: string; title: string; body: string; link: string | null; read_at: string | null; created_at: string }

export function NotificationsPage() {
  const notes = useQuery({ queryKey: ['notifications'], queryFn: () => api<{ data: Note[] }>('/notifications').then(r => r.data) })
  const readAll = useMutate('POST', '/notifications/read-all', [['notifications']])

  return (
    <>
      <PageHeader title="Notifications" actions={<Button variant="secondary" busy={readAll.isPending} onClick={() => readAll.mutate(undefined)}>Mark all as read</Button>} />
      <Card>
        {notes.isLoading ? <Spinner /> : notes.data?.length ? (
          <ul className="divide-y divide-line">
            {notes.data.map(n => (
              <li key={n.id} className={`py-3 text-sm ${n.read_at ? 'text-muted' : ''}`}>
                <p className="font-medium">{n.link ? <Link to={n.link.replace('/workflows/', '/approvals/')} className="text-brand">{n.title}</Link> : n.title}</p>
                <p>{n.body}</p>
                <p className="text-xs text-muted">{formatDate(n.created_at, true)}</p>
              </li>
            ))}
          </ul>
        ) : <p className="text-sm text-muted">No notifications.</p>}
      </Card>
    </>
  )
}
