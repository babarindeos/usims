import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { api, qs } from '../../lib/api'
import type { Course } from '../../lib/types'

/** Type-ahead over the course catalogue. */
export function CourseSearch({ label, onPick, exclude = [] }: { label: string; onPick: (course: Course) => void; exclude?: string[] }) {
  const [q, setQ] = useState('')
  const results = useQuery({
    queryKey: ['courses', 'search', q],
    queryFn: () => api<{ data: Course[] }>(`/courses${qs({ q, per_page: 8 })}`).then(r => r.data),
    enabled: q.trim().length >= 2,
  })
  const options = results.data?.filter(c => !exclude.includes(c.id)) ?? []

  return (
    <div className="relative">
      <label className="mb-1 block text-sm font-medium" htmlFor="course-search">{label}</label>
      <input id="course-search" value={q} onChange={e => setQ(e.target.value)} placeholder="Code or title, e.g. CSC 201"
        className="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm" autoComplete="off" />
      {q.trim().length >= 2 && (
        <ul className="absolute z-10 mt-1 max-h-64 w-full overflow-auto rounded-md border border-line bg-surface text-sm shadow-lg">
          {options.map(c => (
            <li key={c.id}>
              <button type="button" className="w-full px-3 py-2 text-left hover:bg-canvas" onClick={() => { onPick(c); setQ('') }}>
                <span className="font-medium">{c.code}</span> {c.title} <span className="text-muted">· {c.credit_units} units · {c.level} level</span>
              </button>
            </li>
          ))}
          {results.isFetched && !options.length && <li className="px-3 py-2 text-muted">No matching courses.</li>}
        </ul>
      )}
    </div>
  )
}
