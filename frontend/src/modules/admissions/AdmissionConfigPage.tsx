import { useState, type FormEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Button, Card, DataTable, ErrorNotice, PageHeader, SelectField, StatusBadge, TextAreaField, TextField } from '../../design-system'
import { api, ApiError, errorList } from '../../lib/api'
import type { AdmissionConfigRow, OrgUnit } from '../../lib/types'
import { useMutate } from '../../lib/useMutate'
import { useConfig } from '../academic/shared'
import { useAdmissionConfig, type ConfigKind } from './shared'

const TABS: { kind: ConfigKind | 'letter'; title: string; hint: string }[] = [
  { kind: 'categories', title: 'Admission categories', hint: 'UTME, direct entry, postgraduate, transfer and any other route. JAMB details are asked for only where the category requires them.' },
  { kind: 'exam-bodies', title: "O'Level examination bodies", hint: 'Each body has its own grade scale. Credit grades count towards credit-pass rules.' },
  { kind: 'subjects', title: "O'Level and UTME subjects", hint: 'Subjects applicants choose from and screening rules refer to.' },
  { kind: 'document-types', title: 'Document types', hint: 'Documents an exercise can ask for. Types that need verification wait for Registry to check them.' },
  { kind: 'clearance-units', title: 'Clearance offices', hint: "Offices that clear every admitted candidate. Department and faculty offices resolve to the candidate's own department or faculty." },
  { kind: 'matric-formats', title: 'Matriculation number formats', hint: 'Tokens: {YEAR} {YY} {DEPT} {FAC} {PROG} {CAT} {LEVEL} {SEQ:n}. The most specific active format wins; each prefix has its own sequence.' },
  { kind: 'letter', title: 'Admission letter', hint: 'The letter generated for each offer. Placeholders are filled in when the offer is issued; issued letters do not change.' },
]

export function AdmissionConfigPage() {
  const [tab, setTab] = useState<(typeof TABS)[number]['kind']>('categories')
  const current = TABS.find(t => t.kind === tab)!
  return (
    <>
      <PageHeader title="Admissions configuration" description="Lists and rules the admission process uses. Deactivate an entry instead of deleting it." />
      <div role="tablist" className="mb-4 flex flex-wrap gap-1 border-b border-line">
        {TABS.map(t => (
          <button key={t.kind} role="tab" aria-selected={tab === t.kind} onClick={() => setTab(t.kind)}
            className={`rounded-t-md px-3 py-2 text-sm ${tab === t.kind ? 'border-b-2 border-brand font-medium text-brand' : 'text-muted hover:text-ink'}`}>{t.title}</button>
        ))}
      </div>
      <p className="mb-4 text-sm text-muted">{current.hint}</p>
      {tab === 'letter' ? <LetterTemplate /> : <ConfigTable key={tab} kind={tab} />}
    </>
  )
}

function ConfigTable({ kind }: { kind: ConfigKind }) {
  const rows = useAdmissionConfig(kind, true)
  const key = [['admission-config', kind]]
  const toggle = useMutate<{ id: number; active: boolean }>('PATCH', r => `/admission-config/${kind}/${r.id}`, key)
  const [editing, setEditing] = useState<AdmissionConfigRow | null>(null)

  return (
    <div className="grid gap-6 lg:grid-cols-3">
      <Card className="lg:col-span-2">
        <ErrorNotice error={toggle.error} />
        <DataTable rows={rows.data} loading={rows.isLoading} rowKey={r => r.id} columns={[
          ...(kind === 'matric-formats' ? [] : [{ key: 'code', header: 'Code', render: (r: AdmissionConfigRow) => <span className="font-mono text-xs">{r.code}</span> }]),
          { key: 'name', header: 'Name', render: r => <>{r.name}<Detail kind={kind} r={r} /></> },
          { key: 'active', header: 'Status', render: r => (
            <button type="button" title={r.active ? 'Deactivate' : 'Activate'} onClick={() => toggle.mutate({ id: r.id, active: !r.active })}>
              <StatusBadge status={r.active ? 'active' : 'inactive'} />
            </button>
          ) },
          { key: 'edit', header: '', render: r => <Button variant="ghost" onClick={() => setEditing(r)}>Edit</Button> },
        ]} />
      </Card>
      <ConfigForm key={editing?.id ?? 'new'} kind={kind} row={editing} onDone={() => setEditing(null)} />
    </div>
  )
}

function Detail({ kind, r }: { kind: ConfigKind; r: AdmissionConfigRow }) {
  const bits = {
    categories: r.requires_jamb ? 'Requires JAMB details' : null,
    'exam-bodies': r.grades?.map(g => `${g.grade}${g.is_credit ? '' : '*'}`).join(' '),
    subjects: null,
    'document-types': `${r.needs_verification ? 'Needs verification' : 'No verification'} · up to ${r.max_kb} KB`,
    'clearance-units': `${r.target === 'fixed' ? r.org_unit?.name ?? 'No office set' : `Candidate's ${r.target}`} · order ${r.sort_order}`,
    'matric-formats': `${r.format}${r.admission_category ? ` · ${r.admission_category}` : ''}${r.programme_category ? ` · ${r.programme_category}` : ''}`,
  }[kind]
  return bits ? <span className="block text-xs text-muted">{bits}</span> : null
}

function ConfigForm({ kind, row, onDone }: { kind: ConfigKind; row: AdmissionConfigRow | null; onDone: () => void }) {
  const save = useMutate<Record<string, unknown>>(row ? 'PATCH' : 'POST', row ? `/admission-config/${kind}/${row.id}` : `/admission-config/${kind}`, [['admission-config', kind]])
  const units = useQuery({ queryKey: ['org-units'], queryFn: () => api<{ data: OrgUnit[] }>('/org-units').then(r => r.data), enabled: kind === 'clearance-units' })
  const categories = useAdmissionConfig('categories')
  const progCategories = useConfig('programme-categories')
  const [f, setF] = useState<Record<string, string | boolean>>({
    code: row?.code ?? '', name: row?.name ?? '', requires_jamb: row?.requires_jamb ?? false, description: row?.description ?? '',
    needs_verification: row?.needs_verification ?? true, max_kb: String(row?.max_kb ?? 2048), target: row?.target ?? 'fixed',
    org_unit_id: row?.org_unit?.id ?? '', instructions: row?.instructions ?? '', sort_order: String(row?.sort_order ?? 0),
    format: row?.format ?? '{YEAR}/{DEPT}/{SEQ:4}', admission_category: row?.admission_category ?? '', programme_category: row?.programme_category ?? '',
  })
  const [grades, setGrades] = useState(row?.grades?.map(g => ({ ...g, points: String(g.points) })) ?? [{ grade: 'A1', points: '1', is_credit: true }, { grade: 'F9', points: '9', is_credit: false }])
  const err = save.error instanceof ApiError ? save.error : null
  const s = (k: string) => (e: { target: { value: string } }) => setF({ ...f, [k]: e.target.value })
  const c = (k: string) => (e: { target: { checked: boolean } }) => setF({ ...f, [k]: e.target.checked })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    const body: Record<string, unknown> = { name: f.name }
    if (!row && kind !== 'matric-formats') body.code = f.code
    if (kind === 'categories') body.requires_jamb = f.requires_jamb
    if (kind === 'document-types') Object.assign(body, { description: f.description || null, needs_verification: f.needs_verification, max_kb: Number(f.max_kb) })
    if (kind === 'exam-bodies') body.grades = grades.map(g => ({ grade: g.grade, points: Number(g.points), is_credit: g.is_credit }))
    if (kind === 'clearance-units') Object.assign(body, { target: f.target, org_unit_id: f.target === 'fixed' ? f.org_unit_id || null : null, instructions: f.instructions || null, sort_order: Number(f.sort_order) })
    if (kind === 'matric-formats') Object.assign(body, { format: f.format, admission_category: f.admission_category || null, programme_category: f.programme_category || null })
    save.mutate(body, { onSuccess: onDone })
  }

  return (
    <Card title={row ? `Edit ${row.name}` : 'Add'}>
      <form onSubmit={submit} className="space-y-3">
        {kind !== 'matric-formats' && <TextField label="Code" required disabled={!!row} value={f.code as string} onChange={e => setF({ ...f, code: e.target.value.toUpperCase() })} error={err?.fieldError('code')} />}
        <TextField label="Name" required value={f.name as string} onChange={s('name')} error={err?.fieldError('name')} />
        {kind === 'categories' && <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={!!f.requires_jamb} onChange={c('requires_jamb')} />Applicants must give JAMB details</label>}
        {kind === 'document-types' && (
          <>
            <TextAreaField label="Description" value={f.description as string} onChange={s('description')} />
            <TextField label="Largest file (KB)" type="number" min={50} max={20480} value={f.max_kb as string} onChange={s('max_kb')} error={err?.fieldError('max_kb')} />
            <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={!!f.needs_verification} onChange={c('needs_verification')} />Registry must verify it</label>
          </>
        )}
        {kind === 'exam-bodies' && (
          <fieldset>
            <legend className="mb-1 text-sm font-medium">Grade scale</legend>
            {grades.map((g, i) => (
              <div key={i} className="mb-1 grid grid-cols-7 items-center gap-1 text-sm">
                <input aria-label="Grade" className="col-span-2 rounded border border-line px-2 py-1" value={g.grade} onChange={e => setGrades(grades.map((x, j) => (j === i ? { ...x, grade: e.target.value.toUpperCase() } : x)))} />
                <input aria-label="Points" type="number" className="col-span-2 rounded border border-line px-2 py-1" value={g.points} onChange={e => setGrades(grades.map((x, j) => (j === i ? { ...x, points: e.target.value } : x)))} />
                <label className="col-span-2 flex items-center gap-1"><input type="checkbox" checked={g.is_credit} onChange={e => setGrades(grades.map((x, j) => (j === i ? { ...x, is_credit: e.target.checked } : x)))} />Credit</label>
                <button type="button" aria-label="Remove grade" onClick={() => setGrades(grades.filter((_, j) => j !== i))}>✕</button>
              </div>
            ))}
            <Button type="button" variant="ghost" onClick={() => setGrades([...grades, { grade: '', points: '', is_credit: false }])}>Add grade</Button>
          </fieldset>
        )}
        {kind === 'clearance-units' && (
          <>
            <SelectField label="Who clears" value={f.target as string} onChange={s('target')}>
              <option value="fixed">A fixed office</option><option value="department">The candidate's department</option><option value="faculty">The candidate's faculty</option>
            </SelectField>
            {f.target === 'fixed' && (
              <SelectField label="Office" value={f.org_unit_id as string} onChange={s('org_unit_id')}>
                <option value="">Any officer holding the clearance permission</option>
                {units.data?.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
              </SelectField>
            )}
            <TextAreaField label="Instructions to candidates" value={f.instructions as string} onChange={s('instructions')} />
            <TextField label="Order" type="number" min={0} value={f.sort_order as string} onChange={s('sort_order')} />
          </>
        )}
        {kind === 'matric-formats' && (
          <>
            <TextField label="Format" required value={f.format as string} onChange={s('format')} error={err?.fieldError('format')} />
            <SelectField label="Only for admission category" value={f.admission_category as string} onChange={s('admission_category')}>
              <option value="">Any</option>
              {categories.data?.map(x => <option key={x.code} value={x.code}>{x.name}</option>)}
            </SelectField>
            <SelectField label="Only for programme category" value={f.programme_category as string} onChange={s('programme_category')}>
              <option value="">Any</option>
              {progCategories.data?.map(x => <option key={x.code} value={x.code}>{x.name}</option>)}
            </SelectField>
          </>
        )}
        {save.error && <ErrorNotice error={new Error(errorList(save.error).join(' '))} />}
        <div className="flex gap-2">
          <Button type="submit" busy={save.isPending}>{row ? 'Save' : 'Add'}</Button>
          {row && <Button type="button" variant="ghost" onClick={onDone}>Cancel</Button>}
        </div>
      </form>
    </Card>
  )
}

function LetterTemplate() {
  const data = useQuery({ queryKey: ['admission-config', 'letter'], queryFn: () => api<{ data: { template: string; placeholders: string[] } }>('/admission-config/letter-template').then(r => r.data) })
  const save = useMutate<{ template: string }>('PUT', '/admission-config/letter-template', [['admission-config', 'letter']])
  const [text, setText] = useState<string | null>(null)
  if (!data.data) return null
  return (
    <Card>
      <p className="mb-2 text-xs text-muted">Placeholders: {data.data.placeholders.map(p => `{{ ${p} }}`).join(' ')}</p>
      <TextAreaField label="Template" rows={18} className="font-mono" value={text ?? data.data.template} onChange={e => setText(e.target.value)} />
      <ErrorNotice error={save.error} />
      <Button className="mt-3" busy={save.isPending} disabled={text === null} onClick={() => save.mutate({ template: text! }, { onSuccess: () => setText(null) })}>Save template</Button>
      {save.isSuccess && text === null && <span role="status" className="ml-3 text-sm text-ok">Saved</span>}
    </Card>
  )
}
