import { useState, type FormEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { useBranding } from '../../app/branding'
import { Button, ErrorNotice, formatDate, TextField } from '../../design-system'
import { api, ApiError } from '../../lib/api'
import type { Exercise } from '../../lib/types'

/** Public: create an applicant account. The email must be verified before an application can start. */
export function RegisterPage() {
  const branding = useBranding()
  const open = useQuery({ queryKey: ['open-exercises'], queryFn: () => api<{ data: Exercise[] }>('/admission-exercises/open').then(r => r.data) })
  const [form, setForm] = useState({ surname: '', first_name: '', other_names: '', email: '', phone: '', password: '', password_confirmation: '' })
  const [error, setError] = useState<ApiError | Error | null>(null)
  const [done, setDone] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const set = (k: keyof typeof form, v: string) => setForm({ ...form, [k]: v })
  const field = (k: string) => (error instanceof ApiError ? error.fieldError(k) : undefined)

  async function submit(e: FormEvent) {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      const r = await api<{ message: string }>('/applicant/register', { method: 'POST', body: form })
      setDone(r.message)
    } catch (err) {
      setError(err as Error)
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="flex min-h-full items-center justify-center p-4">
      <div className="w-full max-w-lg">
        <div className="mb-6 text-center">
          <h1 className="text-xl font-semibold">Apply for admission</h1>
          <p className="text-sm text-muted">{branding?.institution_name}</p>
        </div>
        <div className="mb-4 rounded-lg border border-line bg-surface p-4 text-sm">
          <p className="mb-2 font-medium">Admission exercises open now</p>
          {open.isLoading ? <p className="text-muted">Loading…</p> : open.data?.length ? (
            <ul className="space-y-1">
              {open.data.map(x => <li key={x.id}>{x.name} <span className="text-muted">· closes {formatDate(x.closes_at, true)}</span></li>)}
            </ul>
          ) : <p className="text-muted">No admission exercise is open at the moment. You can still create an account and apply when one opens.</p>}
        </div>
        {done ? (
          <div role="status" className="rounded-lg border border-ok/30 bg-ok/5 p-6 text-sm">
            <p className="mb-3">{done}</p>
            <Link to="/login" className="font-medium text-brand">Go to sign in</Link>
          </div>
        ) : (
          <form onSubmit={submit} className="grid gap-4 rounded-lg border border-line bg-surface p-6 sm:grid-cols-2">
            <div className="sm:col-span-2">{error && !(error instanceof ApiError && error.problem.errors) && <ErrorNotice error={error} />}</div>
            <TextField label="Surname" required value={form.surname} onChange={e => set('surname', e.target.value)} error={field('surname')} />
            <TextField label="First name" required value={form.first_name} onChange={e => set('first_name', e.target.value)} error={field('first_name')} />
            <TextField label="Other names" value={form.other_names} onChange={e => set('other_names', e.target.value)} className="sm:col-span-2" />
            <TextField label="Email" type="email" autoComplete="email" required value={form.email} onChange={e => set('email', e.target.value)} error={field('email')} />
            <TextField label="Phone" type="tel" required value={form.phone} onChange={e => set('phone', e.target.value)} error={field('phone')} />
            <TextField label="Password" type="password" autoComplete="new-password" required minLength={10} value={form.password} onChange={e => set('password', e.target.value)} error={field('password')} hint="At least 10 characters" />
            <TextField label="Confirm password" type="password" autoComplete="new-password" required value={form.password_confirmation} onChange={e => set('password_confirmation', e.target.value)} />
            <div className="flex items-center justify-between sm:col-span-2">
              <Link to="/login" className="text-sm text-brand">Already registered? Sign in</Link>
              <Button type="submit" busy={busy}>Create account</Button>
            </div>
          </form>
        )}
      </div>
    </div>
  )
}
