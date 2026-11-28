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
  email_verified_at?: string | null
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
  subject: { type: string; id: number; uuid?: string | null }
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

// Admissions (M3)
export type ChoiceRef = { id: number; entry_level: number; programme: { id: string; code: string; name: string; department: string | null } }

export type ExerciseRules = {
  min_utme_score?: number
  required_utme_subjects?: string[]
  required_credit_subjects?: string[]
  min_credit_passes?: number
  max_sittings?: number
  require_verified_documents?: boolean
  manual_review?: boolean
}

export type Exercise = {
  id: string
  name: string
  session: { id: number; name: string } | null
  category: { code: string; name: string; requires_jamb: boolean } | null
  opens_at: string
  closes_at: string
  application_fee: number
  acceptance_fee: number
  capacity: number | null
  acceptance_days: number
  instructions: string | null
  status: string
  effective_status: string
  accepting_applications: boolean
  applications_count?: number
  programmes?: { id: number; programme: ChoiceRef['programme']; entry_level: number; capacity: number | null; rules: ExerciseRules; active: boolean }[]
  documents?: { id: number; stage: 'application' | 'onboarding'; required: boolean; type: { id: number; code: string; name: string; max_kb: number } }[]
}

export type AppDocument = { id: string; type: { id: number; code: string; name: string }; original_name: string; size: number; status: string; remarks: string | null; uploaded_at: string; url: string }
export type AppPayment = { id: string; purpose: 'application_fee' | 'acceptance_fee'; amount: number; status: string; reference: string | null; paid_on: string | null; remarks: string | null; evidence_id: string | null }
export type ScreeningCheck = { check: string; passed: boolean | null; detail: string }

export type ClearanceEntry = { id: number; unit: string; instructions: string | null; status: string; reviewer_unit: string | null; actions: { to: string; remarks: string | null; by: string | null; at: string }[] }

export type Offer = {
  id: string
  admission_no: string
  programme: { id: string; code: string; name: string } | null
  entry_level: number
  offered_at: string
  accept_by: string
  status: string
  responded_at: string | null
  letter: string
  onboarding: Record<string, Record<string, string> | boolean>
  onboarding_submitted_at: string | null
  clearance?: ClearanceEntry[]
  unmet?: string[]
  student?: { id: string; matric_no: string; student_no: string } | null
}

export type Application = {
  id: string
  application_no: string | null
  status: string
  exercise: {
    id: string; name: string; category: { code: string; name: string; requires_jamb: boolean } | null; session: string | null; closes_at: string; instructions: string | null
    programmes?: ChoiceRef[]
    documents?: { code: string; name: string; stage: 'application' | 'onboarding'; required: boolean; max_kb: number }[]
  } | null
  applicant: {
    id: string; person_no: string; full_name: string; title: string | null; surname: string; first_name: string; other_names: string | null
    date_of_birth: string | null; gender: string | null; nationality: string | null; state_of_origin: string | null; lga: string | null
    email: string | null; phone: string | null; address: string | null
  } | null
  choice: ChoiceRef | null
  second_choice: ChoiceRef | null
  next_of_kin: { name?: string; relationship?: string; phone?: string; address?: string }
  jamb: { reg_no: string | null; year: number | null; score: number | null; subjects: string[] }
  screening_outcome: string | null
  screened_at: string | null
  submitted_at: string | null
  corrections_open: boolean
  editable: boolean
  sittings?: { exam_body: string; exam_year: number; exam_number: string; results: { subject: string; subject_name: string; grade: string; credit: boolean }[] }[]
  education?: { institution: string; qualification: string; course_of_study: string | null; grade: string | null; year_obtained: number }[]
  documents?: AppDocument[]
  payments?: AppPayment[]
  screenings?: { outcome: string; checks: ScreeningCheck[]; at: string }[]
  history?: { from: string | null; to: string; reason: string | null; by: string | null; at: string }[]
  missing?: string[]
  offer?: Offer | null
}

export type AdmissionConfigRow = {
  id: number; code?: string; name: string; active: boolean; requires_jamb?: boolean; description?: string | null; needs_verification?: boolean; max_kb?: number
  grades?: { grade: string; points: number; is_credit: boolean }[]
  target?: 'fixed' | 'department' | 'faculty'; instructions?: string | null; sort_order?: number; org_unit?: { id: string; name: string } | null
  format?: string; admission_category?: string | null; programme_category?: string | null
}

export type AdmissionListSummary = { id: string; name: string; status: string; exercise: { id: string; name: string }; approved_at: string | null; created_at: string; entries_count?: number }
export type AdmissionListDetail = AdmissionListSummary & {
  editable: boolean
  workflow: { id: string; reference: string; status: string; current_stage: string | null } | null
  entries: { application_id: string; application_no: string; name: string; utme_score: number | null; screening_outcome: string | null; programme: string; entry_level: number; offer: { admission_no: string; status: string } | null }[]
}

export type OfferRow = { id: string; admission_no: string; name: string; application_no: string; programme: string; entry_level: number; status: string; accept_by: string; clearance: { unit: string; status: string }[]; matric_no: string | null; unmet: string[] }

export type Student = {
  id: string
  student_no: string
  matric_no: string
  full_name: string
  person?: { id: string; person_no: string; date_of_birth: string | null; gender: string | null; nationality: string | null; state_of_origin: string | null; lga: string | null; email: string | null; phone: string | null; address: string | null }
  programme: { id: string; code: string; name: string }
  department: string | null
  faculty?: string | null
  admission_category: string | null
  admission_session: string | null
  entry_level: number
  current_level: number
  status: string
  matriculated_at: string
  curriculum?: { id: string; version: number; title: string } | null
  admission_no?: string
  next_of_kin?: Record<string, string> | null
  onboarding?: Record<string, Record<string, string> | boolean> | null
  identifiers?: { type: string; value: string; issued_at: string; superseded_at: string | null }[]
  status_history?: { from: string | null; to: string; effective_on: string; reason: string | null; by: string | null }[]
  allowed_statuses: string[]
  can_manage: boolean
}
