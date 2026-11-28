import { useQuery } from '@tanstack/react-query'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../../app/auth'
import { Button, Card, ErrorNotice, formatDate, PageHeader, Spinner, StatusBadge } from '../../design-system'
import { api } from '../../lib/api'
import type { Application, Exercise } from '../../lib/types'
import { useMutate } from '../../lib/useMutate'
import { naira } from './shared'

export function ApplicantHomePage() {
  const { user } = useAuth()
  const navigate = useNavigate()
  const mine = useQuery({ queryKey: ['applicant-apps'], queryFn: () => api<{ data: Application[] }>('/applicant/applications').then(r => r.data) })
  const open = useQuery({ queryKey: ['open-exercises'], queryFn: () => api<{ data: Exercise[] }>('/admission-exercises/open').then(r => r.data) })
  const start = useMutate<{ exercise_id: string }, { data: Application }>('POST', '/applicant/applications', [['applicant-apps']])
  const resend = useMutate('POST', '/applicant/verification-notice', [])
  const verified = !!user?.email_verified_at
  const started = new Set(mine.data?.map(a => a.exercise?.id))

  return (
    <>
      <PageHeader title={`Welcome, ${user?.name ?? ''}`} description="Start an application, track its progress, and respond to your admission offer here." />
      {!verified && (
        <div role="status" className="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-warn/30 bg-warn/5 p-4 text-sm">
          <p>Verify your email address before you start an application. We sent a link to <strong>{user?.email}</strong>.</p>
          {resend.isSuccess ? <span className="text-muted">A new link is on its way.</span> : <Button variant="secondary" busy={resend.isPending} onClick={() => resend.mutate(undefined)}>Send a new link</Button>}
        </div>
      )}

      <Card title="My applications" className="mb-6">
        {mine.isLoading ? <Spinner /> : mine.data?.length ? (
          <ul className="divide-y divide-line">
            {mine.data.map(a => (
              <li key={a.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
                <div>
                  <Link to={`/applicant/applications/${a.id}`} className="font-medium text-brand">{a.exercise?.name}</Link>
                  <p className="text-xs text-muted">{a.application_no ?? 'Not yet submitted'} · {a.choice ? `${a.choice.programme.name}, ${a.choice.entry_level} level` : 'No programme chosen yet'}</p>
                </div>
                <div className="flex items-center gap-2">
                  {a.offer && <StatusBadge status={a.offer.status === 'offered' ? 'offered' : a.offer.status} />}
                  <StatusBadge status={a.status} />
                </div>
              </li>
            ))}
          </ul>
        ) : <p className="text-sm text-muted">You have not started an application yet.</p>}
      </Card>

      <Card title="Open admission exercises">
        <ErrorNotice error={start.error} />
        {open.isLoading ? <Spinner /> : open.data?.length ? (
          <ul className="divide-y divide-line">
            {open.data.map(x => (
              <li key={x.id} className="py-3">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <p className="font-medium">{x.name}</p>
                    <p className="text-xs text-muted">{x.category?.name} · {x.session?.name} · closes {formatDate(x.closes_at, true)} · application fee {x.application_fee > 0 ? naira(x.application_fee) : 'none'}</p>
                    <p className="mt-1 text-xs text-muted">Programmes: {x.programmes?.map(p => `${p.programme.name} (${p.entry_level})`).join(', ') || 'none listed'}</p>
                  </div>
                  {started.has(x.id) ? <span className="text-xs text-muted">Already started</span> : (
                    <Button disabled={!verified} busy={start.isPending && start.variables?.exercise_id === x.id}
                      onClick={() => start.mutate({ exercise_id: x.id }, { onSuccess: r => navigate(`/applicant/applications/${r.data.id}`) })}>
                      Start application
                    </Button>
                  )}
                </div>
                {x.instructions && <p className="mt-2 whitespace-pre-line text-sm">{x.instructions}</p>}
              </li>
            ))}
          </ul>
        ) : <p className="text-sm text-muted">No admission exercise is open at the moment.</p>}
      </Card>
    </>
  )
}
