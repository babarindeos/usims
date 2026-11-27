import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { DataTable, StatusBadge, TextField } from '.'

describe('design system', () => {
  it('labels fields and shows errors', () => {
    render(<TextField label="Email" error="Required" />)
    expect(screen.getByLabelText('Email')).toHaveAttribute('aria-invalid', 'true')
    expect(screen.getByText('Required')).toBeInTheDocument()
  })

  it('shows an empty message instead of a blank table', () => {
    render(<DataTable rows={[]} rowKey={() => 1} columns={[]} empty="No users." />)
    expect(screen.getByText('No users.')).toBeInTheDocument()
  })

  it('renders readable status labels', () => {
    render(<StatusBadge status="in_progress" />)
    expect(screen.getByText('in progress')).toBeInTheDocument()
  })
})
