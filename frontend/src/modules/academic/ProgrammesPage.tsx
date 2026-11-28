import { useState, type FormEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../../app/auth'
import { Button, Card, DataTable, PageHeader, SelectField, StatusBadge, TextField } from '../../design-system'
import { api, ApiError, qs } from '../../lib/api'
import type { Programme } from '../../lib/types'
import { useMutate } from '../../lib/useMutate'
import { LEVELS, useAcademicUnits, useConfig } from './shared'

export function ProgrammesPage() {
  const { can } = useAuth()
  const [q, setQ] = useState('')
  const [creating, setCreating] = useState(false)
  const rows = useQuery({ queryKey: ['programmes', q], queryFn: () => api<{ data: Programme[] }>(`/programmes${qs({ q })}`).then(r => r.data) })

  return (
    <>
      <PageHeader title="Programmes" description="Degree and diploma programmes, the department that owns each, and their curricula."
        actions={can('programmes.manage') && <Button onClick={() => setCreating(!creating)}>{creating ? 'Close' : 'New programme'}</Button>} />
      {creating && <ProgrammeForm />}
      <Card>
        <TextField label="Search" placeholder="Name or code" value={q} onChange={e => setQ(e.target.value)} className="mb-3 max-w-sm" />
        <DataTable rows={rows.data} loading={rows.isLoading} rowKey={r => r.id} empty="No programmes yet." columns={[
          { key: 'code', header: 'Code', render: r => <Link className="font-medium text-brand" to={`/academic/programmes/${r.id}`}>{r.code}</Link> },
          { key: 'name', header: 'Programme', render: r => r.name },
          { key: 'dept', header: 'Department', render: r => r.department?.name },
          { key: 'award', header: 'Award', render: r => r.award_type?.name },
          { key: 'duration', header: 'Duration', render: r => `${r.duration_years} years` },
          { key: 'status', header: 'Status', render: r => <StatusBadge status={r.status} /> },
        ]} />
      </Card>
    </>
  )
}

function ProgrammeForm() {
  const navigate = useNavigate()
  const units = useAcademicUnits()
  const awards = useConfig('award-types')
  const categories = useConfig('programme-categories')
  const modes = useConfig('study-modes')
  const create = useMutate<Record<string, unknown>, { data: Programme }>('POST', '/programmes', [['programmes']])
  const [form, setForm] = useState({ code: '', name: '', org_unit_id: '', award_type: '', category: '', duration_years: 4, min_duration_years: 4, max_duration_years: 6, capacity: '' })
  const [selectedModes, setSelectedModes] = useState<string[]>(['FT'])
  const [levels, setLevels] = useState<number[]>([100])
  const err = create.error instanceof ApiError ? create.error : null
  const set = (k: string, v: string | number) => setForm({ ...form, [k]: v })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    create.mutate({
      ...form, capacity: form.capacity === '' ? null : Number(form.capacity),
      study_modes: selectedModes.map(code => ({ code })), entry_levels: levels,
    }, { onSuccess: r => navigate(`/academic/programmes/${r.data.id}`) })
  }

  return (
    <Card title="New programme" className="mb-6">
      <form onSubmit={submit} className="grid gap-3 sm:grid-cols-4">
        <TextField label="Code" value={form.code} onChange={e => set('code', e.target.value.toUpperCase())} placeholder="BSC-CSC" required error={err?.fieldError('code')} />
        <TextField label="Name" value={form.name} onChange={e => set('name', e.target.value)} required className="sm:col-span-3" error={err?.fieldError('name')} />
        <SelectField label="Owning department" value={form.org_unit_id} onChange={e => set('org_unit_id', e.target.value)} required error={err?.fieldError('org_unit_id')} className="sm:col-span-2">
          <option value="">Choose…</option>
          {units.data?.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
        </SelectField>
        <SelectField label="Award" value={form.award_type} onChange={e => set('award_type', e.target.value)} required error={err?.fieldError('award_type')}>
          <option value="">Choose…</option>
          {awards.data?.map(a => <option key={a.code} value={a.code}>{a.name}</option>)}
        </SelectField>
        <SelectField label="Category" value={form.category} onChange={e => set('category', e.target.value)} required error={err?.fieldError('category')}>
          <option value="">Choose…</option>
          {categories.data?.map(a => <option key={a.code} value={a.code}>{a.name}</option>)}
        </SelectField>
        <TextField label="Normal duration (years)" type="number" min={1} value={form.duration_years} onChange={e => set('duration_years', Number(e.target.value))} required error={err?.fieldError('duration_years')} />
        <TextField label="Minimum (years)" type="number" min={1} value={form.min_duration_years} onChange={e => set('min_duration_years', Number(e.target.value))} required error={err?.fieldError('min_duration_years')} />
        <TextField label="Maximum (years)" type="number" min={1} value={form.max_duration_years} onChange={e => set('max_duration_years', Number(e.target.value))} required error={err?.fieldError('max_duration_years')} />
        <TextField label="Capacity" type="number" min={0} value={form.capacity} onChange={e => set('capacity', e.target.value)} hint="Leave blank if not capped" />
        <fieldset className="sm:col-span-2">
          <legend className="mb-1 text-sm font-medium">Study modes</legend>
          <div className="flex flex-wrap gap-3 text-sm">
            {modes.data?.map(m => (
              <label key={m.code} className="flex items-center gap-1.5">
                <input type="checkbox" checked={selectedModes.includes(m.code)} onChange={e => setSelectedModes(e.target.checked ? [...selectedModes, m.code] : selectedModes.filter(c => c !== m.code))} />
                {m.name}
              </label>
            ))}
          </div>
        </fieldset>
        <fieldset className="sm:col-span-2">
          <legend className="mb-1 text-sm font-medium">Entry levels</legend>
          <div className="flex flex-wrap gap-3 text-sm">
            {LEVELS.slice(0, 5).map(l => (
              <label key={l} className="flex items-center gap-1.5">
                <input type="checkbox" checked={levels.includes(l)} onChange={e => setLevels(e.target.checked ? [...levels, l].sort() : levels.filter(x => x !== l))} />
                {l}
              </label>
            ))}
          </div>
        </fieldset>
        <div className="sm:col-span-4"><Button type="submit" busy={create.isPending}>Create programme</Button></div>
      </form>
    </Card>
  )
}
