import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useAuth } from '../../app/auth'
import { Button, Card, DataTable, ErrorNotice, formatDate, PageHeader, SelectField, Spinner, StatusBadge, TextAreaField, TextField } from '../../design-system'
import { api, errorList } from '../../lib/api'
import type { AdmissionListDetail, AdmissionListSummary } from '../../lib/types'
import { useMutate } from '../../lib/useMutate'
import { useExercises } from './shared'

export function AdmissionListsPage() {
  const { can } = useAuth()
  const navigate = useNavigate()
  const lists = useQuery({ queryKey: ['admission-lists', ''], queryFn: () => api<{ data: AdmissionListSummary[] }>('/admission-lists').then(r => r.data) })
  const exercises = useExercises()
  const create = useMutate<{ exercise_id: string; name: string }, { data: AdmissionListDetail }>('POST', '/admission-lists', [['admission-lists']])
  const [form, setForm] = useState({ exercise_id: '', name: '' })
  const submit = (e: FormEvent) => { e.preventDefault(); create.mutate(form, { onSuccess: r => navigate(`/admissions/lists/${r.data.id}`) }) }

  return (
    <>
      <PageHeader title="Admission lists" description="Recommended applicants go on a list, which the committee and approving officer act on through the approval workflow. Approval issues the offers." />
      {can('admissions.lists') && (
        <Card title="New list" className="mb-6">
          <form onSubmit={submit} className="grid gap-3 sm:grid-cols-4">
            <SelectField label="Exercise" required value={form.exercise_id} onChange={e => setForm({ ...form, exercise_id: e.target.value })}>
              <option value="">Choose…</option>
              {exercises.data?.map(x => <option key={x.id} value={x.id}>{x.name}</option>)}
            </SelectField>
            <TextField label="Name" required value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} placeholder="First merit list" className="sm:col-span-2" />
            <div className="flex items-end"><Button type="submit" busy={create.isPending}>Create list</Button></div>
            <div className="sm:col-span-4"><ErrorNotice error={create.error} /></div>
          </form>
        </Card>
      )}
      <Card>
        <DataTable rows={lists.data} loading={lists.isLoading} rowKey={r => r.id} empty="No admission lists yet." columns={[
          { key: 'n', header: 'List', render: r => <Link className="font-medium text-brand" to={`/admissions/lists/${r.id}`}>{r.name}</Link> },
          { key: 'e', header: 'Exercise', render: r => r.exercise.name },
          { key: 'c', header: 'Applicants', render: r => r.entries_count },
          { key: 'd', header: 'Created', render: r => formatDate(r.created_at) },
          { key: 'a', header: 'Approved', render: r => formatDate(r.approved_at) },
          { key: 's', header: 'Status', render: r => <StatusBadge status={r.status} /> },
        ]} />
      </Card>
    </>
  )
}

export function AdmissionListDetailPage() {
  const { id = '' } = useParams()
  const { can } = useAuth()
  const client = useQueryClient()
  const key = ['admission-lists', id]
  const list = useQuery({ queryKey: key, queryFn: () => api<{ data: AdmissionListDetail }>(`/admission-lists/${id}`).then(r => r.data) })
  const submit = useMutation({
    mutationFn: (remarks: string) => api<{ data: AdmissionListDetail }>(`/admission-lists/${id}/submit`, { method: 'POST', body: { remarks: remarks || null } }).then(r => r.data),
    onSuccess: data => { client.setQueryData(key, data); client.invalidateQueries({ queryKey: ['approvals'] }) },
  })
  const remove = useMutation({
    mutationFn: (app: string) => api(`/admission-lists/${id}/entries/${app}`, { method: 'DELETE' }),
    onSuccess: () => client.invalidateQueries({ queryKey: key }),
  })
  const [remarks, setRemarks] = useState('')
  if (list.isLoading) return <Spinner />
  if (!list.data) return <ErrorNotice error={list.error} />
  const l = list.data
  const manage = can('admissions.lists') && l.editable

  const byProgramme = l.entries.reduce<Record<string, number>>((acc, e) => ({ ...acc, [`${e.programme} (${e.entry_level})`]: (acc[`${e.programme} (${e.entry_level})`] ?? 0) + 1 }), {})

  return (
    <>
      <PageHeader title={l.name} description={`${l.exercise.name} · ${l.entries.length} applicants`}
        actions={<><StatusBadge status={l.status} /><Link className="text-sm text-brand" to="/admissions/lists">All lists</Link></>} />
      {l.workflow && (
        <p className="mb-4 text-sm">
          Approval <Link className="text-brand" to={`/approvals/${l.workflow.id}`}>{l.workflow.reference}</Link> · <StatusBadge status={l.workflow.status} />
          {l.workflow.current_stage && <span className="text-muted"> · waiting at {l.workflow.current_stage}</span>}
        </p>
      )}
      <div className="mb-6 flex flex-wrap gap-2 text-sm">{Object.entries(byProgramme).map(([k, n]) => <span key={k} className="rounded-full border border-line px-3 py-1">{k}: {n}</span>)}</div>
      {manage && (
        <Card title="Send for approval" className="mb-6">
          <p className="mb-2 text-sm text-muted">Add recommended applicants from the <Link className="text-brand" to={`/admissions/applications?exercise=${l.exercise.id}&status=recommended`}>applications workspace</Link>. Capacity is checked when the list is sent.</p>
          <TextAreaField label="Remarks for the committee" value={remarks} onChange={e => setRemarks(e.target.value)} />
          {submit.error && <ul role="alert" className="my-2 list-disc pl-5 text-sm text-bad">{errorList(submit.error).map(m => <li key={m}>{m}</li>)}</ul>}
          <Button className="mt-2" disabled={!l.entries.length} busy={submit.isPending} onClick={() => submit.mutate(remarks)}>Send for approval</Button>
        </Card>
      )}
      <Card>
        <ErrorNotice error={remove.error} />
        <DataTable rows={l.entries} rowKey={r => r.application_id} empty="No applicants on this list yet." columns={[
          { key: 'no', header: 'Application', render: r => <Link className="text-brand" to={`/admissions/applications/${r.application_id}`}>{r.application_no}</Link> },
          { key: 'n', header: 'Name', render: r => r.name },
          { key: 'p', header: 'Programme', render: r => `${r.programme} (${r.entry_level})` },
          { key: 's', header: 'UTME', render: r => r.utme_score ?? '—' },
          { key: 'o', header: 'Screening', render: r => r.screening_outcome && <StatusBadge status={r.screening_outcome} /> },
          { key: 'of', header: 'Offer', render: r => (r.offer ? <>{r.offer.admission_no} <StatusBadge status={r.offer.status} /></> : '—') },
          { key: 'a', header: '', render: r => manage && <Button variant="ghost" onClick={() => remove.mutate(r.application_id)}>Remove</Button> },
        ]} />
      </Card>
    </>
  )
}
