import { useState, type FormEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useAuth } from '../../app/auth'
import { Button, Card, DataTable, ErrorNotice, PageHeader, SelectField, TextField, formatDate } from '../../design-system'
import { api, qs } from '../../lib/api'
import type { AcademicSession } from '../../lib/types'
import { useMutate } from '../../lib/useMutate'

type CalendarEvent = { id: number; category: string; title: string; starts_on: string; ends_on: string; audience: string; org_unit: { name: string } | null }

const CATEGORIES = ['resumption', 'registration', 'lectures', 'add_drop', 'continuous_assessment', 'examinations', 'result_processing', 'department_board', 'faculty_board', 'senate', 'graduation', 'holiday', 'other']

export function CalendarPage() {
  const { can } = useAuth()
  const sessions = useQuery({ queryKey: ['sessions'], queryFn: () => api<{ data: AcademicSession[] }>('/academic-sessions').then(r => r.data) })
  const [sessionId, setSessionId] = useState<string>('')
  const selected = sessionId || String(sessions.data?.find(s => s.is_current)?.id ?? sessions.data?.[0]?.id ?? '')
  const events = useQuery({
    queryKey: ['calendar', selected],
    enabled: !!selected,
    queryFn: () => api<{ data: CalendarEvent[] }>('/calendar-events' + qs({ academic_session_id: selected })).then(r => r.data),
  })
  const create = useMutate<Record<string, string | number>>('POST', '/calendar-events', [['calendar']])
  const [form, setForm] = useState({ category: 'lectures', title: '', starts_on: '', ends_on: '' })

  return (
    <>
      <PageHeader title="Academic calendar" actions={
        <SelectField label="Session" value={selected} onChange={e => setSessionId(e.target.value)}>
          {sessions.data?.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
        </SelectField>
      } />
      {can('calendar.manage') && selected && (
        <Card title="Add a university-wide event" className="mb-6">
          <form className="grid gap-3 sm:grid-cols-5 sm:items-end" onSubmit={(e: FormEvent) => { e.preventDefault(); create.mutate({ ...form, academic_session_id: Number(selected) }) }}>
            <SelectField label="Category" value={form.category} onChange={e => setForm({ ...form, category: e.target.value })}>
              {CATEGORIES.map(c => <option key={c} value={c}>{c.replace(/_/g, ' ')}</option>)}
            </SelectField>
            <TextField label="Title" value={form.title} onChange={e => setForm({ ...form, title: e.target.value })} required />
            <TextField label="Starts" type="date" value={form.starts_on} onChange={e => setForm({ ...form, starts_on: e.target.value })} required />
            <TextField label="Ends" type="date" value={form.ends_on} onChange={e => setForm({ ...form, ends_on: e.target.value })} required />
            <Button type="submit" busy={create.isPending}>Add event</Button>
          </form>
          <div className="mt-2"><ErrorNotice error={create.error} /></div>
        </Card>
      )}
      <Card>
        <DataTable rows={events.data} loading={events.isLoading} rowKey={r => r.id} empty="No calendar events for this session."
          columns={[
            { key: 'dates', header: 'Dates', render: r => `${formatDate(r.starts_on)}${r.ends_on !== r.starts_on ? ` to ${formatDate(r.ends_on)}` : ''}` },
            { key: 'title', header: 'Event', render: r => <span className="font-medium">{r.title}</span> },
            { key: 'category', header: 'Category', render: r => <span className="capitalize">{r.category.replace(/_/g, ' ')}</span> },
            { key: 'scope', header: 'For', render: r => r.org_unit?.name ?? 'Whole university' },
          ]} />
      </Card>
    </>
  )
}
