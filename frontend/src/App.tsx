import { Navigate, Route, Routes } from 'react-router-dom'
import { useAuth } from './app/auth'
import { Spinner } from './design-system'
import { AppShell } from './layout/AppShell'
import { AuditLogPage } from './modules/audit/AuditLogPage'
import { LoginPage } from './modules/auth/LoginPage'
import { DashboardPage } from './modules/dashboard/DashboardPage'
import { NotificationsPage } from './modules/dashboard/NotificationsPage'
import { PersonsPage } from './modules/identity/PersonsPage'
import { RolesPage } from './modules/identity/RolesPage'
import { UserDetailPage } from './modules/identity/UserDetailPage'
import { UsersPage } from './modules/identity/UsersPage'
import { CalendarPage } from './modules/institution/CalendarPage'
import { SessionsPage } from './modules/institution/SessionsPage'
import { StructurePage } from './modules/institution/StructurePage'
import { BrandingPage } from './modules/settings/BrandingPage'
import { ApprovalDetailPage } from './modules/workflow/ApprovalDetailPage'
import { ApprovalsPage } from './modules/workflow/ApprovalsPage'

function RequireAuth({ children }: { children: React.ReactNode }) {
  const { user, loading } = useAuth()
  if (loading) return <div className="flex h-full items-center justify-center"><Spinner /></div>
  return user ? <>{children}</> : <Navigate to="/login" replace />
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route element={<RequireAuth><AppShell /></RequireAuth>}>
        <Route index element={<DashboardPage />} />
        <Route path="approvals" element={<ApprovalsPage />} />
        <Route path="approvals/:id" element={<ApprovalDetailPage />} />
        <Route path="calendar" element={<CalendarPage />} />
        <Route path="structure" element={<StructurePage />} />
        <Route path="sessions" element={<SessionsPage />} />
        <Route path="persons" element={<PersonsPage />} />
        <Route path="users" element={<UsersPage />} />
        <Route path="users/:id" element={<UserDetailPage />} />
        <Route path="roles" element={<RolesPage />} />
        <Route path="audit" element={<AuditLogPage />} />
        <Route path="notifications" element={<NotificationsPage />} />
        <Route path="settings/branding" element={<BrandingPage />} />
        <Route path="*" element={<p className="text-sm text-muted">Page not found.</p>} />
      </Route>
    </Routes>
  )
}
