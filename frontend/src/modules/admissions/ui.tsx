import { useEffect, useState } from 'react'
import { Button, ErrorNotice } from '../../design-system'

/** Save button with a brief confirmation, for the sections of a long form. */
export function SaveRow({ busy, saved, error, label = 'Save' }: { busy: boolean; saved: boolean; error?: unknown; label?: string }) {
  const [shown, setShown] = useState(false)
  useEffect(() => {
    if (!saved) return
    setShown(true)
    const t = setTimeout(() => setShown(false), 2500)
    return () => clearTimeout(t)
  }, [saved, busy])
  return (
    <div className="flex items-center gap-3 sm:col-span-4">
      <Button type="submit" variant="secondary" busy={busy}>{label}</Button>
      {shown && !busy && <span role="status" className="text-sm text-ok">Saved</span>}
      {!!error && <ErrorNotice error={error} />}
    </div>
  )
}
