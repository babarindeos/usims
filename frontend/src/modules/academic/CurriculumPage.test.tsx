import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { cleanup, render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import type { Curriculum } from '../../lib/types'
import { CurriculumPage } from './CurriculumPage'

const base: Curriculum = {
  id: 'c1', version: 2, title: 'B.Sc. Computer Science v2', status: 'draft',
  programme: { id: 'p1', code: 'BSC-CSC', name: 'B.Sc. Computer Science', department: { code: 'CSC', name: 'Computer Science' } },
  effective_session: { id: 1, name: '2026/2027' }, duration_years: 4, required_credit_units: 10,
  min_semester_units: null, max_semester_units: null, notes: null, based_on: null,
  approved_at: null, published_at: null, retired_at: null, total_units: 5, elective_groups: [],
  courses: [
    { id: 1, course: { id: 'a', code: 'CSC 101', title: 'Intro to Computing' }, level: 100, semester_sequence: 1, category: 'core', credit_units: 3, elective_group_id: null },
    { id: 2, course: { id: 'b', code: 'CSC 102', title: 'Programming' }, level: 100, semester_sequence: 2, category: 'core', credit_units: 2, elective_group_id: null },
  ],
  workflow: null,
  can: { edit: true, submit: true, publish: false, retire: false, new_version: true },
}

function renderWith(curriculum: Curriculum) {
  vi.stubGlobal('fetch', vi.fn(async () => new Response(JSON.stringify({ data: curriculum }), { status: 200, headers: { 'Content-Type': 'application/json' } })))
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/academic/curricula/c1']}>
        <Routes><Route path="/academic/curricula/:id" element={<CurriculumPage />} /></Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

afterEach(() => { cleanup(); vi.unstubAllGlobals() })

describe('curriculum editor', () => {
  it('groups courses by level and semester and shows the unit shortfall', async () => {
    renderWith(base)
    expect(await screen.findByText('5 / 10')).toBeInTheDocument()
    expect(screen.getByText(/100 level · First semester/)).toBeInTheDocument()
    expect(screen.getByText(/100 level · Second semester/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Submit for approval' })).toBeInTheDocument()
  })

  it('locks a published version and points to a new version', async () => {
    renderWith({ ...base, status: 'published', can: { edit: false, submit: false, publish: false, retire: true, new_version: true } })
    expect(await screen.findByText(/can no longer be edited/)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Submit for approval' })).not.toBeInTheDocument()
    expect(screen.queryByLabelText('Add a course')).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Retire' })).toBeInTheDocument()
  })
})
