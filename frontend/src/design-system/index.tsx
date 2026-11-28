import type { ButtonHTMLAttributes, InputHTMLAttributes, ReactNode, SelectHTMLAttributes, TextareaHTMLAttributes } from 'react'
import { useId } from 'react'

const cx = (...c: (string | false | null | undefined)[]) => c.filter(Boolean).join(' ')

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & { variant?: 'primary' | 'secondary' | 'danger' | 'ghost'; busy?: boolean }

export function Button({ variant = 'primary', busy, className, children, disabled, ...rest }: ButtonProps) {
  const styles = {
    primary: 'bg-brand text-white hover:brightness-110',
    secondary: 'bg-surface text-ink border border-line hover:bg-canvas',
    danger: 'bg-bad text-white hover:brightness-110',
    ghost: 'text-brand hover:bg-canvas',
  }[variant]
  return (
    <button
      className={cx('inline-flex items-center justify-center gap-2 rounded-md px-3.5 py-2 text-sm font-medium transition disabled:opacity-50', styles, className)}
      disabled={disabled || busy}
      {...rest}
    >
      {busy && <Spinner small />}
      {children}
    </button>
  )
}

export function Spinner({ small }: { small?: boolean }) {
  return <span role="status" aria-label="Loading" className={cx('inline-block animate-spin rounded-full border-2 border-current border-r-transparent', small ? 'h-3.5 w-3.5' : 'h-6 w-6 text-brand')} />
}

type FieldWrap = { label: string; error?: string; hint?: string }

export function TextField({ label, error, hint, className, ...rest }: InputHTMLAttributes<HTMLInputElement> & FieldWrap) {
  const id = useId()
  return (
    <div className={className}>
      <label htmlFor={id} className="mb-1 block text-sm font-medium">{label}</label>
      <input id={id} aria-invalid={!!error} className={cx('w-full rounded-md border bg-surface px-3 py-2 text-sm', error ? 'border-bad' : 'border-line')} {...rest} />
      {hint && !error && <p className="mt-1 text-xs text-muted">{hint}</p>}
      {error && <p className="mt-1 text-xs text-bad">{error}</p>}
    </div>
  )
}

export function SelectField({ label, error, className, children, ...rest }: SelectHTMLAttributes<HTMLSelectElement> & FieldWrap & { children: ReactNode }) {
  const id = useId()
  return (
    <div className={className}>
      <label htmlFor={id} className="mb-1 block text-sm font-medium">{label}</label>
      <select id={id} aria-invalid={!!error} className={cx('w-full rounded-md border bg-surface px-3 py-2 text-sm', error ? 'border-bad' : 'border-line')} {...rest}>
        {children}
      </select>
      {error && <p className="mt-1 text-xs text-bad">{error}</p>}
    </div>
  )
}

export function TextAreaField({ label, error, className, ...rest }: TextareaHTMLAttributes<HTMLTextAreaElement> & FieldWrap) {
  const id = useId()
  return (
    <div className={className}>
      <label htmlFor={id} className="mb-1 block text-sm font-medium">{label}</label>
      <textarea id={id} aria-invalid={!!error} className={cx('w-full rounded-md border bg-surface px-3 py-2 text-sm', error ? 'border-bad' : 'border-line')} rows={3} {...rest} />
      {error && <p className="mt-1 text-xs text-bad">{error}</p>}
    </div>
  )
}

export function Card({ title, actions, children, className }: { title?: string; actions?: ReactNode; children: ReactNode; className?: string }) {
  return (
    <section className={cx('rounded-lg border border-line bg-surface', className)}>
      {(title || actions) && (
        <header className="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
          {title && <h2 className="text-sm font-semibold">{title}</h2>}
          {actions}
        </header>
      )}
      <div className="p-4">{children}</div>
    </section>
  )
}

export function PageHeader({ title, description, actions }: { title: string; description?: string; actions?: ReactNode }) {
  return (
    <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 className="text-xl font-semibold">{title}</h1>
        {description && <p className="mt-1 text-sm text-muted">{description}</p>}
      </div>
      {actions && <div className="flex items-center gap-2">{actions}</div>}
    </div>
  )
}

const tones: Record<string, string> = {
  active: 'bg-ok/10 text-ok', open: 'bg-ok/10 text-ok', approved: 'bg-ok/10 text-ok',
  suspended: 'bg-warn/10 text-warn', returned: 'bg-warn/10 text-warn', in_progress: 'bg-brand/10 text-brand',
  closed: 'bg-canvas text-muted', disabled: 'bg-canvas text-muted', inactive: 'bg-canvas text-muted', planned: 'bg-canvas text-muted',
  rejected: 'bg-bad/10 text-bad', cancelled: 'bg-canvas text-muted',
  draft: 'bg-canvas text-muted', in_review: 'bg-brand/10 text-brand', published: 'bg-ok/10 text-ok', retired: 'bg-canvas text-muted',
  on_leave: 'bg-warn/10 text-warn', exited: 'bg-bad/10 text-bad', ended: 'bg-canvas text-muted', phased_out: 'bg-canvas text-muted',
}

export function StatusBadge({ status }: { status: string }) {
  return <span className={cx('inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize', tones[status] ?? 'bg-canvas text-muted')}>{status.replace(/_/g, ' ')}</span>
}

export type Column<T> = { key: string; header: string; render: (row: T) => ReactNode; className?: string }

export function DataTable<T>({ columns, rows, rowKey, empty = 'Nothing to show yet.', loading }: { columns: Column<T>[]; rows: T[] | undefined; rowKey: (row: T) => string | number; empty?: string; loading?: boolean }) {
  if (loading) return <div className="flex justify-center p-8"><Spinner /></div>
  if (!rows?.length) return <p className="p-6 text-center text-sm text-muted">{empty}</p>
  return (
    <div className="overflow-x-auto">
      <table className="w-full text-left text-sm">
        <thead className="border-b border-line text-xs uppercase tracking-wide text-muted">
          <tr>{columns.map(c => <th key={c.key} scope="col" className={cx('px-3 py-2 font-medium', c.className)}>{c.header}</th>)}</tr>
        </thead>
        <tbody>
          {rows.map(row => (
            <tr key={rowKey(row)} className="border-b border-line last:border-0 hover:bg-canvas/60">
              {columns.map(c => <td key={c.key} className={cx('px-3 py-2.5 align-top', c.className)}>{c.render(row)}</td>)}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

export function ErrorNotice({ error }: { error: unknown }) {
  if (!error) return null
  const message = error instanceof Error ? error.message : 'Something went wrong.'
  return <div role="alert" className="rounded-md border border-bad/30 bg-bad/5 px-3 py-2 text-sm text-bad">{message}</div>
}

export function Stat({ label, value, hint }: { label: string; value: ReactNode; hint?: string }) {
  return (
    <div className="rounded-lg border border-line bg-surface p-4">
      <p className="text-xs font-medium uppercase tracking-wide text-muted">{label}</p>
      <p className="mt-1 text-2xl font-semibold">{value}</p>
      {hint && <p className="mt-1 text-xs text-muted">{hint}</p>}
    </div>
  )
}

export function formatDate(value: string | null | undefined, withTime = false): string {
  if (!value) return '—'
  const d = new Date(value)
  return withTime
    ? d.toLocaleString('en-NG', { dateStyle: 'medium', timeStyle: 'short' })
    : d.toLocaleDateString('en-NG', { dateStyle: 'medium' })
}
