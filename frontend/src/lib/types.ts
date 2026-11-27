export type Paginated<T> = { data: T[]; meta?: { current_page: number; last_page: number; total: number }; links?: unknown }

export type Grant = {
  id: number
  role: { code: string; name: string; workspace: string | null }
  scope_type: 'global' | 'org_unit'
  org_unit: { id: string; code: string; name: string } | null
  starts_at: string
  ends_at: string | null
  revoked_at: string | null
  reason: string | null
}

export type User = {
  id: string
  name: string
  email: string
  status: 'active' | 'suspended' | 'disabled'
  mfa_required: boolean
  last_login_at: string | null
  person?: { id: string; person_no: string; full_name: string }
  grants?: Grant[]
}

export type Person = {
  id: string
  person_no: string
  surname: string
  first_name: string
  other_names: string | null
  full_name: string
  date_of_birth: string | null
  gender: string | null
  email: string | null
  phone: string | null
  identifiers?: { type: string; value: string; issued_at: string; superseded_at: string | null }[]
}

export type OrgUnit = {
  id: string
  parent_id: string | null
  type: { code: string; name: string; is_academic: boolean } | null
  code: string
  name: string
  short_name: string | null
  depth: number
  status: string
}

export type Semester = {
  id: number
  name: string
  code: string
  sequence: number
  starts_on: string
  ends_on: string
  registration_opens_on: string | null
  registration_closes_on: string | null
  status: string
}

export type AcademicSession = {
  id: number
  name: string
  starts_on: string
  ends_on: string
  is_current: boolean
  processes: Record<string, 'open' | 'closed' | 'suspended'>
  closed_at: string | null
  semesters: Semester[]
}

export type Role = { code: string; name: string; description: string | null; workspace: string | null; is_system: boolean; permissions?: string[] }
export type PermissionDef = { code: string; module: string; description: string; sensitive: boolean }

export type WorkflowItem = {
  id: string
  reference: string
  process: string
  workflow: string
  subject: { type: string; id: number }
  org_unit: { id: string; name: string } | null
  status: string
  current_stage: { code: string; name: string } | null
  initiated_by: { name: string } | null
  due_at: string | null
  created_at: string
  actions?: { reference: string; action: string; stage: string | null; user: string | null; role: string | null; remarks: string | null; at: string }[]
}

export type AuditEntry = {
  id: number
  action: string
  record_type: string
  record_id: number | null
  before: Record<string, unknown> | null
  after: Record<string, unknown> | null
  reason: string | null
  user: { name: string; email: string } | null
  ip_address: string | null
  created_at: string
}

export type Branding = { institution_name: string; portal_title: string; primary_color: string; secondary_color: string; logo_url: string | null }
