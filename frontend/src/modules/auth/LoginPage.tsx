import { useState, type FormEvent } from 'react'
import { Navigate, useNavigate } from 'react-router-dom'
import { useAuth } from '../../app/auth'
import { useBranding } from '../../app/branding'
import { Button, ErrorNotice, TextField } from '../../design-system'
import { ApiError } from '../../lib/api'

export function LoginPage() {
  const { user, signIn } = useAuth()
  const branding = useBranding()
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<unknown>(null)
  const [busy, setBusy] = useState(false)

  if (user) return <Navigate to="/" replace />

  async function submit(e: FormEvent) {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      await signIn(email, password)
      navigate('/')
    } catch (err) {
      setError(err instanceof ApiError ? new Error(err.fieldError('email') ?? err.message) : err)
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="flex min-h-full items-center justify-center p-4">
      <div className="w-full max-w-sm">
        <div className="mb-6 text-center">
          <div className="mx-auto mb-3 h-12 w-12 rounded-lg bg-brand" aria-hidden />
          <h1 className="text-xl font-semibold">{branding?.portal_title ?? 'USIMS'}</h1>
          <p className="text-sm text-muted">{branding?.institution_name}</p>
        </div>
        <form onSubmit={submit} className="space-y-4 rounded-lg border border-line bg-surface p-6">
          <ErrorNotice error={error} />
          <TextField label="Email" type="email" autoComplete="username" required value={email} onChange={e => setEmail(e.target.value)} />
          <TextField label="Password" type="password" autoComplete="current-password" required value={password} onChange={e => setPassword(e.target.value)} />
          <Button type="submit" busy={busy} className="w-full">Sign in</Button>
        </form>
      </div>
    </div>
  )
}
