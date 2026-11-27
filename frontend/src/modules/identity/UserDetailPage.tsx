import { useState, type FormEvent } from 'react'
import { useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useAuth } from '../../app/auth'
import { Button, Card, DataTable, ErrorNotice, PageHeader, SelectField, Spinner, StatusBadge, TextField, formatDate } from '../../design-system'
import { api, ApiError } from '../../lib/api'
import type { Grant, OrgUnit, Role, User } from '../../lib/types'
import { useMutate } from '../../lib/useMutate'

export function UserDetailPage() {
  const { id } = useParams()
  const { can } = useAuth()
  const user = useQuery({ queryKey: ['user', id], queryFn: () => api<{ data: User }>(`/users/${id}`).then(r => r.data) })
  const grants = useQuery({ queryKey: ['grants', id], queryFn: () => api<{ data: Grant[] }>(`/users/${id}/grants?include_history=1`).then(r => r.data) })
  const roles = useQuery({ queryKey: ['roles'], queryFn: () => api<{ data: Role[] }>('/roles').then(r => r.data) })
  const units = useQuery({ queryKey: ['org-units'], queryFn: () => api<{ data: OrgUnit[] }>('/org-units').then(r => r.data) })

  const add = useMutate<Record<string, string | null>>('POST', `/users/${id}/grants`, [['grants', id!], ['user', id!]])
  const revoke = useMutate<{ id: number; reason: string }>('POST', b => `/grants/${b.id}/revoke`, [['grants', id!], ['user', id!]])
  const setStatus = useMutate<{ status: string }>('PATCH', `/users/${id}`, [['user', id!], ['users']])
  const [form, setForm] = useState({ role: '', scope_type: 'org_unit', org_unit_id: '', ends_at: '', reason: '' })
  const err = add.error instanceof ApiError ? add.error : null

  if (user.isLoading) return <Spinner />
  if (!user.data) return <ErrorNotice error={user.error} />
  const u = user.data

  function submit(e: FormEvent) {
    e.preventDefault()
    add.mutate({ ...form, org_unit_id: form.scope_type === 'org_unit' ? form.org_unit_id : null, ends_at: form.ends_at || null })
  }

  return (
    <>
      <PageHeader title={u.name} description={`${u.email} · ${u.person?.person_no ?? ''}`} actions={
        can('users.manage') && (u.status === 'active'
          ? <Button variant="secondary" busy={setStatus.isPending} onClick={() => setStatus.mutate({ status: 'suspended' })}>Suspend account</Button>
          : <Button variant="secondary" busy={setStatus.isPending} onClick={() => setStatus.mutate({ status: 'active' })}>Reactivate account</Button>)
      } />
      <ErrorNotice error={setStatus.error} />
      <p className="mb-6 text-sm">Status: <StatusBadge status={u.status} /></p>

      <Card title="Role grants (including history)" className="mb-6">
        <ErrorNotice error={revoke.error} />
        <DataTable rows={grants.data} loading={grants.isLoading} rowKey={g => g.id} empty="No roles granted."
          columns={[
            { key: 'role', header: 'Role', render: g => <span className="font-medium">{g.role.name}</span> },
            { key: 'scope', header: 'Scope', render: g => g.org_unit?.name ?? 'Whole university' },
            { key: 'from', header: 'From', render: g => formatDate(g.starts_at) },
            { key: 'to', header: 'Until', render: g => g.revoked_at ? `Revoked ${formatDate(g.revoked_at)}` : formatDate(g.ends_at) },
            { key: 'reason', header: 'Reason', render: g => g.reason ?? '—' },
            { key: 'act', header: '', render: g => !g.revoked_at && can('grants.manage') && (
              <Button variant="ghost" onClick={() => { const reason = window.prompt('Reason for revoking'); if (reason) revoke.mutate({ id: g.id, reason }) }}>Revoke</Button>
            ) },
          ]} />
      </Card>

      {can('grants.manage') && (
        <Card title="Grant a role">
          <form onSubmit={submit} className="grid gap-3 sm:grid-cols-2">
            {add.error && !err?.problem.errors && <div className="sm:col-span-2"><ErrorNotice error={add.error} /></div>}
            <SelectField label="Role" value={form.role} onChange={e => setForm({ ...form, role: e.target.value })} required error={err?.fieldError('role')}>
              <option value="">Choose a role</option>
              {roles.data?.map(r => <option key={r.code} value={r.code}>{r.name}</option>)}
            </SelectField>
            <SelectField label="Scope" value={form.scope_type} onChange={e => setForm({ ...form, scope_type: e.target.value })}>
              <option value="org_unit">One unit and everything below it</option>
              <option value="global">Whole university</option>
            </SelectField>
            {form.scope_type === 'org_unit' && (
              <SelectField label="Unit" value={form.org_unit_id} onChange={e => setForm({ ...form, org_unit_id: e.target.value })} required error={err?.fieldError('org_unit_id')}>
                <option value="">Choose a unit</option>
                {units.data?.map(o => <option key={o.id} value={o.id}>{'— '.repeat(o.depth)}{o.name}</option>)}
              </SelectField>
            )}
            <TextField label="Ends on (optional, for acting or delegated roles)" type="date" value={form.ends_at} onChange={e => setForm({ ...form, ends_at: e.target.value })} error={err?.fieldError('ends_at')} />
            <TextField label="Reason" value={form.reason} onChange={e => setForm({ ...form, reason: e.target.value })} required error={err?.fieldError('reason')} className="sm:col-span-2" />
            <div><Button type="submit" busy={add.isPending}>Grant role</Button></div>
          </form>
        </Card>
      )}
    </>
  )
}
