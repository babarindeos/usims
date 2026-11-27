import { useEffect, useState } from 'react'
import { useBranding } from '../../app/branding'
import { Button, Card, ErrorNotice, PageHeader, TextField } from '../../design-system'
import type { Branding } from '../../lib/types'
import { useMutate } from '../../lib/useMutate'

export function BrandingPage() {
  const branding = useBranding()
  const [form, setForm] = useState<Branding | null>(null)
  const save = useMutate<Branding>('PUT', '/branding', [['branding']])
  useEffect(() => { if (branding && !form) setForm(branding) }, [branding, form])
  if (!form) return null

  return (
    <>
      <PageHeader title="Branding" description="Name, colours and logo shown across every portal, letter and email." />
      <Card className="max-w-xl">
        <form className="space-y-3" onSubmit={e => { e.preventDefault(); save.mutate(form) }}>
          <ErrorNotice error={save.error} />
          <TextField label="Institution name" value={form.institution_name} onChange={e => setForm({ ...form, institution_name: e.target.value })} required />
          <TextField label="Portal title" value={form.portal_title} onChange={e => setForm({ ...form, portal_title: e.target.value })} required />
          <div className="grid grid-cols-2 gap-3">
            <TextField label="Primary colour" type="color" value={form.primary_color} onChange={e => setForm({ ...form, primary_color: e.target.value })} />
            <TextField label="Secondary colour" type="color" value={form.secondary_color} onChange={e => setForm({ ...form, secondary_color: e.target.value })} />
          </div>
          <TextField label="Logo URL" type="url" value={form.logo_url ?? ''} onChange={e => setForm({ ...form, logo_url: e.target.value || null })} />
          <Button type="submit" busy={save.isPending}>Save branding</Button>
          {save.isSuccess && <p className="text-sm text-ok">Saved.</p>}
        </form>
      </Card>
    </>
  )
}
