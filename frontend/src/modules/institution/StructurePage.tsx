import { useState, type FormEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useAuth } from '../../app/auth'
import { Button, Card, ErrorNotice, PageHeader, SelectField, Spinner, StatusBadge, TextField } from '../../design-system'
import { api, ApiError } from '../../lib/api'
import type { OrgUnit } from '../../lib/types'
import { useMutate } from '../../lib/useMutate'

type UnitType = { code: string; name: string; is_academic: boolean }

export function StructurePage() {
  const { can } = useAuth()
  const units = useQuery({ queryKey: ['org-units'], queryFn: () => api<{ data: OrgUnit[] }>('/org-units').then(r => r.data) })
  const types = useQuery({ queryKey: ['org-unit-types'], queryFn: () => api<{ data: UnitType[] }>('/org-unit-types').then(r => r.data) })
  const create = useMutate<Record<string, string | null>>('POST', '/org-units', [['org-units']])
  const [form, setForm] = useState({ parent_id: '', type: 'department', code: '', name: '' })

  async function submit(e: FormEvent) {
    e.preventDefault()
    await create.mutateAsync({ ...form, parent_id: form.parent_id || null }).then(() => setForm({ ...form, code: '', name: '' })).catch(() => undefined)
  }
  const err = create.error instanceof ApiError ? create.error : null

  return (
    <>
      <PageHeader title="Organisation structure" description="Colleges, faculties, departments and administrative units. Moving a unit keeps its history and moves everything below it." />
      <div className="grid gap-6 lg:grid-cols-3">
        <Card title="Units" className="lg:col-span-2">
          {units.isLoading ? <Spinner /> : units.data?.length ? (
            <ul className="text-sm">
              {units.data.map(u => (
                <li key={u.id} className="flex items-center justify-between border-b border-line py-2 last:border-0" style={{ paddingLeft: `${u.depth * 1.25}rem` }}>
                  <span><span className="font-medium">{u.name}</span> <span className="text-muted">· {u.code} · {u.type?.name}</span></span>
                  <StatusBadge status={u.status} />
                </li>
              ))}
            </ul>
          ) : <p className="text-sm text-muted">No units yet. Start with the university itself.</p>}
        </Card>

        {can('org_units.manage') && (
          <Card title="Add a unit">
            <form onSubmit={submit} className="space-y-3">
              {create.error && !err?.problem.errors && <ErrorNotice error={create.error} />}
              <SelectField label="Parent unit" value={form.parent_id} onChange={e => setForm({ ...form, parent_id: e.target.value })} error={err?.fieldError('parent_id')}>
                <option value="">None (top level)</option>
                {units.data?.map(u => <option key={u.id} value={u.id}>{'— '.repeat(u.depth)}{u.name}</option>)}
              </SelectField>
              <SelectField label="Type" value={form.type} onChange={e => setForm({ ...form, type: e.target.value })} error={err?.fieldError('type')}>
                {types.data?.map(t => <option key={t.code} value={t.code}>{t.name}</option>)}
              </SelectField>
              <TextField label="Code" hint="Capital letters and digits, e.g. CSC" value={form.code} onChange={e => setForm({ ...form, code: e.target.value.toUpperCase() })} error={err?.fieldError('code')} required />
              <TextField label="Name" value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} error={err?.fieldError('name')} required />
              <Button type="submit" busy={create.isPending}>Add unit</Button>
            </form>
          </Card>
        )}
      </div>
    </>
  )
}
