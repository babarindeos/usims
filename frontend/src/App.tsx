import { Navigate, Route, Routes } from 'react-router-dom'
import { useAuth } from './app/auth'
import { Spinner } from './design-system'
import { AppShell } from './layout/AppShell'
import { AcademicConfigPage } from './modules/academic/AcademicConfigPage'
import { CourseDetailPage } from './modules/academic/CourseDetailPage'
import { CoursesPage } from './modules/academic/CoursesPage'
import { CurriculumPage } from './modules/academic/CurriculumPage'
import { OfferingDetailPage } from './modules/academic/OfferingDetailPage'
import { OfferingsPage } from './modules/academic/OfferingsPage'
import { ProgrammeDetailPage } from './modules/academic/ProgrammeDetailPage'
import { ProgrammesPage } from './modules/academic/ProgrammesPage'
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
import { StaffPage } from './modules/staff/StaffPage'
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
        <Route path="academic/programmes" element={<ProgrammesPage />} />
        <Route path="academic/programmes/:id" element={<ProgrammeDetailPage />} />
        <Route path="academic/courses" element={<CoursesPage />} />
        <Route path="academic/courses/:id" element={<CourseDetailPage />} />
        <Route path="academic/curricula/:id" element={<CurriculumPage />} />
        <Route path="academic/offerings" element={<OfferingsPage />} />
        <Route path="academic/offerings/:id" element={<OfferingDetailPage />} />
        <Route path="academic/config" element={<AcademicConfigPage />} />
        <Route path="persons" element={<PersonsPage />} />
        <Route path="staff" element={<StaffPage />} />
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
