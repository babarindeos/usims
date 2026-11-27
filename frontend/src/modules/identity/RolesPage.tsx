import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useAuth } from '../../app/auth'
import { Button, Card, ErrorNotice, PageHeader, Spinner } from '../../design-system'
import { api } from '../../lib/api'
import type { PermissionDef, Role } from '../../lib/types'
import { useMutate } from '../../lib/useMutate'

export function RolesPage() {
  const { can } = useAuth()
  const roles = useQuery({ queryKey: ['roles'], queryFn: () => api<{ data: Role[] }>('/roles').then(r => r.data) })
  const perms = useQuery({ queryKey: ['permissions'], queryFn: () => api<{ data: PermissionDef[] }>('/permissions').then(r => r.data) })
  const [selected, setSelected] = useState<string>('')
  const role = roles.data?.find(r => r.code === (selected || roles.data?.[0]?.code))

  if (roles.isLoading || perms.isLoading) return <Spinner />
  const modules = [...new Set(perms.data?.map(p => p.module))]

  return (
    <>
      <PageHeader title="Roles and permissions" description="A role is a set of permissions. Where it applies is decided when the role is granted to a person." />
      <div className="grid gap-6 lg:grid-cols-4">
        <Card title="Roles">
          <ul className="space-y-1 text-sm">
            {roles.data?.map(r => (
              <li key={r.code}>
                <button onClick={() => setSelected(r.code)} className={`w-full rounded px-2 py-1 text-left ${r.code === role?.code ? 'bg-brand/10 font-medium text-brand' : 'hover:bg-canvas'}`}>{r.name}</button>
              </li>
            ))}
          </ul>
        </Card>
        {role && <RoleEditor key={role.code} role={role} perms={perms.data ?? []} modules={modules} editable={can('roles.manage')} />}
      </div>
    </>
  )
}

function RoleEditor({ role, perms, modules, editable }: { role: Role; perms: PermissionDef[]; modules: string[]; editable: boolean }) {
  const [checked, setChecked] = useState(new Set(role.permissions ?? []))
  const save = useMutate<{ permissions: string[] }>('PATCH', `/roles/${role.code}`, [['roles']])
  const dirty = [...checked].sort().join() !== [...(role.permissions ?? [])].sort().join()

  return (
    <Card title={role.name} className="lg:col-span-3" actions={editable && <Button disabled={!dirty} busy={save.isPending} onClick={() => save.mutate({ permissions: [...checked] })}>Save changes</Button>}>
      <ErrorNotice error={save.error} />
      {role.description && <p className="mb-4 text-sm text-muted">{role.description}</p>}
      <div className="grid gap-5 sm:grid-cols-2">
        {modules.map(m => (
          <fieldset key={m}>
            <legend className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">{m}</legend>
            {perms.filter(p => p.module === m).map(p => (
              <label key={p.code} className="flex items-start gap-2 py-1 text-sm">
                <input type="checkbox" disabled={!editable} checked={checked.has(p.code)} className="mt-0.5"
                  onChange={e => { const next = new Set(checked); if (e.target.checked) next.add(p.code); else next.delete(p.code); setChecked(next) }} />
                <span>{p.description}{p.sensitive && <span className="ml-1 text-xs text-warn">sensitive</span>}<br /><code className="text-xs text-muted">{p.code}</code></span>
              </label>
            ))}
          </fieldset>
        ))}
      </div>
    </Card>
  )
}
