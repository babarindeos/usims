import { useState, type FormEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Button, Card, DataTable, ErrorNotice, PageHeader, SelectField, StatusBadge, TextField } from '../../design-system'
import { api, ApiError } from '../../lib/api'
import type { ConfigEntry } from '../../lib/types'
import { useMutate } from '../../lib/useMutate'
import { label } from './shared'

const KINDS = [
  { kind: 'award-types', title: 'Award types', hint: 'Degrees, diplomas and certificates the institution confers.' },
  { kind: 'study-modes', title: 'Study modes', hint: 'Full-time, part-time, distance and any other modes.' },
  { kind: 'programme-categories', title: 'Programme categories', hint: 'Groupings used for admission, fees and reporting.' },
] as const

export function AcademicConfigPage() {
  return (
    <>
      <PageHeader title="Academic configuration" description="Lists used by programmes. Codes stay fixed once used; deactivate an entry instead of deleting it." />
      <div className="grid gap-6 lg:grid-cols-3">
        {KINDS.map(k => <ConfigCard key={k.kind} {...k} />)}
      </div>
    </>
  )
}

function ConfigCard({ kind, title, hint }: { kind: string; title: string; hint: string }) {
  const key = ['academic-config', kind]
  const rows = useQuery({ queryKey: [...key, 'all'], queryFn: () => api<{ data: ConfigEntry[] }>(`/academic-config/${kind}?include_inactive=1`).then(r => r.data) })
  const create = useMutate<Partial<ConfigEntry>>('POST', `/academic-config/${kind}`, [key])
  const toggle = useMutate<ConfigEntry>('PATCH', e => `/academic-config/${kind}/${e.code}`, [key])
  const [form, setForm] = useState({ code: '', name: '', level: 'undergraduate' })
  const err = create.error instanceof ApiError ? create.error : null
  const award = kind === 'award-types'

  const submit = (e: FormEvent) => {
    e.preventDefault()
    create.mutate(award ? form : { code: form.code, name: form.name }, { onSuccess: () => setForm({ ...form, code: '', name: '' }) })
  }

  return (
    <Card title={title}>
      <p className="mb-3 text-xs text-muted">{hint}</p>
      <ErrorNotice error={toggle.error} />
      <DataTable rows={rows.data} loading={rows.isLoading} rowKey={r => r.code} columns={[
        { key: 'code', header: 'Code', render: r => <span className="font-mono text-xs">{r.code}</span> },
        { key: 'name', header: 'Name', render: r => <>{r.name}{r.level && <span className="block text-xs text-muted">{label(r.level)}</span>}</> },
        { key: 'active', header: 'Status', render: r => (
          <button type="button" className="cursor-pointer" title={r.active ? 'Deactivate' : 'Activate'} onClick={() => toggle.mutate({ ...r, active: !r.active })}>
            <StatusBadge status={r.active ? 'active' : 'inactive'} />
          </button>
        ) },
      ]} />
      <form onSubmit={submit} className="mt-4 grid gap-2">
        <div className="grid grid-cols-3 gap-2">
          <TextField label="Code" value={form.code} onChange={e => setForm({ ...form, code: e.target.value.toUpperCase() })} error={err?.fieldError('code')} required />
          <TextField label="Name" value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} error={err?.fieldError('name')} required className="col-span-2" />
        </div>
        {award && (
          <SelectField label="Level" value={form.level} onChange={e => setForm({ ...form, level: e.target.value })}>
            {['sub_degree', 'undergraduate', 'postgraduate', 'certificate'].map(l => <option key={l} value={l}>{label(l)}</option>)}
          </SelectField>
        )}
        <Button type="submit" variant="secondary" busy={create.isPending}>Add</Button>
      </form>
    </Card>
  )
}
