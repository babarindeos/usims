// Sidebar entries. Each shows when the user holds its permission somewhere; the API still checks scope.
export type NavItem = { to: string; label: string; permission?: string; section: string }

export const NAV: NavItem[] = [
  { to: '/', label: 'Dashboard', section: 'Overview' },
  { to: '/approvals', label: 'Approvals', section: 'Overview' },
  { to: '/calendar', label: 'Academic calendar', section: 'Overview' },

  { to: '/structure', label: 'Organisation structure', section: 'Institution' },
  { to: '/sessions', label: 'Sessions and semesters', section: 'Institution' },

  { to: '/persons', label: 'Person records', permission: 'persons.view', section: 'Records' },

  { to: '/users', label: 'Users and access', permission: 'users.view', section: 'Administration' },
  { to: '/roles', label: 'Roles and permissions', permission: 'users.view', section: 'Administration' },
  { to: '/audit', label: 'Audit trail', permission: 'audit.view', section: 'Administration' },
  { to: '/settings/branding', label: 'Branding', permission: 'settings.manage', section: 'Administration' },
]
