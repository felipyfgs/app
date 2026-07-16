import { describe, expect, it } from 'vitest'
import { humanizeStatusCode, kpiLeadingClass, kpiPageCardUi } from '../../app/utils/kpi-ui'
import { mailboxTriageLabel } from '../../app/utils/mailbox-triage'
import { workRiskLabel } from '../../app/utils/work-labels'

describe('kpi-ui (padrão HomeStats / template)', () => {
  it('leading circular muda por tom', () => {
    expect(kpiLeadingClass('error')).toMatch(/error/)
    expect(kpiLeadingClass('success')).toMatch(/success/)
    expect(kpiLeadingClass('default')).toMatch(/primary/)
    expect(kpiLeadingClass('warning', true)).toMatch(/primary/)
  })

  it('page card ui expõe title uppercase do template', () => {
    const ui = kpiPageCardUi('info')
    expect(ui.title).toMatch(/uppercase/)
    expect(ui.leading).toMatch(/rounded-full/)
  })

  it('humanizeStatusCode não deixa código cru', () => {
    expect(humanizeStatusCode('NOT_CONFIRMED')).toBe('Not Confirmed')
    expect(humanizeStatusCode('')).toBe('—')
  })
})

describe('labels pt-BR em superfícies capturadas', () => {
  it('mailbox triage em português', () => {
    expect(mailboxTriageLabel('NEW')).toBe('Nova')
    expect(mailboxTriageLabel('IN_REVIEW')).toBe('Em análise')
    expect(mailboxTriageLabel('RESOLVED')).toBe('Resolvida')
  })

  it('work risks em português', () => {
    expect(workRiskLabel('ATRASADA')).toBe('Atrasada')
    expect(workRiskLabel('EM_MULTA')).toBe('Em multa')
  })
})
