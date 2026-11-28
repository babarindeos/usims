import { useState } from 'react'
import { NavLink, Outlet } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useAuth } from '../app/auth'
import { useBranding } from '../app/branding'
import { APPLICANT_NAV, NAV } from '../app/navigation'
import { api } from '../lib/api'

export function AppShell() {
  const { user, can, signOut, applicantOnly } = useAuth()
  const branding = useBranding()
  const [open, setOpen] = useState(false)
  const unread = useQuery({ queryKey: ['notifications', 'unread'], queryFn: () => api<{ unread_count: number }>('/notifications?unread=1'), refetchInterval: 60_000 })

  const items = applicantOnly ? APPLICANT_NAV : NAV.filter(i => !i.permission || can(i.permission))
  const sections = [...new Set(items.map(i => i.section))]

  return (
    <div className="flex min-h-full">
      <aside className={`fixed inset-y-0 left-0 z-30 w-64 transform border-r border-line bg-surface transition md:static md:translate-x-0 ${open ? 'translate-x-0' : '-translate-x-full'}`}>
        <div className="flex h-14 items-center gap-2 border-b border-line px-4">
          {branding?.logo_url ? <img src={branding.logo_url} alt="" className="h-8 w-8 rounded" /> : <div className="h-8 w-8 rounded bg-brand" aria-hidden />}
          <div className="leading-tight">
            <p className="text-sm font-semibold">{branding?.portal_title ?? 'USIMS'}</p>
            <p className="truncate text-xs text-muted">{branding?.institution_name}</p>
          </div>
        </div>
        <nav aria-label="Main" className="space-y-5 p-3">
          {sections.map(section => (
            <div key={section}>
              <p className="px-2 pb-1 text-xs font-medium uppercase tracking-wide text-muted">{section}</p>
              {items.filter(i => i.section === section).map(i => (
                <NavLink key={i.to} to={i.to} end={i.to === '/' || i.to === '/applicant'} onClick={() => setOpen(false)}
                  className={({ isActive }) => `block rounded-md px-2 py-1.5 text-sm ${isActive ? 'bg-brand/10 font-medium text-brand' : 'text-ink hover:bg-canvas'}`}>
                  {i.label}
                </NavLink>
              ))}
            </div>
          ))}
        </nav>
      </aside>
      {open && <div className="fixed inset-0 z-20 bg-black/30 md:hidden" onClick={() => setOpen(false)} aria-hidden />}

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex h-14 items-center justify-between gap-3 border-b border-line bg-surface px-4">
          <button className="rounded-md p-2 md:hidden" onClick={() => setOpen(true)} aria-label="Open menu">☰</button>
          <div className="flex-1" />
          <NavLink to="/notifications" className="relative rounded-md px-2 py-1 text-sm text-muted hover:bg-canvas">
            Notifications
            {!!unread.data?.unread_count && <span className="ml-1 rounded-full bg-brand px-1.5 text-xs text-white">{unread.data.unread_count}</span>}
          </NavLink>
          <div className="text-right text-sm leading-tight">
            <p className="font-medium">{user?.name}</p>
            <p className="text-xs text-muted">{user?.grants?.map(g => g.role.name).join(', ') || 'No roles yet'}</p>
          </div>
          <button className="rounded-md px-2 py-1 text-sm text-brand hover:bg-canvas" onClick={() => signOut()}>Sign out</button>
        </header>
        <main className="mx-auto w-full max-w-7xl flex-1 p-4 md:p-8">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
