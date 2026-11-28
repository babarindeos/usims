import { useState, type FormEvent } from 'react'
import { Button, Card, ErrorNotice, formatDate, SelectField, StatusBadge, TextAreaField, TextField } from '../../design-system'
import { ApiError, errorList } from '../../lib/api'
import type { Application, ClearanceEntry } from '../../lib/types'
import { useApplicationAction } from './applicantApi'
import { DocumentsSection, PaymentSection } from './ApplicantDocuments'

/** The candidate's side of an offer: letter, acceptance, acceptance fee, onboarding and clearance (PRD 5.6 to 6.3). */
export function OfferSection({ application: a }: { application: Application }) {
  const offer = a.offer!
  const accept = useApplicationAction<{ declaration: boolean }>(a.id, 'POST', '/offer/accept')
  const decline = useApplicationAction<{ reason: string }>(a.id, 'POST', '/offer/decline')
  const [agree, setAgree] = useState(false)
  const [declining, setDeclining] = useState(false)
  const [reason, setReason] = useState('')

  return (
    <div className="mb-6 space-y-6">
      <Card title={`Admission offer ${offer.admission_no}`} actions={<div className="flex items-center gap-2"><StatusBadge status={offer.status} /><Button variant="ghost" onClick={() => printLetter(offer.letter)}>Print letter</Button></div>}>
        <pre className="whitespace-pre-wrap rounded-md border border-line bg-canvas p-4 font-sans text-sm leading-relaxed">{offer.letter}</pre>
        {offer.status === 'offered' && (
          <div className="mt-4 space-y-3">
            <p className="text-sm">Respond by <strong>{formatDate(offer.accept_by)}</strong>. After that date the offer lapses.</p>
            <label className="flex items-start gap-2 text-sm">
              <input type="checkbox" checked={agree} onChange={e => setAgree(e.target.checked)} className="mt-1" />
              I accept this offer and confirm that the information in my application is true. I understand the offer can be withdrawn if any of it is false.
            </label>
            <ErrorNotice error={accept.error ?? decline.error} />
            <div className="flex flex-wrap gap-2">
              <Button disabled={!agree} busy={accept.isPending} onClick={() => accept.mutate({ declaration: true })}>Accept offer</Button>
              <Button variant="secondary" onClick={() => setDeclining(!declining)}>Decline offer</Button>
            </div>
            {declining && (
              <form className="space-y-2" onSubmit={e => { e.preventDefault(); decline.mutate({ reason }) }}>
                <TextAreaField label="Why are you declining?" required value={reason} onChange={e => setReason(e.target.value)} />
                <Button type="submit" variant="danger" busy={decline.isPending}>Confirm decline</Button>
              </form>
            )}
          </div>
        )}
        {offer.status === 'accepted' && <p className="mt-3 text-sm text-ok">You accepted this offer on {formatDate(offer.responded_at, true)}.</p>}
      </Card>

      {offer.status === 'accepted' && (
        <>
          <PaymentSection a={a} purpose="acceptance_fee" />
          <DocumentsSection a={a} stage="onboarding" />
          <OnboardingForm a={a} />
          {!!offer.clearance?.length && <ClearanceStatus a={a} items={offer.clearance} />}
        </>
      )}
    </div>
  )
}

function printLetter(letter: string) {
  const w = window.open('', '_blank', 'noopener=no,width=800,height=900')
  if (!w) return
  const pre = w.document.createElement('pre')
  pre.style.cssText = 'white-space:pre-wrap;font:14px/1.6 Georgia,serif;margin:48px'
  pre.textContent = letter
  w.document.title = 'Admission letter'
  w.document.body.appendChild(pre)
  w.print()
}

type Group = Record<string, string>

function OnboardingForm({ a }: { a: Application }) {
  const offer = a.offer!
  const save = useApplicationAction<Record<string, unknown>>(a.id, 'PUT', '/onboarding')
  const ob = offer.onboarding as Record<string, Group>
  const [sponsor, setSponsor] = useState<Group>({ name: '', relationship: '', phone: '', address: '', ...ob.sponsor })
  const [residence, setResidence] = useState<Group>({ type: 'off_campus', address: '', ...ob.residence })
  const [emergency, setEmergency] = useState<Group>({ name: '', phone: '', ...ob.emergency_contact })
  const [medical, setMedical] = useState<Group>({ blood_group: '', genotype: '', conditions: '', disability: '', ...ob.medical })
  const [declaration, setDeclaration] = useState(false)
  const err = save.error instanceof ApiError ? save.error : null
  const f = (k: string) => err?.fieldError(k)

  const submit = (e: FormEvent) => {
    e.preventDefault()
    save.mutate({ sponsor, residence, emergency_contact: emergency, medical, declaration })
  }

  return (
    <Card title="Onboarding" actions={offer.onboarding_submitted_at && <span className="text-xs text-muted">Submitted {formatDate(offer.onboarding_submitted_at, true)}</span>}>
      <form onSubmit={submit} className="grid gap-3 sm:grid-cols-4">
        <p className="text-sm font-medium sm:col-span-4">Sponsor</p>
        <TextField label="Name" required value={sponsor.name} onChange={e => setSponsor({ ...sponsor, name: e.target.value })} error={f('sponsor.name')} />
        <TextField label="Relationship" required value={sponsor.relationship} onChange={e => setSponsor({ ...sponsor, relationship: e.target.value })} error={f('sponsor.relationship')} />
        <TextField label="Phone" required value={sponsor.phone} onChange={e => setSponsor({ ...sponsor, phone: e.target.value })} error={f('sponsor.phone')} />
        <TextField label="Address" value={sponsor.address ?? ''} onChange={e => setSponsor({ ...sponsor, address: e.target.value })} />
        <p className="text-sm font-medium sm:col-span-4">Residence and emergency contact</p>
        <SelectField label="Residence" value={residence.type} onChange={e => setResidence({ ...residence, type: e.target.value })}>
          <option value="on_campus">On campus</option><option value="off_campus">Off campus</option>
        </SelectField>
        <TextField label="Residential address" value={residence.address ?? ''} onChange={e => setResidence({ ...residence, address: e.target.value })} />
        <TextField label="Emergency contact" required value={emergency.name} onChange={e => setEmergency({ ...emergency, name: e.target.value })} error={f('emergency_contact.name')} />
        <TextField label="Emergency phone" required value={emergency.phone} onChange={e => setEmergency({ ...emergency, phone: e.target.value })} error={f('emergency_contact.phone')} />
        <p className="text-sm font-medium sm:col-span-4">Medical (shared only with the health centre)</p>
        <TextField label="Blood group" value={medical.blood_group ?? ''} onChange={e => setMedical({ ...medical, blood_group: e.target.value })} />
        <TextField label="Genotype" value={medical.genotype ?? ''} onChange={e => setMedical({ ...medical, genotype: e.target.value })} />
        <TextField label="Conditions" value={medical.conditions ?? ''} onChange={e => setMedical({ ...medical, conditions: e.target.value })} />
        <TextField label="Disability or support needs" value={medical.disability ?? ''} onChange={e => setMedical({ ...medical, disability: e.target.value })} />
        <label className="flex items-start gap-2 text-sm sm:col-span-4">
          <input type="checkbox" checked={declaration} onChange={e => setDeclaration(e.target.checked)} className="mt-1" />
          I confirm these details are correct and agree to abide by the institution's rules and regulations.
        </label>
        <div className="sm:col-span-4">
          {save.error && <ul role="alert" className="mb-2 list-disc pl-5 text-sm text-bad">{errorList(save.error).map(m => <li key={m}>{m}</li>)}</ul>}
          {save.isSuccess && !save.isPending && <p role="status" className="mb-2 text-sm text-ok">Onboarding submitted. Clearance has started.</p>}
          <Button type="submit" disabled={!declaration} busy={save.isPending}>{offer.onboarding_submitted_at ? 'Update onboarding' : 'Submit onboarding'}</Button>
        </div>
      </form>
    </Card>
  )
}

function ClearanceStatus({ a, items }: { a: Application; items: ClearanceEntry[] }) {
  const done = items.every(i => i.status === 'approved')
  return (
    <Card title="Clearance">
      {done && <p role="status" className="mb-3 text-sm text-ok">Every office has cleared you. Registry will issue your matriculation number.</p>}
      <ul className="divide-y divide-line">
        {items.map(i => <ClearanceRow key={i.id} a={a} item={i} />)}
      </ul>
    </Card>
  )
}

function ClearanceRow({ a, item }: { a: Application; item: ClearanceEntry }) {
  const resubmit = useApplicationAction<{ remarks: string }>(a.id, 'POST', `/clearance/${item.id}/resubmit`)
  const [remarks, setRemarks] = useState('')
  const last = item.actions[item.actions.length - 1]
  return (
    <li className="py-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <p className="text-sm font-medium">{item.unit}{item.reviewer_unit && <span className="text-muted"> · {item.reviewer_unit}</span>}</p>
          {item.instructions && <p className="text-xs text-muted">{item.instructions}</p>}
        </div>
        <StatusBadge status={item.status} />
      </div>
      {last?.remarks && <p className="mt-1 text-xs">{last.by ? `${last.by}: ` : ''}{last.remarks}</p>}
      {item.status === 'requires_correction' && (
        <form className="mt-2 flex flex-wrap items-end gap-2" onSubmit={e => { e.preventDefault(); resubmit.mutate({ remarks }) }}>
          <TextField label="What did you correct?" value={remarks} onChange={e => setRemarks(e.target.value)} className="min-w-64 flex-1" />
          <Button type="submit" variant="secondary" busy={resubmit.isPending}>Resubmit</Button>
          <ErrorNotice error={resubmit.error} />
        </form>
      )}
    </li>
  )
}
