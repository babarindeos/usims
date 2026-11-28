import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { cleanup, render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import type { Application } from '../../lib/types'
import { ApplicationPage } from './ApplicationPage'

const draft: Application = {
  id: 'a1', application_no: null, status: 'draft', corrections_open: false, editable: true,
  exercise: {
    id: 'e1', name: '2026/2027 UTME admission', category: { code: 'UTME', name: 'UTME', requires_jamb: true }, session: '2026/2027',
    closes_at: '2027-01-28T00:00:00Z', instructions: null,
    programmes: [{ id: 7, entry_level: 100, programme: { id: 'p1', code: 'BSC-CSC', name: 'B.Sc. Computer Science', department: 'Computer Science' } }],
    documents: [{ code: 'OLEVEL', name: "O'Level result", stage: 'application', required: true, max_kb: 2048 }],
  },
  applicant: { id: 'pe1', person_no: 'PER-1', full_name: 'Okafor, Ada', title: null, surname: 'Okafor', first_name: 'Ada', other_names: null, date_of_birth: null, gender: null, nationality: null, state_of_origin: null, lga: null, email: 'ada@example.test', phone: null, address: null },
  choice: null, second_choice: null, next_of_kin: {}, jamb: { reg_no: null, year: null, score: null, subjects: [] },
  screening_outcome: null, screened_at: null, submitted_at: null, sittings: [], education: [], documents: [],
  payments: [{ id: 'pay1', purpose: 'application_fee', amount: 2000, status: 'awaiting_payment', reference: null, paid_on: null, remarks: null, evidence_id: null }],
  history: [], missing: ['Programme choice', "Document: O'Level result", 'Application fee payment'], offer: null,
}

function renderWith(application: Application) {
  vi.stubGlobal('fetch', vi.fn(async (url: string) => {
    const body = String(url).includes('/applicant/applications/') ? { data: application } : { data: [] }
    return new Response(JSON.stringify(body), { status: 200, headers: { 'Content-Type': 'application/json' } })
  }))
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/applicant/applications/a1']}>
        <Routes><Route path="/applicant/applications/:id" element={<ApplicationPage />} /></Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

afterEach(() => { cleanup(); vi.unstubAllGlobals() })

describe('applicant application form', () => {
  it('lists what is missing and keeps submit disabled', async () => {
    renderWith(draft)
    expect(await screen.findByText('Application fee payment')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Submit application' })).toBeDisabled()
    expect(screen.getByLabelText('JAMB registration number')).toBeInTheDocument()
    expect(screen.getByText(/Online payment is not configured/)).toBeInTheDocument()
  })

  it('shows the offer letter and the acceptance declaration once offered', async () => {
    renderWith({
      ...draft, status: 'offered', editable: false, missing: [], application_no: 'APP-2026-000001',
      offer: { id: 'o1', admission_no: 'ADM-2026-000001', programme: null, entry_level: 100, offered_at: '2026-11-28T00:00:00Z', accept_by: '2099-12-19', status: 'offered', responded_at: null, letter: 'OFFER OF PROVISIONAL ADMISSION', onboarding: {}, onboarding_submitted_at: null, clearance: [] },
    })
    expect(await screen.findByText('OFFER OF PROVISIONAL ADMISSION')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Accept offer' })).toBeDisabled()
    expect(screen.queryByRole('button', { name: 'Submit application' })).not.toBeInTheDocument()
  })
})
