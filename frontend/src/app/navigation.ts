// Sidebar entries. Each shows when the user holds its permission somewhere; the API still checks scope.
// Applicant-only accounts see the applicant entries; everyone else sees the staff entries.
export type NavItem = { to: string; label: string; permission?: string; section: string; audience?: 'applicant' | 'staff' }

export const APPLICANT_NAV: NavItem[] = [
  { to: '/applicant', label: 'My applications', section: 'Admission', audience: 'applicant' },
  { to: '/notifications', label: 'Notifications', section: 'Admission', audience: 'applicant' },
]

export const NAV: NavItem[] = [
  { to: '/', label: 'Dashboard', section: 'Overview' },
  { to: '/approvals', label: 'Approvals', section: 'Overview' },
  { to: '/calendar', label: 'Academic calendar', section: 'Overview' },

  { to: '/structure', label: 'Organisation structure', section: 'Institution' },
  { to: '/sessions', label: 'Sessions and semesters', section: 'Institution' },

  { to: '/academic/programmes', label: 'Programmes', section: 'Academics' },
  { to: '/academic/courses', label: 'Course catalogue', section: 'Academics' },
  { to: '/academic/offerings', label: 'Course offerings', section: 'Academics' },
  { to: '/academic/config', label: 'Academic configuration', permission: 'academic_config.manage', section: 'Academics' },

  { to: '/admissions/exercises', label: 'Admission exercises', permission: 'admissions.view', section: 'Admissions' },
  { to: '/admissions/applications', label: 'Applications', permission: 'admissions.view', section: 'Admissions' },
  { to: '/admissions/lists', label: 'Admission lists', permission: 'admissions.view', section: 'Admissions' },
  { to: '/admissions/payments', label: 'Payment verification', permission: 'admission_payments.verify', section: 'Admissions' },
  { to: '/admissions/offers', label: 'Offers and matriculation', permission: 'admissions.view', section: 'Admissions' },
  { to: '/admissions/clearance', label: 'Clearance', permission: 'clearance.review', section: 'Admissions' },
  { to: '/admissions/config', label: 'Admissions configuration', permission: 'admissions.configure', section: 'Admissions' },

  { to: '/students', label: 'Student records', permission: 'students.view', section: 'Records' },
  { to: '/persons', label: 'Person records', permission: 'persons.view', section: 'Records' },
  { to: '/staff', label: 'Staff records', permission: 'staff.view', section: 'Records' },

  { to: '/users', label: 'Users and access', permission: 'users.view', section: 'Administration' },
  { to: '/roles', label: 'Roles and permissions', permission: 'users.view', section: 'Administration' },
  { to: '/audit', label: 'Audit trail', permission: 'audit.view', section: 'Administration' },
  { to: '/settings/branding', label: 'Branding', permission: 'settings.manage', section: 'Administration' },
]
