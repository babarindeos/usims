import { useState, type FormEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useAuth } from '../../app/auth'
import { Button, Card, DataTable, ErrorNotice, PageHeader, SelectField, TextField } from '../../design-system'
import { api, ApiError, qs } from '../../lib/api'
import type { Paginated, Person } from '../../lib/types'
import { useMutate } from '../../lib/useMutate'

const EMPTY = { surname: '', first_name: '', other_names: '', date_of_birth: '', gender: '', email: '', phone: '' }

export function PersonsPage() {
  const { can } = useAuth()
  const [q, setQ] = useState('')
  const people = useQuery({ queryKey: ['persons', q], queryFn: () => api<Paginated<Person>>('/persons' + qs({ q })) })
  const create = useMutate<Record<string, unknown>>('POST', '/persons', [['persons']])
  const [form, setForm] = useState(EMPTY)
  const err = create.error instanceof ApiError ? create.error : null
  const duplicates = err?.problem.status === 409 ? (err.problem.matches as Person[]) : null

  function submit(e: FormEvent, confirm = false) {
    e.preventDefault()
    const body = Object.fromEntries(Object.entries(form).map(([k, v]) => [k, v || null]))
    create.mutate({ ...body, confirm_not_duplicate: confirm }, { onSuccess: () => setForm(EMPTY) })
  }

  return (
    <>
      <PageHeader title="Person records" description="One permanent record per person. Applicant, student, staff and alumni records all link to it." />
      <div className="grid gap-6 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <TextField label="Search by name, person number, email or phone" value={q} onChange={e => setQ(e.target.value)} className="mb-4 max-w-md" />
          <DataTable rows={people.data?.data} loading={people.isLoading} rowKey={p => p.id}
            columns={[
              { key: 'no', header: 'Person no.', render: p => <code>{p.person_no}</code> },
              { key: 'name', header: 'Name', render: p => <span className="font-medium">{p.full_name}</span> },
              { key: 'email', header: 'Email', render: p => p.email ?? '—' },
              { key: 'phone', header: 'Phone', render: p => p.phone ?? '—' },
            ]} />
        </Card>
        {can('persons.manage') && (
          <Card title="Add a person">
            <form onSubmit={submit} className="space-y-3">
              {create.error && !err?.problem.errors && !duplicates && <ErrorNotice error={create.error} />}
              <TextField label="Surname" value={form.surname} onChange={e => setForm({ ...form, surname: e.target.value })} required error={err?.fieldError('surname')} />
              <TextField label="First name" value={form.first_name} onChange={e => setForm({ ...form, first_name: e.target.value })} required error={err?.fieldError('first_name')} />
              <TextField label="Other names" value={form.other_names} onChange={e => setForm({ ...form, other_names: e.target.value })} />
              <TextField label="Date of birth" type="date" value={form.date_of_birth} onChange={e => setForm({ ...form, date_of_birth: e.target.value })} error={err?.fieldError('date_of_birth')} />
              <SelectField label="Gender" value={form.gender} onChange={e => setForm({ ...form, gender: e.target.value })}>
                <option value="">Not recorded</option><option value="female">Female</option><option value="male">Male</option>
              </SelectField>
              <TextField label="Email" type="email" value={form.email} onChange={e => setForm({ ...form, email: e.target.value })} error={err?.fieldError('email')} />
              <TextField label="Phone" value={form.phone} onChange={e => setForm({ ...form, phone: e.target.value })} error={err?.fieldError('phone')} />
              {duplicates ? (
                <div className="rounded-md border border-warn/40 bg-warn/5 p-3 text-sm">
                  <p className="font-medium">This may be someone already on record:</p>
                  <ul className="my-2 list-disc pl-5">{duplicates.map(d => <li key={d.id}>{d.full_name} ({d.person_no})</li>)}</ul>
                  <Button type="button" variant="secondary" onClick={e => submit(e, true)}>It is a different person, create anyway</Button>
                </div>
              ) : <Button type="submit" busy={create.isPending}>Create record</Button>}
            </form>
          </Card>
        )}
      </div>
    </>
  )
}
