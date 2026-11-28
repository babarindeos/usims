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

// Academic setup (M2)
export type Ref = { id: string; code: string; name: string }
export type ConfigEntry = { code: string; name: string; active: boolean; level?: string }

export type Programme = {
  id: string
  code: string
  name: string
  department: Ref | null
  award_type: { code: string; name: string } | null
  category: { code: string; name: string } | null
  duration_years: number
  min_duration_years: number
  max_duration_years: number
  capacity: number | null
  accreditation_status: string | null
  accreditation_expires_on: string | null
  description: string | null
  status: string
  study_modes?: { code: string; name: string; duration_years: number | null }[]
  entry_levels?: number[]
  can_manage: boolean
}

export type Course = {
  id: string
  code: string
  title: string
  description: string | null
  credit_units: number
  level: number
  semester_sequence: number | null
  department: Ref | null
  course_type: string
  has_practical: boolean
  lecture_hours: number | null
  practical_hours: number | null
  status: string
  requisites?: { id: number; type: 'prerequisite' | 'corequisite'; requirement: string; course: { id: string; code: string; title: string } }[]
  can_manage: boolean
}

export type CurriculumRow = {
  id: number
  course: { id: string; code: string; title: string }
  level: number
  semester_sequence: number
  category: string
  credit_units: number
  elective_group_id: number | null
}

export type ElectiveGroup = { id: number; code: string; name: string; level: number; semester_sequence: number; min_units: number; max_units: number | null }

export type Curriculum = {
  id: string
  version: number
  title: string
  status: string
  programme: { id: string; code: string; name: string; department: { code: string; name: string } | null } | null
  effective_session: { id: number; name: string } | null
  duration_years: number
  required_credit_units: number
  min_semester_units: number | null
  max_semester_units: number | null
  notes: string | null
  based_on: { id: string; version: number } | null
  approved_at: string | null
  published_at: string | null
  retired_at: string | null
  total_units?: number
  elective_groups?: ElectiveGroup[]
  courses?: CurriculumRow[]
  workflow?: { id: string; reference: string; status: string; current_stage: string | null } | null
  can: { edit: boolean; submit: boolean; publish: boolean; retire: boolean; new_version: boolean }
}

export type StaffMember = {
  id: string
  staff_no: string
  person: { id: string; full_name: string; person_no: string } | null
  department: Ref | null
  category: string
  rank: string | null
  institutional_email: string | null
  employment_status: string
  source: string
  can_manage: boolean
}

export type StaffAssignment = {
  id: number
  role: string
  status: string
  effective_from: string | null
  effective_to: string | null
  reason: string | null
  end_reason: string | null
  flagged_at: string | null
  flag_reason: string | null
  staff: { id: string; staff_no: string; full_name: string | null; rank: string | null; employment_status: string }
}

export type Offering = {
  id: string
  course: { id: string; code: string; title: string; credit_units: number; level: number }
  semester: { id: number; name: string; sequence: number; session: string | null }
  group_code: string
  department: Ref | null
  capacity: number | null
  registration_opens_on: string | null
  registration_closes_on: string | null
  delivery_mode: string
  status: string
  lms_shortname: string | null
  published_at: string | null
  staff_count?: number
  flagged_count?: number
  programmes?: { id: number; level: number; programme: { id: string; code: string; name: string } }[]
  staff?: StaffAssignment[]
  can: { manage: boolean; allocate: boolean }
}
