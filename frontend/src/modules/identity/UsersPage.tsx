import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Card, DataTable, PageHeader, StatusBadge, TextField, formatDate } from '../../design-system'
import { api, qs } from '../../lib/api'
import type { Paginated, User } from '../../lib/types'

export function UsersPage() {
  const [q, setQ] = useState('')
  const [page, setPage] = useState(1)
  const users = useQuery({ queryKey: ['users', q, page], queryFn: () => api<Paginated<User>>('/users' + qs({ q, page })) })

  return (
    <>
      <PageHeader title="Users and access" description="Accounts and the roles they hold. Open a user to grant or revoke a role within a scope." />
      <Card>
        <TextField label="Search by name or email" value={q} onChange={e => { setQ(e.target.value); setPage(1) }} className="mb-4 max-w-sm" />
        <DataTable rows={users.data?.data} loading={users.isLoading} rowKey={u => u.id}
          columns={[
            { key: 'name', header: 'Name', render: u => <Link className="font-medium text-brand" to={`/users/${u.id}`}>{u.name}</Link> },
            { key: 'email', header: 'Email', render: u => u.email },
            { key: 'person', header: 'Person no.', render: u => u.person?.person_no ?? '—' },
            { key: 'status', header: 'Status', render: u => <StatusBadge status={u.status} /> },
            { key: 'login', header: 'Last sign-in', render: u => formatDate(u.last_login_at, true) },
          ]} />
        {users.data?.meta && users.data.meta.last_page > 1 && (
          <div className="mt-4 flex items-center gap-3 text-sm">
            <button disabled={page <= 1} onClick={() => setPage(p => p - 1)} className="text-brand disabled:text-muted">Previous</button>
            <span>Page {users.data.meta.current_page} of {users.data.meta.last_page}</span>
            <button disabled={page >= users.data.meta.last_page} onClick={() => setPage(p => p + 1)} className="text-brand disabled:text-muted">Next</button>
          </div>
        )}
      </Card>
    </>
  )
}
